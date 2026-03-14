<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;

class SupportTicketPolicy
{
    use AuthorizesTenantResource;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'tickets.read');
    }

    public function view(User $user, SupportTicket $ticket): bool
    {
        return $this->allowsTenantResourceAction($user, 'tickets.read', $ticket->account_id);
    }

    public function create(User $user): bool
    {
        return $this->allowsTenantAction($user, 'tickets.manage');
    }

    public function update(User $user, SupportTicket $ticket): bool
    {
        return $this->allowsTenantResourceAction($user, 'tickets.manage', $ticket->account_id);
    }

    public function delete(User $user, SupportTicket $ticket): bool
    {
        return $this->allowsTenantResourceAction($user, 'tickets.manage', $ticket->account_id);
    }

    public function reply(User $user, SupportTicket $ticket): bool
    {
        return $this->allowsTenantResourceAction($user, 'tickets.manage', $ticket->account_id);
    }

    public function assign(User $user, SupportTicket $ticket): bool
    {
        return $this->allowsTenantResourceAction($user, 'tickets.manage', $ticket->account_id);
    }

    public function close(User $user, SupportTicket $ticket): bool
    {
        return $this->allowsTenantResourceAction($user, 'tickets.manage', $ticket->account_id);
    }

    public function escalate(User $user, SupportTicket $ticket): bool
    {
        return $this->allowsTenantResourceAction($user, 'tickets.manage', $ticket->account_id);
    }
}
