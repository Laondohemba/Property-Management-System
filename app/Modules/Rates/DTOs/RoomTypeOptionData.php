<?php

declare(strict_types=1);

namespace App\Modules\Rates\DTOs;

use App\Modules\Inventory\Models\RoomType;
use Spatie\LaravelData\Data;

/** A room type that can be priced, with the occupancy its supplements depend on. */
class RoomTypeOptionData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $code,
        public int $base_occupancy,
        public int $max_adults,
        public int $max_children,
        public int $max_occupancy,
        public bool $is_active,
    ) {}

    public static function fromModel(RoomType $roomType): self
    {
        return new self(
            $roomType->public_id, $roomType->name, $roomType->code, $roomType->base_occupancy,
            $roomType->max_adults, $roomType->max_children, $roomType->max_occupancy, $roomType->is_active,
        );
    }
}
