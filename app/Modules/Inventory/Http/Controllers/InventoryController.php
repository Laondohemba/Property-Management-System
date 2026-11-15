<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Inventory\Actions\ManageInventory;
use App\Modules\Inventory\DTOs\BlockTypeOptionData;
use App\Modules\Inventory\DTOs\InventoryMetaData;
use App\Modules\Inventory\DTOs\OverbookingResultData;
use App\Modules\Inventory\Http\Requests\InventoryGridRequest;
use App\Modules\Inventory\Http\Requests\OverbookingRequest;
use App\Modules\Inventory\Services\InventoryGrid;
use App\Support\Http\Bff;
use App\Support\PropertyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * /bff/inventory (CHN-01, spec §8.2): allotments per room type and night.
 */
class InventoryController extends Controller implements HasMiddleware
{
    public function __construct(private readonly PropertyContext $context) {}

    public static function middleware(): array
    {
        return [new Middleware('can:'.Permission::InventoryManage->value)];
    }

    public function index(InventoryGridRequest $request, InventoryGrid $grid): JsonResponse
    {
        return Bff::respond(
            $grid->build($this->context->require(), $request->from(), $request->to()),
            new InventoryMetaData(BlockTypeOptionData::options()),
        );
    }

    /**
     * PUT /bff/inventory/overbooking: returns how many nights changed.
     */
    public function overbooking(OverbookingRequest $request, ManageInventory $inventory): JsonResponse
    {
        $changed = $inventory->overbook(
            $this->actor($request),
            $this->context->require(),
            $request->roomType(),
            $request->from(),
            $request->to(),
            (int) $request->validated('allowance'),
            $request->weekdays(),
        );

        return Bff::respond(new OverbookingResultData($changed));
    }
}
