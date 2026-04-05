<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

class InternalReportsDashboardsWebTest extends SeededReadOnlyWebTestCase
{
    /**
     * @var array<string, array{route: string, drilldowns: array<int, string>}>
     */
    private array $dashboards = [
        'shipments' => [
            'route' => 'internal.reports.shipments',
            'drilldowns' => [
                'internal.shipments.index',
                'internal.kyc.index',
                'internal.tickets.index',
            ],
        ],
        'kyc' => [
            'route' => 'internal.reports.kyc',
            'drilldowns' => [
                'internal.kyc.index',
                'internal.compliance.index',
                'internal.billing.index',
            ],
        ],
        'billing' => [
            'route' => 'internal.reports.billing',
            'drilldowns' => [
                'internal.billing.index',
                'internal.shipments.index',
                'internal.kyc.index',
            ],
        ],
        'compliance' => [
            'route' => 'internal.reports.compliance',
            'drilldowns' => [
                'internal.compliance.index',
                'internal.kyc.index',
                'internal.billing.index',
            ],
        ],
        'carriers' => [
            'route' => 'internal.reports.carriers',
            'drilldowns' => [
                'internal.carriers.index',
            ],
        ],
        'tickets' => [
            'route' => 'internal.reports.tickets',
            'drilldowns' => [
                'internal.tickets.index',
                'internal.shipments.index',
            ],
        ],
    ];

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
                    ->assertDontSeeText('i8a-shopify-token-001')
                    ->assertDontSeeText('fedex-client-secret-001')
                    ->assertDontSeeText('Internal escalation note for leadership only.');

                foreach ($dashboard['drilldowns'] as $routeName) {
                    $response->assertSee('href="'.route($routeName).'"', false);
                }

                $this->assertHasNavigationLink($response, 'internal.reports.index');
            }
        }
    }

    #[Test]
    public function carrier_manager_can_open_only_the_carrier_dashboard(): void
    {
        $user = $this->userByEmail('e2e.internal.carrier_manager@example.test');

        $this->actingAs($user, 'web')
            ->get(route('internal.reports.carriers'))
            ->assertOk()
            ->assertSee('data-testid="internal-report-dashboard"', false)
            ->assertSee('href="'.route('internal.carriers.index').'"', false)
            ->assertDontSeeText('i8a-shopify-token-001')
            ->assertDontSeeText('fedex-client-secret-001');

        foreach (['shipments', 'kyc', 'billing', 'compliance', 'tickets'] as $domain) {
            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->get(route('internal.reports.'.$domain))
            );
        }
    }

    #[Test]
    public function external_users_are_forbidden_from_each_dashboard(): void
    {
        $user = $this->userByEmail('e2e.c.organization_owner@example.test');

        foreach ($this->dashboards as $dashboard) {
            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->get(route($dashboard['route']))
            );
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
        $response->assertSee('href="'.route($routeName).'"', false);
    }

    private function assertForbiddenInternalSurface(TestResponse $response): void
    {
        $response->assertForbidden()
            ->assertSee('class="panel"', false)
            ->assertSeeText('403')
            ->assertDontSeeText('Internal Server Error');
    }
}
