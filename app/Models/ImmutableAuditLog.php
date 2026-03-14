<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ImmutableAuditLog extends Model
{
    use HasUuids;
    protected $table = 'immutable_audit_log';
    protected $guarded = ['id'];
    protected $casts = ['payload' => 'json', 'created_at' => 'datetime'];
    public $timestamps = false;

    // Immutable — no updates or deletes
    public static function boot()
    {
        parent::boot();
        static::updating(fn() => false);
        static::deleting(fn() => false);
    }
}
