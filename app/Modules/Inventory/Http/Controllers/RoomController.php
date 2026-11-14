<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Inventory\Actions\CreateRoomRange;
use App\Modules\Inventory\Actions\SaveRoom;
use App\Modules\Inventory\DTOs\RoomData;
use App\Modules\Inventory\DTOs\RoomListMetaData;
use App\Modules\Inventory\DTOs\RoomRangeMetaData;
use App\Modules\Inventory\Http\Requests\RoomRangeRequest;
use App\Modules\Inventory\Http\Requests\RoomRequest;
use App\Modules\Inventory\Models\Room;
use App\Support\Http\Bff;
use App\Support\Http\Paginated;
use App\Support\PropertyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * /bff/settings/rooms (SYS-04).
 */
class RoomController extends Controller implements HasMiddleware
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
        $property = $this->context->require();

        $rooms = Room::forProperty($property)
            ->with('roomType')
            ->when($request->filled('filter.room_type'), fn (Builder $query) => $query->whereHas(
                'roomType',
                fn (Builder $query) => $query->where('public_id', $request->string('filter.room_type')),
            ))
            ->when($request->filled('filter.floor'), fn (Builder $query) => $query->where('floor', $request->string('filter.floor')))
            ->when($request->has('filter.active'), fn (Builder $query) => $query->where('is_active', $request->boolean('filter.active')))
            ->when($request->filled('filter.search'), fn (Builder $query) => $query->where('number', 'like', '%'.$request->string('filter.search')->trim().'%'))
            ->orderBy('sort_order')
            ->orderByRaw('LENGTH(number), number')
            ->paginate(Paginated::perPage($request));

        // Filter options shown by the rooms screen (view-shaped, spec §8.1).
        $floors = Room::forProperty($property)->whereNotNull('floor')->distinct()->orderByRaw('LENGTH(floor), floor')->pluck('floor')->all();

        return Bff::respond(
            $rooms->getCollection()->map(fn (Room $room): RoomData => RoomData::fromModel($room))->all(),
            RoomListMetaData::make($rooms, $floors),
        );
    }

    public function store(RoomRequest $request, SaveRoom $save): JsonResponse
    {
        $room = $save->handle($request->user(), $this->context->require(), $request->validated());

        return Bff::respond(RoomData::fromModel($room), status: 201);
    }

    /**
     * POST /bff/settings/rooms/range: create a run of numbered rooms, e.g. 101–120.
     */
    public function storeRange(RoomRangeRequest $request, CreateRoomRange $createRange): JsonResponse
    {
        $rooms = $createRange->handle(
            $request->user(),
            $this->context->require(),
            $request->validated('room_type_id'),
            $request->numbers(),
            $request->validated('floor'),
        );

        return Bff::respond(
            $rooms->map(fn (Room $room): RoomData => RoomData::fromModel($room))->all(),
            new RoomRangeMetaData($rooms->count()),
            201,
        );
    }

    public function show(Room $room): JsonResponse
    {
        $this->context->ensureOwns($room);

        return Bff::respond(RoomData::fromModel($room->load('roomType')));
    }

    public function update(RoomRequest $request, Room $room, SaveRoom $save): JsonResponse
    {
        $this->context->ensureOwns($room);

        return Bff::respond(RoomData::fromModel(
            $save->handle($request->user(), $this->context->require(), $request->validated(), $room),
        ));
    }
}
