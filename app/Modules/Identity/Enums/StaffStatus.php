<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

enum StaffStatus: string
{
    case Active = 'active';
    /** Invited but has not set a password yet. */
    case Invited = 'invited';
    case Inactive = 'inactive';
}
