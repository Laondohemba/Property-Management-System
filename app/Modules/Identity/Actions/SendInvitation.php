<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\StaffInvitation;
use Illuminate\Support\Facades\Password;

class SendInvitation
{
    /**
     * Issue a fresh invitation token (replacing any previous one) and e-mail it.
     */
    public function handle(User $invitee, User $invitedBy): void
    {
        $token = Password::broker('invites')->createToken($invitee);

        $invitee->notify(new StaffInvitation(
            $token,
            $invitedBy->name,
            (string) $invitee->organization?->name,
        ));
    }
}
