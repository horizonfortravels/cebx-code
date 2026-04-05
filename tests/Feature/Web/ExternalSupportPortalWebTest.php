<?php

namespace Tests\Feature\Web;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
class ExternalSupportPortalWebTest extends TicketWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function individual_user_can_create_a_help_request_and_only_see_their_account_scope(): void
    {
        $individual = $this->userByEmail('e2e.a.individual@example.test');
        $otherTicket = $this->ticketByNumber('TKT-I9A-C-001');

        $this->actingAs($individual, 'web')
            ->get(route('support.index'))
            ->assertOk()
            ->assertSeeText('TKT-I9A-A-001')
            ->assertDontSeeText('TKT-I9A-C-001');

        $subject = 'I9 final external individual ticket';
        $body = 'External individual account holder created a help request from the support center.';

        $this->actingAs($individual, 'web')
            ->post(route('support.store'), [
                'subject' => $subject,
                'category' => 'general',
                'priority' => 'medium',
                'body' => $body,
            ])
            ->assertRedirect();

        $ticket = $this->ticketBySubject($subject);

        $this->assertSame((string) $individual->account_id, (string) $ticket->account_id);
        $this->assertSame((string) $individual->id, (string) $ticket->user_id);

        $this->actingAs($individual, 'web')
            ->get(route('support.show', $ticket))
            ->assertOk()
            ->assertSee('data-testid="external-ticket-thread-card"', false)
            ->assertSeeText($subject)
            ->assertSeeText($body)
            ->assertDontSeeText('Internal escalation note for leadership only.');

        $this->actingAs($individual, 'web')
            ->get(route('support.show', $otherTicket))
            ->assertNotFound();
    }

    #[Test]
    public function organization_user_can_create_a_help_request_and_only_view_their_own_ticket_while_same_tenant_and_cross_tenant_users_cannot(): void
    {
        $owner = $this->userByEmail('e2e.c.organization_owner@example.test');
        $sameTenantMember = $this->userByEmail('e2e.c.organization_admin@example.test');
        $crossTenantUser = $this->userByEmail('e2e.d.organization_owner@example.test');

        $subject = 'I9 final organization support ticket';
        $body = 'Organization owner created a help request that same-tenant members may review.';

        $this->actingAs($owner, 'web')
            ->post(route('support.store'), [
                'subject' => $subject,
                'category' => 'shipment',
                'priority' => 'high',
                'body' => $body,
            ])
            ->assertRedirect();

        $ticket = $this->ticketBySubject($subject);

        $this->assertSame((string) $owner->account_id, (string) $ticket->account_id);

        $this->actingAs($owner, 'web')
            ->get(route('support.index'))
            ->assertOk()
            ->assertSeeText($subject);

        $this->actingAs($sameTenantMember, 'web')
            ->get(route('support.index'))
            ->assertOk()
            ->assertDontSeeText($subject);

        $this->actingAs($sameTenantMember, 'web')
            ->get(route('support.show', $ticket))
            ->assertNotFound();

        $this->actingAs($crossTenantUser, 'web')
            ->get(route('support.show', $ticket))
            ->assertNotFound();
    }

    private function ticketByNumber(string $number): SupportTicket
    {
        $query = SupportTicket::query()->withoutGlobalScopes();
        $column = Schema::hasColumn('support_tickets', 'ticket_number') ? 'ticket_number' : 'reference_number';

        return $query->where($column, $number)->firstOrFail();
    }

    private function ticketBySubject(string $subject): SupportTicket
    {
        return SupportTicket::query()
            ->withoutGlobalScopes()
            ->where('subject', $subject)
            ->latest('created_at')
            ->firstOrFail();
    }

    private function userByEmail(string $email): User
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('email', $email)
            ->firstOrFail();
    }
}
