<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Models\User;
use App\Support\Http\Problem;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of a user whose account or organisation was deactivated
 * after they signed in.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->canSignIn()) {
            Auth::guard('web')->logout();

            // Sanctum only starts a session for first-party (stateful) requests.
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return Problem::response(401, 'account_inactive', 'Account inactive', trans('auth.inactive'));
        }

        return $next($request);
    }
}
