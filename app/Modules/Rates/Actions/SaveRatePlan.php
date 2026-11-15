<?php

declare(strict_types=1);

namespace App\Modules\Rates\Actions;

use App\Modules\Audit\Audit;
use App\Modules\Billing\Models\Tax;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\RoomType;
use App\Modules\Property\Models\Property;
use App\Modules\Rates\Models\CancellationPolicy;
use App\Modules\Rates\Models\DepositPolicy;
use App\Modules\Rates\Models\RatePlan;
use App\Modules\Rates\Models\RatePlanRoomType;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Create or update a rate plan with its prices, taxes and policies (RTE-01, RTE-07).
 *
 * Rate audit (RTE-09): the plan's own fields are logged by the model; every price change per
 * room type, and changes to which taxes apply, are recorded here with old and new values and
 * the reason given.
 */
final class SaveRatePlan
{
    /**
     * @param  array<string, mixed>  $input  validated input with public ids
     */
    public function handle(User $actor, Property $property, array $input, ?RatePlan $plan = null): RatePlan
    {
        return DB::transaction(function () use ($actor, $property, $input, $plan): RatePlan {
            $plan ??= new RatePlan([
                'organization_id' => $property->organization_id,
                'property_id' => $property->id,
                'created_by' => $actor->id,
            ]);
            $creating = ! $plan->exists;
            $reason = isset($input['change_reason']) && is_string($input['change_reason']) ? $input['change_reason'] : null;

            $plan->fill([
                ...Arr::except($input, ['prices', 'tax_ids', 'change_reason', 'cancellation_policy_id', 'deposit_policy_id']),
                'cancellation_policy_id' => $this->idOf(CancellationPolicy::class, $property, $input['cancellation_policy_id'] ?? null),
                'deposit_policy_id' => $this->idOf(DepositPolicy::class, $property, $input['deposit_policy_id'] ?? null),
                'updated_by' => $actor->id,
            ])->save();

            /** @var list<string> $taxIds */
            $taxIds = $input['tax_ids'] ?? [];
            /** @var list<array{room_type_id: string, base_price_minor: int, extra_adult_minor: int, extra_child_minor: int}> $prices */
            $prices = $input['prices'] ?? [];

            $this->syncTaxes($actor, $property, $plan, $taxIds, $reason, $creating);
            $this->syncPrices($actor, $property, $plan, $prices, $reason, $creating);

            return $plan->load(['cancellationPolicy', 'depositPolicy', 'prices.roomType', 'taxes', ...RatePlan::stampRelations()]);
        });
    }

    /**
     * @param  list<string>  $publicIds
     */
    private function syncTaxes(User $actor, Property $property, RatePlan $plan, array $publicIds, ?string $reason, bool $creating): void
    {
        $taxes = Tax::forProperty($property)->whereIn('public_id', $publicIds)->get();
        $before = $plan->taxes()->pluck('code')->sort()->values()->all();
        $plan->taxes()->sync($taxes->modelKeys());
        $after = $taxes->pluck('code')->sort()->values()->all();

        if (! $creating && $before !== $after) {
            Audit::record(Audit::DATA, 'taxes_changed', "Rate plan {$plan->code}: taxes changed", $plan, [
                'old' => ['taxes' => $before],
                'attributes' => ['taxes' => $after],
                'reason' => $reason,
            ], $actor, $property);
        }
    }

    /**
     * @param  list<array{room_type_id: string, base_price_minor: int, extra_adult_minor: int, extra_child_minor: int}>  $prices
     */
    private function syncPrices(User $actor, Property $property, RatePlan $plan, array $prices, ?string $reason, bool $creating): void
    {
        $roomTypes = RoomType::forProperty($property)
            ->whereIn('public_id', array_column($prices, 'room_type_id'))
            ->get()
            ->keyBy('public_id');

        /** @var Collection<int, RatePlanRoomType> $existing room type id => price */
        $existing = $plan->prices()->with('roomType')->get()->keyBy('room_type_id');
        $kept = [];

        foreach ($prices as $row) {
            $roomType = $roomTypes->get($row['room_type_id']);
            if (! $roomType instanceof RoomType) {
                continue;
            }

            $values = [
                'base_price_minor' => (int) $row['base_price_minor'],
                'extra_adult_minor' => (int) $row['extra_adult_minor'],
                'extra_child_minor' => (int) $row['extra_child_minor'],
            ];
            $current = $existing->get($roomType->id);
            $kept[] = $roomType->id;

            if ($current === null) {
                $plan->prices()->create(['room_type_id' => $roomType->id, ...$values]);
                if (! $creating) {
                    $this->auditPrice($actor, $property, $plan, $roomType, null, $values, $reason, 'added');
                }

                continue;
            }

            $old = Arr::only($current->getAttributes(), array_keys($values));
            $old = array_map('intval', $old);
            if ($old !== $values) {
                $current->update($values);
                // Only the amounts that actually changed go into the audit entry.
                $changed = array_keys(array_diff_assoc($values, $old));
                $this->auditPrice($actor, $property, $plan, $roomType, Arr::only($old, $changed), Arr::only($values, $changed), $reason, 'changed');
            }
        }

        foreach ($existing as $roomTypeId => $price) {
            if (! in_array($roomTypeId, $kept, true)) {
                $price->delete();
                $this->auditPrice($actor, $property, $plan, $price->roomType, array_map('intval', Arr::only($price->getAttributes(), ['base_price_minor', 'extra_adult_minor', 'extra_child_minor'])), null, $reason, 'removed');
            }
        }
    }

    /**
     * @param  array<string, int>|null  $old
     * @param  array<string, int>|null  $new
     */
    private function auditPrice(User $actor, Property $property, RatePlan $plan, RoomType $roomType, ?array $old, ?array $new, ?string $reason, string $what): void
    {
        Audit::record(Audit::DATA, 'price_'.$what, "Rate plan {$plan->code}: {$roomType->code} price {$what}", $plan, [
            'room_type' => $roomType->code,
            'old' => $old,
            'attributes' => $new,
            'reason' => $reason,
        ], $actor, $property);
    }

    /**
     * @param  class-string<CancellationPolicy|DepositPolicy>  $model
     */
    private function idOf(string $model, Property $property, mixed $publicId): ?int
    {
        if (! is_string($publicId) || $publicId === '') {
            return null;
        }

        $id = $model::forProperty($property)->where('public_id', $publicId)->value('id');

        return $id === null ? null : (int) $id;
    }
}
