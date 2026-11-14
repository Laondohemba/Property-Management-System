<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTOs;

use Spatie\LaravelData\Data;

class SessionUserData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public bool $two_factor_enabled,
        /** One of the user's roles requires 2FA in their organisation (SYS-01). */
        public bool $two_factor_required,
        public ?string $last_login_at,
    ) {}
}
