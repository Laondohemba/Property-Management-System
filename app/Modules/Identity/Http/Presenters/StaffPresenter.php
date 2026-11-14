<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Presenters;

use App\Modules\Identity\DTOs\AssignableRoleData;
use App\Modules\Identity\DTOs\PropertyRefData;
use App\Modules\Identity\DTOs\RoleRefData;
use App\Modules\Identity\DTOs\StaffAssignmentData;
use App\Modules\Identity\DTOs\StaffOptionsData;
use App\Modules\Identity\DTOs\StaffUserData;
use App\Modules\Identity\Enums\Role as RoleEnum;
use App\Modules\Identity\Enums\StaffStatus;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Property\Models\Property;
use Illuminate\Support\Collection;

/**
 * Shapes users for the staff administration screens. Holds the request's role lookup
 * and the actor's manageable properties, so assignments are only shown where the actor
 * may manage them.
 */
final class StaffPresenter
{
    /** @var Collection<int, Role> */
    private Collection $roles;

    /** @var list<int> */
    private array $manageable;

    public function __construct(private readonly User $actor)
    {
        $this->roles = Role::query()->visibleTo((int) $actor->organization_id)->get()->keyBy('id');
        $this->manageable = $actor->manageablePropertyIds();
    }

    public function user(User $user): StaffUserData
    {
        $superAdmin = $user->relationLoaded('roles')
            ? $user->roles->contains('name', RoleEnum::SuperAdmin->value)
            : $user->isSuperAdmin();

        return new StaffUserData(
            id: $user->public_id,
            name: $user->name,
            email: $user->email,
            status: StaffStatus::from($user->status()),
            is_active: $user->is_active,
            is_super_admin: $superAdmin,
            is_self: $user->is($this->actor),
            two_factor_enabled: $user->hasTwoFactorEnabled(),
            last_login_at: $user->last_login_at?->toIso8601ZuluString(),
            invited_at: $user->invited_at?->toIso8601ZuluString(),
            assignments: $user->properties
                ->filter(fn (Property $property): bool => in_array($property->id, $this->manageable, true))
                ->sortBy('name')
                ->map(fn (Property $property): StaffAssignmentData => new StaffAssignmentData(
                    PropertyRefData::fromProperty($property),
                    $this->role((int) $property->getRelation('pivot')->getAttribute('role_id')),
                ))
                ->values()
                ->all(),
        );
    }

    /**
     * Options for filters and forms: properties the actor manages and roles they can assign.
     */
    public function options(): StaffOptionsData
    {
        return new StaffOptionsData(
            properties: Property::query()->whereKey($this->manageable)->orderBy('name')->get()
                ->map(fn (Property $property): PropertyRefData => PropertyRefData::fromProperty($property))
                ->values()
                ->all(),
            roles: $this->roles
                ->reject(fn (Role $role): bool => $role->name === RoleEnum::SuperAdmin->value)
                ->sortBy(fn (Role $role): string => ($role->isSystem() ? '0' : '1').$role->displayLabel())
                ->map(fn (Role $role): AssignableRoleData => new AssignableRoleData($role->name, $role->displayLabel(), $role->isSystem()))
                ->values()
                ->all(),
            can_manage_accounts: $this->actor->isSuperAdmin(),
        );
    }

    private function role(int $roleId): ?RoleRefData
    {
        $role = $this->roles->get($roleId);

        return $role ? RoleRefData::fromRole($role) : null;
    }
}
