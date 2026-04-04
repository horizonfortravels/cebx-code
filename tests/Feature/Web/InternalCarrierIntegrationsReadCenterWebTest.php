<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternalCarrierIntegrationsReadCenterWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.dhl' => [
                'api_key' => 'dhl-internal-key-001',
                'api_secret' => 'dhl-internal-secret-001',
                'base_url' => 'https://express.api.dhl.com/mydhlapi',
                'account_number' => 'DHL9012',
            ],
            'services.fedex' => [
                'client_id' => 'fedex-client-id-001',
                'client_secret' => 'fedex-client-secret-001',
                'account_number' => 'FEDX3456',
                'base_url' => 'https://apis-sandbox.fedex.com',
            ],
        ]);

        $this->seed(E2EUserMatrixSeeder::class);
    }

    #[Test]
    public function allowed_internal_roles_can_open_carrier_integrations_index_and_detail(): void
    {
        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
            'e2e.internal.carrier_manager@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $index = $this->actingAs($user, 'web')
                ->get(route('internal.carriers.index'))
                ->assertOk()
                ->assertSee('data-testid="internal-carriers-table"', false)
                ->assertSeeText('DHL Express')
                ->assertSeeText('FedEx')
                ->assertDontSeeText('I8A Shopify Store')
                ->assertDontSeeText('Moyasar')
                ->assertDontSeeText('dhl-internal-key-001')
                ->assertDontSeeText('fedex-client-secret-001');

            $this->assertHasNavigationLink($index, 'internal.carriers.index');

            $detail = $this->actingAs($user, 'web')
                ->get(route('internal.carriers.show', 'fedex'))
                ->assertOk()
                ->assertSee('data-testid="internal-carrier-summary-card"', false)
                ->assertSee('data-testid="internal-carrier-health-card"', false)
                ->assertSee('data-testid="internal-carrier-activity-card"', false)
                ->assertSee('data-testid="internal-carrier-error-card"', false)
                ->assertSeeText('FedEx')
                ->assertSeeText('Sandbox')
                ->assertSeeText('Configured ending 3456')
                ->assertSeeText('Carrier service is temporarily unavailable')
                ->assertDontSeeText('fedex-client-id-001')
                ->assertDontSeeText('fedex-client-secret-001')
                ->assertDontSeeText('DHL9012')
                ->assertDontSeeText('connection_config')
                ->assertDontSeeText('payload');

            $detail->assertSee('data-testid="internal-carrier-credentials-card"', false)
                ->assertSeeText('Client ID')
                ->assertSeeText('Client secret')
                ->assertSeeText('Configured ending 3456');
        }
    }

    #[Test]
    public function carrier_integrations_index_supports_search_and_basic_filters(): void
    {
        $viewer = $this->userByEmail('e2e.internal.support@example.test');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.carriers.index', ['q' => 'dhl']))
            ->assertOk()
            ->assertSeeText('DHL Express')
            ->assertDontSeeText('FedEx');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.carriers.index', ['health' => 'degraded']))
            ->assertOk()
            ->assertSeeText('FedEx')
            ->assertDontSeeText('DHL Express');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.carriers.index', ['state' => 'enabled']))
            ->assertOk()
            ->assertSeeText('DHL Express');
    }

    #[Test]
    public function allowed_roles_see_carrier_integrations_navigation(): void
    {
        $superAdminPage = $this->actingAs($this->userByEmail('e2e.internal.super_admin@example.test'), 'web')
            ->get(route('admin.index'))
            ->assertOk();
        $this->assertHasNavigationLink($superAdminPage, 'internal.carriers.index');

        foreach ([
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
            'e2e.internal.carrier_manager@example.test',
        ] as $email) {
            $page = $this->actingAs($this->userByEmail($email), 'web')
                ->get(route('internal.home'))
                ->assertOk();

            $this->assertHasNavigationLink($page, 'internal.carriers.index');
        }
    }

    #[Test]
    public function external_users_are_forbidden_from_internal_carrier_routes(): void
    {
        $externalUser = $this->userByEmail('e2e.c.organization_owner@example.test');

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->get(route('internal.carriers.index'))
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($externalUser, 'web')->get(route('internal.carriers.show', 'dhl'))
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
