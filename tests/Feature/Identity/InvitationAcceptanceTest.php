<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\StaffInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Property\Concerns\CreatesPropertyUsers;
use Tests\TestCase;

/**
 * POST /accept-invitation (SYS-01).
 */
class InvitationAcceptanceTest extends TestCase
{
    use CreatesPropertyUsers, RefreshDatabase;

    private const PASSWORD = 'Welc0me-Aboard!';

    private User $invitee;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        Notification::fake();

        $this->actingAs($this->superAdmin())->postJson('/bff/users', [
            'name' => 'Emeka Obi', 'email' => 'emeka@example.com', 'property_id' => $this->property()->public_id, 'role' => 'front-desk-agent',
        ])->assertCreated();
        $this->app['auth']->forgetGuards();

        $this->invitee = User::where('email', 'emeka@example.com')->firstOrFail();
        Notification::assertSentTo($this->invitee, StaffInvitation::class, function (StaffInvitation $notification): bool {
            $this->token = $notification->token;

            return true;
        });
    }

    public function test_the_invitee_can_set_a_password_and_sign_in(): void
    {
        $this->postJson('/accept-invitation', [
            'token' => $this->token,
            'email' => 'emeka@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertOk();

        $this->assertSame('active', $this->invitee->refresh()->status());

        $this->postJson('/login', ['email' => 'emeka@example.com', 'password' => self::PASSWORD])->assertOk();
        $this->assertAuthenticatedAs($this->invitee);
    }

    public function test_invalid_tokens_are_rejected(): void
    {
        $this->postJson('/accept-invitation', [
            'token' => 'not-a-token',
            'email' => 'emeka@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.message', 'This invitation link is invalid or has expired. Ask your administrator to send a new one.');
    }

    public function test_tokens_expire_after_72_hours(): void
    {
        $this->travel(73)->hours();

        $this->postJson('/accept-invitation', [
            'token' => $this->token,
            'email' => 'emeka@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertStatus(422);
    }

    public function test_the_password_policy_applies(): void
    {
        $this->postJson('/accept-invitation', [
            'token' => $this->token,
            'email' => 'emeka@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(422)->assertJsonPath('errors.0.field', 'password');
    }

    public function test_pending_invitees_cannot_sign_in(): void
    {
        $this->postJson('/login', ['email' => 'emeka@example.com', 'password' => 'anything'])->assertStatus(422);
        $this->assertGuest();
    }
}
