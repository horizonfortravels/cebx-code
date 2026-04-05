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
        return $this->allowsTenantResourceAction($user, 'tickets.read', $ticket->account_id)
            && $this->externalUserOwnsTicket($user, $ticket);
    }

    public function create(User $user): bool
    {
        return $this->allowsTenantAction($user, 'tickets.manage');
    }

    public function update(User $user, SupportTicket $ticket): bool
    {
        if ($this->resolveUserType($user) === 'external') {
            return false;
        }

        return $this->allowsTenantResourceAction($user, 'tickets.manage', $ticket->account_id);
    }

    public function delete(User $user, SupportTicket $ticket): bool
    {
        if ($this->resolveUserType($user) === 'external') {
            return false;
        }

        return $this->allowsTenantResourceAction($user, 'tickets.manage', $ticket->account_id);
    }

    public function reply(User $user, SupportTicket $ticket): bool
    {
        return $this->allowsTenantResourceAction($user, 'tickets.manage', $ticket->account_id)
            && $this->externalUserOwnsTicket($user, $ticket);
    }

    public function assign(User $user, SupportTicket $ticket): bool
    {
        if ($this->resolveUserType($user) === 'external') {
            return false;
        }

        return $this->allowsTenantResourceAction($user, 'tickets.manage', $ticket->account_id);
    }

    public function close(User $user, SupportTicket $ticket): bool
    {
        return $this->allowsTenantResourceAction($user, 'tickets.manage', $ticket->account_id)
            && $this->externalUserOwnsTicket($user, $ticket);
    }

    public function escalate(User $user, SupportTicket $ticket): bool
    {
        if ($this->resolveUserType($user) === 'external') {
            return false;
        }

        return $this->allowsTenantResourceAction($user, 'tickets.manage', $ticket->account_id);
    }

    private function externalUserOwnsTicket(User $user, SupportTicket $ticket): bool
    {
        if ($this->resolveUserType($user) !== 'external') {
            return true;
        }

        $ticketUserId = trim((string) ($ticket->user_id ?? ''));

        return $ticketUserId !== '' && $ticketUserId === trim((string) $user->id);
    }
}
