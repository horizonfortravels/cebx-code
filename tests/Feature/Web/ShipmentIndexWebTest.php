<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShipmentIndexWebTest extends TestCase
{
    #[DataProvider('portalRouteProvider')]
    public function test_portal_search_and_filters_only_show_matching_same_tenant_rows(
        string $accountType,
        string $persona,
        string $indexPath,
        string $exportPath
    ): void {
        $user = $this->createShipmentIndexUser($accountType, $persona);
        $otherTenant = $this->createShipmentIndexUser($accountType, $persona . '_other');

        $match = $this->createShipmentForUser($user, [
            'reference_number' => 'FILTER-MATCH-' . Str::upper(Str::random(6)),
            'status' => Shipment::STATUS_DELIVERED,
            'carrier_code' => 'fedex',
            'carrier_name' => 'FedEx',
            'recipient_name' => 'Filter Recipient',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->createShipmentForUser($user, [
            'reference_number' => 'FILTER-WRONG-STATUS-' . Str::upper(Str::random(4)),
            'status' => Shipment::STATUS_IN_TRANSIT,
            'carrier_code' => 'fedex',
            'carrier_name' => 'FedEx',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->createShipmentForUser($user, [
            'reference_number' => 'FILTER-WRONG-DATE-' . Str::upper(Str::random(4)),
            'status' => Shipment::STATUS_DELIVERED,
            'carrier_code' => 'fedex',
            'carrier_name' => 'FedEx',
            'created_at' => now()->subDays(20),
            'updated_at' => now()->subDays(20),
        ]);

        $this->createShipmentForUser($otherTenant, [
            'reference_number' => 'FILTER-MATCH-OTHER-' . Str::upper(Str::random(4)),
            'status' => Shipment::STATUS_DELIVERED,
            'carrier_code' => 'fedex',
            'carrier_name' => 'FedEx',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $query = [
            'search' => 'FILTER-MATCH',
            'status' => Shipment::STATUS_DELIVERED,
            'carrier' => 'fedex',
            'from' => now()->subDays(7)->toDateString(),
            'to' => now()->toDateString(),
        ];

        $this->actingAs($user, 'web')
            ->get($indexPath . '?' . http_build_query($query))
            ->assertOk()
            ->assertSee($match->reference_number)
            ->assertDontSee('FILTER-WRONG-STATUS')
            ->assertDontSee('FILTER-WRONG-DATE');
    }

    public function test_b2c_shipments_page_renders_clean_arabic_copy(): void
    {
        $user = $this->createShipmentIndexUser('individual', 'individual');
        $this->createShipmentForUser($user, [
            'reference_number' => 'B2C-INDEX-01',
            'recipient_city' => 'New York',
            'recipient_country' => 'US',
        ]);

        $this->actingAs($user, 'web')
            ->get('/b2c/shipments')
            ->assertOk()
            ->assertSee('مساحة الشحنات الفردية')
            ->assertSee('سجل الشحنات')
            ->assertSee('بدء طلب شحنة')
            ->assertDontSee('ط§ظ„ط´ط­ظ†ط§طھ')
            ->assertDontSee('ط·آ');
    }

    public function test_b2b_shipments_page_renders_clean_arabic_copy(): void
    {
        $user = $this->createShipmentIndexUser('organization', 'organization_owner');
        $this->createShipmentForUser($user, [
            'reference_number' => 'B2B-INDEX-01',
            'recipient_name' => 'Recipient One',
        ]);

        $this->actingAs($user, 'web')
            ->get('/b2b/shipments')
            ->assertOk()
            ->assertSee('لوحة تشغيل الشحنات')
            ->assertSee('سجل الشحنات')
            ->assertSee('بدء طلب شحنة لفريقك')
            ->assertDontSee('ط§ظ„ط´ط­ظ†ط§طھ')
            ->assertDontSee('ط·آ');
    }

    public function test_b2b_shipments_index_paginates_and_opens_older_shipments(): void
    {
        $user = $this->createShipmentIndexUser('organization', 'organization_owner');

        foreach (range(1, 12) as $index) {
            $this->createShipmentForUser($user, [
                'reference_number' => 'BROWSE-' . str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'recipient_name' => 'Recipient ' . $index,
                'created_at' => now()->subDays(12 - $index),
                'updated_at' => now()->subDays(12 - $index),
            ]);
        }

        $olderShipment = Shipment::query()
            ->where('account_id', (string) $user->account_id)
            ->where('reference_number', 'BROWSE-01')
            ->firstOrFail();

        $this->actingAs($user, 'web')
            ->get('/b2b/shipments')
            ->assertOk()
            ->assertSee('BROWSE-12')
            ->assertDontSee('BROWSE-01')
            ->assertSee('التالي');

        $this->actingAs($user, 'web')
            ->get('/b2b/shipments?page=2')
            ->assertOk()
            ->assertSee('BROWSE-01')
            ->assertSee('/b2b/shipments/' . $olderShipment->id, false);

        $this->actingAs($user, 'web')
            ->get('/b2b/shipments/' . $olderShipment->id)
            ->assertOk()
            ->assertSee('BROWSE-01');
    }

    public function test_cross_tenant_shipment_list_and_detail_do_not_leak(): void
    {
        $userA = $this->createShipmentIndexUser('organization', 'organization_owner');
        $userB = $this->createShipmentIndexUser('organization', 'organization_owner');
        $shipmentB = $this->createShipmentForUser($userB, [
            'reference_number' => 'TENANT-B-01',
        ]);

        $this->actingAs($userA, 'web')
            ->get('/b2b/shipments')
            ->assertOk()
            ->assertDontSee('TENANT-B-01');

        $this->actingAs($userA, 'web')
            ->get('/b2b/shipments/' . $shipmentB->id)
            ->assertNotFound();
    }

    #[DataProvider('portalRouteProvider')]
    public function test_portal_pagination_preserves_active_query_string(
        string $accountType,
        string $persona,
        string $indexPath,
        string $exportPath
    ): void {
        $user = $this->createShipmentIndexUser($accountType, $persona . '_paging');

        foreach (range(1, 11) as $index) {
            $this->createShipmentForUser($user, [
                'reference_number' => 'PAGE-KEEP-' . str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'status' => Shipment::STATUS_DELIVERED,
                'carrier_code' => 'fedex',
                'carrier_name' => 'FedEx',
                'created_at' => now()->subMinutes(11 - $index),
                'updated_at' => now()->subMinutes(11 - $index),
            ]);
        }

        $response = $this->actingAs($user, 'web')
            ->get($indexPath . '?search=PAGE-KEEP&status=delivered&carrier=fedex');

        $response->assertOk()
            ->assertSee('PAGE-KEEP-11')
            ->assertDontSee('PAGE-KEEP-01');

        $shipments = $response->viewData('shipments');
        $this->assertNotNull($shipments);
        $this->assertStringContainsString('search=PAGE-KEEP', (string) $shipments->nextPageUrl());
        $this->assertStringContainsString('status=delivered', (string) $shipments->nextPageUrl());
        $this->assertStringContainsString('carrier=fedex', (string) $shipments->nextPageUrl());

        $this->actingAs($user, 'web')
            ->get((string) $shipments->nextPageUrl())
            ->assertOk()
            ->assertSee('PAGE-KEEP-01');
    }

    #[DataProvider('portalRouteProvider')]
    public function test_portal_csv_export_respects_filtered_current_tenant_result_set(
        string $accountType,
        string $persona,
        string $indexPath,
        string $exportPath
    ): void {
        $user = $this->createShipmentIndexUser($accountType, $persona . '_export');
        $otherTenant = $this->createShipmentIndexUser($accountType, $persona . '_export_other');

        $firstMatch = $this->createShipmentForUser($user, [
            'reference_number' => 'EXPORT-MATCH-01',
            'status' => Shipment::STATUS_DELIVERED,
            'carrier_code' => 'fedex',
            'carrier_name' => 'FedEx',
            'recipient_name' => 'Export Match One',
            'total_charge' => 125.50,
            'currency' => 'USD',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $secondMatch = $this->createShipmentForUser($user, [
            'reference_number' => 'EXPORT-MATCH-02',
            'status' => Shipment::STATUS_DELIVERED,
            'carrier_code' => 'fedex',
            'carrier_name' => 'FedEx',
            'recipient_name' => 'Export Match Two',
            'total_charge' => 210.75,
            'currency' => 'USD',
            'created_at' => now()->subHours(4),
            'updated_at' => now()->subHours(4),
        ]);

        $this->createShipmentForUser($user, [
            'reference_number' => 'EXPORT-NONMATCH-STATUS',
            'status' => Shipment::STATUS_IN_TRANSIT,
            'carrier_code' => 'fedex',
            'carrier_name' => 'FedEx',
            'created_at' => now()->subHours(4),
            'updated_at' => now()->subHours(4),
        ]);

        $this->createShipmentForUser($otherTenant, [
            'reference_number' => 'EXPORT-MATCH-OTHER-TENANT',
            'status' => Shipment::STATUS_DELIVERED,
            'carrier_code' => 'fedex',
            'carrier_name' => 'FedEx',
            'created_at' => now()->subHours(4),
            'updated_at' => now()->subHours(4),
        ]);

        $response = $this->actingAs($user, 'web')
            ->get($exportPath . '?search=EXPORT-MATCH&status=delivered&carrier=fedex');

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-16LE');

        $csv = $this->decodePortalShipmentCsv($response);

        $this->assertStringContainsString('EXPORT-MATCH-01', $csv);
        $this->assertStringContainsString('EXPORT-MATCH-02', $csv);
        $this->assertStringNotContainsString('EXPORT-NONMATCH-STATUS', $csv);
        $this->assertStringNotContainsString('EXPORT-MATCH-OTHER-TENANT', $csv);
        $this->assertStringContainsString((string) $firstMatch->recipient_name, $csv);
        $this->assertStringContainsString((string) $secondMatch->recipient_name, $csv);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function portalRouteProvider(): array
    {
        return [
            'b2c' => ['individual', 'individual', '/b2c/shipments', '/b2c/shipments/export'],
            'b2b' => ['organization', 'organization_owner', '/b2b/shipments', '/b2b/shipments/export'],
        ];
    }

    private function createShipmentIndexUser(string $accountType, string $persona): User
    {
        $account = $accountType === 'individual'
            ? Account::factory()->individual()->create([
                'name' => 'B2C Shipments ' . Str::upper(Str::random(4)),
                'status' => 'active',
            ])
            : Account::factory()->organization()->create([
                'name' => 'B2B Shipments ' . Str::upper(Str::random(4)),
                'status' => 'active',
            ]);

        $user = User::factory()->create([
            'account_id' => $account->id,
            'user_type' => 'external',
            'status' => 'active',
        ]);

        $this->grantTenantPermissions($user, ['shipments.read', 'shipments.create', 'tracking.read'], 'shipment_index_web_' . $persona . '_' . $accountType);

        return $user;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createShipmentForUser(User $user, array $attributes = []): Shipment
    {
        $payload = array_merge([
            'account_id' => (string) $user->account_id,
            'user_id' => (string) $user->id,
            'status' => Shipment::STATUS_DRAFT,
            'sender_name' => 'Sender',
            'recipient_name' => 'Recipient',
            'recipient_city' => 'Riyadh',
            'recipient_country' => 'SA',
        ], $attributes);

        if (Schema::hasTable('shipments')) {
            $payload = array_filter(
                $payload,
                static fn (mixed $value, string $column): bool => Schema::hasColumn('shipments', $column),
                ARRAY_FILTER_USE_BOTH
            );
        }

        return Shipment::factory()->create($payload);
    }

    private function decodePortalShipmentCsv(TestResponse $response): string
    {
        $content = (string) $response->getContent();
        if (str_starts_with($content, "\xFF\xFE")) {
            $content = substr($content, 2);
        }

        return mb_convert_encoding($content, 'UTF-8', 'UTF-16LE');
    }
}
