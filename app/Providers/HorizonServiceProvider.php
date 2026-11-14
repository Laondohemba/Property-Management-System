<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

/**
 * Horizon supervises the queues in the worker pods (spec §3.2, §10.3).
 * Supervisors and queues are defined in config/horizon.php.
 */
class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * The queue dashboard is an operations tool: Super Admins only, in every environment
     * except local, where Horizon allows access itself.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user = null): bool => $user?->isSuperAdmin() === true && $user->is_active);
    }
}
