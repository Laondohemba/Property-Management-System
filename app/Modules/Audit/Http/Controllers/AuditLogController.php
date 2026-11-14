<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\DTOs\AuditEntryData;
use App\Modules\Audit\Models\AuditEntry;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Amenity;
use App\Modules\Inventory\Models\Room;
use App\Modules\Inventory\Models\RoomType;
use App\Modules\Property\Models\Property;
use App\Support\Http\Bff;
use App\Support\Http\DTOs\PageMetaData;
use App\Support\Http\Paginated;
use App\Support\PropertyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * GET /bff/audit-logs: read-only audit trail search (spec §8.2, AUD-03).
 *
 * Super Admins see their whole organisation (including organisation-wide security events);
 * holders of audit.view see entries for the active property only.
 */
class AuditLogController extends Controller
{
    public function __invoke(Request $request, PropertyContext $context): JsonResponse
    {
        /** @var User $user */
        $user = $this->actor($request);
        $superAdmin = $user->isSuperAdmin();
        $property = $context->property();

        abort_unless(
            $superAdmin || ($property !== null && $user->hasPropertyPermission($property, Permission::AuditView)),
            403,
            'You do not have access to the audit log.',
        );

        $entries = AuditEntry::query()
            ->where('organization_id', $user->organization_id)
            ->when(! $superAdmin, fn (Builder $query) => $query->where('property_id', $property?->id))
            ->when($superAdmin && $request->filled('filter.property'), fn (Builder $query) => $query->where(
                'property_id',
                Property::where('public_id', $request->string('filter.property'))->value('id'),
            ))
            ->when($request->filled('filter.log'), fn (Builder $query) => $query->where('log_name', $request->string('filter.log')))
            ->when($request->filled('filter.event'), fn (Builder $query) => $query->where('event', $request->string('filter.event')))
            ->when($request->filled('filter.user'), fn (Builder $query) => $query->where('causer_type', (new User)->getMorphClass())->where(
                'causer_id',
                User::where('organization_id', $user->organization_id)->where('public_id', $request->string('filter.user'))->value('id'),
            ))
            ->when($request->filled('filter.subject_type') && $request->filled('filter.subject'), function (Builder $query) use ($request, $user): void {
                // History of one record, e.g. ?filter[subject_type]=room&filter[subject]=<public id>.
                [$class, $id] = $this->resolveSubject((string) $request->string('filter.subject_type'), (string) $request->string('filter.subject'), $user);
                $query->where('subject_type', $class)->where('subject_id', $id ?? 0);
            })
            ->when($request->filled('filter.search'), fn (Builder $query) => $query->where('description', 'like', '%'.$request->string('filter.search')->trim().'%'))
            ->when($request->filled('filter.from'), fn (Builder $query) => $query->where('created_at', '>=', Carbon::parse((string) $request->string('filter.from'))->startOfDay()))
            ->when($request->filled('filter.to'), fn (Builder $query) => $query->where('created_at', '<=', Carbon::parse((string) $request->string('filter.to'))->endOfDay()))
            ->with(['causer', 'subject'])
            ->orderByDesc('id')
            ->paginate(Paginated::perPage($request));

        $properties = Property::withoutGlobalScopes()
            ->whereKey($entries->getCollection()->pluck('property_id')->filter()->unique())
            ->get()
            ->keyBy('id')
            ->all();

        return Bff::respond(
            $entries->getCollection()->map(fn (AuditEntry $entry): AuditEntryData => AuditEntryData::fromModel($entry, $properties))->all(),
            PageMetaData::fromPaginator($entries),
        );
    }

    /**
     * Map a subject key and public id to the stored morph class and internal id (unknown → no match).
     *
     * @return array{0: string, 1: int|null}
     */
    private function resolveSubject(string $type, string $publicId, User $user): array
    {
        $class = match ($type) {
            'property' => Property::class,
            'room_type' => RoomType::class,
            'room' => Room::class,
            'amenity' => Amenity::class,
            'user' => User::class,
            default => null,
        };

        if ($class === null) {
            return ['', null];
        }

        $id = $class::withoutGlobalScopes()
            ->where('public_id', $publicId)
            ->where('organization_id', $user->organization_id)
            ->value('id');

        return [(new $class)->getMorphClass(), $id === null ? null : (int) $id];
    }
}
