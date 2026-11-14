<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Models\User;
use App\Support\Http\Problem;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When a user's role requires two-factor authentication and they have not set it up,
 * the BFF answers 403 two_factor_required until they do (SYS-01). GET /bff/session stays
 * open so the SPA can send them to the setup screen; the Fortify /user/* endpoints used for
 * setup are outside /bff.
 */
class EnsureTwoFactorEnrolled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $request->routeIs('bff.session') && $user->mustSetUpTwoFactor()) {
            return Problem::response(
                403,
                'two_factor_required',
                'Two-factor authentication required',
                'Your role requires two-factor authentication. Set it up on your profile to continue.',
            );
        }

        return $next($request);
    }
}
