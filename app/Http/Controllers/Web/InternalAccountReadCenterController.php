<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\KycVerification;
use App\Models\Shipment;
use App\Models\User;
use App\Models\VerificationRestriction;
use App\Services\InternalExternalAccountAdminService;
use App\Services\InternalExternalAccountMemberAdminService;
use App\Services\InternalExternalAccountSupportService;
use App\Services\InternalKycOperationalEffectService;
use App\Support\Internal\InternalControlPlane;
use App\Support\Kyc\AccountKycStatusMapper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class InternalAccountReadCenterController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'type' => $this->normalizedFilter((string) $request->query('type', ''), ['individual', 'organization']),
            'status' => $this->normalizedFilter((string) $request->query('status', ''), ['active', 'pending', 'suspended', 'closed']),
            'kyc' => $this->normalizedFilter((string) $request->query('kyc', ''), KycVerification::ALL_STATUSES),
            'restriction' => $this->normalizedFilter((string) $request->query('restriction', ''), ['restricted', 'clear']),
        ];

        $restrictedStatuses = $this->restrictedStatuses();

        $accounts = Account::query()
            ->withoutGlobalScopes()
            ->with($this->eagerLoadRelations())
            ->withCount([
                'shipments as shipments_count' => static function (Builder $query): void {
                    $query->withoutGlobalScopes();
                },
            ])
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $search = '%' . $filters['q'] . '%';

                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', $search)
                        ->orWhere('slug', 'like', $search)
                        ->orWhereHas('users', function (Builder $userQuery) use ($search): void {
                            $userQuery->withoutGlobalScopes()
                                ->where(function (Builder $userInner) use ($search): void {
                                    $userInner->where('name', 'like', $search)
                                        ->orWhere('email', 'like', $search);
                                });

                            if (Schema::hasColumn('users', 'user_type')) {
                                $userQuery->where('user_type', 'external');
                            }
                        })
                        ->orWhereHas('organizationProfile', function (Builder $orgQuery) use ($search): void {
                            $orgQuery->where('legal_name', 'like', $search)
                                ->orWhere('trade_name', 'like', $search)
                                ->orWhere('registration_number', 'like', $search);
                        });
                });
            })
            ->when($filters['type'] !== '', static function (Builder $query) use ($filters): void {
                $query->where('type', $filters['type']);
            })
            ->when($filters['status'] !== '', static function (Builder $query) use ($filters): void {
                $query->where('status', $filters['status']);
            })
            ->when($filters['kyc'] !== '', function (Builder $query) use ($filters): void {
                if (Schema::hasColumn('accounts', 'kyc_status')) {
                    $query->where('kyc_status', AccountKycStatusMapper::fromVerificationStatus($filters['kyc']));

                    return;
                }

                $query->whereHas('kycVerifications', static function (Builder $kycQuery) use ($filters): void {
                    $kycQuery->where('status', $filters['kyc']);
                });
            })
            ->when($filters['restriction'] !== '' && $restrictedStatuses !== [], function (Builder $query) use ($filters, $restrictedStatuses): void {
                if (!Schema::hasColumn('accounts', 'kyc_status')) {
                    return;
                }

                if ($filters['restriction'] === 'restricted') {
                    $query->whereIn('kyc_status', $restrictedStatuses);

                    return;
                }

                $query->where(function (Builder $inner) use ($restrictedStatuses): void {
                    $inner->whereNull('kyc_status')
                        ->orWhereNotIn('kyc_status', $restrictedStatuses);
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $accounts->setCollection(
            $accounts->getCollection()->map(fn (Account $account): array => $this->buildIndexRow($account, $restrictedStatuses))
        );

        return view('pages.admin.accounts-index', [
            'accounts' => $accounts,
            'filters' => $filters,
            'canCreateAccounts' => $this->canManageSurface(
                $request,
                'accounts.create',
                InternalControlPlane::SURFACE_EXTERNAL_ACCOUNTS_CREATE,
            ),
            'statusOptions' => [
                'active' => 'نشط',
                'pending' => 'قيد التفعيل',
                'suspended' => 'موقوف',
                'closed' => 'مغلق',
            ],
            'kycOptions' => [
                KycVerification::STATUS_UNVERIFIED => 'غير موثق',
                KycVerification::STATUS_PENDING => 'قيد المراجعة',
                KycVerification::STATUS_APPROVED => 'موثق',
                KycVerification::STATUS_REJECTED => 'مرفوض',
                KycVerification::STATUS_EXPIRED => 'منتهي',
            ],
        ]);
    }

    public function show(
        Request $request,
        string $account,
        InternalExternalAccountAdminService $accountAdminService,
        InternalExternalAccountMemberAdminService $memberAdminService,
        InternalExternalAccountSupportService $accountSupportService,
        InternalKycOperationalEffectService $operationalEffectService,
    ): View
    {
        $accountModel = Account::query()
            ->withoutGlobalScopes()
            ->with($this->eagerLoadRelations())
            ->withCount([
                'users as external_users_count' => function (Builder $query): void {
                    $query->withoutGlobalScopes();
                    if (Schema::hasColumn('users', 'user_type')) {
                        $query->where('user_type', 'external');
                    }
                },
                'shipments as shipments_count' => static function (Builder $query): void {
                    $query->withoutGlobalScopes();
                },
                'shipments as kyc_blocked_shipments_count' => static function (Builder $query): void {
                    $query->withoutGlobalScopes()->where('status', Shipment::STATUS_KYC_BLOCKED);
                },
            ])
            ->findOrFail($account);

        $owner = $this->resolveExternalOwner($accountModel);
        $kyc = $this->resolveKycSummary($accountModel);
        $restrictions = $this->restrictionsForStatus($kyc['status']);
        $kycOperationalEffect = $operationalEffectService->summarize(
            $accountModel,
            $kyc['status'],
            $kyc['capabilities'],
            (int) ($accountModel->kyc_blocked_shipments_count ?? 0),
        );
        $wallet = $this->resolveWalletSummary($accountModel);
        $canUpdateAccount = $this->canManageSurface(
            $request,
            'accounts.update',
            InternalControlPlane::SURFACE_EXTERNAL_ACCOUNTS_UPDATE,
        );
        $canManageLifecycle = $this->canManageSurface(
            $request,
            'accounts.lifecycle.manage',
            InternalControlPlane::SURFACE_EXTERNAL_ACCOUNTS_LIFECYCLE,
        );
        $canManageSupportActions = $this->canManageSurface(
            $request,
            'accounts.support.manage',
            InternalControlPlane::SURFACE_EXTERNAL_ACCOUNTS_SUPPORT_ACTIONS,
        );
        $canCreateTickets = $this->canManageSurface(
            $request,
            'tickets.manage',
            InternalControlPlane::SURFACE_INTERNAL_TICKETS_CREATE,
        );
        $canManageMembers = $this->canManageSurface(
            $request,
            'accounts.members.manage',
            InternalControlPlane::SURFACE_EXTERNAL_ACCOUNTS_MEMBER_ADMIN,
        );

        $recentShipments = Shipment::query()
            ->withoutGlobalScopes()
            ->where('account_id', (string) $accountModel->id)
            ->latest()
            ->limit(5)
            ->get();

        return view('pages.admin.accounts-show', [
            'account' => $accountModel,
            'owner' => $owner,
            'typeLabel' => $this->accountTypeLabel((string) $accountModel->type),
            'statusLabel' => $this->accountStatusLabel((string) ($accountModel->status ?? 'pending')),
            'kyc' => $kyc,
            'kycOperationalEffect' => $kycOperationalEffect,
            'wallet' => $wallet,
            'organizationProfile' => $accountModel->organizationProfile,
            'restrictions' => $restrictions,
            'recentKycImpactedShipments' => $operationalEffectService->recentImpactedShipments($accountModel, 3),
            'recentShipments' => $recentShipments,
            'externalUsersCount' => (int) ($accountModel->external_users_count ?? 0),
            'shipmentsCount' => (int) ($accountModel->shipments_count ?? 0),
            'kycBlockedShipmentsCount' => (int) ($accountModel->kyc_blocked_shipments_count ?? 0),
            'canUpdateAccount' => $canUpdateAccount,
            'canManageLifecycle' => $canManageLifecycle,
            'canManageSupportActions' => $canManageSupportActions,
            'canCreateTickets' => $canCreateTickets,
            'canManageMembers' => $canManageMembers,
            'availableLifecycleActions' => $canManageLifecycle
                ? $accountAdminService->availableLifecycleActions($accountModel)
                : [],
            'organizationMembers' => $accountModel->allowsTeamManagement()
                ? $memberAdminService->memberSummaries($accountModel)
                : collect(),
            'memberRoleOptions' => $canManageMembers && $accountModel->allowsTeamManagement()
                ? $memberAdminService->assignableRoleOptions($accountModel)
                : collect(),
            'passwordResetTarget' => $canManageSupportActions
                ? $accountSupportService->resolveResetTarget($accountModel)
                : null,
            'pendingInvitations' => $canManageSupportActions && $accountModel->allowsTeamManagement()
                ? $accountSupportService->pendingInvitationSummaries($accountModel)
                : collect(),
            'verificationResendAvailable' => false,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function eagerLoadRelations(): array
    {
        return [
            'organizationProfile',
            'kycVerification',
            'billingWallet',
            'wallet',
            'users' => function ($query): void {
                $query->withoutGlobalScopes()
                    ->orderByDesc('is_owner')
                    ->orderBy('name');

                if (Schema::hasColumn('users', 'user_type')) {
                    $query->where('user_type', 'external');
                }
            },
        ];
    }

    /**
     * @param array<int, string> $restrictedStatuses
     * @return array<string, mixed>
     */
    private function buildIndexRow(Account $account, array $restrictedStatuses): array
    {
        $owner = $this->resolveExternalOwner($account);
        $kyc = $this->resolveKycSummary($account);
        $wallet = $this->resolveWalletSummary($account);
        $organizationProfile = $account->organizationProfile;

        return [
            'account' => $account,
            'owner' => $owner,
            'typeLabel' => $this->accountTypeLabel((string) $account->type),
            'statusLabel' => $this->accountStatusLabel((string) ($account->status ?? 'pending')),
            'kyc' => $kyc,
            'isRestricted' => in_array($kyc['status'], $restrictedStatuses, true),
            'wallet' => $wallet,
            'organizationSummary' => $account->isOrganization()
                ? trim((string) ($organizationProfile?->legal_name ?: $organizationProfile?->trade_name ?: 'ملف المؤسسة غير مكتمل'))
                : null,
            'shipmentsCount' => (int) ($account->shipments_count ?? 0),
        ];
    }

    private function resolveExternalOwner(Account $account): ?User
    {
        $users = $account->users instanceof Collection
            ? $account->users
            : collect();

        /** @var User|null $owner */
        $owner = $users->first(static fn (User $user): bool => (bool) ($user->is_owner ?? false));

        if ($owner instanceof User) {
            return $owner;
        }

        $first = $users->first();

        return $first instanceof User ? $first : null;
    }

    /**
     * @return array{status: string, label: string, description: string, capabilities: array<string, mixed>, submitted_at: string|null, reviewed_at: string|null, expires_at: string|null, rejection_reason: string|null}
     */
    private function resolveKycSummary(Account $account): array
    {
        $verification = $account->kycVerification;
        $status = trim((string) (
            $verification?->status
            ?? AccountKycStatusMapper::toVerificationStatus((string) ($account->kyc_status ?? ''))
        ));
        $display = $verification?->statusDisplay() ?? $this->fallbackKycDisplay($status);
        $capabilities = $verification?->capabilities() ?? [];

        return [
            'status' => $status,
            'label' => (string) ($display['label'] ?? ucfirst($status)),
            'description' => trim((string) ($capabilities['message'] ?? 'لا توجد بيانات تحقق محدثة لهذا الحساب بعد.')),
            'capabilities' => $capabilities,
            'submitted_at' => optional($verification?->submitted_at)->format('Y-m-d H:i'),
            'reviewed_at' => optional($verification?->reviewed_at)->format('Y-m-d H:i'),
            'expires_at' => optional($verification?->expires_at)->format('Y-m-d'),
            'rejection_reason' => $verification?->rejection_reason,
        ];
    }

    /**
     * @return array{available: bool, headline: string, meta: string, status: string|null}
     */
    private function resolveWalletSummary(Account $account): array
    {
        if ($account->billingWallet) {
            return [
                'available' => true,
                'headline' => sprintf(
                    '%s %s',
                    (string) $account->billingWallet->currency,
                    number_format($account->billingWallet->getEffectiveBalance(), 2)
                ),
                'meta' => sprintf(
                    'متاح %s • محجوز %s',
                    number_format((float) $account->billingWallet->available_balance, 2),
                    number_format((float) $account->billingWallet->reserved_balance, 2)
                ),
                'status' => (string) $account->billingWallet->status,
            ];
        }

        if ($account->wallet) {
            return [
                'available' => true,
                'headline' => sprintf(
                    '%s %s',
                    (string) ($account->wallet->currency ?? 'SAR'),
                    number_format((float) $account->wallet->available_balance, 2)
                ),
                'meta' => 'ملخص من المحفظة التقليدية للقراءة فقط.',
                'status' => (string) $account->wallet->status,
            ];
        }

        return [
            'available' => false,
            'headline' => 'غير متاح',
            'meta' => 'لا توجد محفظة ممولة لهذا الحساب بعد.',
            'status' => null,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function restrictedStatuses(): array
    {
        if (!Schema::hasTable('verification_restrictions')) {
            return [];
        }

        return VerificationRestriction::query()
            ->active()
            ->get()
            ->flatMap(static fn (VerificationRestriction $restriction): array => $restriction->applies_to_statuses ?? [])
            ->filter(static fn ($status): bool => is_string($status) && trim($status) !== '')
            ->map(static fn (string $status): string => trim($status))
            ->unique()
            ->values()
            ->all();
    }

    private function restrictionsForStatus(string $status): Collection
    {
        if (!Schema::hasTable('verification_restrictions')) {
            return collect();
        }

        return VerificationRestriction::getForStatus($status);
    }

    private function normalizedFilter(string $value, array $allowed): string
    {
        $value = strtolower(trim($value));

        return in_array($value, $allowed, true) ? $value : '';
    }

    /**
     * @return array<string, string>
     */
    private function fallbackKycDisplay(string $status): array
    {
        return match (AccountKycStatusMapper::toVerificationStatus($status)) {
            KycVerification::STATUS_PENDING => ['label' => 'قيد المراجعة'],
            KycVerification::STATUS_APPROVED => ['label' => 'موثق'],
            KycVerification::STATUS_REJECTED => ['label' => 'مرفوض'],
            KycVerification::STATUS_EXPIRED => ['label' => 'منتهي'],
            default => ['label' => 'غير موثق'],
        };
    }

    private function accountTypeLabel(string $type): string
    {
        return $type === 'organization' ? 'حساب منظمة' : 'حساب فردي';
    }

    private function accountStatusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'نشط',
            'suspended' => 'موقوف',
            'closed' => 'مغلق',
            default => 'قيد التفعيل',
        };
    }
    private function canManageSurface(Request $request, string $permission, string $surface): bool
    {
        /** @var User|null $user */
        $user = $request->user();

        if (!$user || !$user->hasPermission($permission)) {
            return false;
        }

        return app(InternalControlPlane::class)->canSeeSurface($user, $surface);
    }
}
