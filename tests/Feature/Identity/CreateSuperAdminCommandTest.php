<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateSuperAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_organization_and_its_super_admin(): void
    {
        $this->artisan('pms:create-super-admin', [
            '--organization' => 'Acme Hotels',
            '--name' => 'Ada Admin',
            '--email' => 'Ada@Example.com',
        ])
            ->expectsQuestion('Password', 'Str0ng-Passw0rd!')
            ->assertSuccessful();

        $user = User::with('organization')->where('email', 'ada@example.com')->firstOrFail();

        $this->assertSame('Acme Hotels', $user->organization->name);
        $this->assertTrue($user->hasRole(Role::SuperAdmin->value));
    }

    public function test_it_rejects_a_weak_password(): void
    {
        $this->artisan('pms:create-super-admin', [
            '--organization' => 'Acme Hotels',
            '--name' => 'Ada Admin',
            '--email' => 'ada@example.com',
        ])
            ->expectsQuestion('Password', 'password')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
