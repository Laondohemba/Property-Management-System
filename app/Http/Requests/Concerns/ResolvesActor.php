<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Modules\Identity\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Typed access to the signed-in user from Form Requests used behind auth middleware.
 *
 * @mixin FormRequest
 */
trait ResolvesActor
{
    protected function actor(): User
    {
        $user = $this->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }

    protected function organizationId(): ?int
    {
        return $this->actor()->organization_id;
    }
}
