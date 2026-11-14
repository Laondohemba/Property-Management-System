<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\Room;
use App\Modules\Inventory\Models\RoomType;
use App\Modules\Property\Models\Property;

/**
 * Inventory counts for other modules (e.g. the dashboard), so they don't query Inventory tables directly (ARC-10).
 */
class SummarizeInventory
{
    /**
     * @return array{rooms: int, room_types: int}
     */
    public function handle(Property $property): array
    {
        return [
            'rooms' => Room::forProperty($property)->where('is_active', true)->count(),
            'room_types' => RoomType::forProperty($property)->where('is_active', true)->count(),
        ];
    }
}
