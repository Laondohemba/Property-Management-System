<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\DTOs\AuditEntryData;
use App\Modules\Audit\Models\AuditEntry;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
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
        $user = $request->user();
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
}
