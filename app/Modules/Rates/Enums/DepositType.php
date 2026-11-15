<?php

declare(strict_types=1);

namespace App\Modules\Rates\Enums;

/**
 * How much is taken as a deposit (RTE-07).
 */
enum DepositType: string
{
    case None = 'none';
    case Percent = 'percent';
    case Nights = 'nights';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No deposit',
            self::Percent => 'A percentage of the stay',
            self::Nights => 'A number of nights',
            self::Fixed => 'A fixed amount',
        };
    }
}
