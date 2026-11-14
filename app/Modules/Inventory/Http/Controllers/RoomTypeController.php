<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Inventory\Actions\SaveRoomType;
use App\Modules\Inventory\DTOs\RoomTypeData;
use App\Modules\Inventory\DTOs\RoomTypeListMetaData;
use App\Modules\Inventory\Http\Requests\RoomTypeRequest;
use App\Modules\Inventory\Models\RoomType;
use App\Support\Http\Bff;
use App\Support\Http\Paginated;
use App\Support\PropertyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * /bff/settings/room-types (SYS-04).
 */
class RoomTypeController extends Controller implements HasMiddleware
{
    public function __construct(private readonly PropertyContext $context) {}

    /**
     * Runs after the route's property middleware, so the permission is checked at the active property.
     */
    public static function middleware(): array
    {
        return [new Middleware('can:'.Permission::SettingsRooms->value)];
    }

    public function index(Request $request): JsonResponse
    {
        $roomTypes = RoomType::forProperty($this->context->require())
            ->with(['amenities', ...RoomType::stampRelations()])
            ->withCount('rooms')
            ->when($request->has('filter.active'), fn ($query) => $query->where('is_active', $request->boolean('filter.active')))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(Paginated::perPage($request));

        return Bff::respond(
            $roomTypes->getCollection()->map(fn (RoomType $roomType): RoomTypeData => RoomTypeData::fromModel($roomType))->all(),
            RoomTypeListMetaData::make($roomTypes),
        );
    }

    public function store(RoomTypeRequest $request, SaveRoomType $save): JsonResponse
    {
        $roomType = $save->handle($this->actor($request), $this->context->require(), $request->validated());

        return Bff::respond(RoomTypeData::fromModel($roomType), status: 201);
    }

    public function show(RoomType $roomType): JsonResponse
    {
        $this->context->ensureOwns($roomType);

        return Bff::respond(RoomTypeData::fromModel($roomType->load('amenities')->loadCount('rooms')));
    }

    public function update(RoomTypeRequest $request, RoomType $roomType, SaveRoomType $save): JsonResponse
    {
        $this->context->ensureOwns($roomType);

        return Bff::respond(RoomTypeData::fromModel(
            $save->handle($this->actor($request), $this->context->require(), $request->validated(), $roomType),
        ));
    }
}
