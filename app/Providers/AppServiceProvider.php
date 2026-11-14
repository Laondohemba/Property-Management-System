<?php

namespace App\Providers;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
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
        // Surface N+1 queries during development (spec §10.2).
        Model::preventLazyLoading(! $this->app->isProduction());

        // Strong password policy with breached-password check outside local/testing (spec §13).
        Password::defaults(function (): Password {
            $rule = Password::min(12)->letters()->mixedCase()->numbers()->symbols();

            return $this->app->environment('local', 'testing') ? $rule : $rule->uncompromised();
        });

        // Super Admins pass every authorisation check.
        Gate::before(fn (User $user): ?bool => $user->hasRole(Role::SuperAdmin->value) ? true : null);

        // Default BFF limit: 120 requests/minute per user, or per IP for guests (spec §8.1).
        RateLimiter::for('bff', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));

        Event::listen(function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });
    }
}
