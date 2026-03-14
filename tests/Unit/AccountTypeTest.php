<?php

namespace Tests\Unit;

use App\Exceptions\BusinessException;
use App\Models\Account;
use App\Models\KycVerification;
use App\Models\OrganizationProfile;
use App\Models\User;
use App\Services\AccountService;
use App\Services\AccountTypeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTypeTest extends TestCase
{
    use RefreshDatabase;

    private AccountTypeService $service;
    private AccountService $accountService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AccountTypeService();
        $this->accountService = new AccountService();
    }

    // ─── AC: نجاح — إنشاء حساب منظمة ────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function organization_account_auto_creates_organization_profile(): void
    {
        $result = $this->accountService->createAccount([
            'account_name' => 'شركة التوصيل السريع',
            'account_type' => 'organization',
            'name'         => 'أحمد مالك',
            'email'        => 'owner@fastship.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $account = $result['account']->refresh();

        $this->assertEquals('organization', $account->type);
        $this->assertNotNull($account->organizationProfile);
        $this->assertEquals('شركة التوصيل السريع', $account->organizationProfile->legal_name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function organization_account_starts_with_kyc_unverified(): void
    {
        $result = $this->accountService->createAccount([
            'account_name' => 'شركة الشحن',
            'account_type' => 'organization',
            'name'         => 'سارة',
            'email'        => 'sara@shipping.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $account = $result['account']->refresh();

        $this->assertEquals('unverified', $account->kyc_status);
        $this->assertNotNull($account->kycVerification);
        $this->assertEquals('organization', $account->kycVerification->verification_type);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function organization_kyc_has_required_documents_list(): void
    {
        $result = $this->accountService->createAccount([
            'account_name' => 'مؤسسة النقل',
            'account_type' => 'organization',
            'name'         => 'خالد',
            'email'        => 'khalid@transport.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $kyc = $result['account']->refresh()->kycVerification;

        $this->assertArrayHasKey('commercial_registration', $kyc->required_documents);
        $this->assertArrayHasKey('tax_certificate', $kyc->required_documents);
    }

    // ─── إنشاء حساب فردي ─────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function individual_account_does_not_create_organization_profile(): void
    {
        $result = $this->accountService->createAccount([
            'account_name' => 'حساب فردي',
            'account_type' => 'individual',
            'name'         => 'محمد',
            'email'        => 'mohammed@test.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $account = $result['account']->refresh();

        $this->assertEquals('individual', $account->type);
        $this->assertNull($account->organizationProfile);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function individual_account_has_kyc_with_individual_docs(): void
    {
        $result = $this->accountService->createAccount([
            'account_name' => 'حساب فردي',
            'account_type' => 'individual',
            'name'         => 'فاطمة',
            'email'        => 'fatima@test.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $kyc = $result['account']->refresh()->kycVerification;

        $this->assertEquals('individual', $kyc->verification_type);
        $this->assertArrayHasKey('national_id', $kyc->required_documents);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function default_account_type_is_individual(): void
    {
        $result = $this->accountService->createAccount([
            'account_name' => 'حساب عادي',
            'name'         => 'علي',
            'email'        => 'ali@test.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $this->assertEquals('individual', $result['account']->type);
    }

    // ─── تحديث ملف المنظمة ───────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_update_organization_profile(): void
    {
        $result = $this->accountService->createAccount([
            'account_name' => 'شركة',
            'account_type' => 'organization',
            'name'         => 'مالك',
            'email'        => 'owner@company.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $profile = $this->service->updateOrganizationProfile(
            $result['account']->id,
            [
                'registration_number' => 'CR-1234567890',
                'tax_id'              => 'TAX-9876543210',
                'country'             => 'SA',
                'city'                => 'الرياض',
            ],
            $result['user']
        );

        $this->assertEquals('CR-1234567890', $profile->registration_number);
        $this->assertEquals('SA', $profile->country);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_update_org_profile_on_individual_account(): void
    {
        $result = $this->accountService->createAccount([
            'account_name' => 'فردي',
            'account_type' => 'individual',
            'name'         => 'أحمد',
            'email'        => 'ahmed@test.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $this->expectException(BusinessException::class);

        $this->service->updateOrganizationProfile(
            $result['account']->id,
            ['legal_name' => 'شركة'],
            $result['user']
        );
    }

    // ─── AC: حالة حدية — تغيير نوع الحساب ────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_change_type_before_active_usage(): void
    {
        $result = $this->accountService->createAccount([
            'account_name' => 'حساب',
            'account_type' => 'individual',
            'name'         => 'مستخدم',
            'email'        => 'user@test.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $account = $this->service->requestTypeChange(
            $result['account']->id,
            'organization',
            $result['user']
        );

        $this->assertEquals('organization', $account->type);
        $this->assertNotNull($account->organizationProfile);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function type_change_to_org_creates_profile_and_resets_kyc(): void
    {
        $result = $this->accountService->createAccount([
            'account_name' => 'تغيير',
            'account_type' => 'individual',
            'name'         => 'مستخدم',
            'email'        => 'change@test.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $account = $this->service->requestTypeChange(
            $result['account']->id,
            'organization',
            $result['user']
        );

        $account->refresh();
        $this->assertNotNull($account->organizationProfile);
        $this->assertEquals('unverified', $account->kyc_status);
        $this->assertEquals('organization', $account->kycVerification->verification_type);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_change_type_after_active_usage(): void
    {
        $result = $this->accountService->createAccount([
            'account_name' => 'حساب نشط',
            'account_type' => 'individual',
            'name'         => 'مالك',
            'email'        => 'active@test.com',
            'password'     => 'Str0ng!Pass',
        ]);

        // Simulate active usage by adding another user
        User::withoutGlobalScopes()->create([
            'account_id' => $result['account']->id,
            'name'       => 'موظف',
            'email'      => 'employee@test.com',
            'password'   => 'pass',
            'is_owner'   => false,
        ]);

        $this->expectException(BusinessException::class);

        $this->service->requestTypeChange(
            $result['account']->id,
            'organization',
            $result['user']
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function type_change_blocked_returns_correct_error_code(): void
    {
        $result = $this->accountService->createAccount([
            'account_name' => 'حساب',
            'account_type' => 'individual',
            'name'         => 'مالك',
            'email'        => 'blocked@test.com',
            'password'     => 'Str0ng!Pass',
        ]);

        User::withoutGlobalScopes()->create([
            'account_id' => $result['account']->id,
            'name'       => 'موظف',
            'email'      => 'emp@test.com',
            'password'   => 'pass',
            'is_owner'   => false,
        ]);

        try {
            $this->service->requestTypeChange(
                $result['account']->id, 'organization', $result['user']
            );
            $this->fail('Expected exception');
        } catch (BusinessException $e) {
            $this->assertEquals('ERR_TYPE_CHANGE_NOT_ALLOWED', $e->getErrorCode());
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_change_to_same_type(): void
    {
        $result = $this->accountService->createAccount([
            'account_name' => 'فردي',
            'account_type' => 'individual',
            'name'         => 'مالك',
            'email'        => 'same@test.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $this->expectException(BusinessException::class);

        $this->service->requestTypeChange(
            $result['account']->id, 'individual', $result['user']
        );
    }

    // ─── AC: فشل شائع — وثائق ناقصة ─────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function submitting_kyc_with_missing_documents_fails(): void
    {
        $result = $this->accountService->createAccount([
            'account_name' => 'شركة KYC',
            'account_type' => 'organization',
            'name'         => 'مالك',
            'email'        => 'kyc@test.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $this->expectException(BusinessException::class);

        // Submit only one document (missing others)
        $this->service->submitKycDocuments(
            $result['account']->id,
            ['commercial_registration' => '/docs/cr.pdf'],
            $result['user']
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function submitting_all_kyc_documents_sets_status_pending(): void
    {
        $result = $this->accountService->createAccount([
            'account_name' => 'شركة كاملة',
            'account_type' => 'organization',
            'name'         => 'مالك',
            'email'        => 'complete-kyc@test.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $kyc = $this->service->submitKycDocuments(
            $result['account']->id,
            [
                'commercial_registration' => '/docs/cr.pdf',
                'tax_certificate'         => '/docs/tax.pdf',
                'national_address'        => '/docs/addr.pdf',
                'authorization_letter'    => '/docs/auth.pdf',
            ],
            $result['user']
        );

        $this->assertEquals('pending', $kyc->status);
        $this->assertNotNull($kyc->submitted_at);
        $this->assertEquals('pending', $result['account']->fresh()->kyc_status);
    }

    // ─── Audit Logging ───────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function type_change_is_logged(): void
    {
        $result = $this->accountService->createAccount([
            'account_name' => 'تسجيل',
            'account_type' => 'individual',
            'name'         => 'مالك',
            'email'        => 'log@test.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $this->service->requestTypeChange(
            $result['account']->id, 'organization', $result['user']
        );

        $this->assertDatabaseHas('audit_logs', [
            'account_id' => $result['account']->id,
            'action'     => 'account.type_changed',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function kyc_submission_is_logged(): void
    {
        $result = $this->accountService->createAccount([
            'account_name' => 'KYC Log',
            'account_type' => 'organization',
            'name'         => 'مالك',
            'email'        => 'kyclog@test.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $this->service->submitKycDocuments(
            $result['account']->id,
            [
                'commercial_registration' => '/docs/cr.pdf',
                'tax_certificate'         => '/docs/tax.pdf',
                'national_address'        => '/docs/addr.pdf',
                'authorization_letter'    => '/docs/auth.pdf',
            ],
            $result['user']
        );

        $this->assertDatabaseHas('audit_logs', [
            'account_id' => $result['account']->id,
            'action'     => 'kyc.submitted',
        ]);
    }
}
