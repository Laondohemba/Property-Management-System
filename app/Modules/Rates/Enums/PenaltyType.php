<?php

declare(strict_types=1);

namespace App\Modules\Rates\Enums;

/**
 * What a late cancellation or a no-show costs (RES-03, RES-04).
 */
enum PenaltyType: string
{
    case None = 'none';
    case Nights = 'nights';
    case Percent = 'percent';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No charge',
            self::Nights => 'A number of nights',
            self::Percent => 'A percentage of the stay',
            self::Fixed => 'A fixed amount',
        };
    }
}
