<?php

declare(strict_types=1);

namespace App\Modules\Reporting\DTOs;

use Spatie\LaravelData\Data;

/**
 * GET /bff/dashboard (spec §8.2). Alerts (failed sync, overdue work orders) arrive in Phase 1.
 */
class DashboardData extends Data
{
    /**
     * @param  array<int, DashboardTileData>  $tiles
     * @param  array<int, string>  $alerts
     */
    public function __construct(
        public DashboardPropertyData $property,
        public array $tiles,
        public array $alerts,
    ) {}
}
