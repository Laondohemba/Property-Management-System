<?php

declare(strict_types=1);

namespace App\Modules\Property\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Modules\Property\Actions\CreateProperty;
use App\Modules\Property\Actions\UpdateProperty;
use App\Modules\Property\DTOs\PropertyData;
use App\Modules\Property\Http\Requests\PropertyRequest;
use App\Modules\Property\Models\Property;
use App\Support\Http\Bff;
use App\Support\Http\DTOs\PageMetaData;
use App\Support\Http\Paginated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * /bff/settings/properties: property setup (SYS-03).
 */
class PropertySettingsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Property::class);

        /** @var User $user */
        $user = $request->user();

        $properties = Property::query()
            ->when(! $user->isSuperAdmin(), fn ($query) => $query->whereIn(
                'id',
                $user->propertyIdsWithPermission(Permission::SettingsProperty),
            ))
            ->when($request->filled('filter.search'), fn ($query) => $query->where(function ($query) use ($request): void {
                $term = '%'.$request->string('filter.search')->trim().'%';
                $query->where('name', 'like', $term)->orWhere('code', 'like', $term);
            }))
            ->orderBy('name')
            ->paginate(Paginated::perPage($request));

        return Bff::respond(
            $properties->getCollection()->map(fn (Property $property): PropertyData => PropertyData::fromModel($property))->all(),
            PageMetaData::fromPaginator($properties),
        );
    }

    public function store(PropertyRequest $request, CreateProperty $createProperty): JsonResponse
    {
        Gate::authorize('create', Property::class);

        $property = $createProperty->handle($request->user(), $request->validated());

        return Bff::respond(PropertyData::fromModel($property), status: 201);
    }

    public function show(Property $property): JsonResponse
    {
        Gate::authorize('view', $property);

        return Bff::respond(PropertyData::fromModel($property));
    }

    public function update(PropertyRequest $request, Property $property, UpdateProperty $updateProperty): JsonResponse
    {
        Gate::authorize('update', $property);

        return Bff::respond(PropertyData::fromModel($updateProperty->handle($request->user(), $property, $request->validated())));
    }
}
