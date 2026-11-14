<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTOs;

use App\Modules\Identity\Enums\Permission;
use Spatie\LaravelData\Data;

/**
 * GET /bff/session: everything the SPA shell needs (spec §8.2).
 */
class SessionData extends Data
{
    /**
     * @param  array<int, RoleRefData>  $roles  organisation-wide roles (Super Admin)
     * @param  array<int, Permission>  $permissions  held at the active property
     * @param  array<int, SessionPropertyData>  $properties
     */
    public function __construct(
        public SessionUserData $user,
        public ?OrganizationRefData $organization,
        public bool $is_super_admin,
        public array $roles,
        public array $permissions,
        public array $properties,
        public ?SessionPropertyData $active_property,
    ) {}
}
