<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ReportExport;
use App\Models\Role;
use App\Models\ScheduledReport;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API Tests — RPT Module (FR-RPT-001→010)
 *
 * 18 tests covering all report API endpoints.
 */
class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = Account::factory()->create();
        $role = Role::factory()->create(['account_id' => $this->account->id, 'slug' => 'owner']);
        $this->owner = $this->createUserWithRole((string) $this->account->id, (string) $role->id);
    }

    // FR-RPT-001
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_shipment_dashboard(): void
    {
        Shipment::factory()->count(3)->create(['account_id' => $this->account->id]);

        $response = $this->actingAs($this->owner)->getJson('/api/v1/reports/shipment-dashboard');
        $response->assertOk()->assertJsonPath('data.total_shipments', 3);
    }

    // FR-RPT-001 with filters
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_dashboard_with_filters(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/reports/shipment-dashboard?date_from=2026-01-01&date_to=2026-01-31');
        $response->assertOk();
    }

    // FR-RPT-002
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_profit_report(): void
    {
        Shipment::factory()->count(2)->create(['account_id' => $this->account->id]);

        $response = $this->actingAs($this->owner)->getJson('/api/v1/reports/profit');
        $response->assertOk()
            ->assertJsonStructure(['data' => ['shipments', 'totals']]);
    }

    // FR-RPT-003: Create export
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_create_export(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/reports/export', [
                'report_type' => 'shipment_summary',
                'format'      => 'csv',
            ]);
        $response->assertStatus(201)->assertJsonPath('data.format', 'csv');
    }

    // FR-RPT-003: List exports
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_list_exports(): void
    {
        ReportExport::factory()->count(2)->create([
            'account_id' => $this->account->id, 'user_id' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)->getJson('/api/v1/reports/exports');
        $response->assertOk()->assertJsonPath('data.total', 2);
    }

    // FR-RPT-004
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_exception_report(): void
    {
        $response = $this->actingAs($this->owner)->getJson('/api/v1/reports/exceptions');
        $response->assertOk()->assertJsonStructure(['data' => ['total_exceptions', 'exception_rate']]);
    }

    // FR-RPT-005: Operational
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_operational_report(): void
    {
        $response = $this->actingAs($this->owner)->getJson('/api/v1/reports/operational');
        $response->assertOk()->assertJsonStructure(['data' => ['shipments', 'exceptions']]);
    }

    // FR-RPT-005: Financial
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_financial_report(): void
    {
        $response = $this->actingAs($this->owner)->getJson('/api/v1/reports/financial');
        $response->assertOk()->assertJsonStructure(['data' => ['profit_loss', 'wallet']]);
    }

    // FR-RPT-006
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_grouped_data(): void
    {
        $response = $this->actingAs($this->owner)->getJson('/api/v1/reports/grouped?group_by=month');
        $response->assertOk();
    }

    // FR-RPT-007: Carrier performance
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_carrier_performance(): void
    {
        Shipment::factory()->count(3)->create(['account_id' => $this->account->id]);

        $response = $this->actingAs($this->owner)->getJson('/api/v1/reports/carrier-performance');
        $response->assertOk();
    }

    // FR-RPT-007: Store performance
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_store_performance(): void
    {
        $response = $this->actingAs($this->owner)->getJson('/api/v1/reports/store-performance');
        $response->assertOk();
    }

    // FR-RPT-007: Revenue
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_revenue_chart(): void
    {
        $response = $this->actingAs($this->owner)->getJson('/api/v1/reports/revenue?group_by=month');
        $response->assertOk();
    }

    // FR-RPT-008: Create schedule
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_create_schedule(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/reports/schedules', [
                'name'        => 'Weekly Summary',
                'report_type' => 'shipment_summary',
                'frequency'   => 'weekly',
                'recipients'  => ['admin@test.com'],
            ]);
        $response->assertStatus(201);
    }

    // FR-RPT-008: List schedules
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_list_schedules(): void
    {
        ScheduledReport::factory()->create([
            'account_id' => $this->account->id, 'user_id' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)->getJson('/api/v1/reports/schedules');
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    // FR-RPT-008: Cancel schedule
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_cancel_schedule(): void
    {
        $schedule = ScheduledReport::factory()->create([
            'account_id' => $this->account->id, 'user_id' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)->deleteJson("/api/v1/reports/schedules/{$schedule->id}");
        $response->assertOk();
    }

    // FR-RPT-009
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_wallet_report(): void
    {
        $response = $this->actingAs($this->owner)->getJson('/api/v1/reports/wallet');
        $response->assertOk()->assertJsonStructure(['data' => ['total_deposits', 'total_charges']]);
    }

    // FR-RPT-010
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_generic_report(): void
    {
        $response = $this->actingAs($this->owner)->getJson('/api/v1/reports/api/shipment_summary');
        $response->assertOk();
    }

    // Saved reports
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_save_and_list_reports(): void
    {
        $this->actingAs($this->owner)->postJson('/api/v1/reports/saved', [
            'name' => 'My Report', 'report_type' => 'shipment_summary',
        ])->assertStatus(201);

        $response = $this->actingAs($this->owner)->getJson('/api/v1/reports/saved');
        $response->assertOk()->assertJsonCount(1, 'data');
    }
}
