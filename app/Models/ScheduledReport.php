<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ScheduledReport — FR-RPT-005
 */
class ScheduledReport extends Model
{
    use HasFactory, HasUuids, BelongsToAccount;

    protected $fillable = [
        'account_id', 'user_id', 'name', 'report_type',
        'filters', 'columns', 'frequency', 'time_of_day',
        'day_of_week', 'day_of_month', 'timezone', 'format',
        'recipients', 'is_active', 'last_sent_at', 'next_send_at',
    ];

    protected $casts = [
        'filters'      => 'array',
        'columns'      => 'array',
        'recipients'   => 'array',
        'is_active'    => 'boolean',
        'last_sent_at' => 'datetime',
        'next_send_at' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function isDue(): bool
    {
        return $this->is_active && $this->next_send_at && $this->next_send_at->lte(now());
    }

    public function calculateNextSend(): void
    {
        $next = match ($this->frequency) {
            'daily'   => now()->addDay()->setTimeFromTimeString($this->time_of_day ?? '08:00'),
            'weekly'  => now()->next($this->day_of_week ?? 'monday')->setTimeFromTimeString($this->time_of_day ?? '08:00'),
            'monthly' => now()->addMonth()->day($this->day_of_month ?? 1)->setTimeFromTimeString($this->time_of_day ?? '08:00'),
            default   => null,
        };
        $this->update(['next_send_at' => $next, 'last_sent_at' => now()]);
    }

    public function scopeDue($query)
    {
        return $query->where('is_active', true)->where('next_send_at', '<=', now());
    }
}
