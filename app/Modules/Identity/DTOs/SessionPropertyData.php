<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTOs;

use Spatie\LaravelData\Data;

/**
 * A property the user can work in, with their role there.
 */
class SessionPropertyData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $code,
        public string $timezone,
        public string $currency,
        public string $business_date,
        public ?RoleRefData $role,
    ) {}
}
