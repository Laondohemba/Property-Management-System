<?php

declare(strict_types=1);

namespace App\Modules\Rates\Services;

use App\Modules\Billing\Actions\CalculateTaxes;
use App\Modules\Inventory\DTOs\RoomTypeRefData;
use App\Modules\Inventory\Models\RoomType;
use App\Modules\Rates\DTOs\PricePreviewData;
use App\Modules\Rates\Models\RatePlan;
use App\Modules\Rates\Models\RatePlanRoomType;
use Illuminate\Validation\ValidationException;

/**
 * Prices a stay under a rate plan (RTE-01; resolution order in spec §6.3).
 *
 * This step covers the base of that order: the plan's base price for the room type, occupancy
 * supplements, the plan's min/max clamp, then taxes and fees. Daily overrides (step 1.3) and
 * promotions (Phase 2) slot in before the supplements later without changing callers.
 *
 * Supplements: guests up to the room type's base occupancy are covered by the base price,
 * adults first; every guest above it pays the adult or child supplement.
 */
final class RatePricing
{
    public function __construct(
        private readonly CalculateTaxes $taxes,
        private readonly PolicyWording $wording,
    ) {}

    /**
     * Expects the plan's taxes, cancellationPolicy and depositPolicy loaded.
     */
    public function preview(RatePlan $plan, RoomType $roomType, int $adults, int $children, int $nights, string $currency): PricePreviewData
    {
        $this->ensureOccupancyFits($roomType, $adults, $children);

        $price = $plan->prices()->where('room_type_id', $roomType->id)->first();
        if (! $price instanceof RatePlanRoomType) {
            throw ValidationException::withMessages(['room_type_id' => "{$roomType->name} is not sold under {$plan->name}."]);
        }

        $extraAdults = max(0, $adults - $roomType->base_occupancy);
        $baseLeftForChildren = max(0, $roomType->base_occupancy - $adults);
        $extraChildren = max(0, $children - $baseLeftForChildren);

        $extraAdultMinor = $extraAdults * $price->extra_adult_minor;
        $extraChildMinor = $extraChildren * $price->extra_child_minor;
        $unclamped = $price->base_price_minor + $extraAdultMinor + $extraChildMinor;
        $nightly = $this->clamp($plan, $unclamped);
        $charge = $nightly * $nights;

        return new PricePreviewData(
            room_type: new RoomTypeRefData($roomType->public_id, $roomType->name, $roomType->code),
            adults: $adults,
            children: $children,
            nights: $nights,
            base_minor: $price->base_price_minor,
            extra_adults: $extraAdults,
            extra_adult_minor: $extraAdultMinor,
            extra_children: $extraChildren,
            extra_child_minor: $extraChildMinor,
            nightly_minor: $nightly,
            clamped: $nightly !== $unclamped,
            room_charge_minor: $charge,
            taxes: $this->taxes->handle($charge, $nights, $adults + $children, $plan->taxes, $currency),
            cancellation_summary: $plan->cancellationPolicy === null ? null : $this->wording->cancellation($plan->cancellationPolicy, $currency),
            deposit_summary: $plan->depositPolicy === null ? null : $this->wording->deposit($plan->depositPolicy, $currency),
            currency: $currency,
        );
    }

    private function clamp(RatePlan $plan, int $nightly): int
    {
        if ($plan->min_price_minor !== null && $nightly < $plan->min_price_minor) {
            return $plan->min_price_minor;
        }

        if ($plan->max_price_minor !== null && $nightly > $plan->max_price_minor) {
            return $plan->max_price_minor;
        }

        return $nightly;
    }

    private function ensureOccupancyFits(RoomType $roomType, int $adults, int $children): void
    {
        $errors = [];

        if ($adults > $roomType->max_adults) {
            $errors['adults'] = "{$roomType->name} takes at most {$roomType->max_adults} ".($roomType->max_adults === 1 ? 'adult' : 'adults').'.';
        }
        if ($children > $roomType->max_children) {
            $errors['children'] = "{$roomType->name} takes at most {$roomType->max_children} ".($roomType->max_children === 1 ? 'child' : 'children').'.';
        }
        if ($adults + $children > $roomType->max_occupancy) {
            $errors['children'] ??= "{$roomType->name} takes at most {$roomType->max_occupancy} guests in total.";
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
