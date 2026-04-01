<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;

class InternalExternalAccountSupportService
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly InvitationService $invitationService,
    ) {}

    public function resolveResetTarget(Account $account): ?User
    {
        return $this->externalUsersQuery($account)
            ->orderByDesc('is_owner')
            ->orderBy('name')
            ->first();
    }

    /**
     * @return Collection<int, array{id: string, email: string, name: string|null, role_label: string, expires_at: string|null, send_count: int, can_resend: bool}>
     */
    public function pendingInvitationSummaries(Account $account, int $limit = 10): Collection
    {
        if (!$account->allowsTeamManagement()) {
            return collect();
        }

        $this->invitationService->expireStaleInvitations((string) $account->id);

        return Invitation::query()
            ->withoutGlobalScopes()
            ->where('account_id', (string) $account->id)
            ->where('status', Invitation::STATUS_PENDING)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function (Invitation $invitation): array {
                $role = $invitation->resolvedRole();
                $sendCount = (int) ($invitation->send_count ?? 0);

                return [
                    'id' => (string) $invitation->id,
                    'email' => (string) $invitation->email,
                    'name' => $this->nullableTrim($invitation->name),
                    'role_label' => trim((string) ($role?->display_name ?: $role?->name ?: $role?->slug ?: ($invitation->getAttribute('role_name') ?? 'عضو'))),
                    'expires_at' => optional($invitation->expires_at)->format('Y-m-d H:i'),
                    'send_count' => $sendCount,
                    'can_resend' => $invitation->canResend() && $sendCount < InvitationService::MAX_RESEND_COUNT,
                ];
            })
            ->values();
    }

    /**
     * @throws BusinessException
     */
    public function sendPasswordReset(Account $account, User $actor): User
    {
        $target = $this->resolveResetTarget($account);

        if (!$target instanceof User) {
            throw BusinessException::make(
                'ERR_EXTERNAL_ACCOUNT_RESET_TARGET_NOT_FOUND',
                'لا يوجد مستخدم خارجي مناسب لإرسال رابط إعادة التعيين لهذا الحساب.',
                httpStatus: 422,
            );
        }

        $status = Password::sendResetLink(['email' => $target->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw BusinessException::make(
                'ERR_PASSWORD_RESET_LINK_SEND_FAILED',
                'تعذر إرسال رابط إعادة تعيين كلمة المرور لهذا الحساب في الوقت الحالي.',
                ['broker_status' => $status],
                422,
            );
        }

        $this->auditService->info(
            (string) $account->id,
            (string) $actor->id,
            'account.password_reset_link_sent',
            AuditLog::CATEGORY_AUTH,
            'User',
            (string) $target->id,
            null,
            [
                'email' => $target->email,
                'is_owner' => (bool) ($target->is_owner ?? false),
            ],
            ['source' => 'internal_accounts_center'],
        );

        return $target;
    }

    /**
     * @throws BusinessException
     */
    public function resendInvitation(Account $account, string $invitationId, User $actor): Invitation
    {
        if (!$account->allowsTeamManagement()) {
            throw BusinessException::accountUpgradeRequired();
        }

        return $this->invitationService->resendInvitationForInternalActor(
            $invitationId,
            (string) $account->id,
            $actor,
        );
    }

    private function externalUsersQuery(Account $account): Builder
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('account_id', (string) $account->id)
            ->when(Schema::hasColumn('users', 'user_type'), static function (Builder $query): void {
                $query->where('user_type', 'external');
            });
    }

    private function nullableTrim(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
