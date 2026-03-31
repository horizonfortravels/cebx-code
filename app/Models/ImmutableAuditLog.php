<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class ImmutableAuditLog extends Model
{
    use HasUuids;

    protected $table = 'immutable_audit_log';

    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (self $auditLog): void {
            $auditLog->occurred_at ??= now();
            $auditLog->created_at ??= now();
        });

        static::updating(function (): void {
            throw new LogicException('ImmutableAuditLog is append-only and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('ImmutableAuditLog is append-only and cannot be deleted.');
        });
    }
}
