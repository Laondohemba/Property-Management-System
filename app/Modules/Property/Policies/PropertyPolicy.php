<?php

declare(strict_types=1);

namespace App\Modules\Property\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Modules\Property\Models\Property;

/**
 * Super Admins pass every check via Gate::before; everyone else is checked per property.
 */
class PropertyPolicy
{
    /**
     * Open the property settings list: needs settings.property at one or more properties.
     */
    public function viewAny(User $user): bool
    {
        return $user->propertyIdsWithPermission(Permission::SettingsProperty) !== [];
    }

    public function view(User $user, Property $property): bool
    {
        return $user->hasPropertyPermission($property, Permission::SettingsProperty);
    }

    /**
     * Adding properties changes the organisation itself, so only Super Admins may do it.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Property $property): bool
    {
        return $user->hasPropertyPermission($property, Permission::SettingsProperty);
    }
}
