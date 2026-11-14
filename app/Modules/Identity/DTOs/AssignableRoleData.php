<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTOs;

use Spatie\LaravelData\Data;

class AssignableRoleData extends Data
{
    public function __construct(
        public string $name,
        public string $label,
        public bool $is_system,
    ) {}
}
