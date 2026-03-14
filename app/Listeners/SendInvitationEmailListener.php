<?php

namespace App\Listeners;

use App\Events\InvitationCreated;
use App\Models\AuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Schema;

class SendInvitationEmailListener implements ShouldQueue
{
    public function handle(InvitationCreated $event): void
    {
        $invitation = $event->invitation;

        // Log the invitation send attempt
        $payload = [];

        if (Schema::hasColumn('audit_logs', 'account_id')) {
            $payload['account_id'] = $invitation->account_id;
        }

        if (Schema::hasColumn('audit_logs', 'user_id')) {
            $payload['user_id'] = $event->inviter->id;
        }

        if (Schema::hasColumn('audit_logs', 'action')) {
            $payload['action'] = 'invitation.email_sent';
        }

        if (Schema::hasColumn('audit_logs', 'event')) {
            $payload['event'] = 'invitation.email_sent';
        }

        if (Schema::hasColumn('audit_logs', 'entity_type')) {
            $payload['entity_type'] = 'Invitation';
        }

        if (Schema::hasColumn('audit_logs', 'auditable_type')) {
            $payload['auditable_type'] = 'Invitation';
        }

        if (Schema::hasColumn('audit_logs', 'entity_id')) {
            $payload['entity_id'] = $invitation->id;
        }

        if (Schema::hasColumn('audit_logs', 'old_values')) {
            $payload['old_values'] = null;
        }

        if (Schema::hasColumn('audit_logs', 'new_values')) {
            $payload['new_values'] = [
                'email' => $invitation->email,
                'role_id' => $invitation->resolvedRole()?->id,
                'token' => substr($invitation->token, 0, 8) . '...',
            ];
        }

        if (Schema::hasColumn('audit_logs', 'ip_address')) {
            $payload['ip_address'] = request()->ip();
        }

        if (Schema::hasColumn('audit_logs', 'user_agent')) {
            $payload['user_agent'] = request()->userAgent();
        }

        AuditLog::withoutGlobalScopes()->create($payload);

        // TODO: Send actual email via Mail facade
        // Mail::to($invitation->email)->queue(new InvitationMail($invitation));
        // For now, logged for async processing.
    }
}
