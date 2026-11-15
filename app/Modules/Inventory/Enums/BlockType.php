<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

/**
 * Why units of a room type are taken off sale for some nights (CAL-06).
 */
enum BlockType: string
{
    case OutOfOrder = 'out_of_order';
    case OutOfService = 'out_of_service';
    case Owner = 'owner';
    /** Held for a group or a pending deal; usually given an expiry. */
    case Hold = 'hold';

    public function label(): string
    {
        return match ($this) {
            self::OutOfOrder => 'Out of order',
            self::OutOfService => 'Out of service',
            self::Owner => 'Owner block',
            self::Hold => 'Hold',
        };
    }
}
