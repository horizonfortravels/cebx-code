<?php

namespace Tests\Feature\Web;

use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternalShipmentReadCenterWebTest extends TestCase
{
    use RefreshDatabase;

    private Shipment $shipmentA;
    private Shipment $shipmentC;
    private Shipment $shipmentD;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);

        $this->shipmentA = $this->shipmentByReference('SHP-I5A-A-001');
        $this->shipmentC = $this->shipmentByReference('SHP-I5A-C-001');
        $this->shipmentD = $this->shipmentByReference('SHP-I5A-D-001');
    }

    #[Test]
    public function super_admin_support_and_ops_readonly_can_open_shipment_index_and_detail(): void
    {
        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $index = $this->actingAs($user, 'web')
                ->get(route('internal.shipments.index'))
                ->assertOk()
                ->assertSee('data-testid="internal-shipments-table"', false)
                ->assertSeeText('SHP-I5A-A-001')
                ->assertSeeText('SHP-I5A-C-001')
                ->assertSeeText('SHP-I5A-D-001');

            $this->assertHasNavigationLink($index, 'internal.shipments.index');

            $detail = $this->actingAs($user, 'web')
                ->get(route('internal.shipments.show', $this->shipmentD))
                ->assertOk()
                ->assertSee('data-testid="internal-shipment-summary-card"', false)
                ->assertSee('data-testid="internal-shipment-linked-account-card"', false)
                ->assertSee('data-testid="internal-shipment-operational-state-card"', false)
                ->assertSee('data-testid="internal-shipment-parcels-card"', false)
                ->assertSee('data-testid="internal-shipment-timeline-card"', false)
                ->assertSee('data-testid="internal-shipment-documents-card"', false)
                ->assertSee('data-testid="internal-shipment-documents-link"', false)
                ->assertSee('data-testid="internal-shipment-notifications-card"', false)
                ->assertSee('data-testid="internal-shipment-notification-item"', false)
                ->assertSee('data-testid="internal-shipment-kyc-summary-card"', false)
                ->assertSeeText('SHP-I5A-D-001')
                ->assertSeeText('I5A-DHL-D-001')
                ->assertSeeText('AWB-I5A-D-001')
                ->assertSeeText('E2E Account D')
                ->assertSeeText('i5a-d-label.pdf')
                ->assertSeeText('Shipment documents ready')
                ->assertSeeText('Shipment is moving through the network')
                ->assertSee('data-testid="internal-shipment-public-tracking-link"', false)
                ->assertSee(route('public.tracking.show', ['token' => 'i5a-public-token-d-001']), false)
                ->assertDontSeeText('i5a-public-token-d-001')
                ->assertDontSeeText('public_tracking_token_hash')
                ->assertDontSeeText('content_base64')
                ->assertDontSeeText('storage_path');

            if ($email === 'e2e.internal.ops_readonly@example.test') {
                $detail->assertDontSee(route('internal.accounts.show', $this->shipmentD->account_id), false);
            } else {
                $detail->assertSee(route('internal.accounts.show', $this->shipmentD->account_id), false);
            }

            $detail->assertSee(route('internal.kyc.show', $this->shipmentD->account_id), false);
        }
    }

    #[Test]
    public function shipment_index_supports_search_and_basic_filters(): void
    {
        $viewer = $this->userByEmail('e2e.internal.super_admin@example.test');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.shipments.index', ['q' => 'SHP-I5A-D-001']))
            ->assertOk()
            ->assertSeeText('SHP-I5A-D-001')
            ->assertDontSeeText('SHP-I5A-A-001');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.shipments.index', ['carrier' => 'dhl']))
            ->assertOk()
            ->assertSeeText('SHP-I5A-D-001')
            ->assertDontSeeText('SHP-I5A-A-001');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.shipments.index', ['status' => Shipment::STATUS_KYC_BLOCKED]))
            ->assertOk()
            ->assertSeeText('SHP-I5A-C-001')
            ->assertDontSeeText('SHP-I5A-D-001');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.shipments.index', ['international' => 'yes']))
            ->assertOk()
            ->assertSeeText('SHP-I5A-A-001')
            ->assertSeeText('SHP-I5A-D-001')
            ->assertDontSeeText('SHP-I5A-C-001');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.shipments.index', ['cod' => 'yes']))
            ->assertOk()
            ->assertSeeText('SHP-I5A-D-001')
            ->assertDontSeeText('SHP-I5A-A-001');
    }

    #[Test]
    public function super_admin_support_and_ops_readonly_see_shipment_navigation_while_carrier_manager_does_not(): void
    {
        $superAdminPage = $this->actingAs($this->userByEmail('e2e.internal.super_admin@example.test'), 'web')
            ->get(route('admin.index'))
            ->assertOk();
        $this->assertHasNavigationLink($superAdminPage, 'internal.shipments.index');

        foreach ([
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $page = $this->actingAs($this->userByEmail($email), 'web')
                ->get(route('internal.home'))
                ->assertOk();

            $this->assertHasNavigationLink($page, 'internal.shipments.index');
        }

        $carrierManagerPage = $this->actingAs($this->userByEmail('e2e.internal.carrier_manager@example.test'), 'web')
            ->get(route('internal.home'))
            ->assertOk();

        $this->assertMissingNavigationLink($carrierManagerPage, 'internal.shipments.index');
    }

    #[Test]
    public function carrier_manager_is_forbidden_from_internal_shipment_routes(): void
    {
        $carrierManager = $this->userByEmail('e2e.internal.carrier_manager@example.test');

        $this->assertForbiddenInternalSurface(
            $this->actingAs($carrierManager, 'web')->get(route('internal.shipments.index'))
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($carrierManager, 'web')->get(route('internal.shipments.show', $this->shipmentD))
        );
    }

    #[Test]
    public function external_users_are_forbidden_from_internal_shipment_routes(): void
    {
        $externalUser = $this->userByEmail('e2e.c.organization_owner@example.test');

        $this->actingAs($externalUser, 'web')
            ->get(route('internal.shipments.index'))
            ->assertForbidden()
            ->assertSeeText('هذه الصفحة مخصصة لفريق التشغيل الداخلي في المنصة');

        $this->actingAs($externalUser, 'web')
            ->get(route('internal.shipments.show', $this->shipmentD))
            ->assertForbidden()
            ->assertSeeText('هذه الصفحة مخصصة لفريق التشغيل الداخلي في المنصة');
    }

    private function shipmentByReference(string $reference): Shipment
    {
        return Shipment::query()
            ->withoutGlobalScopes()
            ->where('reference_number', $reference)
            ->firstOrFail();
    }

    private function userByEmail(string $email): User
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('email', $email)
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
