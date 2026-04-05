<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

class InternalReportsHubWebTest extends SeededReadOnlyWebTestCase
{
    #[Test]
    public function super_admin_support_and_ops_readonly_can_open_the_internal_reports_hub_and_see_operational_cards(): void
    {
        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $response = $this->actingAs($this->userByEmail($email), 'web')
                ->get(route('internal.reports.index'))
                ->assertOk()
                ->assertSee('data-testid="internal-reports-grid"', false)
                ->assertSee('data-testid="internal-report-card-shipments"', false)
                ->assertSee('data-testid="internal-report-card-kyc"', false)
                ->assertSee('data-testid="internal-report-card-billing"', false)
                ->assertSee('data-testid="internal-report-card-compliance"', false)
                ->assertSee('data-testid="internal-report-card-carriers"', false)
                ->assertSee('data-testid="internal-report-card-tickets"', false)
                ->assertSee('href="'.route('internal.shipments.index').'"', false)
                ->assertSee('href="'.route('internal.carriers.index').'"', false)
                ->assertSee('href="'.route('internal.billing.index').'"', false)
                ->assertDontSeeText('i8a-shopify-token-001')
                ->assertDontSeeText('fedex-client-secret-001')
                ->assertDontSeeText('Internal escalation note for leadership only.');

            $this->assertHasNavigationLink($response, 'internal.reports.index');
        }
    }

    #[Test]
    public function reports_hub_supports_search_and_domain_filtering(): void
    {
        $viewer = $this->userByEmail('e2e.internal.super_admin@example.test');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.reports.index', ['q' => 'الناقلين']))
            ->assertOk()
            ->assertSee('data-testid="internal-report-card-carriers"', false)
            ->assertDontSee('data-testid="internal-report-card-shipments"', false)
            ->assertDontSee('data-testid="internal-report-card-tickets"', false);

        $this->actingAs($viewer, 'web')
            ->get(route('internal.reports.index', ['domain' => 'carriers']))
            ->assertOk()
            ->assertSee('data-testid="internal-report-card-carriers"', false)
            ->assertDontSee('data-testid="internal-report-card-shipments"', false)
            ->assertDontSee('data-testid="internal-report-card-billing"', false);
    }

    #[Test]
    public function super_admin_support_ops_readonly_and_carrier_manager_see_reports_navigation(): void
    {
        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
            'e2e.internal.carrier_manager@example.test',
        ] as $email) {
            $page = $this->actingAs($this->userByEmail($email), 'web')
                ->get(route('internal.home'))
                ->assertOk();

            $this->assertHasNavigationLink($page, 'internal.reports.index');
        }
    }

    #[Test]
    public function carrier_manager_can_open_the_reports_hub_but_only_sees_the_carrier_slice(): void
    {
        $response = $this->actingAs($this->userByEmail('e2e.internal.carrier_manager@example.test'), 'web')
            ->get(route('internal.reports.index'))
            ->assertOk()
            ->assertSee('data-testid="internal-report-card-carriers"', false)
            ->assertDontSee('data-testid="internal-report-card-shipments"', false)
            ->assertDontSee('data-testid="internal-report-card-kyc"', false)
            ->assertDontSee('data-testid="internal-report-card-billing"', false)
            ->assertDontSee('data-testid="internal-report-card-compliance"', false)
            ->assertDontSee('data-testid="internal-report-card-tickets"', false)
            ->assertDontSee('data-testid="internal-report-card-executive"', false)
            ->assertSee('href="'.route('internal.carriers.index').'"', false)
            ->assertDontSeeText('fedex-client-secret-001')
            ->assertDontSeeText('Internal escalation note for leadership only.');

        $this->assertHasNavigationLink($response, 'internal.reports.index');
    }

    #[Test]
    public function external_users_are_forbidden_from_the_internal_reports_hub(): void
    {
        foreach ([
            'e2e.c.organization_owner@example.test',
        ] as $email) {
            $this->assertForbiddenInternalSurface(
                $this->actingAs($this->userByEmail($email), 'web')->get(route('internal.reports.index'))
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
