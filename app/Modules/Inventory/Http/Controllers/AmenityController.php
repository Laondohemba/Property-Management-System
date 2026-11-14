<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Inventory\DTOs\AmenityData;
use App\Modules\Inventory\Http\Requests\AmenityRequest;
use App\Modules\Inventory\Models\Amenity;
use App\Support\Http\Bff;
use App\Support\Http\DTOs\PageMetaData;
use App\Support\Http\Paginated;
use App\Support\PropertyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * /bff/settings/amenities: the amenity catalogue of the active property.
 */
class AmenityController extends Controller implements HasMiddleware
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
        $amenities = Amenity::forProperty($this->context->require())
            ->withCount('roomTypes')
            ->orderBy('name')
            ->paginate(Paginated::perPage($request));

        return Bff::respond(
            $amenities->getCollection()->map(fn (Amenity $amenity): AmenityData => AmenityData::fromModel($amenity))->all(),
            PageMetaData::fromPaginator($amenities),
        );
    }

    public function store(AmenityRequest $request): JsonResponse
    {
        $property = $this->context->require();

        $amenity = Amenity::create([
            'property_id' => $property->id,
            'organization_id' => $property->organization_id,
            'name' => $request->validated('name'),
        ]);

        return Bff::respond(AmenityData::fromModel($amenity->loadCount('roomTypes')), status: 201);
    }

    public function update(AmenityRequest $request, Amenity $amenity): JsonResponse
    {
        $this->context->ensureOwns($amenity);

        $amenity->update(['name' => $request->validated('name')]);

        return Bff::respond(AmenityData::fromModel($amenity->loadCount('roomTypes')));
    }

    /**
     * Amenities are descriptive only, so removing one simply detaches it from its room types.
     */
    public function destroy(Amenity $amenity): Response
    {
        $this->context->ensureOwns($amenity);

        $amenity->delete();

        return response()->noContent();
    }
}
