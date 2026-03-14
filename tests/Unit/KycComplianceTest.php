<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\KycAuditLog;
use App\Models\Role;
use App\Models\User;
use App\Models\VerificationCase;
use App\Models\VerificationDocument;
use App\Models\VerificationRestriction;
use App\Services\KycComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit Tests — KYC Module (FR-KYC-001→008)
 * 35 tests covering all 8 requirements.
 */
class KycComplianceTest extends TestCase
{
    use RefreshDatabase;

    private KycComplianceService $service;
    private Account $account;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(KycComplianceService::class);
        $this->account = Account::factory()->create();
        $role = Role::factory()->create(['account_id' => $this->account->id]);
        $this->user = $this->createUserWithRole((string) $this->account->id, (string) $role->id);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-KYC-001: Default Unverified (4 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_new_case_defaults_to_unverified(): void
    {
        $case = $this->service->createCase($this->account->id, 'individual');
        $this->assertEquals(VerificationCase::STATUS_UNVERIFIED, $case->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_case_has_case_number(): void
    {
        $case = $this->service->createCase($this->account->id, 'individual');
        $this->assertStringStartsWith('KYC-', $case->case_number);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_individual_requires_national_id(): void
    {
        $case = $this->service->createCase($this->account->id, 'individual');
        $this->assertContains('national_id', $case->required_documents);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_organization_requires_business_docs(): void
    {
        $case = $this->service->createCase($this->account->id, 'organization');
        $this->assertContains('commercial_register', $case->required_documents);
        $this->assertContains('tax_certificate', $case->required_documents);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-KYC-002: Upload Documents (4 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_upload_document(): void
    {
        $case = $this->service->createCase($this->account->id, 'individual');
        $doc = $this->service->uploadDocument($case->id, [
            'document_type' => 'national_id', 'original_filename' => 'id.pdf',
            'stored_path' => '/secure/test.enc', 'mime_type' => 'application/pdf', 'file_size' => 500000,
        ], $this->user->id);

        $this->assertEquals('national_id', $doc->document_type);
        $this->assertEquals('uploaded', $doc->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_upload_creates_audit_log(): void
    {
        $case = $this->service->createCase($this->account->id, 'individual');
        $this->service->uploadDocument($case->id, [
            'document_type' => 'national_id', 'original_filename' => 'id.pdf',
            'stored_path' => '/secure/test.enc', 'mime_type' => 'application/pdf', 'file_size' => 500000,
        ], $this->user->id);

        $this->assertDatabaseHas('kyc_audit_logs', ['case_id' => $case->id, 'action' => KycAuditLog::ACTION_DOCUMENT_UPLOAD]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_cannot_upload_to_verified_case(): void
    {
        $case = VerificationCase::factory()->verified()->create(['account_id' => $this->account->id]);
        $this->expectException(\RuntimeException::class);
        $this->service->uploadDocument($case->id, [
            'document_type' => 'national_id', 'original_filename' => 'id.pdf',
            'stored_path' => '/s/t.enc', 'mime_type' => 'application/pdf', 'file_size' => 100,
        ], $this->user->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_document_is_encrypted_by_default(): void
    {
        $case = $this->service->createCase($this->account->id, 'individual');
        $doc = $this->service->uploadDocument($case->id, [
            'document_type' => 'national_id', 'original_filename' => 'id.pdf',
            'stored_path' => '/s/t.enc', 'mime_type' => 'application/pdf', 'file_size' => 100,
        ], $this->user->id);

        $this->assertTrue($doc->is_encrypted);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-KYC-003: Status Management (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_submit_changes_status(): void
    {
        $case = $this->service->createCase($this->account->id, 'individual');
        VerificationDocument::factory()->create(['case_id' => $case->id, 'document_type' => 'national_id']);

        $submitted = $this->service->submitForReview($case->id, $this->user->id);
        $this->assertEquals(VerificationCase::STATUS_PENDING_REVIEW, $submitted->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_submit_fails_without_required_docs(): void
    {
        $case = $this->service->createCase($this->account->id, 'individual');

        $this->expectException(\RuntimeException::class);
        $this->service->submitForReview($case->id, $this->user->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_resubmit_after_rejection(): void
    {
        $case = VerificationCase::factory()->rejected()->create(['account_id' => $this->account->id, 'required_documents' => ['national_id']]);
        VerificationDocument::factory()->create(['case_id' => $case->id, 'document_type' => 'national_id']);

        $resubmitted = $this->service->submitForReview($case->id, $this->user->id);
        $this->assertEquals(VerificationCase::STATUS_PENDING_REVIEW, $resubmitted->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_cannot_submit_verified_case(): void
    {
        $case = VerificationCase::factory()->verified()->create(['account_id' => $this->account->id]);

        $this->expectException(\RuntimeException::class);
        $this->service->submitForReview($case->id, $this->user->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_get_verification_status(): void
    {
        $this->service->createCase($this->account->id, 'individual');
        $status = $this->service->getVerificationStatus($this->account->id);
        $this->assertEquals('unverified', $status['status']);
        $this->assertFalse($status['is_verified']);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-KYC-004: Restrictions (4 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_restriction(): void
    {
        $r = $this->service->createRestriction([
            'name' => 'Block Intl Shipping', 'restriction_key' => 'intl_shipping',
            'applies_to_statuses' => ['unverified', 'rejected'],
            'restriction_type' => 'block_feature', 'feature_key' => 'international_shipping',
        ]);
        $this->assertTrue($r->appliesTo('unverified'));
        $this->assertFalse($r->appliesTo('verified'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_check_blocked_feature(): void
    {
        $this->service->createRestriction([
            'name' => 'Block Intl', 'restriction_key' => 'blk_intl',
            'applies_to_statuses' => ['unverified'],
            'restriction_type' => 'block_feature', 'feature_key' => 'international_shipping',
        ]);
        $this->service->createCase($this->account->id, 'individual');

        $result = $this->service->checkRestriction($this->account->id, 'international_shipping');
        $this->assertFalse($result['allowed']);
        $this->assertEquals('KYC_REQUIRED', $result['reason']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_verified_account_no_restrictions(): void
    {
        $this->service->createRestriction([
            'name' => 'Block Intl', 'restriction_key' => 'blk_intl2',
            'applies_to_statuses' => ['unverified'],
            'restriction_type' => 'block_feature', 'feature_key' => 'international_shipping',
        ]);
        VerificationCase::factory()->verified()->create(['account_id' => $this->account->id]);

        $result = $this->service->checkRestriction($this->account->id, 'international_shipping');
        $this->assertTrue($result['allowed']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_quota_restriction(): void
    {
        $this->service->createRestriction([
            'name' => 'Limit Shipments', 'restriction_key' => 'max_ship',
            'applies_to_statuses' => ['unverified'],
            'restriction_type' => 'quota_limit', 'feature_key' => 'shipments', 'quota_value' => 10,
        ]);
        $this->service->createCase($this->account->id, 'individual');

        $result = $this->service->checkRestriction($this->account->id, 'shipments');
        $this->assertTrue($result['allowed']);
        $this->assertEquals(10, $result['quota_limit']);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-KYC-005: Admin Review (4 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_approve_case(): void
    {
        $case = VerificationCase::factory()->pending()->create(['account_id' => $this->account->id]);
        $reviewed = $this->service->reviewCase($case->id, $this->user->id, 'approved');
        $this->assertEquals(VerificationCase::STATUS_VERIFIED, $reviewed->status);
        $this->assertNotNull($reviewed->verified_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_reject_case_with_reason(): void
    {
        $case = VerificationCase::factory()->pending()->create(['account_id' => $this->account->id]);
        $reviewed = $this->service->reviewCase($case->id, $this->user->id, 'rejected', 'Documents blurry');
        $this->assertEquals(VerificationCase::STATUS_REJECTED, $reviewed->status);
        $this->assertEquals('Documents blurry', $reviewed->rejection_reason);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_review_creates_record(): void
    {
        $case = VerificationCase::factory()->pending()->create(['account_id' => $this->account->id]);
        $this->service->reviewCase($case->id, $this->user->id, 'approved');
        $this->assertDatabaseHas('verification_reviews', ['case_id' => $case->id, 'decision' => 'approved']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_list_pending_cases(): void
    {
        VerificationCase::factory()->pending()->count(3)->create(['account_id' => $this->account->id]);
        VerificationCase::factory()->verified()->create(['account_id' => $this->account->id]);

        $pending = $this->service->listPendingCases();
        $this->assertEquals(3, $pending->total());
    }

    // ═══════════════════════════════════════════════════════════
    // FR-KYC-006: Status Display (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_unverified_shows_banner(): void
    {
        $this->service->createCase($this->account->id, 'individual');
        $display = $this->service->getStatusDisplay($this->account->id);
        $this->assertNotNull($display['banner_message']);
        $this->assertStringContainsString('غير موثق', $display['banner_message']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_verified_no_banner(): void
    {
        VerificationCase::factory()->verified()->create(['account_id' => $this->account->id]);
        $display = $this->service->getStatusDisplay($this->account->id);
        $this->assertNull($display['banner_message']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_rejected_shows_reason(): void
    {
        VerificationCase::factory()->rejected()->create(['account_id' => $this->account->id]);
        $display = $this->service->getStatusDisplay($this->account->id);
        $this->assertStringContainsString('رفض', $display['banner_message']);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-KYC-007: Secure Document Access (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_temporary_download_url(): void
    {
        $doc = VerificationDocument::factory()->create();
        $url = $this->service->getDocumentDownloadUrl($doc->id, $this->user->id);
        $this->assertStringContainsString('download', $url);
        $this->assertStringContainsString('expires', $url);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_download_creates_audit(): void
    {
        $doc = VerificationDocument::factory()->create();
        $this->service->getDocumentDownloadUrl($doc->id, $this->user->id);
        $this->assertDatabaseHas('kyc_audit_logs', ['document_id' => $doc->id, 'action' => KycAuditLog::ACTION_DOCUMENT_DOWNLOAD]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_document_hash_stored(): void
    {
        $doc = VerificationDocument::factory()->create();
        $this->assertNotNull($doc->file_hash);
        $this->assertEquals(64, strlen($doc->file_hash)); // SHA-256
    }

    // ═══════════════════════════════════════════════════════════
    // FR-KYC-008: Audit Log (4 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_decision_logged(): void
    {
        $case = VerificationCase::factory()->pending()->create(['account_id' => $this->account->id]);
        $this->service->reviewCase($case->id, $this->user->id, 'approved');
        $this->assertDatabaseHas('kyc_audit_logs', ['case_id' => $case->id, 'action' => KycAuditLog::ACTION_DECISION]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_get_audit_log(): void
    {
        $case = VerificationCase::factory()->create(['account_id' => $this->account->id]);
        $this->service->logAction('test_action', $this->user->id, $case->id);

        $logs = $this->service->getAuditLog($case->id);
        $this->assertCount(1, $logs);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_export_audit_log(): void
    {
        $case = VerificationCase::factory()->create(['account_id' => $this->account->id]);
        $this->service->logAction('test1', $this->user->id, $case->id);
        $this->service->logAction('test2', $this->user->id, $case->id);

        $export = $this->service->exportAuditLog($case->id);
        $this->assertCount(2, $export);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_audit_log_no_document_content(): void
    {
        $case = VerificationCase::factory()->create(['account_id' => $this->account->id]);
        $doc = VerificationDocument::factory()->create(['case_id' => $case->id]);

        $this->service->logAction(KycAuditLog::ACTION_DOCUMENT_DOWNLOAD, $this->user->id, $case->id, $doc->id);

        $log = KycAuditLog::where('case_id', $case->id)->first();
        // Audit log contains metadata but NOT document content
        $this->assertNull($log->metadata);
    }
}
