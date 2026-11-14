<?php

namespace App\Providers;

use App\Modules\Audit\Listeners\RecordSecurityEvents;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Support\PropertyContext;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One property context per request; reset between requests (stateless pods, spec ARC-08).
        $this->app->scoped(PropertyContext::class);

        // TypeScript generation for the SPA contract (ARC-11) is a dev-only tool.
        if (class_exists(TypeScriptTransformerApplicationServiceProvider::class)) {
            $this->app->register(TypeScriptTransformerServiceProvider::class);
        }
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

        // Deny by default; permissions are checked against the role held at the active property (spec §13).
        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->isSuperAdmin()) {
                return true;
            }

            $permission = Permission::tryFrom($ability);

            if ($permission === null) {
                return null; // Not a permission name: let the policy decide.
            }

            $property = $this->app->make(PropertyContext::class)->property();

            return $property !== null && $user->hasPropertyPermission($property, $permission);
        });

        // Default BFF limit: 120 requests/minute per user, or per IP for guests (spec §8.1).
        RateLimiter::for('bff', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));

        Event::subscribe(RecordSecurityEvents::class);

        Event::listen(function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });
    }
}
