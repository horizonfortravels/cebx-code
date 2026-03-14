<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ReportExport — FR-RPT-002/003
 */
class ReportExport extends Model
{
    use HasFactory, HasUuids, BelongsToAccount;

    protected $fillable = [
        'account_id', 'user_id', 'report_type', 'format',
        'filters', 'columns',
        'status', 'file_path', 'row_count', 'file_size', 'failure_reason',
        'completed_at',
    ];

    protected $casts = [
        'filters'      => 'array',
        'columns'      => 'array',
        'completed_at' => 'datetime',
    ];

    const STATUS_PENDING    = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_FAILED     = 'failed';

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function markCompleted(string $path, int $rows, int $size): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED, 'file_path' => $path,
            'row_count' => $rows, 'file_size' => $size, 'completed_at' => now(),
        ]);
    }

    public function markFailed(string $reason): void
    {
        $this->update(['status' => self::STATUS_FAILED, 'failure_reason' => $reason]);
    }
}
