<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NotificationPreference — FR-NTF-003/004
 *
 * Per-user preferences: which events on which channels.
 */
class NotificationPreference extends Model
{
    use HasFactory, HasUuids, BelongsToAccount;

    protected $fillable = [
        'user_id', 'account_id', 'event_type', 'channel',
        'enabled', 'language', 'destination',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if user wants this event on this channel.
     * Returns null if no preference exists (use account default).
     */
    public static function isEnabled(string $userId, string $eventType, string $channel): ?bool
    {
        $pref = self::where('user_id', $userId)
            ->where('event_type', $eventType)
            ->where('channel', $channel)
            ->first();

        return $pref?->enabled;
    }

    /**
     * Bulk update preferences for a user.
     */
    public static function bulkUpdate(string $userId, string $accountId, array $preferences): void
    {
        foreach ($preferences as $pref) {
            self::updateOrCreate(
                [
                    'user_id'    => $userId,
                    'event_type' => $pref['event_type'],
                    'channel'    => $pref['channel'],
                ],
                [
                    'account_id'  => $accountId,
                    'enabled'     => $pref['enabled'] ?? true,
                    'language'    => $pref['language'] ?? null,
                    'destination' => $pref['destination'] ?? null,
                ]
            );
        }
    }
}
