<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/**
 * How a tax or fee is worked out (spec §2.8). Only percentages can be included in the price.
 */
enum TaxCalculation: string
{
    case Percentage = 'percentage';
    case PerNight = 'per_night';
    case PerStay = 'per_stay';
    case PerPersonNight = 'per_person_night';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage of the room charge',
            self::PerNight => 'Fixed amount per room night',
            self::PerStay => 'Fixed amount per stay',
            self::PerPersonNight => 'Fixed amount per guest per night',
        };
    }
}
