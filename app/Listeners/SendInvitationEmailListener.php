<?php

namespace App\Listeners;

use App\Events\InvitationCreated;
use App\Events\InvitationResent;
use App\Mail\InvitationMail;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\User;
use App\Services\SmtpSettingsService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Schema;

class SendInvitationEmailListener implements ShouldQueueAfterCommit
{
    public string $queue = 'notifications-email';

    public function __construct(private SmtpSettingsService $smtpSettings)
    {
    }

    public function handle(InvitationCreated|InvitationResent $event): void
    {
        $invitation = Invitation::withoutGlobalScopes()
            ->with('account')
            ->find($event->invitation->id);

        if (! $invitation instanceof Invitation || ! $invitation->isPending()) {
            return;
        }

        $sendCountSnapshot = $this->resolveSendCountSnapshot($event);
        $tokenSnapshot = $this->resolveTokenSnapshot($event);

        if (! $this->matchesSendSnapshot($invitation, $sendCountSnapshot, $tokenSnapshot)) {
            return;
        }

        if ($this->sendAlreadyLogged($invitation, $sendCountSnapshot, $tokenSnapshot)) {
            return;
        }

        $actor = $this->resolveActor($event);

        $this->smtpSettings->sendMailable(
            $invitation->email,
            new InvitationMail(
                email: $invitation->email,
                roleName: $this->resolveRoleName($invitation),
                inviterName: $actor?->name ?? config('app.name', 'CBEX Shipping Gateway'),
                organizationName: (string) ($invitation->account?->name ?? config('app.name', 'CBEX Shipping Gateway')),
                acceptUrl: url('/api/v1/invitations/preview/' . $invitation->token),
                expiresAt: (string) ($invitation->expires_at?->toIso8601String() ?? now()->addDays(3)->toIso8601String()),
            )
        );

        // Log the invitation send attempt
        $payload = [];

        if (Schema::hasColumn('audit_logs', 'account_id')) {
            $payload['account_id'] = $invitation->account_id;
        }

        if (Schema::hasColumn('audit_logs', 'user_id')) {
            $payload['user_id'] = $actor?->id;
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
                'send_count' => $sendCountSnapshot,
                'token_prefix' => $this->tokenPrefix($tokenSnapshot ?? (string) $invitation->token),
            ];
        }

        if (Schema::hasColumn('audit_logs', 'ip_address')) {
            $payload['ip_address'] = request()->ip();
        }

        if (Schema::hasColumn('audit_logs', 'user_agent')) {
            $payload['user_agent'] = request()->userAgent();
        }

        AuditLog::withoutGlobalScopes()->create($payload);
    }

    private function resolveActor(InvitationCreated|InvitationResent $event): ?User
    {
        return $event instanceof InvitationCreated
            ? $event->inviter
            : $event->resentBy;
    }

    private function resolveSendCountSnapshot(InvitationCreated|InvitationResent $event): ?int
    {
        return $event->sendCountSnapshot;
    }

    private function resolveTokenSnapshot(InvitationCreated|InvitationResent $event): ?string
    {
        $token = trim((string) ($event->tokenSnapshot ?? ''));

        return $token !== '' ? $token : null;
    }

    private function matchesSendSnapshot(Invitation $invitation, ?int $sendCountSnapshot, ?string $tokenSnapshot): bool
    {
        if ($sendCountSnapshot !== null && (int) ($invitation->send_count ?? 0) !== $sendCountSnapshot) {
            return false;
        }

        if ($tokenSnapshot !== null && ! hash_equals($tokenSnapshot, (string) $invitation->token)) {
            return false;
        }

        return true;
    }

    private function sendAlreadyLogged(Invitation $invitation, ?int $sendCountSnapshot, ?string $tokenSnapshot): bool
    {
        if (! Schema::hasColumn('audit_logs', 'new_values')) {
            return false;
        }

        $query = AuditLog::withoutGlobalScopes();

        if (Schema::hasColumn('audit_logs', 'action')) {
            $query->where('action', 'invitation.email_sent');
        } elseif (Schema::hasColumn('audit_logs', 'event')) {
            $query->where('event', 'invitation.email_sent');
        } else {
            return false;
        }

        if (Schema::hasColumn('audit_logs', 'entity_type')) {
            $query->where('entity_type', 'Invitation');
        } elseif (Schema::hasColumn('audit_logs', 'auditable_type')) {
            $query->where('auditable_type', 'Invitation');
        }

        if (Schema::hasColumn('audit_logs', 'entity_id')) {
            $query->where('entity_id', (string) $invitation->id);
        } elseif (Schema::hasColumn('audit_logs', 'auditable_id') && ctype_digit((string) $invitation->id)) {
            $query->where('auditable_id', (string) $invitation->id);
        } else {
            return false;
        }

        $expectedTokenPrefix = $tokenSnapshot !== null ? $this->tokenPrefix($tokenSnapshot) : null;

        return $query->get(['new_values'])->contains(function (AuditLog $auditLog) use ($sendCountSnapshot, $expectedTokenPrefix): bool {
            $values = is_array($auditLog->new_values) ? $auditLog->new_values : [];

            if ($sendCountSnapshot !== null && array_key_exists('send_count', $values)) {
                return (int) $values['send_count'] === $sendCountSnapshot;
            }

            if ($expectedTokenPrefix !== null && is_string($values['token_prefix'] ?? null)) {
                return $values['token_prefix'] === $expectedTokenPrefix;
            }

            return false;
        });
    }

    private function resolveRoleName(Invitation $invitation): string
    {
        $role = $invitation->resolvedRole();
        if ($role !== null) {
            return (string) ($role->display_name ?: $role->name ?: $role->slug);
        }

        $roleName = trim((string) ($invitation->getAttribute('role_name') ?? ''));

        return $roleName !== '' ? $roleName : 'عضو';
    }

    private function tokenPrefix(string $token): string
    {
        return substr($token, 0, 8) . '...';
    }
}
