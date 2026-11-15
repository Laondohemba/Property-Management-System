<?php

declare(strict_types=1);

namespace App\Modules\Inventory\DTOs;

use App\Modules\Inventory\Enums\BedType;
use App\Modules\Inventory\Models\Amenity;
use App\Modules\Inventory\Models\RoomType;
use App\Support\Http\DTOs\RecordStampsData;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Room type setup view (SYS-04).
 */
class RoomTypeData extends Data
{
    /**
     * @param  array<int, BedData>  $bed_configuration
     * @param  array<int, AmenityData>  $amenities
     * @param  array<int, RoomTypeImageData>  $images  Gallery in display order; the first is the cover.
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $code,
        public ?string $description,
        public int $base_occupancy,
        public int $max_adults,
        public int $max_children,
        public int $max_occupancy,
        public array $bed_configuration,
        public array $amenities,
        public array $images,
        public int|Optional $rooms_count,
        public int $sort_order,
        public bool $is_active,
        public RecordStampsData $stamps,
    ) {}

    /**
     * Expects the amenities and media relations loaded; rooms_count is included when counted.
     */
    public static function fromModel(RoomType $roomType): self
    {
        $roomsCount = $roomType->getAttribute('rooms_count');

        return new self(
            id: $roomType->public_id,
            name: $roomType->name,
            code: $roomType->code,
            description: $roomType->description,
            base_occupancy: $roomType->base_occupancy,
            max_adults: $roomType->max_adults,
            max_children: $roomType->max_children,
            max_occupancy: $roomType->max_occupancy,
            bed_configuration: array_map(function (array $bed): BedData {
                $type = BedType::from($bed['type']);

                return new BedData($type, $type->label(), (int) $bed['count']);
            }, $roomType->bed_configuration ?? []),
            amenities: $roomType->amenities->map(fn (Amenity $amenity): AmenityData => AmenityData::fromModel($amenity))->values()->all(),
            images: $roomType->getMedia(RoomType::IMAGES)->map(fn (Media $media): RoomTypeImageData => RoomTypeImageData::fromMedia($media))->values()->all(),
            rooms_count: $roomsCount === null ? Optional::create() : (int) $roomsCount,
            sort_order: $roomType->sort_order,
            is_active: $roomType->is_active,
            stamps: RecordStampsData::fromModel($roomType),
        );
    }
}
