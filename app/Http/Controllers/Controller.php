<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Property\Models\Organization;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * The signed-in staff member. Routes using this sit behind auth middleware, so a missing
     * user means the session ended mid-request: answer 401 like any unauthenticated call.
     */
    protected function actor(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }

    /**
     * The signed-in user's organisation (organisation-wide administration requires one).
     */
    protected function organization(Request $request): Organization
    {
        $organization = $this->actor($request)->organization;

        abort_if($organization === null, 403, 'This action requires an organisation.');

        return $organization;
    }
}
