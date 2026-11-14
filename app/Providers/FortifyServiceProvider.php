<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Identity\Actions\Fortify\ResetUserPassword;
use App\Modules\Identity\Actions\Fortify\UpdateUserPassword;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

/**
 * Headless session authentication for the Angular SPA (spec §2.4, §13):
 * POST /login, /logout, /two-factor-challenge, /forgot-password, /reset-password and /user/* endpoints.
 */
class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::with('organization')->where('email', $request->string(Fortify::username())->value())->first();

            if (! $user || ! Hash::check($request->string('password')->value(), $user->password)) {
                return null;
            }

            // Only reached with valid credentials, so this does not reveal which emails exist.
            if (! $user->canSignIn()) {
                throw ValidationException::withMessages([Fortify::username() => trans('auth.inactive')]);
            }

            return $user;
        });

        // Password reset e-mails link to the SPA screen, not to a server-rendered view.
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => url('/password/reset/'.$token).'?'.http_build_query([
            'email' => $user->getEmailForPasswordReset(),
        ]));

        // Stricter limits on login and 2FA than on the rest of the BFF (spec §8.1).
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate($request->string(Fortify::username())->lower()->value().'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
