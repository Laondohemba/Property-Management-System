<?php

declare(strict_types=1);

namespace App\Modules\Rates\Enums;

/**
 * When the deposit is due.
 */
enum DepositDue: string
{
    case AtBooking = 'at_booking';
    case BeforeArrival = 'before_arrival';

    public function label(): string
    {
        return match ($this) {
            self::AtBooking => 'At booking',
            self::BeforeArrival => 'Before arrival',
        };
    }
}
