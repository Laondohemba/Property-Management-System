<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\DTOs\OrganizationRefData;
use App\Modules\Identity\DTOs\RoleRefData;
use App\Modules\Identity\DTOs\SessionData;
use App\Modules\Identity\DTOs\SessionPropertyData;
use App\Modules\Identity\DTOs\SessionUserData;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role as RoleEnum;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Property\Models\Property;

/**
 * Builds GET /bff/session: user, organisation, roles, permissions at the active property,
 * accessible properties and the active property (spec §8.2).
 */
class BuildSessionView
{
    public function handle(User $user, ?Property $activeProperty): SessionData
    {
        $user->loadMissing(['organization', 'roles']);
        $superAdmin = $user->isSuperAdmin();

        $summary = function (Property $property) use ($user, $superAdmin): SessionPropertyData {
            $role = match (true) {
                $superAdmin => new RoleRefData(RoleEnum::SuperAdmin->value, RoleEnum::SuperAdmin->label()),
                ($assigned = $user->roleForProperty($property)) !== null => RoleRefData::fromRole($assigned),
                default => null,
            };

            return new SessionPropertyData(
                $property->public_id,
                $property->name,
                $property->code,
                $property->timezone,
                $property->currency,
                (string) $property->business_date?->toDateString(),
                $role,
            );
        };

        return new SessionData(
            user: new SessionUserData(
                $user->public_id,
                $user->name,
                $user->email,
                $user->hasTwoFactorEnabled(),
                $user->requiresTwoFactor(),
                $user->last_login_at?->toIso8601ZuluString(),
            ),
            organization: $user->organization ? new OrganizationRefData($user->organization->public_id, $user->organization->name) : null,
            is_super_admin: $superAdmin,
            roles: $user->roles->map(fn (Role $role): RoleRefData => RoleRefData::fromRole($role))->values()->all(),
            permissions: array_map(fn (string $name): Permission => Permission::from($name), $user->permissionsForProperty($activeProperty)),
            properties: $user->accessibleProperties()->orderBy('properties.name')->get()->map($summary)->values()->all(),
            active_property: $activeProperty ? $summary($activeProperty) : null,
        );
    }
}
