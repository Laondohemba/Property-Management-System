<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Audit\Audit;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Property\Models\Property;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteUser
{
    public function __construct(private readonly SendInvitation $sendInvitation) {}

    /**
     * Give someone a role at a property. New people get an account and an invitation e-mail;
     * existing colleagues in the same organisation simply gain access to the property.
     *
     * @return array{user: User, invited: bool}
     */
    public function handle(User $actor, string $name, string $email, Property $property, Role $role): array
    {
        $email = Str::lower(trim($email));
        $existing = User::where('email', $email)->first();

        if ($existing && $existing->organization_id !== $actor->organization_id) {
            throw ValidationException::withMessages(['email' => 'This e-mail address is already used by another organisation.']);
        }

        if ($existing && $existing->properties()->whereKey($property->id)->exists()) {
            throw ValidationException::withMessages(['email' => "{$existing->name} already has access to {$property->name}."]);
        }

        return DB::transaction(function () use ($actor, $name, $email, $property, $role, $existing): array {
            $user = $existing ?? User::create([
                'organization_id' => $actor->organization_id,
                'name' => $name,
                'email' => $email,
                // Unusable until the invitee sets their own password.
                'password' => Str::random(64),
                'invited_at' => now(),
                'invited_by' => $actor->id,
            ]);

            $property->users()->attach($user->id, ['role_id' => $role->id]);

            Audit::security(
                'access_granted',
                "{$user->email} is now {$role->displayLabel()} at {$property->name}",
                $user,
                ['role' => $role->name, 'previous_role' => null],
                $actor,
                $property,
            );

            if ($existing === null) {
                $this->sendInvitation->handle($user, $actor);
            }

            return ['user' => $user, 'invited' => $existing === null];
        });
    }
}
