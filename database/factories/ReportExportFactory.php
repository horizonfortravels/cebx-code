<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportExportFactory extends Factory
{
    protected $model = ReportExport::class;

    public function definition(): array
    {
        return [
            'account_id'  => Account::factory(),
            'user_id'     => User::factory(),
            'report_type' => 'shipment_summary',
            'format'      => 'csv',
            'filters'     => [],
            'status'      => ReportExport::STATUS_COMPLETED,
            'file_path'   => 'exports/test.csv',
            'row_count'   => 100,
            'file_size'   => 5000,
            'completed_at' => now(),
        ];
    }

    public function pending(): static { return $this->state(['status' => ReportExport::STATUS_PENDING, 'file_path' => null]); }
    public function failed(): static { return $this->state(['status' => ReportExport::STATUS_FAILED, 'failure_reason' => 'Test error']); }
}
