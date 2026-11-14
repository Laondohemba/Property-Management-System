<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTOs;

use App\Modules\Identity\Enums\Permission;
use Spatie\LaravelData\Data;

class PermissionOptionData extends Data
{
    public function __construct(
        public Permission $name,
        public string $label,
    ) {}
}
