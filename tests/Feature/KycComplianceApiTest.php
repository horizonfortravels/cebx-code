<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Role;
use App\Models\User;
use App\Models\VerificationCase;
use App\Models\VerificationDocument;
use App\Models\VerificationRestriction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API Tests — KYC Module (FR-KYC-001→008)
 * 18 tests covering all endpoints.
 */
class KycComplianceApiTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = Account::factory()->create();
        $role = Role::factory()->create(['account_id' => $this->account->id]);
        $this->user = $this->createUserWithRole((string) $this->account->id, (string) $role->id);
    }

    // FR-KYC-001
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_create_case(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/kyc/cases', ['account_type' => 'individual']);
        $response->assertStatus(201)->assertJsonPath('data.status', 'unverified');
    }

    // FR-KYC-001
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_get_case(): void
    {
        $case = VerificationCase::factory()->create(['account_id' => $this->account->id]);
        $response = $this->actingAs($this->user)->getJson("/api/v1/kyc/cases/{$case->id}");
        $response->assertOk()->assertJsonPath('data.id', $case->id);
    }

    // FR-KYC-001
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_get_status(): void
    {
        VerificationCase::factory()->create(['account_id' => $this->account->id]);
        $response = $this->actingAs($this->user)->getJson('/api/v1/kyc/verification-status');
        $response->assertOk()->assertJsonStructure(['data' => ['status', 'is_verified']]);
    }

    // FR-KYC-002
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_upload_document(): void
    {
        $case = VerificationCase::factory()->create(['account_id' => $this->account->id]);
        $response = $this->actingAs($this->user)->postJson("/api/v1/kyc/cases/{$case->id}/documents", [
            'document_type' => 'national_id', 'original_filename' => 'id.pdf',
            'stored_path' => '/secure/test.enc', 'mime_type' => 'application/pdf', 'file_size' => 500000,
        ]);
        $response->assertStatus(201)->assertJsonPath('data.document_type', 'national_id');
    }

    // FR-KYC-003
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_submit_for_review(): void
    {
        $case = VerificationCase::factory()->create(['account_id' => $this->account->id, 'required_documents' => ['national_id']]);
        VerificationDocument::factory()->create(['case_id' => $case->id, 'document_type' => 'national_id']);

        $response = $this->actingAs($this->user)->postJson("/api/v1/kyc/cases/{$case->id}/submit");
        $response->assertOk()->assertJsonPath('data.status', 'pending_review');
    }

    // FR-KYC-003
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_submit_fails_without_docs(): void
    {
        $case = VerificationCase::factory()->create(['account_id' => $this->account->id, 'required_documents' => ['national_id']]);
        $response = $this->actingAs($this->user)->postJson("/api/v1/kyc/cases/{$case->id}/submit");
        $response->assertStatus(500); // RuntimeException
    }

    // FR-KYC-004
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_check_restriction(): void
    {
        VerificationCase::factory()->create(['account_id' => $this->account->id]);
        $response = $this->actingAs($this->user)->postJson('/api/v1/kyc/restrictions/check', ['feature_key' => 'test_feature']);
        $response->assertOk()->assertJsonStructure(['data' => ['allowed']]);
    }

    // FR-KYC-004
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_list_restrictions(): void
    {
        VerificationRestriction::create([
            'name' => 'R1', 'restriction_key' => 'r1', 'applies_to_statuses' => ['unverified'],
            'restriction_type' => 'block_feature', 'feature_key' => 'test',
        ]);
        $response = $this->actingAs($this->user)->getJson('/api/v1/kyc/restrictions');
        $response->assertOk();
    }

    // FR-KYC-004
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_create_restriction(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/kyc/restrictions', [
            'name' => 'Block Export', 'restriction_key' => 'blk_export',
            'applies_to_statuses' => ['unverified'], 'restriction_type' => 'block_feature',
            'feature_key' => 'export',
        ]);
        $response->assertStatus(201);
    }

    // FR-KYC-005
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_list_pending(): void
    {
        VerificationCase::factory()->pending()->count(2)->create(['account_id' => $this->account->id]);
        $response = $this->actingAs($this->user)->getJson('/api/v1/kyc/pending');
        $response->assertOk();
    }

    // FR-KYC-005
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_approve_case(): void
    {
        $case = VerificationCase::factory()->pending()->create(['account_id' => $this->account->id]);
        $response = $this->actingAs($this->user)->postJson("/api/v1/kyc/cases/{$case->id}/review", ['decision' => 'approved']);
        $response->assertOk()->assertJsonPath('data.status', 'verified');
    }

    // FR-KYC-005
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_reject_case(): void
    {
        $case = VerificationCase::factory()->pending()->create(['account_id' => $this->account->id]);
        $response = $this->actingAs($this->user)->postJson("/api/v1/kyc/cases/{$case->id}/review", [
            'decision' => 'rejected', 'reason' => 'Blurry docs',
        ]);
        $response->assertOk()->assertJsonPath('data.status', 'rejected');
    }

    // FR-KYC-006
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_status_display(): void
    {
        VerificationCase::factory()->create(['account_id' => $this->account->id]);
        $response = $this->actingAs($this->user)->getJson('/api/v1/kyc/display');
        $response->assertOk()->assertJsonStructure(['data' => ['status', 'banner_message', 'restrictions']]);
    }

    // FR-KYC-007
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_document_download(): void
    {
        $case = VerificationCase::factory()->create(['account_id' => $this->account->id]);
        $doc = VerificationDocument::factory()->create(['case_id' => $case->id]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/kyc/documents/{$doc->id}/download");
        $response->assertOk()->assertJsonStructure(['data' => ['url']]);
    }

    // FR-KYC-008
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_audit_log(): void
    {
        $case = VerificationCase::factory()->pending()->create(['account_id' => $this->account->id]);
        $this->actingAs($this->user)->postJson("/api/v1/kyc/cases/{$case->id}/review", ['decision' => 'approved']);

        $response = $this->actingAs($this->user)->getJson("/api/v1/kyc/cases/{$case->id}/audit-log");
        $response->assertOk();
    }

    // FR-KYC-008
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_export_audit_log(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/kyc/audit-log/export');
        $response->assertOk();
    }

    // FR-KYC-001
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_create_org_case(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/kyc/cases', ['account_type' => 'organization']);
        $response->assertStatus(201)->assertJsonPath('data.account_type', 'organization');
    }

    // FR-KYC-005
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_needs_more_info(): void
    {
        $case = VerificationCase::factory()->pending()->create(['account_id' => $this->account->id]);
        $response = $this->actingAs($this->user)->postJson("/api/v1/kyc/cases/{$case->id}/review", [
            'decision' => 'needs_more_info', 'reason' => 'Need bank statement',
        ]);
        $response->assertOk()->assertJsonPath('data.status', 'rejected');
    }
}
