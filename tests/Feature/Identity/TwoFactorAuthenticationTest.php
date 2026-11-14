<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_with_two_factor_enabled_must_complete_the_challenge(): void
    {
        $user = $this->userWithTwoFactor();

        $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJson(['two_factor' => true]);

        $this->assertGuest();

        $code = (new Google2FA)->getCurrentOtp(decrypt($user->two_factor_secret));

        $this->postJson('/two-factor-challenge', ['code' => $code])->assertNoContent();

        $this->assertAuthenticatedAs($user);
    }

    public function test_an_invalid_challenge_code_is_rejected(): void
    {
        $user = $this->userWithTwoFactor();

        $this->postJson('/login', ['email' => $user->email, 'password' => 'password']);

        $this->postJson('/two-factor-challenge', ['code' => '000000'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed');

        $this->assertGuest();
    }

    public function test_enabling_two_factor_requires_a_recent_password_confirmation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/user/two-factor-authentication')->assertStatus(423);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->postJson('/user/two-factor-authentication')
            ->assertOk();

        $this->assertNotNull($user->refresh()->two_factor_secret);
        $this->assertFalse($user->hasTwoFactorEnabled(), 'Two-factor stays unconfirmed until a code is verified.');
    }

    public function test_two_factor_setup_is_confirmed_with_a_valid_code(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->postJson('/user/two-factor-authentication');

        $code = (new Google2FA)->getCurrentOtp(decrypt($user->refresh()->two_factor_secret));

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->postJson('/user/confirmed-two-factor-authentication', ['code' => $code])
            ->assertOk();

        $this->assertTrue($user->refresh()->hasTwoFactorEnabled());
    }

    private function userWithTwoFactor(): User
    {
        $user = User::factory()->create();

        app(EnableTwoFactorAuthentication::class)($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return $user->refresh();
    }
}
