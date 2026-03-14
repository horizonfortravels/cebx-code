<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    public function definition(): array
    {
        return [
            'account_id'    => Account::factory(),
            'user_id'       => User::factory(),
            'ticket_number' => SupportTicket::generateNumber(),
            'subject'       => 'Shipment delivery issue',
            'description'   => 'My shipment has not been delivered.',
            'category'      => 'shipping',
            'priority'      => 'medium',
            'status'        => SupportTicket::STATUS_OPEN,
        ];
    }

    public function urgent(): static { return $this->state(['priority' => 'urgent']); }
    public function resolved(): static { return $this->state(['status' => SupportTicket::STATUS_RESOLVED, 'resolved_at' => now()]); }
    public function closed(): static { return $this->state(['status' => SupportTicket::STATUS_CLOSED, 'closed_at' => now()]); }
}
