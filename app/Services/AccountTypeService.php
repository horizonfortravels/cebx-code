<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\KycVerification;
use App\Models\OrganizationProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AccountTypeService
{
    /**
     * Initialize account type after registration.
     * Called by AccountService::createAccount() when type is specified.
     *
     * - Organization: auto-creates OrganizationProfile + KYC (pending)
     * - Individual: creates KYC record (unverified)
     */
    public function initializeAccountType(Account $account, array $data = []): Account
    {
        return DB::transaction(function () use ($account, $data) {

            if ($account->isOrganization()) {
                $this->createOrganizationProfile($account, $data);
                $this->initializeKyc($account, 'organization', $data);
            } else {
                $this->initializeKyc($account, 'individual', $data);
            }

            return $account->refresh();
        });
    }

    /**
     * Update the organization profile.
     */
    public function updateOrganizationProfile(string $accountId, array $data, User $performer): OrganizationProfile
    {
        $account = Account::findOrFail($accountId);

        if (!$account->isOrganization()) {
            throw new BusinessException(
                'هذا الحساب ليس حساب منظمة.',
                'ERR_NOT_ORGANIZATION',
                422
            );
        }

        $profile = $account->organizationProfile;

        if (!$profile) {
            throw new BusinessException(
                'ملف المنظمة غير موجود.',
                'ERR_PROFILE_NOT_FOUND',
                404
            );
        }

        return DB::transaction(function () use ($profile, $data, $performer, $accountId) {
            $oldValues = $profile->only(array_keys($data));

            $profile->update($data);

            $this->logAction(
                $accountId,
                $performer->id,
                'organization_profile.updated',
                'OrganizationProfile',
                $profile->id,
                $oldValues,
                $data
            );

            return $profile->fresh();
        });
    }

    /**
     * Request to change account type.
     * Only allowed if the account has NOT been actively used.
     */
    public function requestTypeChange(string $accountId, string $newType, User $performer): Account
    {
        $account = Account::findOrFail($accountId);

        // Validate new type
        if (!in_array($newType, ['individual', 'organization'])) {
            throw new BusinessException('نوع الحساب غير صالح.', 'ERR_INVALID_INPUT', 422);
        }

        // Same type?
        if ($account->type === $newType) {
            throw new BusinessException('الحساب من نفس النوع بالفعل.', 'ERR_SAME_TYPE', 422);
        }

        // Check active usage — cannot change after using services
        if ($account->hasActiveUsage()) {
            throw new BusinessException(
                'لا يمكن تغيير نوع الحساب بعد استخدام الخدمات. يوجد مستخدمين أو عمليات نشطة.',
                'ERR_TYPE_CHANGE_NOT_ALLOWED',
                409
            );
        }

        return DB::transaction(function () use ($account, $newType, $performer) {
            $oldType = $account->type;

            // If changing FROM organization, clean up org profile
            if ($oldType === 'organization' && $newType === 'individual') {
                $account->organizationProfile?->delete();
            }

            // Update type
            $account->update(['type' => $newType]);

            // If changing TO organization, create profile
            if ($newType === 'organization') {
                $this->createOrganizationProfile($account, [
                    'legal_name' => $account->name,
                ]);
            }

            // Reset KYC for new type
            $account->kycVerifications()->delete();
            $this->initializeKyc($account, $newType, []);

            $this->logAction(
                $account->id,
                $performer->id,
                'account.type_changed',
                'Account',
                $account->id,
                ['type' => $oldType],
                ['type' => $newType]
            );

            return $account->refresh();
        });
    }

    /**
     * Get account type info including profile and KYC status.
     */
    public function getAccountTypeInfo(string $accountId): array
    {
        $account = Account::with(['organizationProfile', 'kycVerification'])
            ->findOrFail($accountId);

        $info = [
            'account_id'   => $account->id,
            'type'         => $account->type,
            'kyc_status'   => $account->kyc_status,
            'can_change_type' => !$account->hasActiveUsage(),
        ];

        if ($account->isOrganization() && $account->organizationProfile) {
            $info['organization'] = [
                'legal_name'          => $account->organizationProfile->legal_name,
                'trade_name'          => $account->organizationProfile->trade_name,
                'registration_number' => $account->organizationProfile->registration_number,
                'tax_id'              => $account->organizationProfile->tax_id,
                'industry'            => $account->organizationProfile->industry,
                'country'             => $account->organizationProfile->country,
                'is_complete'         => $account->organizationProfile->isComplete(),
            ];
        }

        if ($account->kycVerification) {
            $info['kyc'] = [
                'status'             => $account->kycVerification->status,
                'verification_type'  => $account->kycVerification->verification_type,
                'required_documents' => $account->kycVerification->required_documents,
                'submitted_at'       => $account->kycVerification->submitted_at?->toISOString(),
                'reviewed_at'        => $account->kycVerification->reviewed_at?->toISOString(),
            ];
        }

        return $info;
    }

    /**
     * Submit KYC documents for verification.
     */
    public function submitKycDocuments(string $accountId, array $documents, User $performer): KycVerification
    {
        $account = Account::findOrFail($accountId);
        $kyc = $account->kycVerification;

        if (!$kyc) {
            throw new BusinessException('سجل التحقق غير موجود.', 'ERR_KYC_NOT_FOUND', 404);
        }

        // Validate required documents are provided
        $required = $kyc->required_documents ?? [];
        $submitted = array_keys($documents);
        $missing = array_diff(array_keys($required), $submitted);

        if (!empty($missing)) {
            throw new BusinessException(
                'وثائق ناقصة: ' . implode(', ', array_values(array_intersect_key($required, array_flip($missing)))),
                'ERR_MISSING_DOCUMENTS',
                422
            );
        }

        return DB::transaction(function () use ($kyc, $documents, $performer, $account) {
            $kyc->update([
                'submitted_documents' => $documents,
                'status'              => KycVerification::STATUS_PENDING,
                'submitted_at'        => now(),
            ]);

            $account->update(['kyc_status' => KycVerification::STATUS_PENDING]);

            $this->logAction(
                $account->id,
                $performer->id,
                'kyc.submitted',
                'KycVerification',
                $kyc->id,
                null,
                ['status' => 'pending', 'documents_count' => count($documents)]
            );

            return $kyc->fresh();
        });
    }

    // ─── Private Helpers ──────────────────────────────────────────

    /**
     * Create OrganizationProfile for organization accounts.
     */
    private function createOrganizationProfile(Account $account, array $data): OrganizationProfile
    {
        return OrganizationProfile::create([
            'account_id'          => $account->id,
            'legal_name'          => $data['legal_name'] ?? $data['organization_name'] ?? $account->name,
            'trade_name'          => $data['trade_name'] ?? null,
            'registration_number' => $data['registration_number'] ?? null,
            'tax_id'              => $data['tax_id'] ?? null,
            'industry'            => $data['industry'] ?? null,
            'company_size'        => $data['company_size'] ?? null,
            'country'             => $data['country'] ?? null,
            'city'                => $data['city'] ?? null,
            'phone'               => $data['org_phone'] ?? null,
            'email'               => $data['org_email'] ?? null,
            'billing_currency'    => $data['billing_currency'] ?? 'SAR',
            'billing_cycle'       => $data['billing_cycle'] ?? 'monthly',
        ]);
    }

    /**
     * Initialize KYC record with required documents for the account type.
     */
    private function initializeKyc(Account $account, string $verificationType, array $data): KycVerification
    {
        $requiredDocs = KycVerification::requiredDocumentsFor($verificationType);

        $kyc = KycVerification::create([
            'account_id'         => $account->id,
            'status'             => KycVerification::STATUS_UNVERIFIED,
            'verification_type'  => $verificationType,
            'required_documents' => $requiredDocs,
        ]);

        $account->update(['kyc_status' => KycVerification::STATUS_UNVERIFIED]);

        return $kyc;
    }

    private function logAction(string $accountId, string $userId, string $action, string $entityType, string $entityId, ?array $old, ?array $new): void
    {
        AuditLog::withoutGlobalScopes()->create([
            'account_id'  => $accountId,
            'user_id'     => $userId,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'old_values'  => $old,
            'new_values'  => $new,
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
        ]);
    }
}
