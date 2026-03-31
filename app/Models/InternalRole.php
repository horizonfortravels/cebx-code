<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InternalRole extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'internal_roles';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'internal_role_permission')
            ->withPivot('granted_at');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'internal_user_role')
            ->withPivot(['assigned_by', 'assigned_at']);
    }
}
