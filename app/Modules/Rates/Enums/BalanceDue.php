<?php

declare(strict_types=1);

namespace App\Modules\Rates\Enums;

/**
 * When the rest of the stay is paid.
 */
enum BalanceDue: string
{
    case AtCheckIn = 'at_check_in';
    case AtCheckOut = 'at_check_out';

    public function label(): string
    {
        return match ($this) {
            self::AtCheckIn => 'At check-in',
            self::AtCheckOut => 'At check-out',
        };
    }
}
