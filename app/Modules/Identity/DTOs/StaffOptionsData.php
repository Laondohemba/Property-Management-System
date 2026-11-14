<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTOs;

use Spatie\LaravelData\Data;

/**
 * Options for the staff screens: properties the viewer manages and roles they can assign.
 */
class StaffOptionsData extends Data
{
    /**
     * @param  array<int, PropertyRefData>  $properties
     * @param  array<int, AssignableRoleData>  $roles
     */
    public function __construct(
        public array $properties,
        public array $roles,
        public bool $can_manage_accounts,
    ) {}
}
