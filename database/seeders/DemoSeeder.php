<?php

namespace Database\Seeders;

use App\Modules\Identity\Enums\Role as RoleEnum;
use App\Modules\Identity\Models\User;
use App\Modules\Property\Models\Organization;
use App\Modules\Property\Models\Property;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Local demo data. Idempotent, so it can be re-run without wiping existing accounts.
 * All demo users have the password "password". Never runs in production.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::firstOrCreate(['slug' => 'demo-hotels'], ['name' => 'Demo Hotels']);

        $admin = $this->user($organization, 'admin@example.com', 'Demo Admin');
        $admin->assignRole(RoleEnum::SuperAdmin->value);

        $property = Property::withoutGlobalScopes()->firstOrCreate(
            ['organization_id' => $organization->id, 'code' => 'DEMO'],
            [
                'name' => 'Demo Grand Hotel',
                'timezone' => 'Africa/Lagos',
                'currency' => 'NGN',
                'business_date' => now('Africa/Lagos')->toDateString(),
                'check_in_time' => '14:00',
                'check_out_time' => '12:00',
                'city' => 'Lagos',
                'country' => 'NG',
            ],
        );

        $frontDesk = $this->user($organization, 'frontdesk@example.com', 'Demo Front Desk');
        $property->users()->syncWithoutDetaching([
            $frontDesk->id => ['role_id' => Role::findByName(RoleEnum::FrontDeskAgent->value, 'web')->id],
        ]);
    }

    private function user(Organization $organization, string $email, string $name): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['organization_id' => $organization->id, 'name' => $name, 'password' => 'password'],
        );

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user;
    }
}
