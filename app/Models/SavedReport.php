<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SavedReport — FR-RPT-001/003
 */
class SavedReport extends Model
{
    use HasFactory, HasUuids, BelongsToAccount;

    protected $fillable = [
        'account_id', 'user_id', 'name', 'report_type',
        'filters', 'columns', 'group_by', 'sort_by', 'sort_direction',
        'is_favorite', 'is_shared',
    ];

    protected $casts = [
        'filters'     => 'array',
        'columns'     => 'array',
        'is_favorite' => 'boolean',
        'is_shared'   => 'boolean',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
