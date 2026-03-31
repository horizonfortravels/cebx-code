<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ContentDeclaration;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WaiverVersion;
use App\Services\DgComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API Tests — DG Module (FR-DG-001→009)
 * 20 tests covering all dangerous goods compliance endpoints.
 */
class DgComplianceApiTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;
    private User $user;
    private WaiverVersion $waiver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = Account::factory()->create();
        $this->user = $this->createUserWithRole((string) $this->account->id);
        $this->grantTenantPermissions($this->user, ['dg.read', 'dg.manage'], 'test_dg_api');
        $this->waiver = WaiverVersion::factory()->create();
    }

    // ── FR-DG-001: Create Declaration ────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_create_declaration(): void
    {
        $r = $this->actingAs($this->user)->postJson('/api/v1/dg/declarations', ['shipment_id' => 'SH-API-001']);
        $r->assertStatus(201)->assertJsonPath('data.shipment_id', 'SH-API-001');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_create_declaration_validation(): void
    {
        $r = $this->actingAs($this->user)->postJson('/api/v1/dg/declarations', []);
        $r->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_list_declarations(): void
    {
        $service = app(DgComplianceService::class);
        $service->createDeclaration($this->account->id, 'SH-LIST-1', $this->user->id);

        $r = $this->actingAs($this->user)->getJson('/api/v1/dg/declarations');
        $r->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_get_declaration(): void
    {
        $service = app(DgComplianceService::class);
        $decl = $service->createDeclaration($this->account->id, 'SH-GET-1', $this->user->id);

        $r = $this->actingAs($this->user)->getJson("/api/v1/dg/declarations/{$decl->id}");
        $r->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_get_shipment_declaration(): void
    {
        $shipment = Shipment::factory()->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id,
            'user_id' => $this->user->id,
        ]);

        $service = app(DgComplianceService::class);
        $service->createDeclaration($this->account->id, (string) $shipment->id, $this->user->id);

        $r = $this->actingAs($this->user)->getJson('/api/v1/dg/shipments/' . $shipment->id . '/declaration');
        $r->assertOk()->assertJsonPath('data.shipment_id', (string) $shipment->id);
    }

    // ── FR-DG-002: Set DG Flag ──────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_set_dg_flag_no(): void
    {
        $service = app(DgComplianceService::class);
        $decl = $service->createDeclaration($this->account->id, 'SH-FLAG-1', $this->user->id);

        $r = $this->actingAs($this->user)->postJson("/api/v1/dg/declarations/{$decl->id}/dg-flag", [
            'contains_dangerous_goods' => false,
        ]);
        $r->assertOk()->assertJsonPath('data.contains_dangerous_goods', false);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_set_dg_flag_yes(): void
    {
        $service = app(DgComplianceService::class);
        $decl = $service->createDeclaration($this->account->id, 'SH-FLAG-2', $this->user->id);

        $r = $this->actingAs($this->user)->postJson("/api/v1/dg/declarations/{$decl->id}/dg-flag", [
            'contains_dangerous_goods' => true,
        ]);
        $r->assertOk()->assertJsonPath('data.status', 'hold_dg');
    }

    // ── FR-DG-003: Hold Info ────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_hold_info(): void
    {
        $service = app(DgComplianceService::class);
        $decl = $service->createDeclaration($this->account->id, 'SH-HOLD-1', $this->user->id);
        $service->setDgFlag($decl->id, true, $this->user->id);

        $r = $this->actingAs($this->user)->getJson("/api/v1/dg/declarations/{$decl->id}/hold-info");
        $r->assertOk()->assertJsonPath('data.is_blocked', true);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_list_blocked(): void
    {
        $service = app(DgComplianceService::class);
        $decl = $service->createDeclaration($this->account->id, 'SH-BLOCKED', $this->user->id);
        $service->setDgFlag($decl->id, true, $this->user->id);

        $r = $this->actingAs($this->user)->getJson('/api/v1/dg/blocked');
        $r->assertOk();
    }

    // ── FR-DG-004: Accept Waiver ────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_accept_waiver(): void
    {
        $service = app(DgComplianceService::class);
        $decl = $service->createDeclaration($this->account->id, 'SH-WAIVER-1', $this->user->id);
        $service->setDgFlag($decl->id, false, $this->user->id);

        $r = $this->actingAs($this->user)->postJson("/api/v1/dg/declarations/{$decl->id}/accept-waiver");
        $r->assertOk()->assertJsonPath('data.waiver_accepted', true)->assertJsonPath('data.status', 'completed');
    }

    // ── FR-DG-007: Validate for Issuance ────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_validate_issuance_success(): void
    {
        $service = app(DgComplianceService::class);
        $decl = $service->createDeclaration($this->account->id, 'SH-VALID-1', $this->user->id);
        $service->setDgFlag($decl->id, false, $this->user->id);
        $service->acceptWaiver($decl->id, $this->user->id);

        $r = $this->actingAs($this->user)->postJson('/api/v1/dg/validate-issuance', ['shipment_id' => 'SH-VALID-1']);
        $r->assertOk()->assertJsonPath('data.valid', true);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_validate_issuance_no_declaration(): void
    {
        $r = $this->actingAs($this->user)->postJson('/api/v1/dg/validate-issuance', ['shipment_id' => 'SH-NONE']);
        $r->assertStatus(422)->assertJsonPath('valid', false);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_validate_issuance_dg_hold(): void
    {
        $service = app(DgComplianceService::class);
        $decl = $service->createDeclaration($this->account->id, 'SH-HOLD-V', $this->user->id);
        $service->setDgFlag($decl->id, true, $this->user->id);

        $r = $this->actingAs($this->user)->postJson('/api/v1/dg/validate-issuance', ['shipment_id' => 'SH-HOLD-V']);
        $r->assertStatus(422);
    }

    // ── FR-DG-009: DG Metadata ──────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_save_dg_metadata(): void
    {
        $service = app(DgComplianceService::class);
        $decl = $service->createDeclaration($this->account->id, 'SH-META-1', $this->user->id);

        $r = $this->actingAs($this->user)->postJson("/api/v1/dg/declarations/{$decl->id}/metadata", [
            'un_number' => 'UN3481', 'dg_class' => '9', 'quantity' => 1.5,
        ]);
        $r->assertOk()->assertJsonPath('data.un_number', 'UN3481');
    }

    // ── FR-DG-006: Waiver Version Management ────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_publish_waiver(): void
    {
        $r = $this->actingAs($this->user)->postJson('/api/v1/dg/waivers', [
            'version' => '2.0', 'locale' => 'en', 'waiver_text' => 'New legal text.',
        ]);
        $r->assertStatus(201)->assertJsonPath('data.version', '2.0');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_active_waiver(): void
    {
        $r = $this->actingAs($this->user)->getJson('/api/v1/dg/waivers/active?locale=ar');
        $r->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_list_waivers(): void
    {
        $r = $this->actingAs($this->user)->getJson('/api/v1/dg/waivers?locale=ar');
        $r->assertOk();
    }

    // ── FR-DG-005: Audit Log ────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_audit_log(): void
    {
        $service = app(DgComplianceService::class);
        $decl = $service->createDeclaration($this->account->id, 'SH-AUDIT-1', $this->user->id);

        $r = $this->actingAs($this->user)->getJson("/api/v1/dg/declarations/{$decl->id}/audit-log");
        $r->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_export_audit_log(): void
    {
        $service = app(DgComplianceService::class);
        $service->createDeclaration($this->account->id, 'SH-EXPORT-1', $this->user->id);

        $r = $this->actingAs($this->user)->getJson('/api/v1/dg/audit-log/export');
        $r->assertOk();
    }
}
