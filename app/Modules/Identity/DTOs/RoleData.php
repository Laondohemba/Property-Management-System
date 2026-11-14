<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTOs;

use App\Modules\Identity\Enums\Permission;
use Spatie\LaravelData\Data;

/**
 * A role with its permissions, as shown on the roles screens (SYS-02).
 */
class RoleData extends Data
{
    /**
     * @param  array<int, Permission>  $permissions
     */
    public function __construct(
        public string $name,
        public string $label,
        public ?string $description,
        public bool $is_system,
        public bool $is_super_admin,
        public bool $requires_two_factor,
        public array $permissions,
        public int $users_count,
    ) {}
}
