<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role as RoleEnum;
use App\Modules\Property\Models\Organization;
use App\Modules\Property\Models\Property;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['organization_id', 'name', 'email', 'password', 'is_active'])]
#[Hidden(['id', 'organization_id', 'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUlids, Notifiable, TwoFactorAuthenticatable;

    /**
     * Mirror the database defaults so freshly created instances behave the same.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Per-request memo of the role held at each property, keyed by property id.
     *
     * @var array<int, Role|null>
     */
    private array $propertyRoles = [];

    /**
     * Only the public identifier is a ULID; the primary key stays an internal auto-increment.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Properties this user is assigned to, with their role at each.
     *
     * @return BelongsToMany<Property, $this>
     */
    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class)->withPivot('role_id')->withTimestamps();
    }

    /**
     * A user may sign in only while both the account and its organisation are active.
     */
    public function canSignIn(): bool
    {
        return $this->is_active
            && ($this->organization_id === null || $this->organization?->is_active === true);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Super Admins manage the whole organisation and can open every property in it.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RoleEnum::SuperAdmin->value);
    }

    /**
     * Active properties the user may work in: all of the organisation's for a Super Admin,
     * otherwise the ones they are assigned to.
     *
     * @return Builder<Property>|BelongsToMany<Property, $this>
     */
    public function accessibleProperties(): Builder|BelongsToMany
    {
        $query = $this->isSuperAdmin()
            ? Property::query()->where('properties.organization_id', $this->organization_id)
            : $this->properties();

        return $query->where('properties.is_active', true);
    }

    /**
     * The role this user holds at a property, or null when not assigned.
     */
    public function roleForProperty(Property $property): ?Role
    {
        if (! array_key_exists($property->id, $this->propertyRoles)) {
            $roleId = $this->properties()->whereKey($property->id)->first()?->pivot?->getAttribute('role_id');
            $this->propertyRoles[$property->id] = $roleId ? Role::findById((int) $roleId, 'web') : null;
        }

        return $this->propertyRoles[$property->id];
    }

    public function hasPropertyPermission(Property $property, Permission $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return $property->organization_id === $this->organization_id;
        }

        return $this->roleForProperty($property)?->hasPermissionTo($permission->value) ?? false;
    }

    /**
     * Permission names the user holds at a property.
     *
     * @return list<string>
     */
    public function permissionsForProperty(?Property $property): array
    {
        if ($this->isSuperAdmin()) {
            return Permission::values();
        }

        if ($property === null) {
            return [];
        }

        return $this->roleForProperty($property)?->permissions->pluck('name')->sort()->values()->all() ?? [];
    }

    /**
     * Ids of the properties where the user holds the given permission.
     *
     * @return list<int>
     */
    public function propertyIdsWithPermission(Permission $permission): array
    {
        $roleIds = Role::query()
            ->whereHas('permissions', fn (Builder $query) => $query->where('name', $permission->value))
            ->pluck('id');

        return $this->properties()
            ->wherePivotIn('role_id', $roleIds)
            ->pluck('properties.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
