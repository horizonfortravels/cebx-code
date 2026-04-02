<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\KycDocument;
use App\Models\KycVerification;
use App\Models\OrganizationProfile;
use App\Models\Shipment;
use App\Models\User;
use App\Models\VerificationRestriction;
use App\Services\AuditService;
use App\Support\Kyc\AccountKycStatusMapper;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AssertsSchemaAwareAuditLogs;
use Tests\TestCase;

class InternalKycOperationsWebTest extends TestCase
{
    use AssertsSchemaAwareAuditLogs;
    use RefreshDatabase;

    private Account $individualAccount;
    private Account $secondPendingAccount;
    private Account $organizationAccount;
    private Account $approvedAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);

        $this->individualAccount = $this->accountBySlug('e2e-account-a');
        $this->secondPendingAccount = $this->accountBySlug('e2e-account-b');
        $this->organizationAccount = $this->accountBySlug('e2e-account-c');
        $this->approvedAccount = $this->accountBySlug('e2e-account-d');

        $this->seedKycFixtures();
    }

    #[Test]
    public function super_admin_support_and_ops_readonly_can_open_kyc_queue_and_detail(): void
    {
        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $index = $this->actingAs($user, 'web')
                ->get(route('internal.kyc.index'))
                ->assertOk()
                ->assertSee('data-testid="internal-kyc-table"', false)
                ->assertSeeText('E2E Account A')
                ->assertSeeText('E2E Account C');

            $this->assertHasNavigationLink($index, 'internal.kyc.index');

            $individualDetail = $this->actingAs($user, 'web')
                ->get(route('internal.kyc.show', $this->individualAccount))
                ->assertOk()
                ->assertSee('data-testid="kyc-account-summary-card"', false)
                ->assertSee('data-testid="kyc-status-card"', false)
                ->assertSee('data-testid="kyc-documents-card"', false)
                ->assertSee('data-testid="kyc-restrictions-card"', false)
                ->assertSee('data-testid="kyc-audit-card"', false)
                ->assertSeeText('E2E A Individual User')
                ->assertSeeText('e2e-account-a-id.pdf')
                ->assertDontSeeText('kyc/e2e-account-a-id.pdf');

            if ($email === 'e2e.internal.ops_readonly@example.test') {
                $individualDetail->assertDontSee('data-testid="kyc-account-summary-link"', false);
            } else {
                $individualDetail->assertSee('data-testid="kyc-account-summary-link"', false);
            }

            if ($email === 'e2e.internal.super_admin@example.test') {
                $individualDetail
                    ->assertSee('data-testid="kyc-review-card"', false)
                    ->assertSee('data-testid="kyc-approve-button"', false)
                    ->assertSee('data-testid="kyc-reject-button"', false)
                    ->assertDontSee('data-testid="kyc-request-more-info-button"', false)
                    ->assertSee('data-testid="kyc-restriction-management-card"', false);
            } else {
                $individualDetail
                    ->assertDontSee('data-testid="kyc-review-card"', false)
                    ->assertDontSee('data-testid="kyc-approve-button"', false)
                    ->assertDontSee('data-testid="kyc-reject-button"', false)
                    ->assertDontSee('data-testid="kyc-restriction-management-card"', false);
            }

            $this->actingAs($user, 'web')
                ->get(route('internal.kyc.show', $this->organizationAccount))
                ->assertOk()
                ->assertSeeText('E2E Account C Logistics LLC')
                ->assertSee('data-testid="kyc-operational-effects-card"', false)
                ->assertSee('data-testid="kyc-blocked-shipments-count"', false)
                ->assertSee('data-testid="kyc-impacted-shipments-card"', false)
                ->assertSeeText('SHP-KYC-C-002')
                ->assertSee('data-testid="kyc-restrictions-card"', false);
        }
    }

    #[Test]
    public function super_admin_and_support_can_see_kyc_operational_effect_on_account_detail(): void
    {
        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $this->actingAs($user, 'web')
                ->get(route('internal.accounts.show', $this->organizationAccount))
                ->assertOk()
                ->assertSee('data-testid="account-kyc-operational-effect-card"', false)
                ->assertSee('data-testid="account-kyc-shipping-operability"', false)
                ->assertSee('data-testid="account-kyc-international-shipping-state"', false)
                ->assertSee('data-testid="account-kyc-next-action"', false)
                ->assertSee('data-testid="account-kyc-blocked-shipments-count"', false)
                ->assertSee('data-testid="account-kyc-impacted-shipments-card"', false)
                ->assertSeeText('SHP-KYC-C-002')
                ->assertSee('href="' . route('internal.kyc.show', $this->organizationAccount) . '"', false);
        }
    }

    #[Test]
    public function kyc_queue_supports_search_and_basic_filters(): void
    {
        $viewer = $this->userByEmail('e2e.internal.super_admin@example.test');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.kyc.index', ['q' => 'E2E Account C']))
            ->assertOk()
            ->assertSeeText('E2E Account C')
            ->assertDontSeeText('E2E Account A');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.kyc.index', ['type' => 'organization']))
            ->assertOk()
            ->assertSeeText('E2E Account C')
            ->assertDontSeeText('E2E Account A');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.kyc.index', ['status' => KycVerification::STATUS_PENDING]))
            ->assertOk()
            ->assertSeeText('E2E Account A')
            ->assertSeeText('E2E Account B')
            ->assertDontSeeText('E2E Account C');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.kyc.index', ['status' => KycVerification::STATUS_APPROVED]))
            ->assertOk()
            ->assertSeeText('E2E Account D')
            ->assertDontSeeText('E2E Account A');

        $restrictionResponse = $this->actingAs($viewer, 'web')
            ->get(route('internal.kyc.index', ['restriction' => 'restricted']))
            ->assertOk();

        if (Schema::hasTable('verification_restrictions')) {
            $restrictionResponse
                ->assertSeeText('E2E Account A')
                ->assertDontSeeText('E2E Account D');
        } else {
            $restrictionResponse->assertSeeText('E2E Account A');
        }
    }

    #[Test]
    public function super_admin_support_and_ops_readonly_see_kyc_navigation_while_carrier_manager_does_not(): void
    {
        $superAdminPage = $this->actingAs($this->userByEmail('e2e.internal.super_admin@example.test'), 'web')
            ->get(route('admin.index'))
            ->assertOk();
        $this->assertHasNavigationLink($superAdminPage, 'internal.kyc.index');

        foreach ([
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $page = $this->actingAs($this->userByEmail($email), 'web')
                ->get(route('internal.home'))
                ->assertOk();

            $this->assertHasNavigationLink($page, 'internal.kyc.index');
        }

        $carrierManagerPage = $this->actingAs($this->userByEmail('e2e.internal.carrier_manager@example.test'), 'web')
            ->get(route('internal.home'))
            ->assertOk();

        $this->assertMissingNavigationLink($carrierManagerPage, 'internal.kyc.index');
    }

    #[Test]
    public function super_admin_can_approve_a_pending_kyc_case_and_audit_entry_is_recorded(): void
    {
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');
        $notes = 'تمت مراجعة الوثائق واعتمادها من مركز العمليات الداخلي.';

        $response = $this->actingAs($actor, 'web')->post(route('internal.kyc.approve', $this->individualAccount), [
            'notes' => $notes,
        ]);

        $response->assertRedirect(route('internal.kyc.show', $this->individualAccount));
        $response->assertSessionHasNoErrors();

        $verification = $this->individualAccount->kycVerification()->withoutGlobalScopes()->firstOrFail()->fresh();
        $this->individualAccount->refresh();

        $this->assertSame(KycVerification::STATUS_APPROVED, (string) $verification->status);
        $this->assertSame((string) $actor->id, (string) $verification->reviewed_by);
        $this->assertSame($notes, (string) $verification->review_notes);
        $this->assertNotNull($verification->reviewed_at);
        $this->assertSame(
            AccountKycStatusMapper::fromVerificationStatus(KycVerification::STATUS_APPROVED),
            (string) $this->individualAccount->kyc_status
        );

        $this->assertAuditLogRecorded(
            'kyc.approved',
            (string) $actor->id,
            (string) $this->individualAccount->id,
            'KycVerification',
            (string) $verification->id,
        );
    }

    #[Test]
    public function super_admin_can_reject_a_pending_kyc_case_and_audit_entry_is_recorded(): void
    {
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');
        $reason = 'الوثيقة المرفوعة لا تطابق بيانات الحساب الحالية.';
        $notes = 'يلزم رفع نسخة أوضح من الوثيقة مع مطابقة الاسم.';

        $response = $this->actingAs($actor, 'web')->post(route('internal.kyc.reject', $this->secondPendingAccount), [
            'reason' => $reason,
            'notes' => $notes,
        ]);

        $response->assertRedirect(route('internal.kyc.show', $this->secondPendingAccount));
        $response->assertSessionHasNoErrors();

        $verification = $this->secondPendingAccount->kycVerification()->withoutGlobalScopes()->firstOrFail()->fresh();
        $this->secondPendingAccount->refresh();

        $this->assertSame(KycVerification::STATUS_REJECTED, (string) $verification->status);
        $this->assertSame((string) $actor->id, (string) $verification->reviewed_by);
        $this->assertSame($reason, (string) $verification->rejection_reason);
        $this->assertSame($notes, (string) $verification->review_notes);
        $this->assertNotNull($verification->reviewed_at);
        $this->assertSame(
            AccountKycStatusMapper::fromVerificationStatus(KycVerification::STATUS_REJECTED),
            (string) $this->secondPendingAccount->kyc_status
        );

        $this->assertAuditLogRecorded(
            'kyc.rejected',
            (string) $actor->id,
            (string) $this->secondPendingAccount->id,
            'KycVerification',
            (string) $verification->id,
        );
    }

    #[Test]
    public function super_admin_can_manage_verification_restriction_overlays_and_audit_entries_are_recorded(): void
    {
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');

        $shippingResponse = $this->actingAs($actor, 'web')->post(
            route('internal.kyc.restrictions.sync', [
                'account' => $this->organizationAccount,
                'feature' => 'shipping_limit',
            ]),
            [
                'mode' => 'set',
                'quota_value' => 25,
                'note' => 'تقييد تشغيلي مؤقت بعد مراجعة ملف KYC.',
            ]
        );

        $shippingResponse->assertRedirect(route('internal.kyc.show', $this->organizationAccount));
        $shippingResponse->assertSessionHasNoErrors();

        $shippingRestriction = VerificationRestriction::query()
            ->where('restriction_key', 'kyc_rejected_shipping_limit')
            ->firstOrFail();

        $this->assertSame(VerificationRestriction::TYPE_QUOTA_LIMIT, (string) $shippingRestriction->restriction_type);
        $this->assertSame('shipping_limit', (string) $shippingRestriction->feature_key);
        $this->assertSame(25, (int) $shippingRestriction->quota_value);
        $this->assertTrue((bool) $shippingRestriction->is_active);

        $this->assertAuditLogRecorded(
            'kyc.restriction_updated',
            (string) $actor->id,
            (string) $this->organizationAccount->id,
            'VerificationRestriction',
            (string) $shippingRestriction->id,
        );

        $blockResponse = $this->actingAs($actor, 'web')->post(
            route('internal.kyc.restrictions.sync', [
                'account' => $this->organizationAccount,
                'feature' => 'international_shipping',
            ]),
            [
                'mode' => 'enable',
                'note' => 'الإبقاء على الحظر الدولي حتى اكتمال المراجعة الداخلية.',
            ]
        );

        $blockResponse->assertRedirect(route('internal.kyc.show', $this->organizationAccount));
        $blockResponse->assertSessionHasNoErrors();

        $blockRestriction = VerificationRestriction::query()
            ->where('restriction_key', 'kyc_rejected_international_shipping')
            ->firstOrFail();

        $this->assertSame(VerificationRestriction::TYPE_BLOCK_FEATURE, (string) $blockRestriction->restriction_type);
        $this->assertSame('international_shipping', (string) $blockRestriction->feature_key);
        $this->assertTrue((bool) $blockRestriction->is_active);

        $this->assertAuditLogRecorded(
            'kyc.restriction_updated',
            (string) $actor->id,
            (string) $this->organizationAccount->id,
            'VerificationRestriction',
            (string) $blockRestriction->id,
        );

        $this->actingAs($actor, 'web')
            ->get(route('internal.kyc.show', $this->organizationAccount))
            ->assertOk()
            ->assertSeeText('حد الشحن الكلي')
            ->assertSeeText('25')
            ->assertSeeText('تعليق الشحن الدولي');
    }

    #[Test]
    public function support_and_ops_readonly_are_forbidden_from_kyc_mutation_routes(): void
    {
        foreach ([
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $this->actingAs($user, 'web')
                ->post(route('internal.kyc.approve', $this->individualAccount), ['notes' => 'not allowed'])
                ->assertForbidden();

            $this->actingAs($user, 'web')
                ->post(route('internal.kyc.reject', $this->individualAccount), ['reason' => 'not allowed'])
                ->assertForbidden();

            $this->actingAs($user, 'web')
                ->post(route('internal.kyc.restrictions.sync', [
                    'account' => $this->individualAccount,
                    'feature' => 'shipping_limit',
                ]), ['mode' => 'set', 'quota_value' => 12])
                ->assertForbidden();
        }
    }

    #[Test]
    public function carrier_manager_is_forbidden_from_internal_kyc_routes(): void
    {
        $user = $this->userByEmail('e2e.internal.carrier_manager@example.test');

        $this->assertForbiddenInternalSurface(
            $this->actingAs($user, 'web')->get(route('internal.kyc.index'))
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($user, 'web')->get(route('internal.kyc.show', $this->organizationAccount))
        );
    }

    #[Test]
    public function external_users_are_forbidden_from_internal_kyc_routes(): void
    {
        $externalUser = $this->userByEmail('e2e.c.organization_owner@example.test');

        $this->actingAs($externalUser, 'web')
            ->get(route('internal.kyc.index'))
            ->assertForbidden()
            ->assertSeeText('هذه الصفحة مخصصة لفريق التشغيل الداخلي في المنصة');

        $this->actingAs($externalUser, 'web')
            ->get(route('internal.kyc.show', $this->individualAccount))
            ->assertForbidden()
            ->assertSeeText('هذه الصفحة مخصصة لفريق التشغيل الداخلي في المنصة');

        $this->actingAs($externalUser, 'web')
            ->post(route('internal.kyc.approve', $this->individualAccount), ['notes' => 'blocked'])
            ->assertForbidden();

        $this->actingAs($externalUser, 'web')
            ->post(route('internal.kyc.reject', $this->individualAccount), ['reason' => 'blocked'])
            ->assertForbidden();

        $this->actingAs($externalUser, 'web')
            ->post(route('internal.kyc.restrictions.sync', [
                'account' => $this->individualAccount,
                'feature' => 'international_shipping',
            ]), ['mode' => 'enable'])
            ->assertForbidden();
    }

    private function seedKycFixtures(): void
    {
        $individualOwner = $this->userByEmail('e2e.a.individual@example.test');
        $secondPendingOwner = $this->userByEmail('e2e.b.individual@example.test');
        $organizationOwner = $this->userByEmail('e2e.c.organization_owner@example.test');
        $approvedOwner = $this->userByEmail('e2e.d.organization_owner@example.test');

        $this->individualAccount->forceFill([
            'status' => 'active',
            'kyc_status' => AccountKycStatusMapper::fromVerificationStatus(KycVerification::STATUS_PENDING),
        ])->save();

        $this->secondPendingAccount->forceFill([
            'status' => 'active',
            'kyc_status' => AccountKycStatusMapper::fromVerificationStatus(KycVerification::STATUS_PENDING),
        ])->save();

        $this->organizationAccount->forceFill([
            'status' => 'active',
            'kyc_status' => AccountKycStatusMapper::fromVerificationStatus(KycVerification::STATUS_REJECTED),
        ])->save();

        $this->approvedAccount->forceFill([
            'status' => 'active',
            'kyc_status' => AccountKycStatusMapper::fromVerificationStatus(KycVerification::STATUS_APPROVED),
        ])->save();

        OrganizationProfile::query()->updateOrCreate(
            ['account_id' => (string) $this->organizationAccount->id],
            [
                'legal_name' => 'E2E Account C Logistics LLC',
                'trade_name' => 'E2E Logistics',
                'registration_number' => 'CR-300300300',
                'industry' => 'logistics',
                'company_size' => 'medium',
                'country' => 'SA',
                'city' => 'Riyadh',
                'email' => 'ops@e2e-account-c.example.test',
            ]
        );

        $individualVerification = KycVerification::factory()->pending()->create([
            'account_id' => (string) $this->individualAccount->id,
            'verification_type' => 'individual',
            'verification_level' => 'basic',
            'required_documents' => ['national_id' => 'الهوية الوطنية', 'address_proof' => 'إثبات العنوان'],
            'submitted_documents' => ['national_id' => 'kyc/e2e-account-a-id.pdf'],
            'review_notes' => 'تمت مراجعة أولية للهوية وما زال إثبات العنوان مطلوبًا قبل الاعتماد الكامل.',
        ]);

        $secondPendingVerification = KycVerification::factory()->pending()->create([
            'account_id' => (string) $this->secondPendingAccount->id,
            'verification_type' => 'individual',
            'verification_level' => 'basic',
            'required_documents' => ['passport' => 'جواز السفر'],
            'submitted_documents' => ['passport' => 'kyc/e2e-account-b-passport.pdf'],
            'review_notes' => 'تم استلام نسخة أولية من الجواز وتحتاج إلى قرار نهائي.',
        ]);

        $organizationVerification = KycVerification::factory()->rejected()->organization()->create([
            'account_id' => (string) $this->organizationAccount->id,
            'verification_level' => 'enhanced',
            'submitted_documents' => [
                'commercial_registration' => 'kyc/e2e-account-c-cr.pdf',
                'tax_certificate' => 'kyc/e2e-account-c-tax.pdf',
            ],
            'review_notes' => 'يلزم إعادة رفع السجل التجاري بنسخة أوضح ومطابقة للاسم القانوني.',
        ]);

        KycVerification::factory()->approved()->organization()->create([
            'account_id' => (string) $this->approvedAccount->id,
            'verification_level' => 'enhanced',
        ]);

        KycDocument::factory()->create([
            'account_id' => (string) $this->individualAccount->id,
            'kyc_verification_id' => (string) $individualVerification->id,
            'document_type' => 'national_id',
            'original_filename' => 'e2e-account-a-id.pdf',
            'stored_path' => 'kyc/e2e-account-a-id.pdf',
            'uploaded_by' => (string) $individualOwner->id,
        ]);

        KycDocument::factory()->create([
            'account_id' => (string) $this->secondPendingAccount->id,
            'kyc_verification_id' => (string) $secondPendingVerification->id,
            'document_type' => 'passport',
            'original_filename' => 'e2e-account-b-passport.pdf',
            'stored_path' => 'kyc/e2e-account-b-passport.pdf',
            'uploaded_by' => (string) $secondPendingOwner->id,
        ]);

        KycDocument::factory()->create([
            'account_id' => (string) $this->organizationAccount->id,
            'kyc_verification_id' => (string) $organizationVerification->id,
            'document_type' => 'commercial_registration',
            'original_filename' => 'e2e-account-c-cr.pdf',
            'stored_path' => 'kyc/e2e-account-c-cr.pdf',
            'uploaded_by' => (string) $organizationOwner->id,
        ]);

        KycDocument::factory()->create([
            'account_id' => (string) $this->organizationAccount->id,
            'kyc_verification_id' => (string) $organizationVerification->id,
            'document_type' => 'tax_certificate',
            'original_filename' => 'e2e-account-c-tax.pdf',
            'stored_path' => 'kyc/e2e-account-c-tax.pdf',
            'uploaded_by' => (string) $organizationOwner->id,
        ]);

        Shipment::query()
            ->where('account_id', (string) $this->organizationAccount->id)
            ->where('status', Shipment::STATUS_KYC_BLOCKED)
            ->delete();

        Shipment::factory()->create([
            'account_id' => (string) $this->organizationAccount->id,
            'user_id' => (string) $organizationOwner->id,
            'created_by' => (string) $organizationOwner->id,
            'status' => Shipment::STATUS_KYC_BLOCKED,
            'reference_number' => 'SHP-KYC-C-001',
        ]);

        Shipment::factory()->create([
            'account_id' => (string) $this->organizationAccount->id,
            'user_id' => (string) $organizationOwner->id,
            'created_by' => (string) $organizationOwner->id,
            'status' => Shipment::STATUS_KYC_BLOCKED,
            'reference_number' => 'SHP-KYC-C-002',
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        if (Schema::hasTable('verification_restrictions')) {
            VerificationRestriction::query()->updateOrCreate(
                ['restriction_key' => 'kyc_pending_international_shipping'],
                [
                    'name' => 'تعليق الشحن الدولي',
                    'description' => 'لا يتاح الشحن الدولي حتى اكتمال المراجعة.',
                    'applies_to_statuses' => [KycVerification::STATUS_PENDING],
                    'restriction_type' => VerificationRestriction::TYPE_BLOCK_FEATURE,
                    'feature_key' => 'international_shipping',
                    'is_active' => true,
                ]
            );
        }

        /** @var AuditService $audit */
        $audit = app(AuditService::class);

        $audit->info(
            (string) $this->individualAccount->id,
            (string) $individualOwner->id,
            'kyc.document_uploaded',
            AuditLog::CATEGORY_KYC,
            'KycVerification',
            (string) $individualVerification->id,
            null,
            null,
            ['document_type' => 'national_id']
        );

        $audit->info(
            (string) $this->secondPendingAccount->id,
            (string) $secondPendingOwner->id,
            'kyc.document_uploaded',
            AuditLog::CATEGORY_KYC,
            'KycVerification',
            (string) $secondPendingVerification->id,
            null,
            null,
            ['document_type' => 'passport']
        );

        $audit->warning(
            (string) $this->organizationAccount->id,
            (string) $organizationOwner->id,
            'kyc.rejected',
            AuditLog::CATEGORY_KYC,
            'KycVerification',
            (string) $organizationVerification->id,
            ['status' => 'pending'],
            ['status' => 'rejected'],
            ['reason' => 'commercial register mismatch']
        );

        $audit->info(
            (string) $this->approvedAccount->id,
            (string) $approvedOwner->id,
            'kyc.approved',
            AuditLog::CATEGORY_KYC,
            'KycVerification',
            (string) $this->approvedAccount->kycVerification?->id,
        );
    }

    private function userByEmail(string $email): User
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('email', $email)
            ->firstOrFail();
    }

    private function accountBySlug(string $slug): Account
    {
        return Account::query()
            ->withoutGlobalScopes()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    private function assertHasNavigationLink(TestResponse $response, string $routeName): void
    {
        $response->assertSee('href="' . route($routeName) . '"', false);
    }

    private function assertMissingNavigationLink(TestResponse $response, string $routeName): void
    {
        $response->assertDontSee('href="' . route($routeName) . '"', false);
    }

    private function assertForbiddenInternalSurface(TestResponse $response): void
    {
        $response->assertForbidden()
            ->assertSeeText('هذه الصفحة ليست ضمن دورك الحالي');
    }
}
