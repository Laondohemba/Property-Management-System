<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Property\Models\Property;
use App\Support\PropertyContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything the SPA shell needs: user, organisation, roles, permissions at the active
 * property, assigned properties and the active property itself (spec §8.2).
 *
 * @mixin User
 */
class SessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $activeProperty = app(PropertyContext::class)->property();
        $superAdmin = $this->isSuperAdmin();

        $properties = $this->accessibleProperties()->orderBy('properties.name')->get();

        return [
            'user' => [
                'id' => $this->public_id,
                'name' => $this->name,
                'email' => $this->email,
                'two_factor_enabled' => $this->hasTwoFactorEnabled(),
                'last_login_at' => $this->last_login_at?->toIso8601ZuluString(),
            ],
            'organization' => $this->organization ? [
                'id' => $this->organization->public_id,
                'name' => $this->organization->name,
            ] : null,
            'is_super_admin' => $superAdmin,
            // Organisation-wide roles (Super Admin). Property roles appear on each property below.
            'roles' => $this->roles->map(fn ($role): array => self::role($role->name))->values(),
            'permissions' => $this->permissionsForProperty($activeProperty),
            'properties' => $properties->map(fn (Property $property): array => $this->propertySummary($property, $superAdmin))->values(),
            'active_property' => $activeProperty ? $this->propertySummary($activeProperty, $superAdmin) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function propertySummary(Property $property, bool $superAdmin): array
    {
        $role = $superAdmin ? Role::SuperAdmin->value : $this->roleForProperty($property)?->name;

        return [
            'id' => $property->public_id,
            'name' => $property->name,
            'code' => $property->code,
            'timezone' => $property->timezone,
            'currency' => $property->currency,
            'business_date' => $property->business_date?->toDateString(),
            'role' => $role ? self::role($role) : null,
        ];
    }

    /**
     * @return array{name: string, label: string}
     */
    private static function role(string $name): array
    {
        return ['name' => $name, 'label' => Role::tryFrom($name)?->label() ?? $name];
    }
}
