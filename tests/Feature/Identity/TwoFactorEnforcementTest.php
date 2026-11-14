<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Enums\Role;
use App\Modules\Property\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Tests\Feature\Property\Concerns\CreatesPropertyUsers;
use Tests\TestCase;

/**
 * Two-factor authentication enforceable by role (SYS-01).
 */
class TwoFactorEnforcementTest extends TestCase
{
    use CreatesPropertyUsers, RefreshDatabase;

    private Property $hotel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->hotel = $this->property();
        $this->organization->setTwoFactorRequired(Role::FrontDeskAgent->value, true);
    }

    public function test_users_in_a_required_role_are_blocked_until_they_enable_2fa(): void
    {
        $user = $this->staff($this->hotel, Role::FrontDeskAgent);

        $this->actingAs($user)
            ->getJson('/bff/session')
            ->assertOk()
            ->assertJsonPath('data.user.two_factor_required', true)
            ->assertJsonPath('data.user.two_factor_enabled', false);

        $this->actingAs($user)
            ->withHeader('X-Property-Id', $this->hotel->public_id)
            ->getJson('/bff/dashboard')
            ->assertForbidden()
            ->assertJsonPath('code', 'two_factor_required');

        // The Fortify setup endpoints stay reachable.
        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->postJson('/user/two-factor-authentication')
            ->assertOk();
    }

    public function test_access_is_restored_once_2fa_is_enabled(): void
    {
        $user = $this->staff($this->hotel, Role::FrontDeskAgent);
        app(EnableTwoFactorAuthentication::class)($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        $this->actingAs($user)
            ->withHeader('X-Property-Id', $this->hotel->public_id)
            ->getJson('/bff/dashboard')
            ->assertOk();
    }

    public function test_other_roles_are_not_affected(): void
    {
        $this->actingAs($this->staff($this->hotel, Role::Accountant))
            ->withHeader('X-Property-Id', $this->hotel->public_id)
            ->getJson('/bff/dashboard')
            ->assertOk();
    }
}
