<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Audit\Audit;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\BlockType;
use App\Modules\Inventory\Models\InventoryBlock;
use App\Modules\Inventory\Models\RoomType;
use App\Modules\Inventory\Services\InventoryLedger;
use App\Modules\Property\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Staff-facing inventory changes (CAL-06, spec §7.3): blocks and overbooking allowances.
 * The block record and the ledger update commit together or not at all.
 */
final class ManageInventory
{
    public function __construct(private readonly InventoryLedger $ledger) {}

    public function block(
        User $actor,
        Property $property,
        RoomType $roomType,
        BlockType $type,
        int $quantity,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $reason,
        ?CarbonImmutable $expiresAt,
    ): InventoryBlock {
        return DB::transaction(function () use ($actor, $property, $roomType, $type, $quantity, $from, $to, $reason, $expiresAt): InventoryBlock {
            $this->ledger->block($roomType, $from, $to, $quantity);

            return InventoryBlock::create([
                'organization_id' => $property->organization_id,
                'property_id' => $property->id,
                'room_type_id' => $roomType->id,
                'type' => $type,
                'quantity' => $quantity,
                'from_date' => $from->toDateString(),
                'to_date' => $to->toDateString(),
                'reason' => $reason,
                'expires_at' => $expiresAt,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        });
    }

    /**
     * Give the units back. Nights already in the past stay as they were; idempotent, so a
     * second release (for example the expiry job racing a person) changes nothing.
     *
     * @param  User|null  $actor  null when the scheduler releases an expired block
     */
    public function release(?User $actor, InventoryBlock $block): InventoryBlock
    {
        return DB::transaction(function () use ($actor, $block): InventoryBlock {
            /** @var InventoryBlock $locked */
            $locked = InventoryBlock::query()->withoutGlobalScopes()->whereKey($block->id)->lockForUpdate()->firstOrFail();

            if ($locked->released_at !== null) {
                return $locked;
            }

            $roomType = RoomType::withTrashed()->withoutGlobalScopes()->findOrFail($locked->room_type_id);
            $property = Property::query()->withoutGlobalScopes()->findOrFail($locked->property_id);
            $from = CarbonImmutable::parse($locked->from_date->toDateString())->max($this->ledger->firstDate($property));
            $to = CarbonImmutable::parse($locked->to_date->toDateString());

            if ($from->lte($to)) {
                $this->ledger->release($roomType, $from, $to, $locked->quantity);
            }

            $locked->fill([
                'released_at' => now(),
                'released_by' => $actor?->id,
                'updated_by' => $actor !== null ? $actor->id : $locked->getAttribute('updated_by'),
            ])->save();

            return $locked;
        });
    }

    /**
     * @param  list<int>|null  $weekdays
     */
    public function overbook(
        User $actor,
        Property $property,
        RoomType $roomType,
        CarbonImmutable $from,
        CarbonImmutable $to,
        int $allowance,
        ?array $weekdays,
    ): int {
        $changed = $this->ledger->setOverbooking($roomType, $from, $to, $allowance, $weekdays);

        if ($changed > 0) {
            Audit::record(Audit::DATA, 'overbooking_changed', "Room type {$roomType->code}: overbooking allowance set to {$allowance}", $roomType, [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'weekdays' => $weekdays,
                'allowance' => $allowance,
                'nights' => $changed,
            ], $actor, $property);
        }

        return $changed;
    }
}
