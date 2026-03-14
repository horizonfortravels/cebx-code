<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit trail for dangerous goods declarations.
 */
class DgAuditLog extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'declaration_id',
        'shipment_id',
        'account_id',
        'action',
        'actor_id',
        'actor_role',
        'ip_address',
        'old_values',
        'new_values',
        'notes',
        'payload',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'payload' => 'array',
    ];

    const ACTION_CREATED = 'declaration.created';
    const ACTION_DG_FLAG_SET = 'declaration.dg_flag_set';
    const ACTION_WAIVER_ACCEPTED = 'declaration.waiver_accepted';
    const ACTION_HOLD_APPLIED = 'declaration.hold_applied';
    const ACTION_DG_METADATA_SAVED = 'declaration.dg_metadata_saved';
    const ACTION_COMPLETED = 'declaration.completed';
    const ACTION_VIEWED = 'declaration.viewed';
    const ACTION_EXPORTED = 'declaration.exported';
    const ACTION_STATUS_CHANGED = 'declaration.status_changed';

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(ContentDeclaration::class, 'declaration_id');
    }

    public static function log(
        string $action,
        string $accountId,
        string $actorId,
        ?string $declarationId = null,
        ?string $shipmentId = null,
        ?string $actorRole = null,
        ?string $ipAddress = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $notes = null,
    ): self {
        return static::create([
            'action' => $action,
            'account_id' => $accountId,
            'actor_id' => $actorId,
            'declaration_id' => $declarationId,
            'shipment_id' => $shipmentId,
            'actor_role' => $actorRole,
            'ip_address' => $ipAddress,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'notes' => $notes,
            'payload' => array_filter([
                'account_id' => $accountId,
                'actor_id' => $actorId,
                'actor_role' => $actorRole,
                'ip_address' => $ipAddress,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'notes' => $notes,
            ], static fn ($value) => $value !== null),
        ]);
    }

    public function scopeForDeclaration($query, string $declarationId)
    {
        return $query->where('declaration_id', $declarationId);
    }

    public function scopeForShipment($query, string $shipmentId)
    {
        return $query->where('shipment_id', $shipmentId);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }
}
