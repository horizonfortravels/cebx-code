<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\PaymentTransaction;
use App\Models\ReportExport;
use App\Models\Role;
use App\Models\SavedReport;
use App\Models\ScheduledReport;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit Tests — RPT Module (FR-RPT-001→010)
 *
 * 35 tests covering all 10 functional requirements.
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    private ReportService $service;
    private Account $account;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(ReportService::class);
        $this->account = Account::factory()->create();
        $role = Role::factory()->create(['account_id' => $this->account->id, 'slug' => 'owner']);
        $this->owner = User::factory()->create([
            'account_id' => $this->account->id,
            'role_id'    => $role->id,
        ]);
    }

    private function seedShipments(int $count = 5): void
    {
        Shipment::factory()->count($count)->create([
            'account_id' => $this->account->id,
            'store_id'   => 'store-1',
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-RPT-001: Shipment Dashboard (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_shipment_dashboard_returns_totals(): void
    {
        $this->seedShipments(10);
        $dashboard = $this->service->shipmentDashboard($this->account);

        $this->assertEquals(10, $dashboard['total_shipments']);
        $this->assertArrayHasKey('by_status', $dashboard);
        $this->assertArrayHasKey('by_carrier', $dashboard);
        $this->assertArrayHasKey('by_service', $dashboard);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_dashboard_with_date_filter(): void
    {
        Shipment::factory()->create(['account_id' => $this->account->id, 'created_at' => now()->subDays(5)]);
        Shipment::factory()->create(['account_id' => $this->account->id, 'created_at' => now()->subDays(30)]);

        $dashboard = $this->service->shipmentDashboard($this->account, [
            'date_from' => now()->subDays(10)->toDateString(),
            'date_to'   => now()->toDateString(),
        ]);

        $this->assertEquals(1, $dashboard['total_shipments']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_dashboard_delivery_rate(): void
    {
        Shipment::factory()->count(3)->create(['account_id' => $this->account->id, 'status' => 'delivered']);
        Shipment::factory()->count(2)->create(['account_id' => $this->account->id, 'status' => 'in_transit']);

        $dashboard = $this->service->shipmentDashboard($this->account);
        $this->assertEquals(60.00, $dashboard['delivery_rate']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_dashboard_by_store_filter(): void
    {
        Shipment::factory()->count(3)->create(['account_id' => $this->account->id, 'store_id' => 'store-A']);
        Shipment::factory()->count(2)->create(['account_id' => $this->account->id, 'store_id' => 'store-B']);

        $dashboard = $this->service->shipmentDashboard($this->account, ['store_id' => 'store-A']);
        $this->assertEquals(3, $dashboard['total_shipments']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_empty_dashboard(): void
    {
        $dashboard = $this->service->shipmentDashboard($this->account);
        $this->assertEquals(0, $dashboard['total_shipments']);
        $this->assertEquals(0, $dashboard['delivery_rate']);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-RPT-002: Profit Report (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_profit_report_structure(): void
    {
        $this->seedShipments(3);
        $report = $this->service->profitReport($this->account);

        $this->assertArrayHasKey('shipments', $report);
        $this->assertArrayHasKey('totals', $report);
        $this->assertArrayHasKey('total_retail', $report['totals']);
        $this->assertArrayHasKey('total_profit', $report['totals']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_profit_report_count(): void
    {
        $this->seedShipments(5);
        $report = $this->service->profitReport($this->account);
        $this->assertEquals(5, $report['totals']['shipment_count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_profit_report_filtered(): void
    {
        Shipment::factory()->create(['account_id' => $this->account->id, 'carrier_code' => 'DHL']);
        Shipment::factory()->create(['account_id' => $this->account->id, 'carrier_code' => 'ARAMEX']);

        $report = $this->service->profitReport($this->account, ['carrier' => 'DHL']);
        $this->assertEquals(1, $report['totals']['shipment_count']);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-RPT-003: Export (4 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_csv_export(): void
    {
        $this->seedShipments(3);
        $export = $this->service->createExport($this->account, $this->owner, 'shipment_summary', 'csv');

        $this->assertEquals(ReportExport::STATUS_COMPLETED, $export->status);
        $this->assertNotNull($export->file_path);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_excel_export(): void
    {
        $export = $this->service->createExport($this->account, $this->owner, 'shipment_summary', 'excel');
        $this->assertEquals('excel', $export->format);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_export_with_filters(): void
    {
        $export = $this->service->createExport(
            $this->account, $this->owner, 'shipment_summary', 'csv',
            ['date_from' => '2026-01-01', 'date_to' => '2026-01-31']
        );
        $this->assertEquals(['date_from' => '2026-01-01', 'date_to' => '2026-01-31'], $export->filters);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_list_exports(): void
    {
        ReportExport::factory()->count(3)->create(['account_id' => $this->account->id, 'user_id' => $this->owner->id]);
        $exports = $this->service->getExports($this->account);
        $this->assertEquals(3, $exports->total());
    }

    // ═══════════════════════════════════════════════════════════
    // FR-RPT-004: Exception Reports (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_exception_report_structure(): void
    {
        $report = $this->service->exceptionReport($this->account);
        $this->assertArrayHasKey('total_exceptions', $report);
        $this->assertArrayHasKey('exception_rate', $report);
        $this->assertArrayHasKey('by_code', $report);
        $this->assertArrayHasKey('by_carrier', $report);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_exception_rate_calculation(): void
    {
        $this->seedShipments(10);
        // Without real exception data, rate should be 0
        $report = $this->service->exceptionReport($this->account);
        $this->assertEquals(0, $report['exception_rate']);
        $this->assertEquals(10, $report['total_shipments']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_exception_report_with_date_filter(): void
    {
        $report = $this->service->exceptionReport($this->account, [
            'date_from' => now()->subMonth()->toDateString(),
        ]);
        $this->assertIsArray($report);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-RPT-005: Operational vs Financial (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_operational_report_no_profit(): void
    {
        $report = $this->service->operationalReport($this->account);
        $this->assertArrayHasKey('shipments', $report);
        $this->assertArrayHasKey('exceptions', $report);
        $this->assertArrayHasKey('performance', $report);
        $this->assertArrayNotHasKey('profit_loss', $report);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_financial_report_has_profit(): void
    {
        $report = $this->service->financialReport($this->account);
        $this->assertArrayHasKey('profit_loss', $report);
        $this->assertArrayHasKey('wallet', $report);
        $this->assertArrayHasKey('revenue', $report);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_reports_separated(): void
    {
        $ops = $this->service->operationalReport($this->account);
        $fin = $this->service->financialReport($this->account);
        $this->assertArrayNotHasKey('wallet', $ops);
        $this->assertArrayNotHasKey('exceptions', $fin);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-RPT-006: Filter & Group (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_group_by_day(): void
    {
        $this->seedShipments(5);
        $data = $this->service->groupedShipmentData($this->account, [], 'day');
        $this->assertIsArray($data);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_group_by_month(): void
    {
        $this->seedShipments(5);
        $data = $this->service->groupedShipmentData($this->account, [], 'month');
        $this->assertIsArray($data);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_group_with_filter(): void
    {
        $this->seedShipments(3);
        $data = $this->service->groupedShipmentData($this->account, ['carrier' => 'DHL'], 'week');
        $this->assertIsArray($data);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-RPT-007: Charts & Analytics (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_carrier_performance(): void
    {
        $this->seedShipments(5);
        $data = $this->service->carrierPerformance($this->account);
        $this->assertIsArray($data);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_store_performance(): void
    {
        $this->seedShipments(5);
        $data = $this->service->storePerformance($this->account);
        $this->assertIsArray($data);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_revenue_by_period(): void
    {
        PaymentTransaction::factory()->create([
            'account_id' => $this->account->id,
            'direction' => 'debit', 'status' => 'captured',
        ]);
        $data = $this->service->revenueByPeriod($this->account);
        $this->assertIsArray($data);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-RPT-008: Scheduled Reports (4 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_scheduled_report(): void
    {
        $schedule = $this->service->createScheduledReport($this->account, $this->owner, [
            'name' => 'Weekly Summary', 'report_type' => 'shipment_summary',
            'frequency' => 'weekly', 'recipients' => ['test@test.com'],
        ]);
        $this->assertTrue($schedule->is_active);
        $this->assertNotNull($schedule->next_send_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_cancel_schedule(): void
    {
        $schedule = ScheduledReport::factory()->create([
            'account_id' => $this->account->id, 'user_id' => $this->owner->id,
        ]);
        $this->service->cancelScheduledReport($schedule->id);
        $this->assertFalse($schedule->fresh()->is_active);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_scheduled_report_is_due(): void
    {
        $due = ScheduledReport::factory()->due()->make();
        $notDue = ScheduledReport::factory()->make(['next_send_at' => now()->addDay()]);

        $this->assertTrue($due->isDue());
        $this->assertFalse($notDue->isDue());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_process_scheduled_reports(): void
    {
        ScheduledReport::factory()->due()->create([
            'account_id' => $this->account->id, 'user_id' => $this->owner->id,
        ]);
        $result = $this->service->processScheduledReports();
        $this->assertEquals(1, $result['processed']);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-RPT-009: Wallet Report (2 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_wallet_report_structure(): void
    {
        $report = $this->service->walletReport($this->account);
        $this->assertArrayHasKey('total_deposits', $report);
        $this->assertArrayHasKey('total_charges', $report);
        $this->assertArrayHasKey('total_refunds', $report);
        $this->assertArrayHasKey('net_balance', $report);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_wallet_report_with_data(): void
    {
        PaymentTransaction::factory()->create([
            'account_id' => $this->account->id, 'direction' => 'credit',
            'net_amount' => 500, 'status' => 'completed',
        ]);
        $report = $this->service->walletReport($this->account);
        $this->assertEquals(500, $report['total_deposits']);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-RPT-010: Reports API (2 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_generic_report_api(): void
    {
        $this->seedShipments(3);
        $data = $this->service->getReportData($this->account->id, 'shipment_summary');
        $this->assertArrayHasKey('total_shipments', $data);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_invalid_report_type_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->getReportData($this->account->id, 'invalid_type');
    }

    // ═══════════════════════════════════════════════════════════
    // Saved Reports (2 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_save_report(): void
    {
        $report = $this->service->saveReport($this->account, $this->owner, [
            'name' => 'My Report', 'report_type' => 'shipment_summary',
        ]);
        $this->assertEquals('My Report', $report->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_list_saved_reports(): void
    {
        SavedReport::factory()->count(2)->create([
            'account_id' => $this->account->id, 'user_id' => $this->owner->id,
        ]);
        $reports = $this->service->getSavedReports($this->account, $this->owner);
        $this->assertCount(2, $reports);
    }
}
