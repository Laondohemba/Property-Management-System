<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\User;
use App\Modules\Property\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_sign_in_with_valid_credentials(): void
    {
        $user = User::factory()->create();

        $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJson(['two_factor' => false]);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->refresh()->last_login_at);
    }

    public function test_invalid_credentials_return_a_problem_document(): void
    {
        $user = User::factory()->create();

        $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson([
                'status' => 422,
                'code' => 'validation_failed',
                'errors' => [['field' => 'email', 'message' => trans('auth.failed')]],
            ]);

        $this->assertGuest();
    }

    public function test_inactive_users_cannot_sign_in(): void
    {
        $user = User::factory()->inactive()->create();

        $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.message', trans('auth.inactive'));

        $this->assertGuest();
    }

    public function test_users_of_an_inactive_organization_cannot_sign_in(): void
    {
        $user = User::factory()->for(Organization::factory()->state(['is_active' => false]))->create();

        $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(422);

        $this->assertGuest();
    }

    public function test_sign_in_is_rate_limited(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertStatus(429)
            ->assertJsonPath('code', 'rate_limited')
            ->assertHeader('Retry-After');
    }

    public function test_users_can_sign_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/logout')->assertNoContent();

        $this->assertGuest('web');
    }

    public function test_there_is_no_public_registration(): void
    {
        $this->assertFalse(Route::has('register'));
        $this->assertFalse(Route::has('register.store'));

        // Only the SPA's GET fallback matches the path, so a POST is not allowed.
        $this->postJson('/register', [])->assertMethodNotAllowed();
    }
}
