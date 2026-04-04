<?php

namespace Tests\Feature\Web;

use App\Models\PaymentGateway;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternalIntegrationsReadCenterWebTest extends TestCase
{
    use RefreshDatabase;

    private Store $shopifyStore;
    private PaymentGateway $moyasarGateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);

        $this->shopifyStore = Store::query()
            ->withoutGlobalScopes()
            ->where('name', 'I8A Shopify Store')
            ->firstOrFail();

        $this->moyasarGateway = PaymentGateway::query()
            ->where('slug', 'moyasar')
            ->firstOrFail();
    }

    #[Test]
    public function super_admin_support_and_ops_readonly_can_open_integrations_index_and_detail(): void
    {
        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $index = $this->actingAs($user, 'web')
                ->get(route('internal.integrations.index'))
                ->assertOk()
                ->assertSee('data-testid="internal-integrations-table"', false)
                ->assertSeeText('DHL Express')
                ->assertSeeText('I8A Shopify Store')
                ->assertSeeText('Moyasar')
                ->assertDontSeeText('i8a-shopify-token-001')
                ->assertDontSeeText('i8a-moyasar-secret-001');

            $this->assertHasNavigationLink($index, 'internal.integrations.index');

            $detail = $this->actingAs($user, 'web')
                ->get(route('internal.integrations.show', 'store~' . (string) $this->shopifyStore->id))
                ->assertOk()
                ->assertSee('data-testid="internal-integration-summary-card"', false)
                ->assertSee('data-testid="internal-integration-health-card"', false)
                ->assertSee('data-testid="internal-integration-activity-card"', false)
                ->assertSee('data-testid="internal-integration-feature-flags-card"', false)
                ->assertSee('data-testid="internal-integration-credentials-card"', false)
                ->assertSeeText('I8A Shopify Store')
                ->assertSeeText('Store Connector')
                ->assertSeeText('Last webhook')
                ->assertSeeText('Shopify')
                ->assertSeeText('masked credential field')
                ->assertDontSeeText('i8a-shopify-token-001')
                ->assertDontSeeText('i8a-shopify-webhook-secret-001')
                ->assertDontSeeText('connection_config')
                ->assertDontSeeText('payload');

            if ($email === 'e2e.internal.ops_readonly@example.test') {
                $detail->assertDontSee('data-testid="internal-integration-account-link"', false);
            } else {
                $detail->assertSee('data-testid="internal-integration-account-link"', false);
            }
        }
    }

    #[Test]
    public function carrier_manager_only_sees_carrier_integrations_and_can_open_carrier_detail(): void
    {
        $carrierManager = $this->userByEmail('e2e.internal.carrier_manager@example.test');

        $index = $this->actingAs($carrierManager, 'web')
            ->get(route('internal.integrations.index'))
            ->assertOk()
            ->assertSeeText('DHL Express')
            ->assertSeeText('FedEx')
            ->assertDontSeeText('I8A Shopify Store')
            ->assertDontSeeText('Moyasar');

        $this->assertHasNavigationLink($index, 'internal.integrations.index');

        $this->actingAs($carrierManager, 'web')
            ->get(route('internal.integrations.show', 'carrier~dhl'))
            ->assertOk()
            ->assertSee('data-testid="internal-integration-summary-card"', false)
            ->assertSee('data-testid="internal-integration-health-card"', false)
            ->assertSee('data-testid="internal-integration-feature-flags-card"', false)
            ->assertDontSee('data-testid="internal-integration-credentials-card"', false)
            ->assertSeeText('DHL Express');

        $this->actingAs($carrierManager, 'web')
            ->get(route('internal.integrations.show', 'store~' . (string) $this->shopifyStore->id))
            ->assertNotFound();

        $this->actingAs($carrierManager, 'web')
            ->get(route('internal.integrations.show', 'gateway~' . (string) $this->moyasarGateway->id))
            ->assertNotFound();
    }

    #[Test]
    public function integrations_index_supports_search_and_basic_filters(): void
    {
        $viewer = $this->userByEmail('e2e.internal.super_admin@example.test');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.integrations.index', ['q' => 'shopify']))
            ->assertOk()
            ->assertSeeText('I8A Shopify Store')
            ->assertDontSeeText('Moyasar');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.integrations.index', ['type' => 'carrier']))
            ->assertOk()
            ->assertSeeText('DHL Express')
            ->assertDontSeeText('I8A Shopify Store');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.integrations.index', ['health' => 'degraded']))
            ->assertOk()
            ->assertSeeText('FedEx')
            ->assertDontSeeText('DHL Express');
    }

    #[Test]
    public function support_ops_readonly_and_carrier_manager_see_integrations_navigation(): void
    {
        $superAdminDashboard = $this->actingAs($this->userByEmail('e2e.internal.super_admin@example.test'), 'web')
            ->get(route('admin.index'))
            ->assertOk();
        $this->assertHasNavigationLink($superAdminDashboard, 'internal.integrations.index');

        foreach ([
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
            'e2e.internal.carrier_manager@example.test',
        ] as $email) {
            $page = $this->actingAs($this->userByEmail($email), 'web')
                ->get(route('internal.home'))
                ->assertOk();

            $this->assertHasNavigationLink($page, 'internal.integrations.index');
        }
    }

    #[Test]
    public function external_users_are_forbidden_from_internal_integrations_routes(): void
    {
        $externalUser = $this->userByEmail('e2e.c.organization_owner@example.test');

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->get(route('internal.integrations.index'))
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->get(route('internal.integrations.show', 'carrier~dhl'))
        );
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

    private function assertForbiddenInternalSurface(TestResponse $response): void
    {
        $response->assertForbidden()
            ->assertSee('class="panel"', false)
            ->assertSeeText('403')
            ->assertDontSeeText('Internal Server Error');
    }
}
