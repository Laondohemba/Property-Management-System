<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Inventory\Actions\SummarizeInventory;
use App\Modules\Reporting\DTOs\DashboardData;
use App\Modules\Reporting\DTOs\DashboardPropertyData;
use App\Modules\Reporting\DTOs\DashboardTileData;
use App\Support\Http\Bff;
use App\Support\PropertyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * GET /bff/dashboard: the dashboard view for the active property (spec §8.2).
 * KPI tiles, arrivals/departures, occupancy trend and alerts are added in Phase 1.
 */
class DashboardController extends Controller
{
    public function __invoke(PropertyContext $context, SummarizeInventory $inventory): JsonResponse
    {
        Gate::authorize(Permission::DashboardView->value);

        $property = $context->require();
        $counts = $inventory->handle($property);

        return Bff::respond(new DashboardData(
            property: new DashboardPropertyData(
                id: $property->public_id,
                name: $property->name,
                code: $property->code,
                timezone: $property->timezone,
                currency: $property->currency,
                business_date: (string) $property->business_date?->toDateString(),
                check_in_time: substr((string) $property->check_in_time, 0, 5),
                check_out_time: substr((string) $property->check_out_time, 0, 5),
                local_time: now($property->timezone)->toIso8601String(),
            ),
            tiles: [
                new DashboardTileData('rooms', 'Active rooms', $counts['rooms']),
                new DashboardTileData('room_types', 'Room types', $counts['room_types']),
            ],
            alerts: [],
        ));
    }
}
