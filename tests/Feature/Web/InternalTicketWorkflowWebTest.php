<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
class InternalTicketWorkflowWebTest extends TicketWebTestCase
{
    private Account $accountC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountC = $this->accountBySlug('e2e-account-c');
    }

    #[Test]
    public function super_admin_and_support_can_move_a_ticket_into_in_progress(): void
    {
        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
        ] as $email) {
            $actor = $this->userByEmail($email);
            $ticket = $this->createTicket('I9C status start ' . $email);

            $response = $this->actingAs($actor, 'web')
                ->post(route('internal.tickets.status', $ticket), [
                    'status' => 'in_progress',
                ]);

            $response->assertRedirect(route('internal.tickets.show', $ticket));

            $ticket->refresh();

            $this->assertSame('in_progress', (string) $ticket->status);
            $this->assertTicketAuditLogged($ticket, $actor, 'support.ticket_status_changed');
        }
    }

    #[Test]
    public function support_can_apply_waiting_resolved_closed_and_reopen_transitions_with_internal_reasons(): void
    {
        $actor = $this->userByEmail('e2e.internal.support@example.test');
        $ticket = $this->createTicket('I9C workflow transitions');

        $transitions = [
            ['status' => 'waiting_customer', 'note' => 'Waiting for the customer to confirm the requested shipping details.'],
            ['status' => 'resolved', 'note' => 'Support confirmed the shipment question was resolved safely.'],
            ['status' => 'closed', 'note' => 'Support closed the ticket after the customer confirmation window passed.'],
            ['status' => 'open', 'note' => null],
        ];

        foreach ($transitions as $transition) {
            $response = $this->actingAs($actor, 'web')
                ->post(route('internal.tickets.status', $ticket), [
                    'status' => $transition['status'],
                    'note' => $transition['note'],
                ]);

            $response->assertRedirect(route('internal.tickets.show', $ticket));
            $ticket->refresh();
            $this->assertSame($transition['status'], (string) $ticket->status);
        }

        if (Schema::hasTable('support_ticket_replies')) {
            $notes = SupportTicketReply::query()
                ->withoutGlobalScopes()
                ->where('ticket_id', (string) $ticket->id)
                ->where('is_internal_note', true)
                ->orderBy('created_at')
                ->pluck('body')
                ->all();

            $this->assertCount(3, $notes);
            $this->assertStringContainsString('Workflow update: Open -> Waiting on customer.', (string) $notes[0]);
            $this->assertStringContainsString('Workflow update: Waiting on customer -> Resolved.', (string) $notes[1]);
            $this->assertStringContainsString('Workflow update: Resolved -> Closed.', (string) $notes[2]);
        }

        $audits = $this->auditRowsForTicket($ticket, 'support.ticket_status_changed', $actor);
        $this->assertCount(4, $audits);

        foreach ($audits as $audit) {
            $this->assertArrayNotHasKey('status_note', (array) $audit->new_values);
        }
    }

    #[Test]
    public function support_can_assign_a_ticket_and_the_audit_remains_safe(): void
    {
        $actor = $this->userByEmail('e2e.internal.support@example.test');
        $assignee = $this->userByEmail('e2e.internal.super_admin@example.test');
        $ticket = $this->createTicket('I9C assignment flow');

        $this->actingAs($actor, 'web')
            ->get(route('internal.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('data-testid="internal-ticket-assignment-form"', false)
            ->assertSeeText($actor->email)
            ->assertSeeText($assignee->email)
            ->assertDontSeeText('e2e.internal.ops_readonly@example.test')
            ->assertDontSeeText('e2e.internal.carrier_manager@example.test');

        $response = $this->actingAs($actor, 'web')
            ->post(route('internal.tickets.assignment', $ticket), [
                'assigned_to' => (string) $assignee->id,
                'note' => 'Escalating the ticket to the platform lead for a safe follow-up.',
            ]);

        $response->assertRedirect(route('internal.tickets.show', $ticket));

        $ticket->refresh();

        $this->assertSame((string) $assignee->id, (string) $ticket->assigned_to);

        if (Schema::hasTable('support_ticket_replies')) {
            $note = SupportTicketReply::query()
                ->withoutGlobalScopes()
                ->where('ticket_id', (string) $ticket->id)
                ->where('is_internal_note', true)
                ->latest('created_at')
                ->firstOrFail();

            $this->assertStringContainsString('Assignment update: Unassigned -> ' . $assignee->name . '.', (string) $note->body);
            $this->assertStringContainsString('Escalating the ticket to the platform lead', (string) $note->body);
        }

        $audit = $this->auditRowsForTicket($ticket, 'support.ticket_assigned', $actor)->firstOrFail();
        $this->assertSame((string) $assignee->id, (string) data_get($audit->new_values, 'assigned_to'));
        $this->assertArrayNotHasKey('assignment_note', (array) $audit->new_values);
        $this->assertTrue((bool) data_get($audit->metadata, 'note_recorded'));
        $this->assertStringNotContainsString('Escalating the ticket to the platform lead', json_encode($audit->metadata));
    }

    #[Test]
    public function support_can_add_internal_notes_and_ops_readonly_can_only_view_them(): void
    {
        $support = $this->userByEmail('e2e.internal.support@example.test');
        $opsReadonly = $this->userByEmail('e2e.internal.ops_readonly@example.test');
        $ticket = $this->createTicket('I9C internal note thread');
        $body = 'I9C internal note for support-only workflow context.';

        $response = $this->actingAs($support, 'web')
            ->post(route('internal.tickets.notes.store', $ticket), [
                'body' => $body,
            ]);

        $response->assertRedirect(route('internal.tickets.show', $ticket));

        $note = SupportTicketReply::query()
            ->withoutGlobalScopes()
            ->where('ticket_id', (string) $ticket->id)
            ->where('is_internal_note', true)
            ->latest('created_at')
            ->firstOrFail();

        $this->assertSame($body, (string) $note->body);

        $audit = $this->auditRowsForTicket($ticket, 'support.ticket_note_added', $support)->firstOrFail();
        $this->assertSame((string) $note->id, (string) data_get($audit->new_values, 'note_id'));
        $this->assertSame(mb_strlen($body), (int) data_get($audit->new_values, 'note_length'));
        $this->assertStringNotContainsString($body, json_encode($audit->new_values));
        $this->assertStringNotContainsString($body, json_encode($audit->metadata));

        $this->actingAs($opsReadonly, 'web')
            ->get(route('internal.tickets.show', $ticket))
            ->assertOk()
            ->assertSeeText($body)
            ->assertDontSee('data-testid="internal-ticket-status-form"', false)
            ->assertDontSee('data-testid="internal-ticket-assignment-form"', false)
            ->assertDontSee('data-testid="internal-ticket-note-form"', false);

        $this->assertForbiddenInternalSurface(
            $this->actingAs($opsReadonly, 'web')->post(route('internal.tickets.status', $ticket), [
                'status' => 'in_progress',
            ])
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($opsReadonly, 'web')->post(route('internal.tickets.assignment', $ticket), [
                'assigned_to' => (string) $support->id,
            ])
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($opsReadonly, 'web')->post(route('internal.tickets.notes.store', $ticket), [
                'body' => 'This must be rejected.',
            ])
        );
    }

    #[Test]
    public function carrier_manager_and_external_users_are_forbidden_from_ticket_workflow_routes(): void
    {
        $ticket = $this->createTicket('I9C forbidden workflow');
        $support = $this->userByEmail('e2e.internal.support@example.test');

        foreach ([
            'e2e.internal.carrier_manager@example.test',
            'e2e.c.organization_owner@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->post(route('internal.tickets.status', $ticket), [
                    'status' => 'in_progress',
                ])
            );

            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->post(route('internal.tickets.assignment', $ticket), [
                    'assigned_to' => (string) $support->id,
                ])
            );

            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->post(route('internal.tickets.notes.store', $ticket), [
                    'body' => 'Forbidden note',
                ])
            );
        }
    }

    private function createTicket(string $subject): SupportTicket
    {
        $requester = $this->externalRequester($this->accountC);
        $ticketNumber = 'TKT-I9C-' . strtoupper(substr(str_replace('-', '', (string) fake()->uuid()), 0, 8));

        $ticket = SupportTicket::query()
            ->withoutGlobalScopes()
            ->create($this->filterExistingColumns('support_tickets', [
                'account_id' => (string) $this->accountC->id,
                'user_id' => (string) $requester->id,
                'reference_number' => $ticketNumber,
                'ticket_number' => $ticketNumber,
                'subject' => $subject,
                'body' => 'Internal workflow verification ticket body.',
                'description' => 'Internal workflow verification ticket body.',
                'category' => 'general',
                'priority' => 'medium',
                'status' => 'open',
                'assigned_to' => null,
                'assigned_team' => null,
            ]));

        return $ticket->fresh(['account.organizationProfile', 'user', 'assignee']);
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

    private function assertTicketAuditLogged(SupportTicket $ticket, User $actor, string $action): void
    {
        $this->assertTrue(
            $this->auditRowsForTicket($ticket, $action, $actor)->isNotEmpty(),
            'Expected ' . $action . ' audit entry.'
        );
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
