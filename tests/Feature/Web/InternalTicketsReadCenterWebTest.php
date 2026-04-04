<?php

namespace Tests\Feature\Web;

use App\Models\SupportTicket;
use App\Models\User;
use App\Services\InternalTicketReadService;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternalTicketsReadCenterWebTest extends TestCase
{
    use RefreshDatabase;

    private SupportTicket $shippingTicket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);

        $this->shippingTicket = $this->ticketByNumber('TKT-I9A-C-001');
    }

    #[Test]
    public function super_admin_support_and_ops_readonly_can_open_tickets_index_and_detail(): void
    {
        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);
            $visibleDetail = app(InternalTicketReadService::class)->findVisibleDetail($user, (string) $this->shippingTicket->id);

            $this->assertIsArray($visibleDetail);

            $index = $this->actingAs($user, 'web')
                ->get(route('internal.tickets.index'))
                ->assertOk()
                ->assertSee('data-testid="internal-tickets-table"', false)
                ->assertSeeText('TKT-I9A-C-001')
                ->assertSeeText('TKT-I9A-A-001')
                ->assertSeeText('Delayed organization shipment follow-up')
                ->assertSeeText('Wallet charge clarification requested');

            $this->assertHasNavigationLink($index, 'internal.tickets.index');

            $detail = $this->actingAs($user, 'web')
                ->get(route('internal.tickets.show', (string) $this->shippingTicket->id))
                ->assertOk()
                ->assertSee('data-testid="internal-ticket-summary-card"', false)
                ->assertSee('data-testid="internal-ticket-context-card"', false)
                ->assertSee('data-testid="internal-ticket-request-card"', false)
                ->assertSee('data-testid="internal-ticket-activity-card"', false)
                ->assertSeeText('TKT-I9A-C-001')
                ->assertSeeText('Delayed organization shipment follow-up')
                ->assertSeeText((string) $visibleDetail['category_label'])
                ->assertSeeText((string) $visibleDetail['status_label'])
                ->assertSeeText('E2E Account C')
                ->assertSeeText((string) data_get($visibleDetail, 'account_summary.type_label'))
                ->assertSeeText('E2E Account C Logistics LLC')
                ->assertSeeText('SHP-I5A-C-001')
                ->assertSeeText('Support reply')
                ->assertSee('data-testid="internal-ticket-notes-card"', false)
                ->assertSeeText('Internal escalation note for leadership only.')
                ->assertSee('data-testid="internal-ticket-workflow-activity-card"', false);

            if ($email === 'e2e.internal.ops_readonly@example.test') {
                $detail->assertDontSee('data-testid="internal-ticket-reply-form"', false)
                    ->assertDontSee('data-testid="internal-ticket-note-form"', false)
                    ->assertDontSee('data-testid="internal-ticket-status-form"', false)
                    ->assertDontSee('data-testid="internal-ticket-assignment-form"', false)
                    ->assertDontSee('data-testid="internal-ticket-triage-form"', false);
            } else {
                $detail->assertSee('data-testid="internal-ticket-reply-form"', false)
                    ->assertSee('data-testid="internal-ticket-note-form"', false)
                    ->assertSee('data-testid="internal-ticket-status-form"', false)
                    ->assertSee('data-testid="internal-ticket-assignment-form"', false)
                    ->assertSee('data-testid="internal-ticket-triage-form"', false);
            }

            if ($email === 'e2e.internal.ops_readonly@example.test') {
                $detail->assertDontSee('data-testid="internal-ticket-account-link"', false);
            } else {
                $detail->assertSee('data-testid="internal-ticket-account-link"', false)
                    ->assertSee('href="' . route('internal.accounts.show', $this->shippingTicket->account) . '"', false);
            }

            $detail->assertSee('data-testid="internal-ticket-shipment-link"', false)
                ->assertSee('href="' . route('internal.shipments.show', $this->shippingTicket->shipment) . '"', false);
        }
    }

    #[Test]
    public function tickets_index_supports_search_and_basic_filters(): void
    {
        $viewer = $this->userByEmail('e2e.internal.super_admin@example.test');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.tickets.index', ['q' => 'TKT-I9A-C-001']))
            ->assertOk()
            ->assertSeeText('TKT-I9A-C-001')
            ->assertDontSeeText('TKT-I9A-A-001');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.tickets.index', ['status' => 'resolved']))
            ->assertOk()
            ->assertSeeText('TKT-I9A-A-001')
            ->assertDontSeeText('TKT-I9A-C-001');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.tickets.index', ['priority' => 'urgent']))
            ->assertOk()
            ->assertSeeText('TKT-I9A-D-001')
            ->assertDontSeeText('TKT-I9A-A-001');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.tickets.index', ['category' => 'billing']))
            ->assertOk()
            ->assertSeeText('TKT-I9A-A-001')
            ->assertDontSeeText('TKT-I9A-C-001');
    }

    #[Test]
    public function super_admin_support_and_ops_readonly_see_tickets_navigation(): void
    {
        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $page = $this->actingAs($this->userByEmail($email), 'web')
                ->get(route('internal.home'))
                ->assertOk();

            $this->assertHasNavigationLink($page, 'internal.tickets.index');
        }
    }

    #[Test]
    public function carrier_manager_and_external_users_are_forbidden_from_internal_ticket_routes(): void
    {
        $carrierManager = $this->userByEmail('e2e.internal.carrier_manager@example.test');
        $externalUser = $this->userByEmail('e2e.c.organization_owner@example.test');

        foreach ([$carrierManager, $externalUser] as $user) {
            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->get(route('internal.tickets.index'))
            );

            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->get(route('internal.tickets.show', (string) $this->shippingTicket->id))
            );
        }
    }

    private function ticketByNumber(string $number): SupportTicket
    {
        $query = SupportTicket::query()->withoutGlobalScopes();
        $column = Schema::hasColumn('support_tickets', 'ticket_number') ? 'ticket_number' : 'reference_number';

        return $query->where($column, $number)->firstOrFail();
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
