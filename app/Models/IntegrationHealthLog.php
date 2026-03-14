<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * IntegrationHealthLog — FR-ADM-002/006
 */
class IntegrationHealthLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'service', 'status', 'response_time_ms', 'error_rate',
        'total_requests', 'failed_requests', 'error_message',
        'correlation_id', 'metadata', 'checked_at',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'error_rate' => 'float',
        'checked_at' => 'datetime',
    ];

    const STATUS_HEALTHY  = 'healthy';
    const STATUS_DEGRADED = 'degraded';
    const STATUS_DOWN     = 'down';

    public function isHealthy(): bool { return $this->status === self::STATUS_HEALTHY; }

    public static function recordCheck(string $service, string $status, int $responseMs = 0, ?string $error = null): self
    {
        return self::create([
            'service' => $service, 'status' => $status,
            'response_time_ms' => $responseMs, 'error_message' => $error,
            'checked_at' => now(),
        ]);
    }

    public function scopeForService($query, string $service) { return $query->where('service', $service); }
    public function scopeRecent($query, int $hours = 24) { return $query->where('checked_at', '>=', now()->subHours($hours)); }
}
