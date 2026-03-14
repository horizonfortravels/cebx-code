<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * ReconciliationReport — FR-BW-010
 */
class ReconciliationReport extends Model
{
    use HasUuids;

    protected $fillable = [
        'report_date', 'payment_gateway',
        'total_topups', 'matched', 'unmatched_gateway', 'unmatched_ledger',
        'total_amount', 'discrepancy_amount', 'anomalies', 'status', 'reviewed_by',
    ];

    protected $casts = [
        'report_date'        => 'date',
        'total_amount'       => 'decimal:2',
        'discrepancy_amount' => 'decimal:2',
        'anomalies'          => 'array',
    ];

    public function hasDiscrepancies(): bool
    {
        return $this->unmatched_gateway > 0 || $this->unmatched_ledger > 0 || (float) $this->discrepancy_amount != 0;
    }
}
