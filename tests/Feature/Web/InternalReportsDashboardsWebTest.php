<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternalReportsDashboardsWebTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<string, array{route: string, title: string, breakdown: string, trend: string, primary_link: string}>
     */
    private array $dashboards = [
        'shipments' => [
            'route' => 'internal.reports.shipments',
            'title' => 'Shipment operations dashboard',
            'breakdown' => 'Current workflow status breakdown',
            'trend' => 'Recent shipment intake',
            'primary_link' => 'internal.shipments.index',
        ],
        'kyc' => [
            'route' => 'internal.reports.kyc',
            'title' => 'KYC operations dashboard',
            'breakdown' => 'Verification status breakdown',
            'trend' => 'Recent KYC submissions',
            'primary_link' => 'internal.kyc.index',
        ],
        'billing' => [
            'route' => 'internal.reports.billing',
            'title' => 'Wallet & billing dashboard',
            'breakdown' => 'Wallet status breakdown',
            'trend' => 'Recent confirmed top-ups',
            'primary_link' => 'internal.billing.index',
        ],
        'compliance' => [
            'route' => 'internal.reports.compliance',
            'title' => 'Compliance & DG dashboard',
            'breakdown' => 'Compliance status breakdown',
            'trend' => 'Recent declaration intake',
            'primary_link' => 'internal.compliance.index',
        ],
        'tickets' => [
            'route' => 'internal.reports.tickets',
            'title' => 'Helpdesk & tickets dashboard',
            'breakdown' => 'Workflow status breakdown',
            'trend' => 'Recent ticket intake',
            'primary_link' => 'internal.tickets.index',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);
    }

    #[Test]
    public function super_admin_support_and_ops_readonly_can_open_each_operational_dashboard(): void
    {
        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            foreach ($this->dashboards as $dashboard) {
                $response = $this->actingAs($user, 'web')
                    ->get(route($dashboard['route']))
                    ->assertOk()
                    ->assertSee('data-testid="internal-report-dashboard"', false)
                    ->assertSee('data-testid="internal-report-breakdown-card"', false)
                    ->assertSee('data-testid="internal-report-trend-card"', false)
                    ->assertSee('data-testid="internal-report-actions-card"', false)
                    ->assertSee('data-testid="internal-report-drilldown-card"', false)
                    ->assertSeeText($dashboard['title'])
                    ->assertSeeText($dashboard['breakdown'])
                    ->assertSeeText($dashboard['trend'])
                    ->assertSee('href="' . route($dashboard['primary_link']) . '"', false)
                    ->assertDontSeeText('i8a-shopify-token-001')
                    ->assertDontSeeText('Internal escalation note for leadership only.');

                $this->assertHasNavigationLink($response, 'internal.reports.index');
            }
        }
    }

    #[Test]
    public function carrier_manager_and_external_users_are_forbidden_from_each_dashboard(): void
    {
        foreach ([
            'e2e.internal.carrier_manager@example.test',
            'e2e.c.organization_owner@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            foreach ($this->dashboards as $dashboard) {
                $this->assertForbiddenInternalSurface(
                    $this->actingAs($user, 'web')->get(route($dashboard['route']))
                );
            }
        }
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
