<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\ScheduledReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduledReportFactory extends Factory
{
    protected $model = ScheduledReport::class;

    public function definition(): array
    {
        return [
            'account_id'  => Account::factory(),
            'user_id'     => User::factory(),
            'name'        => 'Weekly Shipment Report',
            'report_type' => 'shipment_summary',
            'frequency'   => 'weekly',
            'time_of_day' => '08:00',
            'day_of_week' => 'monday',
            'timezone'    => 'Asia/Riyadh',
            'format'      => 'csv',
            'recipients'  => ['admin@test.com'],
            'is_active'   => true,
            'next_send_at' => now()->addDay(),
        ];
    }

    public function daily(): static { return $this->state(['frequency' => 'daily', 'day_of_week' => null]); }
    public function due(): static { return $this->state(['next_send_at' => now()->subHour()]); }
    public function inactive(): static { return $this->state(['is_active' => false]); }
}
