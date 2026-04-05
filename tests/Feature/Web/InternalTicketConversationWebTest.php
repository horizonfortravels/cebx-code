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
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
class InternalTicketConversationWebTest extends TicketWebTestCase
{
    private Account $accountC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountC = $this->accountBySlug('e2e-account-c');
    }

    #[Test]
    public function super_admin_and_support_can_add_customer_visible_staff_replies(): void
    {
        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
        ] as $email) {
            $actor = $this->userByEmail($email);
            $ticket = $this->createTicket('I9C staff reply ' . $email);
            $replyBody = 'I9C customer-visible reply from ' . $actor->email;

            $response = $this->actingAs($actor, 'web')
                ->post(route('internal.tickets.reply.store', $ticket), [
                    'body' => $replyBody,
                ]);

            $response->assertRedirect(route('internal.tickets.show', $ticket));

            $reply = SupportTicketReply::query()
                ->withoutGlobalScopes()
                ->where('ticket_id', (string) $ticket->id)
                ->where('body', $replyBody)
                ->latest('created_at')
                ->firstOrFail();

            $this->assertFalse((bool) $reply->is_internal_note);

            $audit = $this->auditRowsForTicket($ticket, 'support.ticket_reply_added', $actor)->last();
            $this->assertInstanceOf(AuditLog::class, $audit);
            $this->assertSame((string) $reply->id, (string) data_get($audit?->new_values, 'reply_id'));
            $this->assertStringNotContainsString($replyBody, json_encode($audit?->new_values));
            $this->assertStringNotContainsString($replyBody, json_encode($audit?->metadata));

            $this->actingAs($actor, 'web')
                ->get(route('internal.tickets.show', $ticket))
                ->assertOk()
                ->assertSeeText($replyBody)
                ->assertSeeText('رد الدعم');
        }
    }

    #[Test]
    public function support_reply_is_visible_to_external_users_but_internal_notes_are_not(): void
    {
        $support = $this->userByEmail('e2e.internal.support@example.test');
        $external = $this->userByEmail('e2e.c.organization_owner@example.test');
        $ticket = $this->createTicket('I9C separation ticket');
        $replyBody = 'I9C customer-visible support answer for the organization requester.';
        $noteBody = 'I9C internal-only note that must never leak externally.';

        $this->actingAs($support, 'web')
            ->post(route('internal.tickets.reply.store', $ticket), [
                'body' => $replyBody,
            ])
            ->assertRedirect(route('internal.tickets.show', $ticket));

        $this->actingAs($support, 'web')
            ->post(route('internal.tickets.notes.store', $ticket), [
                'body' => $noteBody,
            ])
            ->assertRedirect(route('internal.tickets.show', $ticket));

        $internalNote = SupportTicketReply::query()
            ->withoutGlobalScopes()
            ->where('ticket_id', (string) $ticket->id)
            ->where('body', $noteBody)
            ->latest('created_at')
            ->firstOrFail();

        $this->assertTrue((bool) $internalNote->is_internal_note);

        $noteAudit = $this->auditRowsForTicket($ticket, 'support.ticket_note_added', $support)->last();
        $this->assertInstanceOf(AuditLog::class, $noteAudit);
        $this->assertStringNotContainsString($noteBody, json_encode($noteAudit?->new_values));
        $this->assertStringNotContainsString($noteBody, json_encode($noteAudit?->metadata));

        $this->actingAs($support, 'web')
            ->get(route('internal.tickets.show', $ticket))
            ->assertOk()
            ->assertSeeText($replyBody)
            ->assertSeeText($noteBody)
            ->assertSee('data-testid="internal-ticket-reply-form"', false)
            ->assertSee('data-testid="internal-ticket-note-form"', false);

        $this->actingAs($external, 'web')
            ->get(route('support.show', $ticket))
            ->assertOk()
            ->assertSeeText($replyBody)
            ->assertDontSeeText($noteBody)
            ->assertSee('data-testid="external-ticket-thread-card"', false)
            ->assertSee('data-testid="external-ticket-reply-form"', false);
    }

    #[Test]
    public function ops_readonly_is_denied_from_internal_reply_and_note_mutation_routes(): void
    {
        $opsReadonly = $this->userByEmail('e2e.internal.ops_readonly@example.test');
        $ticket = $this->createTicket('I9C ops readonly denied');

        $this->assertForbiddenInternalSurface(
            $this->actingAs($opsReadonly, 'web')->post(route('internal.tickets.reply.store', $ticket), [
                'body' => 'This reply must be rejected.',
            ])
        );

        $this->assertForbiddenInternalSurface(
            $this->actingAs($opsReadonly, 'web')->post(route('internal.tickets.notes.store', $ticket), [
                'body' => 'This note must be rejected.',
            ])
        );
    }

    #[Test]
    public function external_customer_replies_stay_customer_visible_and_never_become_internal_notes(): void
    {
        $support = $this->userByEmail('e2e.internal.support@example.test');
        $external = $this->userByEmail('e2e.c.organization_owner@example.test');
        $ticket = $this->createTicket('I9C external customer reply');
        $replyBody = 'I9C external customer follow-up after the first support review.';

        $this->actingAs($external, 'web')
            ->post(route('support.reply', $ticket), [
                'body' => $replyBody,
            ])
            ->assertRedirect();

        $reply = SupportTicketReply::query()
            ->withoutGlobalScopes()
            ->where('ticket_id', (string) $ticket->id)
            ->where('body', $replyBody)
            ->latest('created_at')
            ->firstOrFail();

        $this->assertFalse((bool) $reply->is_internal_note);

        $this->actingAs($support, 'web')
            ->get(route('internal.tickets.show', $ticket))
            ->assertOk()
            ->assertSeeText($replyBody)
            ->assertSeeText('رد مقدم الطلب');
    }

    private function createTicket(string $subject): SupportTicket
    {
        $requester = $this->userByEmail('e2e.c.organization_owner@example.test');
        $ticketNumber = 'TKT-I9C-' . strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 8));

        return SupportTicket::query()
            ->withoutGlobalScopes()
            ->create($this->filterExistingColumns('support_tickets', [
                'account_id' => (string) $this->accountC->id,
                'user_id' => (string) $requester->id,
                'reference_number' => $ticketNumber,
                'ticket_number' => $ticketNumber,
                'subject' => $subject,
                'body' => 'I9C support conversation verification ticket body.',
                'description' => 'I9C support conversation verification ticket body.',
                'category' => 'general',
                'priority' => 'medium',
                'status' => 'open',
                'assigned_to' => null,
                'assigned_team' => 'support',
            ]));
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
