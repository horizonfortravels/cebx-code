<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Shipment;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use App\Services\InternalTicketReadService;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternalTicketTriageWebTest extends TestCase
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
    public function support_can_update_priority_and_category_and_the_audit_remains_safe(): void
    {
        $actor = $this->userByEmail('e2e.internal.support@example.test');
        $ticket = $this->createTicket('I9D triage mutation');

        $this->actingAs($actor, 'web')
            ->get(route('internal.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('data-testid="internal-ticket-triage-form"', false)
            ->assertSeeText('Medium')
            ->assertSeeText('General');

        $response = $this->actingAs($actor, 'web')
            ->post(route('internal.tickets.triage', $ticket), [
                'priority' => 'urgent',
                'category' => 'shipping',
                'note' => 'Escalating triage because the delayed shipment context needs same-day support attention.',
            ]);

        $response->assertRedirect(route('internal.tickets.show', $ticket));

        $ticket->refresh();

        $this->assertSame('urgent', (string) $ticket->priority);
        $this->assertSame('shipping', (string) $ticket->category);

        if (Schema::hasTable('support_ticket_replies')) {
            $note = SupportTicketReply::query()
                ->withoutGlobalScopes()
                ->where('ticket_id', (string) $ticket->id)
                ->where('is_internal_note', true)
                ->latest('created_at')
                ->firstOrFail();

            $this->assertStringContainsString('Triage update: Priority Medium -> Urgent; Category General -> Shipping.', (string) $note->body);
            $this->assertStringContainsString('Escalating triage because the delayed shipment context needs same-day support attention.', (string) $note->body);
        }

        $audit = $this->auditRowsForTicket($ticket, 'support.ticket_triaged', $actor)->firstOrFail();
        $this->assertSame('medium', (string) data_get($audit->old_values, 'priority'));
        $this->assertSame('general', (string) data_get($audit->old_values, 'category'));
        $this->assertSame('urgent', (string) data_get($audit->new_values, 'priority'));
        $this->assertSame('shipping', (string) data_get($audit->new_values, 'category'));
        $this->assertArrayNotHasKey('triage_note', (array) $audit->new_values);
        $this->assertTrue((bool) data_get($audit->metadata, 'note_recorded'));
        $this->assertStringNotContainsString('same-day support attention', json_encode($audit->metadata));

        $this->actingAs($actor, 'web')
            ->get(route('internal.tickets.show', $ticket))
            ->assertOk()
            ->assertSeeText('Urgent')
            ->assertSeeText('Shipping')
            ->assertSeeText('Triage updated');
    }

    #[Test]
    public function tickets_index_supports_queue_triage_filters_for_status_priority_account_shipment_and_assignee(): void
    {
        $viewer = $this->userByEmail('e2e.internal.support@example.test');
        $assignee = $this->userByEmail('e2e.internal.support@example.test');

        $linkedTicket = $this->createTicket(
            subject: 'I9D triage linked ticket',
            account: $this->accountC,
            shipment: $this->shipmentC,
            status: 'in_progress',
            priority: 'high',
            category: 'shipping',
            assignee: $assignee,
        );

        $unlinkedTicket = $this->createTicket(
            subject: 'I9D triage unlinked ticket',
            account: $this->accountA,
            shipment: null,
            status: 'open',
            priority: 'low',
            category: 'billing',
            assignee: null,
        );

        $this->actingAs($viewer, 'web')
            ->get(route('internal.tickets.index', ['status' => 'in_progress']))
            ->assertOk()
            ->assertSeeText($linkedTicket->subject)
            ->assertDontSeeText($unlinkedTicket->subject);

        $this->actingAs($viewer, 'web')
            ->get(route('internal.tickets.index', ['priority' => 'low']))
            ->assertOk()
            ->assertSeeText($unlinkedTicket->subject)
            ->assertDontSeeText($linkedTicket->subject);

        $this->actingAs($viewer, 'web')
            ->get(route('internal.tickets.index', ['account_id' => (string) $this->accountC->id]))
            ->assertOk()
            ->assertSeeText($linkedTicket->subject)
            ->assertDontSeeText($unlinkedTicket->subject);

        $this->actingAs($viewer, 'web')
            ->get(route('internal.tickets.index', ['shipment_scope' => 'linked']))
            ->assertOk()
            ->assertSeeText($linkedTicket->subject)
            ->assertDontSeeText($unlinkedTicket->subject);

        $this->actingAs($viewer, 'web')
            ->get(route('internal.tickets.index', ['shipment_scope' => 'unlinked']))
            ->assertOk()
            ->assertSeeText($unlinkedTicket->subject)
            ->assertDontSeeText($linkedTicket->subject);

        $this->actingAs($viewer, 'web')
            ->get(route('internal.tickets.index', ['assignee_id' => (string) $assignee->id]))
            ->assertOk()
            ->assertSeeText($linkedTicket->subject)
            ->assertDontSeeText($unlinkedTicket->subject);

        $this->actingAs($viewer, 'web')
            ->get(route('internal.tickets.index', ['assignee_id' => InternalTicketReadService::ASSIGNEE_FILTER_UNASSIGNED]))
            ->assertOk()
            ->assertSeeText($unlinkedTicket->subject)
            ->assertDontSeeText($linkedTicket->subject);
    }

    #[Test]
    public function ops_readonly_carrier_manager_and_external_users_cannot_mutate_triage_and_ops_readonly_stays_read_only(): void
    {
        $ticket = $this->createTicket('I9D forbidden triage');
        $opsReadonly = $this->userByEmail('e2e.internal.ops_readonly@example.test');

        $this->actingAs($opsReadonly, 'web')
            ->get(route('internal.tickets.show', $ticket))
            ->assertOk()
            ->assertDontSee('data-testid="internal-ticket-triage-form"', false);

        foreach ([
            'e2e.internal.ops_readonly@example.test',
            'e2e.internal.carrier_manager@example.test',
            'e2e.c.organization_owner@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->post(route('internal.tickets.triage', $ticket), [
                    'priority' => 'low',
                    'category' => 'billing',
                    'note' => 'This mutation should be rejected.',
                ])
            );
        }
    }

    private function createTicket(
        string $subject,
        ?Account $account = null,
        ?Shipment $shipment = null,
        string $status = 'open',
        string $priority = 'medium',
        string $category = 'general',
        ?User $assignee = null,
    ): SupportTicket {
        $account ??= $this->accountC;
        $requester = $this->externalRequester($account);
        $ticketNumber = 'TKT-I9D-' . strtoupper(substr(str_replace('-', '', (string) fake()->uuid()), 0, 8));

        $ticket = SupportTicket::query()
            ->withoutGlobalScopes()
            ->create($this->filterExistingColumns('support_tickets', [
                'account_id' => (string) $account->id,
                'user_id' => (string) $requester->id,
                'reference_number' => $ticketNumber,
                'ticket_number' => $ticketNumber,
                'subject' => $subject,
                'body' => 'Internal triage verification ticket body.',
                'description' => 'Internal triage verification ticket body.',
                'category' => $category,
                'priority' => $priority,
                'status' => $status,
                'shipment_id' => $shipment?->id ? (string) $shipment->id : null,
                'entity_type' => $shipment instanceof Shipment ? 'shipment' : null,
                'entity_id' => $shipment?->id ? (string) $shipment->id : null,
                'assigned_to' => $assignee?->id ? (string) $assignee->id : null,
                'assigned_team' => $assignee?->internalRoleNames()[0] ?? null,
            ]));

        return $ticket->fresh(['account.organizationProfile', 'user', 'assignee']);
    }

    private function shipmentByReference(string $reference): Shipment
    {
        return Shipment::query()
            ->withoutGlobalScopes()
            ->where('reference_number', $reference)
            ->firstOrFail();
    }

    private function externalRequester(Account $account): User
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

    private function accountBySlug(string $slug): Account
    {
        return Account::query()
            ->withoutGlobalScopes()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    private function userByEmail(string $email): User
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('email', $email)
            ->firstOrFail();
    }

    /**
     * @return Collection<int, AuditLog>
     */
    private function auditRowsForTicket(SupportTicket $ticket, string $action, User $actor): Collection
    {
        $query = AuditLog::query()
            ->withoutGlobalScopes()
            ->where('user_id', (string) $actor->id);

        if (Schema::hasColumn('audit_logs', 'action')) {
            $query->where('action', $action);
        } else {
            $query->where('event', $action);
        }

        if (Schema::hasColumn('audit_logs', 'entity_id')) {
            $query->where('entity_id', (string) $ticket->id);
        }

        return $query->orderBy('created_at')->get();
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function filterExistingColumns(string $table, array $values): array
    {
        $filtered = [];

        foreach ($values as $column => $value) {
            if (Schema::hasColumn($table, $column)) {
                $filtered[$column] = $value;
            }
        }

        return $filtered;
    }

    private function assertForbiddenInternalSurface(TestResponse $response): void
    {
        $response->assertForbidden()
            ->assertSee('class="panel"', false)
            ->assertSeeText('403')
            ->assertDontSeeText('Internal Server Error');
    }
}
