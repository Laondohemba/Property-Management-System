<?php

declare(strict_types=1);

namespace App\Modules\Audit\Listeners;

use App\Modules\Audit\Audit;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

/**
 * Security events (AUD-04): sign-ins, failed sign-ins, lockouts, password and 2FA changes.
 * Never records passwords or codes.
 */
class RecordSecurityEvents
{
    public function login(Login $event): void
    {
        $this->recordFor($event->user, 'login', 'Signed in', ['remember' => $event->remember]);
    }

    public function logout(Logout $event): void
    {
        $this->recordFor($event->user, 'logout', 'Signed out');
    }

    public function failed(Failed $event): void
    {
        $email = is_string($event->credentials['email'] ?? null) ? mb_strtolower($event->credentials['email']) : null;

        // Attribute the attempt to the account's organisation when the e-mail exists (internal record only).
        $user = $event->user instanceof User ? $event->user : ($email ? User::where('email', $email)->first() : null);

        Audit::security('login_failed', 'Failed sign-in attempt', $user, ['email' => $email]);
    }

    public function lockout(Lockout $event): void
    {
        $email = $event->request->input('email');
        $user = is_string($email) ? User::where('email', mb_strtolower($email))->first() : null;

        Audit::security('lockout', 'Sign-in locked out after too many attempts', $user, ['email' => $email]);
    }

    public function passwordReset(PasswordReset $event): void
    {
        $this->recordFor($event->user, 'password_reset', 'Password reset via e-mail link');
    }

    public function passwordUpdated(PasswordUpdatedViaController $event): void
    {
        $this->recordFor($event->user, 'password_changed', 'Password changed');
    }

    public function twoFactorEnabled(TwoFactorAuthenticationEnabled $event): void
    {
        $this->recordFor($event->user, 'two_factor_enabled', 'Two-factor authentication set-up started');
    }

    public function twoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->recordFor($event->user, 'two_factor_confirmed', 'Two-factor authentication enabled');
    }

    public function twoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->recordFor($event->user, 'two_factor_disabled', 'Two-factor authentication disabled');
    }

    public function twoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        // The user is not signed in yet, so they are the subject but not the actor.
        $this->recordFor($event->user, 'two_factor_failed', 'Invalid two-factor code', asCauser: false);
    }

    public function recoveryCodesGenerated(RecoveryCodesGenerated $event): void
    {
        $this->recordFor($event->user, 'recovery_codes_generated', 'Two-factor recovery codes regenerated');
    }

    public function recoveryCodeUsed(RecoveryCodeReplaced $event): void
    {
        $this->recordFor($event->user, 'recovery_code_used', 'Signed in with a recovery code');
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            Login::class => 'login',
            Logout::class => 'logout',
            Failed::class => 'failed',
            Lockout::class => 'lockout',
            PasswordReset::class => 'passwordReset',
            PasswordUpdatedViaController::class => 'passwordUpdated',
            TwoFactorAuthenticationEnabled::class => 'twoFactorEnabled',
            TwoFactorAuthenticationConfirmed::class => 'twoFactorConfirmed',
            TwoFactorAuthenticationDisabled::class => 'twoFactorDisabled',
            TwoFactorAuthenticationFailed::class => 'twoFactorFailed',
            RecoveryCodesGenerated::class => 'recoveryCodesGenerated',
            RecoveryCodeReplaced::class => 'recoveryCodeUsed',
        ];
    }

    /**
     * Framework and Fortify events type their users loosely; only our User model is recorded.
     *
     * @param  array<string, mixed>  $properties
     */
    private function recordFor(mixed $user, string $event, string $description, array $properties = [], bool $asCauser = true): void
    {
        if ($user instanceof User) {
            Audit::security($event, $description, $user, $properties, $asCauser ? $user : null);
        }
    }
}
