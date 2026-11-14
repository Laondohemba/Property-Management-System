<?php

namespace Database\Seeders;

use App\Modules\Identity\Enums\Permission as PermissionEnum;
use App\Modules\Identity\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Seed the permissions and the default role matrix. Safe to run repeatedly.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (RoleEnum::cases() as $roleEnum) {
            Role::findOrCreate($roleEnum->value, 'web')->syncPermissions(
                array_map(fn (PermissionEnum $permission): string => $permission->value, $roleEnum->defaultPermissions()),
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
