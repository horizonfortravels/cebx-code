<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Account;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class InternalExternalAccountMemberAdminService
{
    /**
     * @var array<int, string>
     */
    private const ASSIGNABLE_ROLE_NAMES = [
        'organization_admin',
        'staff',
    ];

    public function __construct(
        private readonly InvitationService $invitationService,
        private readonly UserService $userService,
    ) {}

    /**
     * @return Collection<int, array{id: string, name: string, email: string, status: string, status_label: string, is_owner: bool, role_labels: array<int, string>, can_deactivate: bool, can_reactivate: bool}>
     */
    public function memberSummaries(Account $account, int $limit = 50): Collection
    {
        if (!$account->allowsTeamManagement()) {
            return collect();
        }

        return $this->membersQuery($account)
            ->with([
                'roles' => function ($query): void {
                    $query->withoutGlobalScopes()->orderBy('name');
                },
            ])
            ->orderByDesc('is_owner')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(function (User $member): array {
                $status = strtolower(trim((string) ($member->status ?? 'inactive')));
                $roleLabels = $member->roles
                    ->map(static fn (Role $role): string => trim((string) ($role->display_name ?: $role->name)))
                    ->filter(static fn (string $label): bool => $label !== '')
                    ->values()
                    ->all();

                if ((bool) ($member->is_owner ?? false)) {
                    array_unshift($roleLabels, 'مالك الحساب');
                }

                if ($roleLabels === []) {
                    $roleLabels = ['بدون دور'];
                }

                return [
                    'id' => (string) $member->id,
                    'name' => (string) $member->name,
                    'email' => (string) $member->email,
                    'status' => $status,
                    'status_label' => $this->memberStatusLabel($status),
                    'is_owner' => (bool) ($member->is_owner ?? false),
                    'role_labels' => $roleLabels,
                    'can_deactivate' => !(bool) ($member->is_owner ?? false) && $status === 'active',
                    'can_reactivate' => !(bool) ($member->is_owner ?? false) && $status !== 'active',
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array{id: string, name: string, label: string, description: string|null}>
     */
    public function assignableRoleOptions(Account $account): Collection
    {
        if (!$account->allowsTeamManagement()) {
            return collect();
        }

        $priority = array_flip(self::ASSIGNABLE_ROLE_NAMES);

        return $this->assignableRolesQuery($account)
            ->get()
            ->sortBy(static fn (Role $role): int => $priority[$role->name] ?? PHP_INT_MAX)
            ->values()
            ->map(static function (Role $role): array {
                $label = trim((string) ($role->display_name ?: $role->name));
                $description = trim((string) ($role->description ?? ''));

                return [
                    'id' => (string) $role->id,
                    'name' => (string) $role->name,
                    'label' => $label !== '' ? $label : (string) $role->name,
                    'description' => $description !== '' ? $description : null,
                ];
            });
    }

    /**
     * @throws BusinessException
     */
    public function inviteMember(Account $account, array $data, User $actor): Invitation
    {
        $this->assertOrganizationAccount($account);
        $role = $this->resolveAssignableRole($account, (string) ($data['role_id'] ?? ''));

        return $this->invitationService->createInvitationForInternalActor([
            'email' => strtolower(trim((string) $data['email'])),
            'name' => $this->nullableTrim($data['name'] ?? null),
            'role_id' => (string) $role->id,
            'ttl_hours' => InvitationService::DEFAULT_TTL_HOURS,
        ], (string) $account->id, $actor);
    }

    /**
     * @throws BusinessException
     */
    public function deactivateMember(Account $account, string $memberId, User $actor): User
    {
        $this->assertOrganizationAccount($account);

        return $this->userService->disableUser($memberId, $this->tenantScopedActor($actor, $account));
    }

    /**
     * @throws BusinessException
     */
    public function reactivateMember(Account $account, string $memberId, User $actor): User
    {
        $this->assertOrganizationAccount($account);

        return $this->userService->enableUser($memberId, $this->tenantScopedActor($actor, $account));
    }

    private function membersQuery(Account $account): Builder
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('account_id', (string) $account->id)
            ->when(Schema::hasColumn('users', 'user_type'), static function (Builder $query): void {
                $query->where('user_type', 'external');
            });
    }

    private function assignableRolesQuery(Account $account): Builder
    {
        return Role::query()
            ->withoutGlobalScopes()
            ->where('account_id', (string) $account->id)
            ->whereIn('name', self::ASSIGNABLE_ROLE_NAMES);
    }

    /**
     * @throws BusinessException
     */
    private function resolveAssignableRole(Account $account, string $roleId): Role
    {
        $roleId = trim($roleId);

        if ($roleId === '') {
            throw BusinessException::make(
                'ERR_ACCOUNT_MEMBER_ROLE_REQUIRED',
                'اختر دورًا آمنًا للعضو قبل إرسال الدعوة.',
                httpStatus: 422,
            );
        }

        $role = $this->assignableRolesQuery($account)
            ->where('id', $roleId)
            ->first();

        if (!$role instanceof Role) {
            throw BusinessException::make(
                'ERR_ACCOUNT_MEMBER_ROLE_NOT_ALLOWED',
                'هذا الدور غير متاح ضمن نطاق إدارة أعضاء المنظمة في هذه المرحلة.',
                httpStatus: 422,
            );
        }

        return $role;
    }

    /**
     * @throws BusinessException
     */
    private function assertOrganizationAccount(Account $account): void
    {
        if (!$account->allowsTeamManagement()) {
            throw BusinessException::accountUpgradeRequired();
        }
    }

    private function tenantScopedActor(User $actor, Account $account): User
    {
        /** @var User $proxy */
        $proxy = clone $actor;
        $proxy->setAttribute('account_id', (string) $account->id);

        if (Schema::hasColumn('users', 'user_type')) {
            $proxy->setAttribute('user_type', 'internal');
        }

        return $proxy;
    }

    private function memberStatusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'نشط',
            'suspended' => 'موقوف',
            'disabled', 'inactive' => 'معطل',
            default => 'غير معروف',
        };
    }

    private function nullableTrim(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
