<?php

declare(strict_types=1);

namespace App\Modules\Rates\DTOs;

use App\Modules\Billing\DTOs\TaxBreakdownData;
use App\Modules\Inventory\DTOs\RoomTypeRefData;
use Spatie\LaravelData\Data;

/**
 * What a stay costs under a rate plan at its base prices (before the daily overrides that come
 * with the rates grid), with taxes and the policy wording guests would see.
 */
class PricePreviewData extends Data
{
    public function __construct(
        public RoomTypeRefData $room_type,
        public int $adults,
        public int $children,
        public int $nights,
        public int $base_minor,
        public int $extra_adults,
        public int $extra_adult_minor,
        public int $extra_children,
        public int $extra_child_minor,
        /** Per night after supplements, clamped to the plan's minimum and maximum. */
        public int $nightly_minor,
        public bool $clamped,
        public int $room_charge_minor,
        public TaxBreakdownData $taxes,
        public ?string $cancellation_summary,
        public ?string $deposit_summary,
        public string $currency,
    ) {}
}
