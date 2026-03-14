<?php

namespace Tests\Unit;

use App\Exceptions\BusinessException;
use App\Models\Account;
use App\Models\ContentDeclaration;
use App\Models\DgAuditLog;
use App\Models\DgMetadata;
use App\Models\Role;
use App\Models\User;
use App\Models\WaiverVersion;
use App\Services\DgComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit Tests — DG Module (FR-DG-001→009)
 * 40 tests covering all 9 dangerous goods requirements.
 */
class DgComplianceTest extends TestCase
{
    use RefreshDatabase;

    private DgComplianceService $service;
    private Account $account;
    private User $user;
    private WaiverVersion $waiver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(DgComplianceService::class);
        $this->account = Account::factory()->create();
        $role = Role::factory()->create(['account_id' => $this->account->id]);
        $this->user = $this->createUserWithRole((string) $this->account->id, (string) $role->id);
        $this->waiver = WaiverVersion::factory()->create();
    }

    // ═══════════════════════════════════════════════════════════
    // FR-DG-001: Mandatory Content Declaration Step (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_declaration(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-001', $this->user->id);
        $this->assertNotNull($decl);
        $this->assertEquals('SH-001', $decl->shipment_id);
        $this->assertEquals(ContentDeclaration::STATUS_PENDING, $decl->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_declaration_linked_to_shipment(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-002', $this->user->id);
        $this->assertEquals('SH-002', $decl->shipment_id);
        $this->assertEquals($this->account->id, $decl->account_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_declaration_records_ip_and_user_agent(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-003', $this->user->id, 'ar', '192.168.1.1', 'TestAgent');
        $this->assertEquals('192.168.1.1', $decl->ip_address);
        $this->assertEquals('TestAgent', $decl->user_agent);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_idempotent_declaration_creation(): void
    {
        $d1 = $this->service->createDeclaration($this->account->id, 'SH-004', $this->user->id);
        $d2 = $this->service->createDeclaration($this->account->id, 'SH-004', $this->user->id);
        $this->assertEquals($d1->id, $d2->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_declaration_has_locale(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-005', $this->user->id, 'en');
        $this->assertEquals('en', $decl->locale);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-DG-002: Mandatory DG Yes/No Question (4 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_set_dg_flag_no(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-010', $this->user->id);
        $updated = $this->service->setDgFlag($decl->id, false, $this->user->id);
        $this->assertFalse($updated->contains_dangerous_goods);
        $this->assertEquals(ContentDeclaration::STATUS_PENDING, $updated->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_set_dg_flag_yes(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-011', $this->user->id);
        $updated = $this->service->setDgFlag($decl->id, true, $this->user->id);
        $this->assertTrue($updated->contains_dangerous_goods);
        $this->assertEquals(ContentDeclaration::STATUS_HOLD_DG, $updated->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_dg_flag_creates_audit_log(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-012', $this->user->id);
        $this->service->setDgFlag($decl->id, true, $this->user->id, '10.0.0.1');

        $logs = DgAuditLog::forDeclaration($decl->id)->where('action', DgAuditLog::ACTION_DG_FLAG_SET)->get();
        $this->assertCount(1, $logs);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_dg_flag_change_tracked(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-013', $this->user->id);
        $this->service->setDgFlag($decl->id, true, $this->user->id);
        $this->service->setDgFlag($decl->id, false, $this->user->id);

        $logs = DgAuditLog::forDeclaration($decl->id)->where('action', DgAuditLog::ACTION_DG_FLAG_SET)->get();
        $this->assertCount(2, $logs);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-DG-003: Block Label Issuance when DG=Yes (4 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_dg_yes_blocks_shipment(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-020', $this->user->id);
        $updated = $this->service->setDgFlag($decl->id, true, $this->user->id);
        $this->assertTrue($updated->isBlocked());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_dg_yes_hold_reason(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-021', $this->user->id);
        $updated = $this->service->setDgFlag($decl->id, true, $this->user->id);
        $this->assertNotNull($updated->hold_reason);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_hold_info_returns_alternatives(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-022', $this->user->id);
        $this->service->setDgFlag($decl->id, true, $this->user->id);

        $info = $this->service->getHoldInfo($decl->id);
        $this->assertTrue($info['is_blocked']);
        $this->assertNotEmpty($info['alternatives']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_hold_creates_audit_entry(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-023', $this->user->id);
        $this->service->setDgFlag($decl->id, true, $this->user->id);

        $logs = DgAuditLog::forDeclaration($decl->id)->where('action', DgAuditLog::ACTION_HOLD_APPLIED)->get();
        $this->assertCount(1, $logs);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-DG-004: Liability Waiver when DG=No (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_accept_waiver(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-030', $this->user->id);
        $this->service->setDgFlag($decl->id, false, $this->user->id);

        $updated = $this->service->acceptWaiver($decl->id, $this->user->id);
        $this->assertTrue($updated->waiver_accepted);
        $this->assertEquals(ContentDeclaration::STATUS_COMPLETED, $updated->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_waiver_cannot_accept_when_dg_yes(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-031', $this->user->id);
        $this->service->setDgFlag($decl->id, true, $this->user->id);

        try {
            $this->service->acceptWaiver($decl->id, $this->user->id);
            $this->fail('Expected BusinessException for dangerous goods hold.');
        } catch (BusinessException $e) {
            $this->assertEquals('ERR_DG_HOLD_REQUIRED', $e->getErrorCode());
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_waiver_stores_version_info(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-032', $this->user->id);
        $this->service->setDgFlag($decl->id, false, $this->user->id);
        $updated = $this->service->acceptWaiver($decl->id, $this->user->id);

        $this->assertEquals($this->waiver->id, $updated->waiver_version_id);
        $this->assertEquals($this->waiver->waiver_hash, $updated->waiver_hash_snapshot);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_waiver_stores_text_snapshot(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-033', $this->user->id);
        $this->service->setDgFlag($decl->id, false, $this->user->id);
        $updated = $this->service->acceptWaiver($decl->id, $this->user->id);

        $this->assertNotNull($updated->waiver_text_snapshot);
        $this->assertEquals($this->waiver->waiver_text, $updated->waiver_text_snapshot);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_waiver_accepted_at_timestamp(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-034', $this->user->id);
        $this->service->setDgFlag($decl->id, false, $this->user->id);
        $updated = $this->service->acceptWaiver($decl->id, $this->user->id);

        $this->assertNotNull($updated->waiver_accepted_at);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-DG-005: Append-only Audit Log (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_creation_creates_audit_entry(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-040', $this->user->id);
        $logs = DgAuditLog::forDeclaration($decl->id)->get();
        $this->assertGreaterThanOrEqual(1, $logs->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_waiver_acceptance_creates_audit(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-041', $this->user->id);
        $this->service->setDgFlag($decl->id, false, $this->user->id);
        $this->service->acceptWaiver($decl->id, $this->user->id);

        $logs = DgAuditLog::forDeclaration($decl->id)->where('action', DgAuditLog::ACTION_WAIVER_ACCEPTED)->get();
        $this->assertCount(1, $logs);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_audit_log_retrieval(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-042', $this->user->id);
        $this->service->setDgFlag($decl->id, false, $this->user->id);

        $log = $this->service->getAuditLog($decl->id);
        $this->assertGreaterThanOrEqual(2, $log->total()); // creation + dg_flag
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_audit_export(): void
    {
        $this->service->createDeclaration($this->account->id, 'SH-043', $this->user->id);
        $export = $this->service->exportAuditLog($this->account->id);
        $this->assertIsArray($export);
        $this->assertNotEmpty($export);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_shipment_audit_log(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-044', $this->user->id);
        $log = $this->service->getShipmentAuditLog('SH-044');
        $this->assertGreaterThanOrEqual(1, $log->total());
    }

    // ═══════════════════════════════════════════════════════════
    // FR-DG-006: Waiver Versioning (4 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_publish_waiver_version(): void
    {
        $v = $this->service->publishWaiverVersion('2.0', 'en', 'New waiver text', $this->user->id);
        $this->assertEquals('2.0', $v->version);
        $this->assertTrue($v->is_active);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_new_version_deactivates_old(): void
    {
        $this->service->publishWaiverVersion('2.0', 'ar', 'Updated text');
        $oldWaiver = WaiverVersion::find($this->waiver->id);
        $oldWaiver->refresh();
        $this->assertFalse($oldWaiver->is_active);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_get_active_waiver(): void
    {
        $active = $this->service->getActiveWaiver('ar');
        $this->assertNotNull($active);
        $this->assertTrue($active->is_active);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_list_waiver_versions(): void
    {
        $this->service->publishWaiverVersion('2.0', 'ar', 'V2 text');
        $versions = $this->service->listWaiverVersions('ar');
        $this->assertCount(2, $versions);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-DG-007: Block Carrier Call Without Declaration (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_validate_completed_declaration(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-060', $this->user->id);
        $this->service->setDgFlag($decl->id, false, $this->user->id);
        $this->service->acceptWaiver($decl->id, $this->user->id);

        $valid = $this->service->validateForIssuance('SH-060', $this->account->id);
        $this->assertTrue($valid->isReadyForIssuance());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_validate_no_declaration_throws(): void
    {
        try {
            $this->service->validateForIssuance('SH-NONE', $this->account->id);
            $this->fail('Expected BusinessException for missing declaration.');
        } catch (BusinessException $e) {
            $this->assertEquals('ERR_DG_DECLARATION_REQUIRED', $e->getErrorCode());
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_validate_dg_hold_throws(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-061', $this->user->id);
        $this->service->setDgFlag($decl->id, true, $this->user->id);

        try {
            $this->service->validateForIssuance('SH-061', $this->account->id);
            $this->fail('Expected BusinessException for DG hold.');
        } catch (BusinessException $e) {
            $this->assertEquals('ERR_DG_HOLD_REQUIRED', $e->getErrorCode());
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_validate_incomplete_declaration_throws(): void
    {
        $this->service->createDeclaration($this->account->id, 'SH-062', $this->user->id);

        try {
            $this->service->validateForIssuance('SH-062', $this->account->id);
            $this->fail('Expected BusinessException for incomplete declaration.');
        } catch (BusinessException $e) {
            $this->assertEquals('ERR_DG_DECLARATION_INCOMPLETE', $e->getErrorCode());
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_validate_no_waiver_throws(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-063', $this->user->id);
        $this->service->setDgFlag($decl->id, false, $this->user->id);
        // Don't accept waiver

        try {
            $this->service->validateForIssuance('SH-063', $this->account->id);
            $this->fail('Expected BusinessException for missing disclaimer acceptance.');
        } catch (BusinessException $e) {
            $this->assertEquals('ERR_DG_DISCLAIMER_REQUIRED', $e->getErrorCode());
        }
    }

    // ═══════════════════════════════════════════════════════════
    // FR-DG-008: RBAC Access Control (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_summary_view_no_sensitive_data(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-070', $this->user->id, 'ar', '192.168.1.1');
        $summary = $this->service->getDeclaration($decl->id, false);

        $this->assertArrayNotHasKey('ip_address', $summary);
        $this->assertArrayNotHasKey('user_agent', $summary);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_detail_view_includes_sensitive_data(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-071', $this->user->id, 'ar', '10.0.0.1');
        $detail = $this->service->getDeclaration($decl->id, true, $this->user->id);

        $this->assertArrayHasKey('ip_address', $detail);
        $this->assertArrayHasKey('declared_by', $detail);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_view_creates_audit_log(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-072', $this->user->id);
        $this->service->getDeclaration($decl->id, true, $this->user->id);

        $logs = DgAuditLog::forDeclaration($decl->id)->where('action', DgAuditLog::ACTION_VIEWED)->get();
        $this->assertCount(1, $logs);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-DG-009: Optional DG Metadata (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_save_dg_metadata(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-080', $this->user->id);
        $this->service->setDgFlag($decl->id, true, $this->user->id);

        $meta = $this->service->saveDgMetadata($decl->id, [
            'un_number' => 'UN3481',
            'dg_class'  => '9',
            'quantity'  => 2.5,
        ], $this->user->id);

        $this->assertEquals('UN3481', $meta->un_number);
        $this->assertEquals('9', $meta->dg_class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_dg_metadata_linked_to_declaration(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-081', $this->user->id);
        $this->service->setDgFlag($decl->id, true, $this->user->id);
        $this->service->saveDgMetadata($decl->id, ['un_number' => 'UN1203'], $this->user->id);

        $decl->refresh();
        $this->assertNotNull($decl->dgMetadata);
        $this->assertEquals('UN1203', $decl->dgMetadata->un_number);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_valid_un_number_format(): void
    {
        $meta = new DgMetadata(['un_number' => 'UN3481']);
        $this->assertTrue($meta->isValidUnNumber());

        $meta2 = new DgMetadata(['un_number' => 'INVALID']);
        $this->assertFalse($meta2->isValidUnNumber());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_valid_dg_class(): void
    {
        $meta = new DgMetadata(['dg_class' => '9']);
        $this->assertTrue($meta->isValidClass());

        $meta2 = new DgMetadata(['dg_class' => '99']);
        $this->assertFalse($meta2->isValidClass());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_metadata_creates_audit_log(): void
    {
        $decl = $this->service->createDeclaration($this->account->id, 'SH-084', $this->user->id);
        $this->service->saveDgMetadata($decl->id, ['un_number' => 'UN1234'], $this->user->id);

        $logs = DgAuditLog::forDeclaration($decl->id)->where('action', DgAuditLog::ACTION_DG_METADATA_SAVED)->get();
        $this->assertCount(1, $logs);
    }
}
