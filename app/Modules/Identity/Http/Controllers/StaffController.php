<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Audit;
use App\Modules\Identity\Actions\InviteUser;
use App\Modules\Identity\Actions\SendInvitation;
use App\Modules\Identity\DTOs\StaffListMetaData;
use App\Modules\Identity\DTOs\StaffResponseMetaData;
use App\Modules\Identity\DTOs\StaffUserData;
use App\Modules\Identity\Enums\Role as RoleEnum;
use App\Modules\Identity\Http\Presenters\StaffPresenter;
use App\Modules\Identity\Http\Requests\InviteUserRequest;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Property\Models\Property;
use App\Support\Http\Bff;
use App\Support\Http\Paginated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * /bff/users: staff administration (SYS-01).
 */
class StaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        /** @var User $actor */
        $actor = $this->actor($request);
        $presenter = new StaffPresenter($actor);

        $users = $this->staffQuery($actor)
            ->when($request->filled('filter.search'), function (Builder $query) use ($request): void {
                $term = '%'.$request->string('filter.search')->trim().'%';
                $query->where(fn (Builder $query) => $query->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($request->filled('filter.property'), fn (Builder $query) => $query->whereHas(
                'properties',
                fn (Builder $query) => $query->where('properties.public_id', $request->string('filter.property')),
            ))
            ->when($request->filled('filter.status'), fn (Builder $query) => match ((string) $request->string('filter.status')) {
                'inactive' => $query->where('is_active', false),
                'invited' => $query->where('is_active', true)->whereNotNull('invited_at')->whereNull('email_verified_at'),
                default => $query->where('is_active', true)->where(fn (Builder $query) => $query->whereNull('invited_at')->orWhereNotNull('email_verified_at')),
            })
            ->orderBy('name')
            ->paginate(Paginated::perPage($request));

        return Bff::respond(
            $users->getCollection()->map(fn (User $user): StaffUserData => $presenter->user($user))->all(),
            StaffListMetaData::make($users, $presenter->options()),
        );
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->ensureSameOrganization($request, $user);
        Gate::authorize('view', $user);

        return $this->userResponse($request, $user);
    }

    /**
     * Invite someone to a property, or give an existing colleague access to it.
     */
    public function store(InviteUserRequest $request, InviteUser $inviteUser): JsonResponse
    {
        $property = $request->property();
        Gate::authorize('invite', [User::class, $property]);

        ['user' => $user, 'invited' => $invited] = $inviteUser->handle(
            $this->actor($request),
            $request->validated('name'),
            $request->validated('email'),
            $property,
            $request->role(),
        );

        return $this->userResponse($request, $user, $invited)->setStatusCode(201);
    }

    /**
     * Account-wide changes, Super Admins only: name, activation and the Super Admin role.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $this->ensureSameOrganization($request, $user);
        Gate::authorize('updateAccount', $user);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'is_super_admin' => ['sometimes', 'boolean'],
        ]);

        if ($user->is($this->actor($request)) && (($validated['is_active'] ?? true) === false || ($validated['is_super_admin'] ?? true) === false)) {
            throw ValidationException::withMessages(['is_active' => 'You cannot deactivate yourself or remove your own Super Admin role.']);
        }

        $wasActive = $user->is_active;
        $user->fill(array_intersect_key($validated, array_flip(['name', 'is_active'])))->save();

        if ($wasActive !== $user->is_active) {
            Audit::security(
                $user->is_active ? 'account_reactivated' : 'account_deactivated',
                ($user->is_active ? 'Reactivated' : 'Deactivated')." the account of {$user->email}",
                $user,
            );
        }

        if (array_key_exists('is_super_admin', $validated) && $validated['is_super_admin'] !== $user->isSuperAdmin()) {
            $validated['is_super_admin']
                ? $user->assignRole(RoleEnum::SuperAdmin->value)
                : $user->removeRole(RoleEnum::SuperAdmin->value);

            Audit::security(
                $validated['is_super_admin'] ? 'super_admin_granted' : 'super_admin_revoked',
                ($validated['is_super_admin'] ? 'Granted' : 'Removed')." Super Admin for {$user->email}",
                $user,
            );
        }

        return $this->userResponse($request, $user->refresh());
    }

    /**
     * PUT /bff/users/{user}/properties/{property}: give or change the user's role at a property.
     */
    public function assign(Request $request, User $user, Property $property): JsonResponse
    {
        $this->ensureSameOrganization($request, $user);
        Gate::authorize('manageAccess', [$user, $property]);

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(
                Role::query()->visibleTo((int) $this->actor($request)->organization_id)->assignable()->pluck('name')->all(),
            )],
        ]);

        $role = Role::query()->visibleTo((int) $this->actor($request)->organization_id)->where('name', $validated['role'])->firstOrFail();
        $previous = $user->roleForProperty($property);
        $user->properties()->syncWithoutDetaching([$property->id => ['role_id' => $role->id]]);

        if ($previous?->id !== $role->id) {
            Audit::security(
                $previous ? 'role_changed' : 'access_granted',
                "{$user->email} is now {$role->displayLabel()} at {$property->name}",
                $user,
                ['role' => $role->name, 'previous_role' => $previous?->name],
                property: $property,
            );
        }

        return $this->userResponse($request, $user);
    }

    /**
     * DELETE /bff/users/{user}/properties/{property}: remove the user's access to a property.
     */
    public function unassign(Request $request, User $user, Property $property): JsonResponse
    {
        $this->ensureSameOrganization($request, $user);
        Gate::authorize('manageAccess', [$user, $property]);

        $previous = $user->roleForProperty($property);

        if ($user->properties()->detach($property->id) > 0) {
            Audit::security(
                'access_revoked',
                "Removed {$user->email}'s access to {$property->name}",
                $user,
                ['previous_role' => $previous?->name],
                property: $property,
            );
        }

        return $this->userResponse($request, $user);
    }

    public function resendInvitation(Request $request, User $user, SendInvitation $sendInvitation): Response
    {
        $this->ensureSameOrganization($request, $user);
        Gate::authorize('resendInvitation', $user);

        if ($user->status() !== 'invited') {
            throw ValidationException::withMessages(['user' => 'This person has already accepted their invitation.']);
        }

        $sendInvitation->handle($user, $this->actor($request));

        return response()->noContent();
    }

    /**
     * Users of the actor's organisation; non-Super Admins see only staff of properties they manage.
     *
     * @return Builder<User>
     */
    private function staffQuery(User $actor): Builder
    {
        return User::query()
            ->where('organization_id', $actor->organization_id)
            ->with(['properties', 'roles'])
            ->when(! $actor->isSuperAdmin(), fn (Builder $query) => $query->whereHas(
                'properties',
                fn (Builder $query) => $query->whereIn('properties.id', $actor->manageablePropertyIds()),
            ));
    }

    /**
     * Users of other organisations are treated as non-existent.
     */
    private function ensureSameOrganization(Request $request, User $user): void
    {
        if ($user->organization_id !== $this->actor($request)->organization_id) {
            abort(404);
        }
    }

    /**
     * @param  bool|null  $invited  after an invite: whether an e-mail was sent
     */
    private function userResponse(Request $request, User $user, ?bool $invited = null): JsonResponse
    {
        $presenter = new StaffPresenter($this->actor($request));
        $user->load(['properties', 'roles']);

        return Bff::respond($presenter->user($user), StaffResponseMetaData::make($presenter->options(), $invited));
    }
}
