<?php

namespace App\Listeners;

use App\Events\UserInvited;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendUserInvitationListener implements ShouldQueue
{
    public function handle(UserInvited $event): void
    {
        // TODO: Implement actual email/SMS sending
        // This will be connected to notification service later.
        Log::info('User invitation sent', [
            'user_id'    => $event->user->id,
            'email'      => $event->user->email,
            'invited_by' => $event->invitedBy->id,
            'account_id' => $event->user->account_id,
        ]);

        // Future implementation:
        // Mail::to($event->user->email)->queue(new UserInvitationMail($event->user, $event->invitedBy));
    }
}
