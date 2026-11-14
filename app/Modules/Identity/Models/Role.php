<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Enums\Role as RoleEnum;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Built-in roles (organization_id null) are shared and read-only; custom roles belong to
 * one organisation and are fully editable (SYS-02).
 *
 * @property int|null $organization_id
 * @property string|null $label
 * @property string|null $description
 */
class Role extends SpatieRole
{
    public function isSystem(): bool
    {
        return $this->organization_id === null;
    }

    public function displayLabel(): string
    {
        return $this->label ?? RoleEnum::tryFrom($this->name)?->label() ?? $this->name;
    }

    /**
     * Built-in roles plus the given organisation's custom roles.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, int $organizationId): void
    {
        $query->where(fn (Builder $query) => $query->whereNull('organization_id')->orWhere('organization_id', $organizationId));
    }

    /**
     * Roles that can be held at a property (everything except Super Admin).
     *
     * @param  Builder<static>  $query
     */
    public function scopeAssignable(Builder $query): void
    {
        $query->where('name', '!=', RoleEnum::SuperAdmin->value);
    }
}
