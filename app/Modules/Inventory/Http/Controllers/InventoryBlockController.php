<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Inventory\Actions\ManageInventory;
use App\Modules\Inventory\DTOs\InventoryBlockData;
use App\Modules\Inventory\Http\Requests\InventoryBlockRequest;
use App\Modules\Inventory\Models\InventoryBlock;
use App\Modules\Inventory\Services\InventoryLedger;
use App\Support\Http\Bff;
use App\Support\Http\DTOs\PageMetaData;
use App\Support\Http\Paginated;
use App\Support\PropertyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * /bff/inventory/blocks (CAL-06): units taken off sale for a range of nights.
 */
class InventoryBlockController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly PropertyContext $context,
        private readonly InventoryLedger $ledger,
    ) {}

    public static function middleware(): array
    {
        return [new Middleware('can:'.Permission::InventoryManage->value)];
    }

    /**
     * ?filter[status]=active (default: holding units now or later) | all.
     */
    public function index(Request $request): JsonResponse
    {
        $property = $this->context->require();
        $businessDate = $this->ledger->firstDate($property);

        $blocks = InventoryBlock::forProperty($property)
            ->with(['roomType', 'releasedBy', ...InventoryBlock::stampRelations()])
            ->when($request->input('filter.status', 'active') === 'active', fn ($query) => $query
                ->active()
                ->where('to_date', '>=', $businessDate->toDateString()))
            ->orderBy('from_date')
            ->orderBy('id')
            ->paginate(Paginated::perPage($request));

        return Bff::respond(
            $blocks->getCollection()->map(fn (InventoryBlock $block): InventoryBlockData => InventoryBlockData::fromModel($block, $businessDate))->all(),
            PageMetaData::fromPaginator($blocks),
        );
    }

    public function store(InventoryBlockRequest $request, ManageInventory $inventory): JsonResponse
    {
        $property = $this->context->require();

        $block = $inventory->block(
            $this->actor($request),
            $property,
            $request->roomType(),
            $request->type(),
            (int) $request->validated('quantity'),
            $request->from(),
            $request->to(),
            (string) $request->validated('reason'),
            $request->expiresAt(),
        );

        return Bff::respond($this->present($block), status: 201);
    }

    /**
     * POST /bff/inventory/blocks/{block}/release: puts the units back on sale.
     */
    public function release(Request $request, InventoryBlock $block, ManageInventory $inventory): JsonResponse
    {
        $this->context->ensureOwns($block);

        return Bff::respond($this->present($inventory->release($this->actor($request), $block)));
    }

    private function present(InventoryBlock $block): InventoryBlockData
    {
        $block->load(['roomType', 'releasedBy', ...InventoryBlock::stampRelations()]);

        return InventoryBlockData::fromModel($block, $this->ledger->firstDate($this->context->require()));
    }
}
