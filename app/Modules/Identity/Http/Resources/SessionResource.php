<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class SessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
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
            'roles' => $this->roles->map(fn ($role): array => [
                'name' => $role->name,
                'label' => Role::tryFrom($role->name)?->label() ?? $role->name,
            ])->values(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values(),
            // Filled once the Property module lands (property assignment per user, X-Property-Id).
            'properties' => [],
            'active_property' => null,
        ];
    }
}
