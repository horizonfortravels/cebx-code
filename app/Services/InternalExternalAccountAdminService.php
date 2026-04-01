<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\KycVerification;
use App\Models\OrganizationProfile;
use App\Models\User;
use App\Support\Kyc\AccountKycStatusMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InternalExternalAccountAdminService
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly WalletBillingService $walletBillingService,
    ) {}

    public function createAccount(array $data, User $actor): Account
    {
        return DB::transaction(function () use ($data, $actor): Account {
            $account = Account::query()->withoutGlobalScopes()->create($this->buildAccountPayload($data));

            $owner = $this->createOwnerUser($account, $data);
            $this->createKycShell($account);

            if ($account->isOrganization()) {
                OrganizationProfile::query()->withoutGlobalScopes()->create($this->buildOrganizationPayload($account, $data));
            }

            $this->auditService->info(
                (string) $account->id,
                (string) $actor->id,
                'account.created',
                AuditLog::CATEGORY_ACCOUNT,
                'Account',
                (string) $account->id,
                null,
                [
                    'name' => $account->name,
                    'type' => $account->type,
                    'status' => $account->status,
                    'slug' => $account->slug,
                    'owner_email' => $owner->email,
                ],
                ['source' => 'internal_accounts_center']
            );

            return $account->fresh([
                'organizationProfile',
                'kycVerification',
                'users' => function ($query): void {
                    $query->withoutGlobalScopes()->orderByDesc('is_owner');
                },
            ]);
        });
    }

    public function updateAccount(Account $account, array $data, User $actor): Account
    {
        return DB::transaction(function () use ($account, $data, $actor): Account {
            $oldValues = [];
            $newValues = [];

            $accountChanges = [];
            foreach ($this->editableAccountFields() as $field) {
                if (!array_key_exists($field, $data)) {
                    continue;
                }

                $value = $data[$field];
                if ($account->{$field} === $value) {
                    continue;
                }

                $oldValues[$field] = $account->{$field};
                $newValues[$field] = $value;
                $accountChanges[$field] = $value;
            }

            if ($accountChanges !== []) {
                $account->fill($accountChanges);
                $account->slug = $this->uniqueSlug((string) $account->name, $account);
                $account->save();
            }

            if ($account->isOrganization()) {
                $profile = OrganizationProfile::query()
                    ->withoutGlobalScopes()
                    ->firstOrNew(['account_id' => (string) $account->id]);
                $profileChanges = $this->buildOrganizationUpdatePayload($data);

                foreach ($profileChanges as $field => $value) {
                    if ($profile->{$field} === $value) {
                        continue;
                    }

                    $oldValues['organization.' . $field] = $profile->{$field};
                    $newValues['organization.' . $field] = $value;
                }

                if ($profileChanges !== []) {
                    $profile->fill($profileChanges);
                    $profile->save();
                }
            }

            if ($oldValues !== [] || $newValues !== []) {
                $this->auditService->info(
                    (string) $account->id,
                    (string) $actor->id,
                    'account.updated',
                    AuditLog::CATEGORY_ACCOUNT,
                    'Account',
                    (string) $account->id,
                    $oldValues === [] ? null : $oldValues,
                    $newValues === [] ? null : $newValues,
                    ['source' => 'internal_accounts_center']
                );
            }

            return $account->fresh([
                'organizationProfile',
                'kycVerification',
                'users' => function ($query): void {
                    $query->withoutGlobalScopes()->orderByDesc('is_owner');
                },
            ]);
        });
    }

    public function transitionLifecycle(Account $account, string $action, User $actor, ?string $note = null): Account
    {
        $action = strtolower(trim($action));
        $fromStatus = (string) ($account->status ?? 'pending');
        $toStatus = $this->targetStatusFor($fromStatus, $action);

        if ($toStatus === null) {
            throw new BusinessException(
                'هذه العملية غير متاحة للحالة الحالية لهذا الحساب.',
                'ERR_INVALID_ACCOUNT_STATUS_TRANSITION',
                422
            );
        }

        return DB::transaction(function () use ($account, $actor, $action, $fromStatus, $toStatus, $note): Account {
            $account->forceFill(['status' => $toStatus])->save();

            if ($toStatus !== 'active') {
                $this->revokeExternalTokens($account);
            }

            if (in_array($toStatus, ['suspended', 'closed'], true)) {
                $this->walletBillingService->maskPaymentDataForDisabledAccount((string) $account->id);
            }

            if ($fromStatus !== 'active' && $toStatus === 'active') {
                $this->walletBillingService->restorePaymentDataForReactivatedAccount((string) $account->id);
            }

            $auditAction = match ($action) {
                'activate' => 'account.activated',
                'deactivate' => 'account.deactivated',
                'suspend' => 'account.suspended',
                'unsuspend' => 'account.unsuspended',
                default => 'account.status_changed',
            };
            $auditMethod = in_array($action, ['deactivate', 'suspend'], true) ? 'warning' : 'info';
            $this->auditService->{$auditMethod}(
                (string) $account->id,
                (string) $actor->id,
                $auditAction,
                AuditLog::CATEGORY_ACCOUNT,
                'Account',
                (string) $account->id,
                ['status' => $fromStatus],
                ['status' => $toStatus],
                array_filter([
                    'source' => 'internal_accounts_center',
                    'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
                ])
            );

            return $account->refresh();
        });
    }

    /**
     * @return array<int, array{action: string, label: string, tone: string}>
     */
    public function availableLifecycleActions(Account $account): array
    {
        $status = (string) ($account->status ?? 'pending');

        return array_values(array_filter([
            $this->buildLifecycleAction('activate', 'تفعيل الحساب', 'success', $status),
            $this->buildLifecycleAction('deactivate', 'إلغاء التفعيل', 'danger', $status),
            $this->buildLifecycleAction('suspend', 'تعليق الحساب', 'warning', $status),
            $this->buildLifecycleAction('unsuspend', 'رفع التعليق', 'success', $status),
        ]));
    }

    /**
     * @return array<int, string>
     */
    public function editableAccountFields(): array
    {
        return [
            'name',
            'language',
            'currency',
            'timezone',
            'country',
            'contact_phone',
            'contact_email',
            'address_line_1',
            'address_line_2',
            'city',
            'postal_code',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function editableOrganizationFields(): array
    {
        return [
            'legal_name',
            'trade_name',
            'registration_number',
            'tax_id',
            'industry',
            'company_size',
            'country',
            'city',
            'address_line_1',
            'address_line_2',
            'postal_code',
            'phone',
            'email',
            'website',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAccountPayload(array $data): array
    {
        $payload = [
            'name' => $data['account_name'],
            'type' => $data['account_type'],
            'status' => 'pending',
            'slug' => $this->uniqueSlug($data['account_name']),
            'language' => $data['language'] ?? 'ar',
            'currency' => $data['currency'] ?? 'SAR',
            'timezone' => $data['timezone'] ?? 'Asia/Riyadh',
            'country' => $data['country'] ?? 'SA',
            'contact_phone' => $data['contact_phone'] ?? ($data['owner_phone'] ?? null),
            'contact_email' => $data['contact_email'] ?? $data['owner_email'],
            'address_line_1' => $data['address_line_1'] ?? null,
            'address_line_2' => $data['address_line_2'] ?? null,
            'city' => $data['city'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'kyc_status' => AccountKycStatusMapper::STATUS_NOT_SUBMITTED,
        ];

        // Older environments may still be missing the canonical JSON settings column
        // until the forward-only schema repair has been applied.
        if (Schema::hasColumn('accounts', 'settings')) {
            $payload['settings'] = [];
        }

        return $payload;
    }

    private function createOwnerUser(Account $account, array $data): User
    {
        $payload = [
            'account_id' => (string) $account->id,
            'name' => $data['owner_name'],
            'email' => $data['owner_email'],
            'password' => Hash::make(Str::random(32)),
            'phone' => $data['owner_phone'] ?? null,
            'status' => 'active',
            'is_owner' => true,
            'locale' => $data['language'] ?? 'ar',
            'timezone' => $data['timezone'] ?? 'Asia/Riyadh',
        ];

        if (Schema::hasColumn('users', 'user_type')) {
            $payload['user_type'] = 'external';
        }

        if (Schema::hasColumn('users', 'email_verified_at')) {
            $payload['email_verified_at'] = null;
        }

        return User::query()->withoutGlobalScopes()->create($payload);
    }

    private function createKycShell(Account $account): void
    {
        KycVerification::query()->withoutGlobalScopes()->create([
            'account_id' => (string) $account->id,
            'status' => KycVerification::STATUS_UNVERIFIED,
            'verification_type' => $account->isOrganization() ? 'organization' : 'individual',
            'required_documents' => KycVerification::requiredDocumentsFor((string) $account->type),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOrganizationPayload(Account $account, array $data): array
    {
        $payload = [
            'account_id' => (string) $account->id,
            'legal_name' => $data['legal_name'] ?? $account->name,
            'trade_name' => $data['trade_name'] ?? null,
            'registration_number' => $data['registration_number'] ?? null,
            'tax_id' => $data['tax_id'] ?? null,
            'industry' => $data['industry'] ?? null,
            'company_size' => $data['company_size'] ?? null,
            'country' => $data['org_country'] ?? ($data['country'] ?? null),
            'city' => $data['org_city'] ?? ($data['city'] ?? null),
            'address_line_1' => $data['org_address_line_1'] ?? ($data['address_line_1'] ?? null),
            'address_line_2' => $data['org_address_line_2'] ?? ($data['address_line_2'] ?? null),
            'postal_code' => $data['org_postal_code'] ?? ($data['postal_code'] ?? null),
            'phone' => $data['org_phone'] ?? ($data['contact_phone'] ?? ($data['owner_phone'] ?? null)),
            'email' => $data['org_email'] ?? ($data['contact_email'] ?? $data['owner_email']),
            'website' => $data['website'] ?? null,
            'billing_currency' => $data['currency'] ?? 'SAR',
            'billing_cycle' => 'monthly',
        ];

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOrganizationUpdatePayload(array $data): array
    {
        $map = [
            'legal_name' => 'legal_name',
            'trade_name' => 'trade_name',
            'registration_number' => 'registration_number',
            'tax_id' => 'tax_id',
            'industry' => 'industry',
            'company_size' => 'company_size',
            'org_country' => 'country',
            'org_city' => 'city',
            'org_address_line_1' => 'address_line_1',
            'org_address_line_2' => 'address_line_2',
            'org_postal_code' => 'postal_code',
            'org_phone' => 'phone',
            'org_email' => 'email',
            'website' => 'website',
        ];

        $payload = [];
        foreach ($map as $inputKey => $column) {
            if (array_key_exists($inputKey, $data)) {
                $payload[$column] = $data[$inputKey];
            }
        }

        return $payload;
    }

    private function uniqueSlug(string $name, ?Account $ignore = null): string
    {
        $base = trim(Str::slug($name));
        $base = $base !== '' ? $base : 'account';
        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($slug, $ignore)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?Account $ignore = null): bool
    {
        return Account::query()
            ->withoutGlobalScopes()
            ->when($ignore instanceof Account, static function ($query) use ($ignore): void {
                $query->where('id', '!=', (string) $ignore->id);
            })
            ->where('slug', $slug)
            ->exists();
    }

    private function revokeExternalTokens(Account $account): void
    {
        User::query()
            ->withoutGlobalScopes()
            ->where('account_id', (string) $account->id)
            ->when(Schema::hasColumn('users', 'user_type'), static function ($query): void {
                $query->where('user_type', 'external');
            })
            ->get()
            ->each(static function (User $user): void {
                $user->tokens()->delete();
            });
    }

    private function targetStatusFor(string $fromStatus, string $action): ?string
    {
        return match ($action) {
            'activate' => in_array($fromStatus, ['pending', 'closed'], true) ? 'active' : null,
            'deactivate' => in_array($fromStatus, ['pending', 'active', 'suspended'], true) ? 'closed' : null,
            'suspend' => $fromStatus === 'active' ? 'suspended' : null,
            'unsuspend' => $fromStatus === 'suspended' ? 'active' : null,
            default => null,
        };
    }

    /**
     * @return array{action: string, label: string, tone: string}|null
     */
    private function buildLifecycleAction(string $action, string $label, string $tone, string $status): ?array
    {
        return $this->targetStatusFor($status, $action) === null
            ? null
            : [
                'action' => $action,
                'label' => $label,
                'tone' => $tone,
            ];
    }
}
