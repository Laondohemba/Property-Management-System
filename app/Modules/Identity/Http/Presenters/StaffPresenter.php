<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Presenters;

use App\Modules\Identity\Enums\Role as RoleEnum;
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

    private bool $actorIsSuperAdmin;

    public function __construct(private readonly User $actor)
    {
        $this->roles = Role::query()->visibleTo((int) $actor->organization_id)->get()->keyBy('id');
        $this->manageable = $actor->manageablePropertyIds();
        $this->actorIsSuperAdmin = $actor->isSuperAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function user(User $user): array
    {
        $superAdmin = $user->relationLoaded('roles')
            ? $user->roles->contains('name', RoleEnum::SuperAdmin->value)
            : $user->isSuperAdmin();

        return [
            'id' => $user->public_id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status(),
            'is_active' => $user->is_active,
            'is_super_admin' => $superAdmin,
            'is_self' => $user->is($this->actor),
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            'last_login_at' => $user->last_login_at?->toIso8601ZuluString(),
            'invited_at' => $user->invited_at?->toIso8601ZuluString(),
            'assignments' => $user->properties
                ->filter(fn (Property $property): bool => in_array($property->id, $this->manageable, true))
                ->sortBy('name')
                ->map(fn (Property $property): array => [
                    'property' => ['id' => $property->public_id, 'name' => $property->name, 'code' => $property->code],
                    'role' => $this->role((int) $property->pivot->getAttribute('role_id')),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Options for filters and forms: properties the actor manages and roles they can assign.
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return [
            'properties' => Property::query()->whereKey($this->manageable)->orderBy('name')->get()
                ->map(fn (Property $property): array => ['id' => $property->public_id, 'name' => $property->name, 'code' => $property->code])
                ->values(),
            'roles' => $this->roles
                ->reject(fn (Role $role): bool => $role->name === RoleEnum::SuperAdmin->value)
                ->sortBy(fn (Role $role): string => ($role->isSystem() ? '0' : '1').$role->displayLabel())
                ->map(fn (Role $role): array => ['name' => $role->name, 'label' => $role->displayLabel(), 'is_system' => $role->isSystem()])
                ->values(),
            'can_manage_accounts' => $this->actorIsSuperAdmin,
        ];
    }

    /**
     * @return array{name: string, label: string}|null
     */
    private function role(int $roleId): ?array
    {
        $role = $this->roles->get($roleId);

        return $role ? ['name' => $role->name, 'label' => $role->displayLabel()] : null;
    }
}
