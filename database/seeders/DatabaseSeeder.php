<?php

namespace Database\Seeders;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Property\Models\Organization;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        if (! app()->isProduction()) {
            $organization = Organization::factory()->create([
                'name' => 'Demo Hotels',
                'slug' => 'demo-hotels',
            ]);

            User::factory()
                ->for($organization)
                ->create([
                    'name' => 'Demo Admin',
                    'email' => 'admin@example.com',
                ])
                ->assignRole(Role::SuperAdmin->value);
        }
    }
}
