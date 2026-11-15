<?php

declare(strict_types=1);

namespace App\Modules\Rates\DTOs;

use Spatie\LaravelData\Data;

/** Nightly base price and supplements of one room type under a rate plan (RTE-01). */
class RatePlanPriceData extends Data
{
    public function __construct(
        public RoomTypeOptionData $room_type,
        /** Per night for the room type's base occupancy. */
        public int $base_price_minor,
        public int $extra_adult_minor,
        public int $extra_child_minor,
    ) {}
}
