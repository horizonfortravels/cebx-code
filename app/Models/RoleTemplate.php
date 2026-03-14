<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * RoleTemplate — FR-ADM-006
 */
class RoleTemplate extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'slug', 'description', 'permissions', 'is_active', 'sort_order'];

    protected $casts = ['permissions' => 'array', 'is_active' => 'boolean'];

    public function scopeActive($query) { return $query->where('is_active', true)->orderBy('sort_order'); }
}
