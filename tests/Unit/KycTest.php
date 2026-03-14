<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Account;
use App\Models\User;
use App\Models\Role;
use App\Models\KycVerification;
use App\Models\KycDocument;
use App\Models\AuditLog;
use App\Services\KycService;
use App\Services\AuditService;
use App\Exceptions\BusinessException;
use Tests\Concerns\InteractsWithStrictRbac;

/**
 * FR-IAM-014 + FR-IAM-016: KYC Status & Document Access — Unit Tests (25 tests)
 */
class KycTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithStrictRbac;

    protected KycService $kycService;
    protected Account $account;
    protected User $owner;
    protected User $member;
    protected User $kycManager;

    protected function setUp(): void
    {
        parent::setUp();

        AuditService::resetRequestId();
        $this->kycService = app(KycService::class);

        $this->account = Account::factory()->create(['kyc_status' => 'unverified']);
        $this->owner = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner'   => true,
        ]);

        $this->member = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner'   => false,
        ]);

        $kycRole = $this->createTenantRoleWithPermissions(
            (string) $this->account->id,
            ['kyc.manage', 'kyc.documents', 'kyc.read'],
            'kyc_manager'
        );
        $this->kycManager = $this->createUserWithRole((string) $this->account->id, (string) $kycRole->id, [
            'is_owner' => false,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-IAM-014: KYC Status & Capabilities
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_unverified_status_without_kyc_record()
    {
        $status = $this->kycService->getKycStatus($this->account->id);

        $this->assertEquals('unverified', $status['status']);
        $this->assertNotEmpty($status['capabilities']);
        $this->assertFalse($status['capabilities']['can_ship_international']);
        $this->assertNotEmpty($status['required_documents']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_pending_capabilities()
    {
        KycVerification::factory()->pending()->create([
            'account_id' => $this->account->id,
        ]);

        $status = $this->kycService->getKycStatus($this->account->id);

        $this->assertEquals('pending', $status['status']);
        $this->assertTrue($status['capabilities']['can_ship_domestic']);
        $this->assertFalse($status['capabilities']['can_ship_international']);
        $this->assertEquals(50, $status['capabilities']['shipping_limit']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_approved_capabilities()
    {
        KycVerification::factory()->approved()->create([
            'account_id' => $this->account->id,
        ]);

        $status = $this->kycService->getKycStatus($this->account->id);

        $this->assertEquals('approved', $status['status']);
        $this->assertTrue($status['capabilities']['can_ship_international']);
        $this->assertTrue($status['capabilities']['can_use_cod']);
        $this->assertNull($status['capabilities']['shipping_limit']); // Unlimited
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_rejected_capabilities_with_reason()
    {
        KycVerification::factory()->rejected()->create([
            'account_id' => $this->account->id,
        ]);

        $status = $this->kycService->getKycStatus($this->account->id);

        $this->assertEquals('rejected', $status['status']);
        $this->assertNotNull($status['rejection_reason']);
        $this->assertEquals(10, $status['capabilities']['shipping_limit']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_status_display_info()
    {
        $kyc = KycVerification::factory()->approved()->create([
            'account_id' => $this->account->id,
        ]);

        $status = $this->kycService->getKycStatus($this->account->id);
        $display = $status['status_display'];

        $this->assertEquals('مقبول', $display['label']);
        $this->assertEquals('green', $display['color']);
        $this->assertEquals('shield-check', $display['icon']);
    }

    // ─── Approve ─────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_approve_pending_kyc()
    {
        $kyc = KycVerification::factory()->pending()->create([
            'account_id' => $this->account->id,
        ]);

        $result = $this->kycService->approveKyc(
            $this->account->id, $this->owner, 'Documents verified'
        );

        $this->assertEquals('approved', $result->status);
        $this->assertNotNull($result->reviewed_at);
        $this->assertNotNull($result->expires_at);
        $this->assertEquals('approved', $this->account->fresh()->kyc_status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function approve_creates_audit_log()
    {
        KycVerification::factory()->pending()->create([
            'account_id' => $this->account->id,
        ]);

        $this->kycService->approveKyc($this->account->id, $this->owner);

        $log = AuditLog::withoutGlobalScopes()
            ->where('action', 'kyc.approved')
            ->where('account_id', $this->account->id)
            ->first();

        $this->assertNotNull($log);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_approve_non_pending_kyc()
    {
        KycVerification::factory()->approved()->create([
            'account_id' => $this->account->id,
        ]);

        $this->expectException(BusinessException::class);
        $this->kycService->approveKyc($this->account->id, $this->owner);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function member_without_permission_cannot_approve()
    {
        KycVerification::factory()->pending()->create([
            'account_id' => $this->account->id,
        ]);

        $this->expectException(BusinessException::class);
        $this->kycService->approveKyc($this->account->id, $this->member);
    }

    // ─── Reject ──────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_reject_pending_kyc()
    {
        KycVerification::factory()->pending()->create([
            'account_id' => $this->account->id,
        ]);

        $result = $this->kycService->rejectKyc(
            $this->account->id, $this->owner, 'صور غير واضحة'
        );

        $this->assertEquals('rejected', $result->status);
        $this->assertEquals('صور غير واضحة', $result->rejection_reason);
        $this->assertEquals('rejected', $this->account->fresh()->kyc_status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_reject_non_pending_kyc()
    {
        KycVerification::factory()->approved()->create([
            'account_id' => $this->account->id,
        ]);

        $this->expectException(BusinessException::class);
        $this->kycService->rejectKyc($this->account->id, $this->owner, 'test');
    }

    // ─── Resubmit ────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_resubmit_after_rejection()
    {
        KycVerification::factory()->rejected()->create([
            'account_id' => $this->account->id,
        ]);

        $result = $this->kycService->resubmitKyc(
            $this->account->id,
            ['national_id' => 'new_path.pdf'],
            $this->owner
        );

        $this->assertEquals('pending', $result->status);
        $this->assertNull($result->rejection_reason);
        $this->assertEquals('pending', $this->account->fresh()->kyc_status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_resubmit_after_expiry()
    {
        KycVerification::factory()->expired()->create([
            'account_id' => $this->account->id,
        ]);

        $result = $this->kycService->resubmitKyc(
            $this->account->id,
            ['national_id' => 'path.pdf'],
            $this->owner
        );

        $this->assertEquals('pending', $result->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_resubmit_pending_kyc()
    {
        KycVerification::factory()->pending()->create([
            'account_id' => $this->account->id,
        ]);

        $this->expectException(BusinessException::class);
        $this->kycService->resubmitKyc(
            $this->account->id, ['doc' => 'path'], $this->owner
        );
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-IAM-016: Document Access Control
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_upload_document()
    {
        $kyc = KycVerification::factory()->pending()->create([
            'account_id' => $this->account->id,
        ]);

        $doc = $this->kycService->uploadDocument(
            $this->account->id, $kyc->id,
            'national_id', 'id_card.pdf', 'kyc/uploads/id.pdf',
            'application/pdf', 102400, $this->owner
        );

        $this->assertNotNull($doc->id);
        $this->assertEquals('national_id', $doc->document_type);
        $this->assertTrue($doc->is_sensitive);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function upload_creates_audit_log()
    {
        $kyc = KycVerification::factory()->pending()->create([
            'account_id' => $this->account->id,
        ]);

        $this->kycService->uploadDocument(
            $this->account->id, $kyc->id,
            'national_id', 'id.pdf', 'path/id.pdf',
            'application/pdf', 50000, $this->owner
        );

        $log = AuditLog::withoutGlobalScopes()
            ->where('action', 'kyc.document_uploaded')
            ->first();
        $this->assertNotNull($log);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_list_documents()
    {
        $kyc = KycVerification::factory()->pending()->create([
            'account_id' => $this->account->id,
        ]);

        KycDocument::factory()->count(3)->create([
            'account_id'          => $this->account->id,
            'kyc_verification_id' => $kyc->id,
            'uploaded_by'         => $this->owner->id,
        ]);

        $docs = $this->kycService->listDocuments($this->account->id, $this->owner);

        $this->assertCount(3, $docs);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function member_without_permission_cannot_list_documents()
    {
        KycVerification::factory()->pending()->create([
            'account_id' => $this->account->id,
        ]);

        $this->expectException(BusinessException::class);
        $this->kycService->listDocuments($this->account->id, $this->member);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function document_access_denied_is_logged()
    {
        KycVerification::factory()->pending()->create([
            'account_id' => $this->account->id,
        ]);

        try {
            $this->kycService->listDocuments($this->account->id, $this->member);
        } catch (BusinessException $e) {
            // Expected
        }

        $log = AuditLog::withoutGlobalScopes()
            ->where('action', 'kyc.document_access_denied')
            ->first();
        $this->assertNotNull($log);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_get_download_url()
    {
        $kyc = KycVerification::factory()->pending()->create([
            'account_id' => $this->account->id,
        ]);
        $doc = KycDocument::factory()->create([
            'account_id'          => $this->account->id,
            'kyc_verification_id' => $kyc->id,
            'uploaded_by'         => $this->owner->id,
        ]);

        $result = $this->kycService->getDocumentDownloadUrl(
            $this->account->id, $doc->id, $this->owner
        );

        $this->assertArrayHasKey('download_url', $result);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertEquals(15, $result['ttl_minutes']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function document_access_is_logged()
    {
        $kyc = KycVerification::factory()->pending()->create([
            'account_id' => $this->account->id,
        ]);
        $doc = KycDocument::factory()->create([
            'account_id'          => $this->account->id,
            'kyc_verification_id' => $kyc->id,
            'uploaded_by'         => $this->owner->id,
        ]);

        $this->kycService->getDocumentDownloadUrl(
            $this->account->id, $doc->id, $this->owner
        );

        $log = AuditLog::withoutGlobalScopes()
            ->where('action', 'kyc.document_accessed')
            ->first();
        $this->assertNotNull($log);
        $this->assertEquals($doc->id, $log->entity_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_purge_document()
    {
        $kyc = KycVerification::factory()->pending()->create([
            'account_id' => $this->account->id,
        ]);
        $doc = KycDocument::factory()->create([
            'account_id'          => $this->account->id,
            'kyc_verification_id' => $kyc->id,
            'uploaded_by'         => $this->owner->id,
        ]);

        $result = $this->kycService->purgeDocument(
            $this->account->id, $doc->id, $this->owner
        );

        $this->assertTrue($result->is_purged);
        $this->assertEquals('[PURGED]', $result->stored_path);
        $this->assertNotNull($result->purged_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_purge_already_purged_document()
    {
        $kyc = KycVerification::factory()->pending()->create([
            'account_id' => $this->account->id,
        ]);
        $doc = KycDocument::factory()->purged()->create([
            'account_id'          => $this->account->id,
            'kyc_verification_id' => $kyc->id,
            'uploaded_by'         => $this->owner->id,
        ]);

        $this->expectException(BusinessException::class);
        $this->kycService->purgeDocument($this->account->id, $doc->id, $this->owner);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function purged_documents_excluded_from_list()
    {
        $kyc = KycVerification::factory()->pending()->create([
            'account_id' => $this->account->id,
        ]);

        KycDocument::factory()->create([
            'account_id' => $this->account->id,
            'kyc_verification_id' => $kyc->id,
            'uploaded_by' => $this->owner->id,
        ]);
        KycDocument::factory()->purged()->create([
            'account_id' => $this->account->id,
            'kyc_verification_id' => $kyc->id,
            'uploaded_by' => $this->owner->id,
        ]);

        $docs = $this->kycService->listDocuments($this->account->id, $this->owner);
        $this->assertCount(1, $docs);
    }
}
