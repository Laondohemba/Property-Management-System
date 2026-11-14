<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Audit;
use App\Modules\Identity\DTOs\PermissionGroupData;
use App\Modules\Identity\DTOs\RoleData;
use App\Modules\Identity\DTOs\RoleMetaData;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role as RoleEnum;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Support\Http\Bff;
use App\Support\Http\Problem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

/**
 * /bff/roles: roles and the permission matrix (SYS-02). Organisation-wide, so Super Admins only.
 * Built-in roles are read-only except for their two-factor requirement; custom roles are editable.
 */
class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $organization = $this->organization($request);
        $roles = $this->rolesQuery($request)->with('permissions')->get();
        $counts = $this->userCounts($request, $roles);

        return Bff::respond(
            $roles
                ->sortBy(fn (Role $role): string => ($role->isSystem() ? '0'.str_pad((string) $role->id, 4, '0', STR_PAD_LEFT) : '1'.$role->displayLabel()))
                ->map(fn (Role $role): RoleData => $this->present($role, $organization->twoFactorRoles(), $counts[$role->id] ?? 0))
                ->values()
                ->all(),
            new RoleMetaData(PermissionGroupData::catalogue()),
        );
    }

    public function show(Request $request, string $role): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $model = $this->find($request, $role)->load('permissions');

        return $this->roleResponse($request, $model);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);
        $validated = $this->validateRole($request);

        $role = DB::transaction(function () use ($request, $validated): Role {
            /** @var Role $role */
            $role = Role::create([
                'name' => 'custom-'.Str::lower((string) Str::ulid()),
                'guard_name' => 'web',
                'organization_id' => $this->actor($request)->organization_id,
                'label' => $validated['label'],
                'description' => $validated['description'] ?? null,
            ]);
            $role->syncPermissions($validated['permissions'] ?? []);

            if ($validated['requires_two_factor'] ?? false) {
                $this->organization($request)->setTwoFactorRequired($role->name, true);
            }

            Audit::security('role_created', "Created role {$role->displayLabel()}", $role, [
                'permissions' => array_values($validated['permissions'] ?? []),
                'requires_two_factor' => (bool) ($validated['requires_two_factor'] ?? false),
            ]);

            return $role;
        });

        return $this->roleResponse($request, $role->load('permissions'))->setStatusCode(201);
    }

    public function update(Request $request, string $role): JsonResponse
    {
        $this->authorizeSuperAdmin($request);
        $model = $this->find($request, $role);

        if ($model->isSystem() && $request->hasAny(['label', 'description', 'permissions'])) {
            throw ValidationException::withMessages([
                'permissions' => 'Built-in roles cannot be changed. Duplicate the role to customise its permissions.',
            ]);
        }

        $validated = $model->isSystem()
            ? $request->validate(['requires_two_factor' => ['required', 'boolean']])
            : $this->validateRole($request, $model);

        DB::transaction(function () use ($request, $model, $validated): void {
            $organization = $this->organization($request);
            $before = [
                'label' => $model->displayLabel(),
                'description' => $model->description,
                'permissions' => $model->permissions()->pluck('name')->sort()->values()->all(),
                'requires_two_factor' => in_array($model->name, $organization->twoFactorRoles(), true),
            ];

            if (! $model->isSystem()) {
                $model->fill(array_intersect_key($validated, array_flip(['label', 'description'])))->save();

                if (array_key_exists('permissions', $validated)) {
                    $model->syncPermissions($validated['permissions']);
                }
            }

            if (array_key_exists('requires_two_factor', $validated)) {
                $organization->setTwoFactorRequired($model->name, (bool) $validated['requires_two_factor']);
            }

            $after = [
                'label' => $model->displayLabel(),
                'description' => $model->description,
                'permissions' => $model->permissions()->pluck('name')->sort()->values()->all(),
                'requires_two_factor' => in_array($model->name, $organization->twoFactorRoles(), true),
            ];
            $changed = array_keys(array_filter($after, fn (mixed $value, string $key): bool => $value !== $before[$key], ARRAY_FILTER_USE_BOTH));

            if ($changed !== []) {
                Audit::security('role_updated', "Updated role {$model->displayLabel()}", $model, [
                    'old' => array_intersect_key($before, array_flip($changed)),
                    'attributes' => array_intersect_key($after, array_flip($changed)),
                    'permissions_added' => array_values(array_diff($after['permissions'], $before['permissions'])),
                    'permissions_removed' => array_values(array_diff($before['permissions'], $after['permissions'])),
                ]);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->roleResponse($request, $model->refresh()->load('permissions'));
    }

    public function destroy(Request $request, string $role): Response|JsonResponse
    {
        $this->authorizeSuperAdmin($request);
        $model = $this->find($request, $role);

        if ($model->isSystem()) {
            return Problem::response(409, 'conflict', 'Conflict', 'Built-in roles cannot be deleted.');
        }

        if (DB::table('property_user')->where('role_id', $model->id)->exists()) {
            return Problem::response(409, 'conflict', 'Conflict', 'This role is still assigned to staff. Reassign them first.');
        }

        DB::transaction(function () use ($request, $model): void {
            Audit::security('role_deleted', "Deleted role {$model->displayLabel()}", null, [
                'role' => $model->name,
                'permissions' => $model->permissions()->pluck('name')->sort()->values()->all(),
            ]);

            $this->organization($request)->setTwoFactorRequired($model->name, false);
            $model->delete();
        });

        return response()->noContent();
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($this->actor($request)->isSuperAdmin(), 403, 'Only Super Admins can manage roles.');
    }

    /**
     * @return Builder<Role>
     */
    private function rolesQuery(Request $request)
    {
        return Role::query()->visibleTo((int) $this->actor($request)->organization_id);
    }

    private function find(Request $request, string $name): Role
    {
        return $this->rolesQuery($request)->where('name', $name)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRole(Request $request, ?Role $role = null): array
    {
        $required = $role ? 'sometimes' : 'required';
        $organizationId = $this->actor($request)->organization_id;
        $systemLabels = array_map(fn (RoleEnum $role): string => $role->label(), RoleEnum::cases());

        return $request->validate([
            'label' => [
                $required, 'string', 'max:100', Rule::notIn($systemLabels),
                Rule::unique('roles', 'label')->where('organization_id', $organizationId)->ignore($role?->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(Permission::values())],
            'requires_two_factor' => ['sometimes', 'boolean'],
        ], [
            'label.not_in' => 'This name is used by a built-in role.',
            'label.unique' => 'Another role already has this name.',
        ]);
    }

    /**
     * Staff holding each role within the organisation.
     *
     * @param  Collection<int, Role>  $roles
     * @return array<int, int>
     */
    private function userCounts(Request $request, Collection $roles): array
    {
        $organizationId = $this->actor($request)->organization_id;

        $counts = DB::table('property_user')
            ->join('users', 'users.id', '=', 'property_user.user_id')
            ->where('users.organization_id', $organizationId)
            ->whereIn('property_user.role_id', $roles->pluck('id'))
            ->groupBy('property_user.role_id')
            ->selectRaw('property_user.role_id, COUNT(DISTINCT property_user.user_id) as total')
            ->pluck('total', 'role_id')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        $superAdmin = $roles->firstWhere('name', RoleEnum::SuperAdmin->value);
        if ($superAdmin) {
            $counts[$superAdmin->id] = User::role(RoleEnum::SuperAdmin->value)->where('organization_id', $organizationId)->count();
        }

        return $counts;
    }

    /**
     * @param  list<string>  $twoFactorRoles
     */
    private function present(Role $role, array $twoFactorRoles, int $usersCount): RoleData
    {
        return new RoleData(
            name: $role->name,
            label: $role->displayLabel(),
            description: $role->description,
            is_system: $role->isSystem(),
            is_super_admin: $role->name === RoleEnum::SuperAdmin->value,
            requires_two_factor: in_array($role->name, $twoFactorRoles, true),
            permissions: $role->permissions->pluck('name')->sort()->values()
                ->map(fn (string $name): Permission => Permission::from($name))->all(),
            users_count: $usersCount,
        );
    }

    private function roleResponse(Request $request, Role $role): JsonResponse
    {
        $organization = $this->organization($request);
        $count = $this->userCounts($request, collect([$role]))[$role->id] ?? 0;

        return Bff::respond(
            $this->present($role, $organization->twoFactorRoles(), $count),
            new RoleMetaData(PermissionGroupData::catalogue()),
        );
    }
}
