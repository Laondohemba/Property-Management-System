<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_receive_a_401_problem(): void
    {
        $this->getJson('/bff/session')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson(['status' => 401, 'code' => 'unauthenticated']);
    }

    public function test_it_returns_the_session_view_for_the_signed_in_user(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create()->assignRole(Role::FrontDeskAgent->value);

        $this->actingAs($user)
            ->getJson('/bff/session')
            ->assertOk()
            ->assertHeader('X-App-Version', config('app.version'))
            ->assertJsonPath('data.user.id', $user->public_id)
            ->assertJsonPath('data.user.two_factor_enabled', false)
            ->assertJsonPath('data.organization.id', $user->organization->public_id)
            ->assertJsonPath('data.roles.0', ['name' => 'front-desk-agent', 'label' => 'Front Desk Agent'])
            ->assertJsonPath('data.active_property', null)
            ->assertJsonPath('meta.app_version', config('app.version'))
            ->assertJsonMissingPath('data.user.password');
    }

    public function test_sign_in_then_session_works_from_the_spa_origin(): void
    {
        $user = User::factory()->create();
        $spa = ['Referer' => 'http://localhost:4200/sign-in', 'Origin' => 'http://localhost:4200'];

        $this->withHeaders($spa)->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertOk();

        $this->withHeaders($spa)->getJson('/bff/session')->assertOk()->assertJsonPath('data.user.email', $user->email);
    }

    public function test_users_deactivated_mid_session_are_signed_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/bff/session')->assertOk();

        $user->update(['is_active' => false]);

        $this->getJson('/bff/session')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'account_inactive');
    }

    public function test_no_cors_allowances_are_sent(): void
    {
        $this->withHeaders(['Origin' => 'https://evil.example'])
            ->get('/sanctum/csrf-cookie')
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_unknown_bff_routes_return_a_404_problem(): void
    {
        $this->getJson('/bff/does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');
    }
}
