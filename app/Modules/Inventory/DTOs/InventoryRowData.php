<?php

declare(strict_types=1);

namespace App\Modules\Inventory\DTOs;

use Spatie\LaravelData\Data;

class InventoryRowData extends Data
{
    /**
     * @param  array<int, InventoryDayData>  $days  one per date of the grid, in order
     */
    public function __construct(
        public RoomTypeRefData $room_type,
        public bool $is_active,
        public array $days,
    ) {}
}
