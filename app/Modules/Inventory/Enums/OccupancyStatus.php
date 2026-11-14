<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

/**
 * Driven by check-in and check-out only (spec §6.2).
 */
enum OccupancyStatus: string
{
    case Vacant = 'vacant';
    case Occupied = 'occupied';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
