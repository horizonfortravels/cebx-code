<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Mail\PasswordResetMail;
use App\Models\AuditLog;
use App\Models\InternalRole;
use App\Models\User;
use App\Support\Internal\InternalControlPlane;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class InternalStaffAdminService
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly InternalControlPlane $controlPlane,
        private readonly SmtpSettingsService $smtpSettings,
    ) {}

    public function createStaffUser(array $data, User $actor): User
    {
        return DB::transaction(function () use ($data, $actor): User {
            $role = $this->resolveCanonicalRole($data['role']);
            $user = User::query()->withoutGlobalScopes()->create(
                $this->buildUserPayload($data, Hash::make($data['password']), true)
            );

            $this->syncCanonicalRole($user, $role, $actor);
            $this->auditStaffCreated($user, $actor, $role, 'create');

            return $user->fresh(['internalRoles']);
        });
    }

    /**
     * @throws BusinessException
     */
    public function inviteStaffUser(array $data, User $actor): User
    {
        try {
            return DB::transaction(function () use ($data, $actor): User {
                $role = $this->resolveCanonicalRole($data['role']);
                $user = User::query()->withoutGlobalScopes()->create(
                    $this->buildUserPayload($data, Hash::make(Str::random(32)), false)
                );

                $this->syncCanonicalRole($user, $role, $actor);
                $this->dispatchPasswordResetLink(
                    $user,
                    'ERR_INTERNAL_STAFF_INVITE_SEND_FAILED',
                    'تعذر إرسال رابط إعداد كلمة المرور لهذا الموظف الآن. تحقق من إعدادات البريد ثم أعد المحاولة.'
                );
                $this->auditStaffCreated($user, $actor, $role, 'invite');
                $this->auditService->info(
                    null,
                    (string) $actor->id,
                    'auth.password_reset_link_sent',
                    AuditLog::CATEGORY_AUTH,
                    'User',
                    (string) $user->id,
                    null,
                    [
                        'email' => $user->email,
                        'role' => $role->name,
                    ],
                    ['source' => 'internal_staff_center', 'delivery' => 'invite_bootstrap'],
                );

                return $user->fresh(['internalRoles']);
            });
        } catch (BusinessException $exception) {
            if ($exception->getErrorCode() === 'ERR_INTERNAL_STAFF_INVITE_SEND_FAILED') {
                $this->auditService->warning(
                    null,
                    (string) $actor->id,
                    'auth.password_reset_link_failed',
                    AuditLog::CATEGORY_AUTH,
                    'User',
                    null,
                    null,
                    [
                        'email' => $data['email'],
                        'role' => $data['role'],
                    ],
                    ['source' => 'internal_staff_center', 'delivery' => 'invite_bootstrap'],
                );
            }

            throw $exception;
        }
    }

    public function updateStaffUser(User $staffUser, array $data, User $actor): User
    {
        return DB::transaction(function () use ($staffUser, $data, $actor): User {
            $oldValues = [];
            $newValues = [];

            foreach ($this->editableStaffFields() as $field) {
                if (! array_key_exists($field, $data)) {
                    continue;
                }

                $value = $data[$field];
                if ($staffUser->{$field} === $value) {
                    continue;
                }

                $oldValues[$field] = $staffUser->{$field};
                $newValues[$field] = $value;
                $staffUser->{$field} = $value;
            }

            if ($oldValues !== []) {
                $staffUser->save();

                $this->auditService->info(
                    null,
                    (string) $actor->id,
                    'user.updated',
                    AuditLog::CATEGORY_USERS,
                    'User',
                    (string) $staffUser->id,
                    $oldValues,
                    $newValues,
                    ['source' => 'internal_staff_center'],
                );
            }

            $targetRole = $this->resolveCanonicalRole($data['role']);
            $currentRole = $this->controlPlane->primaryCanonicalRole($staffUser);
            $currentAssignments = $this->assignedRoleNames($staffUser);
            $shouldSyncRole = $currentRole !== $targetRole->name
                || $this->controlPlane->hasDeprecatedAssignmentsFromNames($currentAssignments)
                || count($currentAssignments) !== 1;

            if ($shouldSyncRole) {
                $this->ensureRoleChangeAllowed($staffUser, $currentRole, $targetRole->name);
                $this->syncCanonicalRole($staffUser, $targetRole, $actor, $currentRole);
            }

            return $staffUser->fresh(['internalRoles']);
        });
    }

    /**
     * @throws BusinessException
     */
    public function transitionLifecycle(User $staffUser, string $action, User $actor, ?string $note = null): User
    {
        $normalizedAction = strtolower(trim($action));
        $fromStatus = $this->normalizedStatus((string) ($staffUser->status ?? 'active'));
        $toStatus = $this->targetLifecycleStatus($fromStatus, $normalizedAction);

        if ($toStatus === null) {
            throw BusinessException::make(
                'ERR_INTERNAL_STAFF_INVALID_STATUS_TRANSITION',
                'هذه العملية غير متاحة لحالة الموظف الداخلي الحالية.',
                ['action' => $normalizedAction, 'status' => $fromStatus],
                422,
            );
        }

        $this->ensureLifecycleActionAllowed($staffUser, $normalizedAction, $actor);

        return DB::transaction(function () use ($staffUser, $actor, $normalizedAction, $fromStatus, $toStatus, $note): User {
            $staffUser->forceFill(['status' => $toStatus])->save();

            if ($toStatus !== 'active') {
                $this->invalidateStaffAccess($staffUser);
            }

            $auditMethod = in_array($normalizedAction, ['deactivate', 'suspend'], true)
                ? 'warning'
                : 'info';

            $this->auditService->{$auditMethod}(
                null,
                (string) $actor->id,
                $this->lifecycleAuditAction($normalizedAction),
                AuditLog::CATEGORY_USERS,
                'User',
                (string) $staffUser->id,
                ['status' => $fromStatus],
                ['status' => $toStatus],
                array_filter([
                    'source' => 'internal_staff_center',
                    'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
                ]),
            );

            return $staffUser->refresh();
        });
    }

    /**
     * @return array<int, array{action: string, label: string, tone: string}>
     */
    public function availableLifecycleActions(User $staffUser, ?User $actor = null): array
    {
        $status = $this->normalizedStatus((string) ($staffUser->status ?? 'active'));

        return array_values(array_filter(
            $this->lifecycleActionsForStatus($status),
            fn (array $action): bool => ! ($actor instanceof User)
                || $this->isLifecycleActionAllowed($staffUser, $action['action'], $actor)
        ));
    }

    public function lifecycleProtectionMessage(User $staffUser, ?User $actor = null): ?string
    {
        if (! $actor instanceof User) {
            return null;
        }

        if ((string) $actor->id === (string) $staffUser->id) {
            return 'لا يمكنك تعطيل أو تعليق حسابك الداخلي الحالي من هذه الواجهة.';
        }

        if ($this->isLastLoginCapableSuperAdmin($staffUser)) {
            return 'تتم حماية آخر مدير منصة قادر على تسجيل الدخول من التعطيل أو التعليق.';
        }

        return null;
    }

    /**
     * @throws BusinessException
     */
    public function sendStaffPasswordReset(User $staffUser, User $actor): User
    {
        try {
            $this->dispatchPasswordResetLink(
                $staffUser,
                'ERR_INTERNAL_STAFF_RESET_SEND_FAILED',
                'تعذر إرسال رابط إعادة تعيين كلمة المرور الآن. تحقق من جاهزية البريد الداخلي ثم أعد المحاولة.'
            );
        } catch (BusinessException $exception) {
            $this->auditService->warning(
                null,
                (string) $actor->id,
                'auth.password_reset_link_failed',
                AuditLog::CATEGORY_AUTH,
                'User',
                (string) $staffUser->id,
                null,
                [
                    'email' => $staffUser->email,
                ],
                [
                    'source' => 'internal_staff_center',
                    'delivery' => 'manual_reset',
                    'transport_provider' => $this->safeTransportProviderName(),
                    'error_code' => $exception->getErrorCode(),
                ],
            );

            throw $exception;
        }

        $this->auditService->info(
            null,
            (string) $actor->id,
            'auth.password_reset_link_sent',
            AuditLog::CATEGORY_AUTH,
            'User',
            (string) $staffUser->id,
            null,
            [
                'email' => $staffUser->email,
            ],
            ['source' => 'internal_staff_center', 'delivery' => 'manual_reset'],
        );

        return $staffUser;
    }

    /**
     * @return array<int, string>
     */
    public function editableStaffFields(): array
    {
        return [
            'name',
            'email',
            'locale',
            'timezone',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUserPayload(array $data, string $passwordHash, bool $markEmailVerified): array
    {
        $payload = [
            'account_id' => null,
            'name' => $data['name'],
            'email' => strtolower(trim((string) $data['email'])),
            'password' => $passwordHash,
            'status' => 'active',
            'is_owner' => false,
            'locale' => $data['locale'] ?? 'en',
            'timezone' => $data['timezone'] ?? 'UTC',
        ];

        if (Schema::hasColumn('users', 'user_type')) {
            $payload['user_type'] = 'internal';
        }

        if (Schema::hasColumn('users', 'email_verified_at')) {
            $payload['email_verified_at'] = $markEmailVerified ? now() : null;
        }

        if (Schema::hasColumn('users', 'remember_token')) {
            $payload['remember_token'] = Str::random(60);
        }

        return $payload;
    }

    /**
     * @throws BusinessException
     */
    private function resolveCanonicalRole(string $roleName): InternalRole
    {
        $normalized = strtolower(trim($roleName));

        if (! in_array($normalized, $this->controlPlane->canonicalRoles(), true)) {
            throw BusinessException::make(
                'ERR_INTERNAL_ROLE_NOT_ALLOWED',
                'يمكن تعيين الأدوار الداخلية المعتمدة فقط عند إدارة فريق المنصة.',
                ['role' => $normalized],
                422,
            );
        }

        $role = InternalRole::query()
            ->where('name', $normalized)
            ->first();

        if (! $role instanceof InternalRole) {
            throw BusinessException::make(
                'ERR_INTERNAL_ROLE_NOT_FOUND',
                'تعذر العثور على الدور الداخلي المعتمد المطلوب.',
                ['role' => $normalized],
                422,
            );
        }

        return $role;
    }

    private function syncCanonicalRole(
        User $staffUser,
        InternalRole $role,
        User $actor,
        ?string $previousRole = null,
    ): void {
        DB::table('internal_user_role')
            ->where('user_id', (string) $staffUser->id)
            ->delete();

        $payload = [
            'user_id' => (string) $staffUser->id,
            'internal_role_id' => (string) $role->id,
        ];

        if (Schema::hasColumn('internal_user_role', 'assigned_by')) {
            $payload['assigned_by'] = (string) $actor->id;
        }

        if (Schema::hasColumn('internal_user_role', 'assigned_at')) {
            $payload['assigned_at'] = now();
        }

        DB::table('internal_user_role')->insert($payload);
        $staffUser->unsetRelation('internalRoles');

        $this->auditService->info(
            null,
            (string) $actor->id,
            'role.assigned',
            AuditLog::CATEGORY_ROLES,
            'User',
            (string) $staffUser->id,
            $previousRole === null ? null : ['role' => $previousRole],
            ['role' => $role->name],
            ['source' => 'internal_staff_center'],
        );
    }

    private function auditStaffCreated(User $user, User $actor, InternalRole $role, string $mode): void
    {
        $this->auditService->info(
            null,
            (string) $actor->id,
            'user.added',
            AuditLog::CATEGORY_USERS,
            'User',
            (string) $user->id,
            null,
            [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role->name,
                'status' => $user->status,
            ],
            ['source' => 'internal_staff_center', 'provisioning_mode' => $mode],
        );
    }

    /**
     * @throws BusinessException
     */
    private function dispatchPasswordResetLink(User $user, string $errorCode, string $errorMessage): void
    {
        try {
            $status = Password::broker($this->passwordBrokerName())->sendResetLink(
                ['email' => $user->email],
                function (User $resetUser, string $token): void {
                    $this->smtpSettings->sendMailable(
                        (string) $resetUser->email,
                        new PasswordResetMail(
                            email: (string) $resetUser->email,
                            resetUrl: route('password.reset', [
                                'token' => $token,
                                'email' => $resetUser->email,
                            ]),
                            expiresAt: now()
                                ->addMinutes($this->passwordResetExpiryMinutes())
                                ->format('Y-m-d H:i'),
                        )
                    );

                    event(new PasswordResetLinkSent($resetUser));
                }
            );
        } catch (Throwable $exception) {
            report($exception);

            throw BusinessException::make(
                $errorCode,
                $errorMessage,
                ['exception_class' => $exception::class],
                422,
            );
        }

        if ($status !== Password::RESET_LINK_SENT) {
            throw BusinessException::make(
                $errorCode,
                $errorMessage,
                ['broker_status' => $status],
                422,
            );
        }
    }

    private function passwordBrokerName(): string
    {
        return (string) config('auth.defaults.passwords', 'users');
    }

    private function passwordResetExpiryMinutes(): int
    {
        return (int) config('auth.passwords.' . $this->passwordBrokerName() . '.expire', 60);
    }

    /**
     * @return array<int, string>
     */
    private function assignedRoleNames(User $user): array
    {
        return $user->internalRoles()
            ->pluck('internal_roles.name')
            ->map(static fn ($name): string => strtolower(trim((string) $name)))
            ->filter(static fn (string $name): bool => $name !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{action: string, label: string, tone: string}>
     */
    private function lifecycleActionsForStatus(string $status): array
    {
        return match ($status) {
            'active' => [
                ['action' => 'suspend', 'label' => 'تعليق الموظف', 'tone' => 'warning'],
                ['action' => 'deactivate', 'label' => 'إيقاف الموظف', 'tone' => 'danger'],
            ],
            'suspended' => [
                ['action' => 'unsuspend', 'label' => 'رفع التعليق', 'tone' => 'success'],
                ['action' => 'deactivate', 'label' => 'إيقاف الموظف', 'tone' => 'danger'],
            ],
            'disabled' => [
                ['action' => 'activate', 'label' => 'إعادة التفعيل', 'tone' => 'success'],
            ],
            default => [],
        };
    }

    private function targetLifecycleStatus(string $fromStatus, string $action): ?string
    {
        return match ($action) {
            'activate' => $fromStatus === 'disabled' ? 'active' : null,
            'deactivate' => in_array($fromStatus, ['active', 'suspended'], true) ? 'disabled' : null,
            'suspend' => $fromStatus === 'active' ? 'suspended' : null,
            'unsuspend' => $fromStatus === 'suspended' ? 'active' : null,
            default => null,
        };
    }

    private function lifecycleAuditAction(string $action): string
    {
        return match ($action) {
            'activate' => 'user.activated',
            'deactivate' => 'user.deactivated',
            'suspend' => 'user.suspended',
            'unsuspend' => 'user.unsuspended',
            default => 'user.updated',
        };
    }

    /**
     * @throws BusinessException
     */
    private function ensureLifecycleActionAllowed(User $staffUser, string $action, User $actor): void
    {
        if ((string) $actor->id === (string) $staffUser->id && in_array($action, ['deactivate', 'suspend'], true)) {
            throw BusinessException::make(
                'ERR_INTERNAL_STAFF_SELF_LIFECYCLE_FORBIDDEN',
                'لا يمكنك تعطيل أو تعليق حسابك الداخلي الحالي من هذه الواجهة.',
                ['action' => $action],
                422,
            );
        }

        if ($this->removesLoginCapability($action) && $this->isLastLoginCapableSuperAdmin($staffUser)) {
            throw BusinessException::make(
                'ERR_LAST_SUPER_ADMIN_PROTECTED',
                'تتم حماية آخر مدير منصة قادر على تسجيل الدخول من التعطيل أو التعليق.',
                ['action' => $action],
                422,
            );
        }
    }

    private function isLifecycleActionAllowed(User $staffUser, string $action, User $actor): bool
    {
        if ((string) $actor->id === (string) $staffUser->id && in_array($action, ['deactivate', 'suspend'], true)) {
            return false;
        }

        if ($this->removesLoginCapability($action) && $this->isLastLoginCapableSuperAdmin($staffUser)) {
            return false;
        }

        return true;
    }

    private function removesLoginCapability(string $action): bool
    {
        return in_array($action, ['deactivate', 'suspend'], true);
    }

    private function isLastLoginCapableSuperAdmin(User $staffUser): bool
    {
        if ($this->controlPlane->primaryCanonicalRole($staffUser) !== InternalControlPlane::ROLE_SUPER_ADMIN) {
            return false;
        }

        if (! $this->isLoginCapableStatus($this->normalizedStatus((string) ($staffUser->status ?? 'active')))) {
            return false;
        }

        return $this->loginCapableSuperAdminCountExcluding($staffUser) === 0;
    }

    private function loginCapableSuperAdminCountExcluding(User $staffUser): int
    {
        return $this->internalUsersQuery()
            ->where('id', '!=', (string) $staffUser->id)
            ->where(function (Builder $query): void {
                $query->whereNull('status')
                    ->orWhereNotIn('status', ['suspended', 'disabled']);
            })
            ->whereHas('internalRoles', function (Builder $query): void {
                $query->where('name', InternalControlPlane::ROLE_SUPER_ADMIN);
            })
            ->count();
    }

    /**
     * @throws BusinessException
     */
    private function ensureRoleChangeAllowed(User $staffUser, ?string $currentRole, string $targetRole): void
    {
        if (
            $currentRole === InternalControlPlane::ROLE_SUPER_ADMIN
            && $targetRole !== InternalControlPlane::ROLE_SUPER_ADMIN
            && $this->isLastLoginCapableSuperAdmin($staffUser)
        ) {
            throw BusinessException::make(
                'ERR_LAST_SUPER_ADMIN_ROLE_CHANGE_FORBIDDEN',
                'لا يمكن إزالة دور مدير المنصة من آخر حساب داخلي قادر على تسجيل الدخول.',
                ['role' => $targetRole],
                422,
            );
        }
    }

    private function invalidateStaffAccess(User $staffUser): void
    {
        if (Schema::hasTable('personal_access_tokens')) {
            $staffUser->tokens()->delete();
        }

        if (Schema::hasColumn('users', 'remember_token')) {
            $staffUser->forceFill([
                'remember_token' => Str::random(60),
            ])->save();
        }

        if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
            DB::table('sessions')
                ->where('user_id', (string) $staffUser->id)
                ->delete();
        }
    }

    private function normalizedStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        return $normalized !== '' ? $normalized : 'active';
    }

    private function isLoginCapableStatus(string $status): bool
    {
        return ! in_array($status, ['suspended', 'disabled'], true);
    }

    private function safeTransportProviderName(): string
    {
        try {
            return $this->smtpSettings->providerName();
        } catch (Throwable) {
            return 'smtp';
        }
    }

    private function internalUsersQuery(): Builder
    {
        $query = User::query()->withoutGlobalScopes();

        if (! Schema::hasColumn('users', 'user_type')) {
            return $query->whereNull('account_id');
        }

        return $query->where(function (Builder $inner): void {
            $inner->where('user_type', 'internal')
                ->orWhere(function (Builder $legacy): void {
                    $legacy->where(function (Builder $legacyType): void {
                        $legacyType->whereNull('user_type')
                            ->orWhere('user_type', '');
                    })->whereNull('account_id');
                });
        });
    }
}
