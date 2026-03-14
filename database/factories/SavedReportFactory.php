<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\SavedReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SavedReportFactory extends Factory
{
    protected $model = SavedReport::class;

    public function definition(): array
    {
        return [
            'account_id'  => Account::factory(),
            'user_id'     => User::factory(),
            'name'        => 'Monthly Shipment Report',
            'report_type' => 'shipment_summary',
            'filters'     => ['date_from' => now()->subMonth()->toDateString(), 'date_to' => now()->toDateString()],
            'columns'     => ['tracking_number', 'status', 'carrier_code'],
            'group_by'    => 'day',
            'is_favorite' => false,
            'is_shared'   => false,
        ];
    }

    public function shared(): static { return $this->state(['is_shared' => true]); }
    public function profit(): static { return $this->state(['report_type' => 'profit_loss', 'name' => 'Profit Report']); }
}
