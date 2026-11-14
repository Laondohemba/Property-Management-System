<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

/**
 * Housekeeping status per room (spec §6.2, HK-01).
 */
enum HousekeepingStatus: string
{
    case Dirty = 'dirty';
    case Cleaning = 'cleaning';
    case Clean = 'clean';
    case Inspected = 'inspected';
    /** Removed from inventory. */
    case OutOfOrder = 'out_of_order';
    /** Still sellable, but flagged. */
    case OutOfService = 'out_of_service';

    public function label(): string
    {
        return match ($this) {
            self::Dirty => 'Dirty',
            self::Cleaning => 'Cleaning',
            self::Clean => 'Clean',
            self::Inspected => 'Inspected',
            self::OutOfOrder => 'Out of order',
            self::OutOfService => 'Out of service',
        };
    }
}
