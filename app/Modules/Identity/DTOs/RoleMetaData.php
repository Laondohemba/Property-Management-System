<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTOs;

use Spatie\LaravelData\Data;

class RoleMetaData extends Data
{
    /**
     * @param  array<int, PermissionGroupData>  $permission_groups
     */
    public function __construct(
        public array $permission_groups,
    ) {}
}
