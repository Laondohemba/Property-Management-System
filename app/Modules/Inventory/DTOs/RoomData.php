<?php

declare(strict_types=1);

namespace App\Modules\Inventory\DTOs;

use App\Modules\Inventory\Models\Room;
use App\Support\Http\DTOs\RecordStampsData;
use Spatie\LaravelData\Data;

/**
 * Room setup view (SYS-04).
 */
class RoomData extends Data
{
    public function __construct(
        public string $id,
        public string $number,
        public ?string $floor,
        public ?string $notes,
        public RoomTypeRefData $room_type,
        public HousekeepingStatusData $housekeeping_status,
        public OccupancyStatusData $occupancy_status,
        public int $sort_order,
        public bool $is_active,
        public RecordStampsData $stamps,
    ) {}

    /**
     * Expects the roomType relation loaded.
     */
    public static function fromModel(Room $room): self
    {
        return new self(
            id: $room->public_id,
            number: $room->number,
            floor: $room->floor,
            notes: $room->notes,
            room_type: new RoomTypeRefData($room->roomType->public_id, $room->roomType->name, $room->roomType->code),
            housekeeping_status: HousekeepingStatusData::fromEnum($room->housekeeping_status),
            occupancy_status: OccupancyStatusData::fromEnum($room->occupancy_status),
            sort_order: $room->sort_order,
            is_active: $room->is_active,
            stamps: RecordStampsData::fromModel($room),
        );
    }
}
