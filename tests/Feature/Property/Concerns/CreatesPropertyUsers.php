<?php

namespace Tests\Feature\Property\Concerns;

use App\Modules\Identity\Enums\Role as RoleEnum;
use App\Modules\Identity\Models\User;
use App\Modules\Property\Models\Organization;
use App\Modules\Property\Models\Property;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;

trait CreatesPropertyUsers
{
    protected Organization $organization;

    protected function setUpOrganization(): void
    {
        $this->seed(RoleSeeder::class);
        $this->organization = Organization::factory()->create();
    }

    protected function superAdmin(): User
    {
        return User::factory()->for($this->organization)->create()->assignRole(RoleEnum::SuperAdmin->value);
    }

    protected function staff(?Property $property = null, RoleEnum $role = RoleEnum::FrontDeskAgent): User
    {
        $user = User::factory()->for($this->organization)->create();

        if ($property) {
            $this->assign($user, $property, $role);
        }

        return $user;
    }

    protected function assign(User $user, Property $property, RoleEnum $role): void
    {
        $property->users()->attach($user->id, ['role_id' => Role::findByName($role->value, 'web')->id]);
    }

    protected function property(array $attributes = []): Property
    {
        return Property::factory()->for($this->organization)->create($attributes);
    }
}
