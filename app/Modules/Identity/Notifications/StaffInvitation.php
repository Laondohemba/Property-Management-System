<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications;

use App\Modules\Identity\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitation e-mail with a link to the SPA's set-password screen (SYS-01).
 */
class StaffInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly string $invitedBy,
        public readonly string $organization,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function url(User $notifiable): string
    {
        return url('/invitation/'.$this->token).'?'.http_build_query(['email' => $notifiable->email]);
    }

    public function toMail(User $notifiable): MailMessage
    {
        $hours = (int) round(config('auth.passwords.invites.expire') / 60);

        return (new MailMessage)
            ->subject("You've been invited to {$this->organization} on ".config('app.name'))
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->invitedBy} has invited you to join {$this->organization} on ".config('app.name').'.')
            ->action('Accept invitation', $this->url($notifiable))
            ->line("This invitation expires in {$hours} hours. If you were not expecting it, you can ignore this e-mail.");
    }
}
