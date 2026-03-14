<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NotificationSchedule — FR-NTF-007
 *
 * Scheduled/digest notification preferences (daily/weekly summaries).
 */
class NotificationSchedule extends Model
{
    use HasFactory, HasUuids, BelongsToAccount;

    protected $fillable = [
        'account_id', 'user_id', 'frequency', 'time_of_day',
        'day_of_week', 'timezone', 'event_types', 'channel',
        'is_active', 'last_sent_at', 'next_send_at',
    ];

    protected $casts = [
        'event_types'  => 'array',
        'is_active'    => 'boolean',
        'last_sent_at' => 'datetime',
        'next_send_at' => 'datetime',
    ];

    const FREQ_IMMEDIATE = 'immediate';
    const FREQ_HOURLY    = 'hourly';
    const FREQ_DAILY     = 'daily';
    const FREQ_WEEKLY    = 'weekly';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDue(): bool
    {
        return $this->is_active && $this->next_send_at && $this->next_send_at->lte(now());
    }

    public function calculateNextSend(): void
    {
        $next = match ($this->frequency) {
            self::FREQ_HOURLY => now()->addHour(),
            self::FREQ_DAILY  => now()->addDay()->setTimeFromTimeString($this->time_of_day ?? '08:00'),
            self::FREQ_WEEKLY => now()->next($this->day_of_week ?? 'monday')->setTimeFromTimeString($this->time_of_day ?? '08:00'),
            default           => null,
        };

        $this->update(['next_send_at' => $next, 'last_sent_at' => now()]);
    }

    public function scopeDue($query)
    {
        return $query->where('is_active', true)
            ->where('frequency', '!=', self::FREQ_IMMEDIATE)
            ->where('next_send_at', '<=', now());
    }
}
