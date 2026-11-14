<?php

declare(strict_types=1);

namespace App\Modules\Identity\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Property\Models\Property;

/**
 * Staff administration (SYS-01). Super Admins pass via Gate::before. Everyone else
 * manages access only at properties where they hold users.manage, and never
 * account-wide settings (activation, Super Admin).
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->manageablePropertyIds() !== [];
    }

    public function view(User $actor, User $target): bool
    {
        return $target->organization_id === $actor->organization_id
            && $target->properties()->whereIn('properties.id', $actor->manageablePropertyIds())->exists();
    }

    public function invite(User $actor, Property $property): bool
    {
        return in_array($property->id, $actor->manageablePropertyIds(), true);
    }

    /**
     * Account-wide changes: name, activation and the Super Admin role.
     */
    public function updateAccount(User $actor, User $target): bool
    {
        return false;
    }

    public function manageAccess(User $actor, User $target, Property $property): bool
    {
        return $target->organization_id === $actor->organization_id
            && ! $target->isSuperAdmin()
            && in_array($property->id, $actor->manageablePropertyIds(), true);
    }

    public function resendInvitation(User $actor, User $target): bool
    {
        return $this->view($actor, $target);
    }
}
