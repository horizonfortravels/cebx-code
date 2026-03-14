<?php
namespace App\Policies;

use App\Models\{User, SupportTicket};

class TicketPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('tickets.view'); }
    public function view(User $user, SupportTicket $ticket): bool { return $user->account_id === $ticket->account_id; }
    public function create(User $user): bool { return $user->hasPermission('tickets.create'); }
    public function reply(User $user, SupportTicket $ticket): bool { return $user->account_id === $ticket->account_id && $ticket->status !== 'closed'; }
    public function resolve(User $user, SupportTicket $ticket): bool { return $user->account_id === $ticket->account_id && $user->hasPermission('tickets.resolve'); }
    public function assign(User $user): bool { return $user->hasPermission('tickets.assign'); }
}
