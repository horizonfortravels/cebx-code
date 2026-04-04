<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Shipment;
use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternalTicketManagementWebTest extends TestCase
{
    use RefreshDatabase;

    private Account $accountA;
    private Account $accountC;
    private Shipment $shipmentC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);

        $this->accountA = $this->accountBySlug('e2e-account-a');
        $this->accountC = $this->accountBySlug('e2e-account-c');
        $this->shipmentC = $this->shipmentByReference('SHP-I5A-C-001');
    }

    #[Test]
    public function super_admin_and_support_can_create_general_tickets_from_the_helpdesk_center(): void
    {
        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);
            $subject = 'I9B general ticket ' . $user->email;

            $this->actingAs($user, 'web')
                ->get(route('internal.tickets.create'))
                ->assertOk()
                ->assertSee('data-testid="internal-ticket-create-form"', false)
                ->assertSee('data-testid="internal-ticket-account-select"', false);

            $response = $this->actingAs($user, 'web')
                ->post(route('internal.tickets.store'), [
                    'account_id' => (string) $this->accountA->id,
                    'subject' => $subject,
                    'description' => 'Need a safe general follow-up for the seeded account with clear internal context.',
                    'category' => 'general',
                    'priority' => 'medium',
                ]);

            $ticket = $this->ticketBySubject($subject);

            $response->assertRedirect(route('internal.tickets.show', $ticket));

            $this->assertSame((string) $this->accountA->id, (string) $ticket->account_id);
            $this->assertSame((string) $this->accountOwner($this->accountA)->id, (string) $ticket->user_id);
            $this->assertNull($this->linkedShipmentId($ticket));
            $this->assertSame('open', (string) $ticket->status);
            $this->assertSame('general', (string) $ticket->category);
            $this->assertSame('medium', (string) $ticket->priority);
            $this->assertStringContainsString('safe general follow-up', $this->ticketBody($ticket));
            $this->assertTicketAuditLogged($ticket, $user);

            $this->actingAs($user, 'web')
                ->get(route('internal.tickets.show', $ticket))
                ->assertOk()
                ->assertSeeText($subject)
                ->assertSeeText('E2E Account A')
                ->assertSeeText('No linked shipment')
                ->assertDontSee('data-testid="internal-ticket-shipment-link"', false);
        }
    }

    #[Test]
    public function support_can_create_a_shipment_linked_ticket_from_shipment_context(): void
    {
        $user = $this->userByEmail('e2e.internal.support@example.test');
        $subject = 'I9B shipment-linked ticket';

        $this->actingAs($user, 'web')
            ->get(route('internal.shipments.tickets.create', $this->shipmentC))
            ->assertOk()
            ->assertSee('data-testid="internal-ticket-linked-account-card"', false)
            ->assertSee('data-testid="internal-ticket-linked-shipment-card"', false)
            ->assertDontSee('data-testid="internal-ticket-account-select"', false)
            ->assertSeeText('E2E Account C')
            ->assertSeeText('SHP-I5A-C-001');

        $response = $this->actingAs($user, 'web')
            ->post(route('internal.tickets.store'), [
                'account_id' => (string) $this->accountC->id,
                'shipment_id' => (string) $this->shipmentC->id,
                'subject' => $subject,
                'description' => 'Shipment-linked support review is needed for the seeded organization shipment context.',
                'category' => 'shipping',
                'priority' => 'high',
            ]);

        $ticket = $this->ticketBySubject($subject);

        $response->assertRedirect(route('internal.tickets.show', $ticket));

        $this->assertSame((string) $this->accountC->id, (string) $ticket->account_id);
        $this->assertSame((string) $this->shipmentC->id, (string) $this->linkedShipmentId($ticket));
        $this->assertSame((string) $this->shipmentC->user_id, (string) $ticket->user_id);
        $this->assertTicketAuditLogged($ticket, $user);

        $this->actingAs($user, 'web')
            ->get(route('internal.tickets.show', $ticket))
            ->assertOk()
            ->assertSeeText($subject)
            ->assertSeeText('E2E Account C')
            ->assertSeeText('Organization')
            ->assertSeeText('SHP-I5A-C-001')
            ->assertSee('data-testid="internal-ticket-shipment-link"', false)
            ->assertSee('data-testid="internal-ticket-account-link"', false);
    }

    #[Test]
    public function support_sees_ticket_create_entry_points_but_ops_readonly_does_not(): void
    {
        $support = $this->userByEmail('e2e.internal.support@example.test');
        $opsReadonly = $this->userByEmail('e2e.internal.ops_readonly@example.test');

        $this->actingAs($support, 'web')
            ->get(route('internal.tickets.index'))
            ->assertOk()
            ->assertSee('data-testid="internal-tickets-create-link"', false);

        $this->actingAs($support, 'web')
            ->get(route('internal.accounts.show', $this->accountC))
            ->assertOk()
            ->assertSee('data-testid="account-create-linked-ticket-link"', false);

        $this->actingAs($support, 'web')
            ->get(route('internal.shipments.show', $this->shipmentC))
            ->assertOk()
            ->assertSee('data-testid="internal-shipment-create-linked-ticket-link"', false);

        $this->actingAs($opsReadonly, 'web')
            ->get(route('internal.tickets.index'))
            ->assertOk()
            ->assertDontSee('data-testid="internal-tickets-create-link"', false);

        $this->actingAs($opsReadonly, 'web')
            ->get(route('internal.shipments.show', $this->shipmentC))
            ->assertOk()
            ->assertDontSee('data-testid="internal-shipment-create-linked-ticket-link"', false);
    }

    #[Test]
    public function shipment_link_must_belong_to_the_selected_account(): void
    {
        $support = $this->userByEmail('e2e.internal.support@example.test');
        $subject = 'I9B mismatched shipment ticket';

        $this->actingAs($support, 'web')
            ->from(route('internal.tickets.create'))
            ->post(route('internal.tickets.store'), [
                'account_id' => (string) $this->accountA->id,
                'shipment_id' => (string) $this->shipmentC->id,
                'subject' => $subject,
                'description' => 'This should fail because the shipment does not belong to the selected account.',
                'category' => 'shipping',
                'priority' => 'high',
            ])
            ->assertRedirect(route('internal.tickets.create'))
            ->assertSessionHasErrors('shipment_id');

        $this->assertNull(
            SupportTicket::query()
                ->withoutGlobalScopes()
                ->where('subject', $subject)
                ->first()
        );
    }

    #[Test]
    public function ops_readonly_carrier_manager_and_external_users_are_forbidden_from_ticket_create_routes_and_store(): void
    {
        foreach ([
            'e2e.internal.ops_readonly@example.test',
            'e2e.internal.carrier_manager@example.test',
            'e2e.c.organization_owner@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->get(route('internal.tickets.create'))
            );

            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->get(route('internal.accounts.tickets.create', $this->accountC))
            );

            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->get(route('internal.shipments.tickets.create', $this->shipmentC))
            );

            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->post(route('internal.tickets.store'), [
                    'account_id' => (string) $this->accountA->id,
                    'subject' => 'Forbidden ticket create',
                    'description' => 'This should never create a ticket.',
                    'category' => 'general',
                    'priority' => 'medium',
                ])
            );
        }
    }

    private function accountBySlug(string $slug): Account
    {
        return Account::query()
            ->withoutGlobalScopes()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    private function shipmentByReference(string $reference): Shipment
    {
        return Shipment::query()
            ->withoutGlobalScopes()
            ->where('reference_number', $reference)
            ->firstOrFail();
    }

    private function ticketBySubject(string $subject): SupportTicket
    {
        return SupportTicket::query()
            ->withoutGlobalScopes()
            ->where('subject', $subject)
            ->latest('created_at')
            ->firstOrFail();
    }

    private function accountOwner(Account $account): User
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('account_id', (string) $account->id)
            ->when(
                Schema::hasColumn('users', 'user_type'),
                static fn ($query) => $query->where('user_type', 'external')
            )
            ->orderByDesc('is_owner')
            ->orderBy('name')
            ->firstOrFail();
    }

    private function userByEmail(string $email): User
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('email', $email)
            ->firstOrFail();
    }

    private function linkedShipmentId(SupportTicket $ticket): ?string
    {
        foreach (['shipment_id', 'entity_id'] as $column) {
            $value = trim((string) $ticket->getAttribute($column));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function ticketBody(SupportTicket $ticket): string
    {
        foreach (['body', 'description'] as $column) {
            $value = trim((string) $ticket->getAttribute($column));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function assertTicketAuditLogged(SupportTicket $ticket, User $actor): void
    {
        $query = AuditLog::query()->withoutGlobalScopes()
            ->where('user_id', (string) $actor->id);

        if (Schema::hasColumn('audit_logs', 'action')) {
            $query->where('action', 'support.ticket_created');
        } else {
            $query->where('event', 'support.ticket_created');
        }

        if (Schema::hasColumn('audit_logs', 'entity_id')) {
            $query->where('entity_id', (string) $ticket->id);
        }

        $this->assertTrue($query->exists(), 'Expected support.ticket_created audit entry.');
    }

    private function assertForbiddenInternalSurface(TestResponse $response): void
    {
        $response->assertForbidden()
            ->assertSee('class="panel"', false)
            ->assertSeeText('403')
            ->assertDontSeeText('Internal Server Error');
    }
}
