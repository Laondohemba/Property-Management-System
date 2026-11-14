<?php

declare(strict_types=1);

namespace App\Modules\Inventory\DTOs;

use App\Modules\Inventory\Models\Amenity;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class AmenityData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        /** Present on the amenities screen. */
        public int|Optional $room_types_count,
    ) {}

    public static function fromModel(Amenity $amenity): self
    {
        $count = $amenity->getAttribute('room_types_count');

        return new self($amenity->public_id, $amenity->name, $count === null ? Optional::create() : (int) $count);
    }
}
