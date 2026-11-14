<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTOs;

use App\Modules\Identity\Enums\Permission;
use Spatie\LaravelData\Data;

/**
 * One section of the permission matrix (SYS-02).
 */
class PermissionGroupData extends Data
{
    /**
     * @param  array<int, PermissionOptionData>  $permissions
     */
    public function __construct(
        public string $group,
        public string $label,
        public array $permissions,
    ) {}

    /**
     * @return array<int, self>
     */
    public static function catalogue(): array
    {
        $groups = [];

        foreach (Permission::cases() as $permission) {
            $groups[$permission->group()][] = new PermissionOptionData($permission, $permission->label());
        }

        return array_map(
            fn (string $group, array $permissions): self => new self($group, Permission::groupLabel($group), $permissions),
            array_keys($groups),
            array_values($groups),
        );
    }
}
