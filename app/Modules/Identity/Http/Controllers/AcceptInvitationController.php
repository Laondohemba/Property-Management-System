<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Audit;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

/**
 * POST /accept-invitation: the invitee chooses their password (SYS-01).
 * Session-auth endpoint alongside Fortify's, not part of /bff.
 */
class AcceptInvitationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::broker('invites')->reset($credentials, function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'email_verified_at' => $user->email_verified_at ?? now(),
                'remember_token' => Str::random(60),
            ])->save();

            Audit::security('invitation_accepted', "{$user->email} accepted their invitation", $user, [], $user);
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => $status === Password::INVALID_TOKEN
                    ? 'This invitation link is invalid or has expired. Ask your administrator to send a new one.'
                    : trans($status),
            ]);
        }

        return new JsonResponse(['message' => 'Your account is ready. You can now sign in.']);
    }
}
