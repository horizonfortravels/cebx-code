<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * PermissionCatalog — FR-ORG-004/005/006
 */
class PermissionCatalog extends Model
{
    use HasUuids;

    protected $table = 'permission_catalog';

    protected $fillable = [
        'key', 'name', 'description', 'module', 'category', 'is_active', 'sort_order',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function isFinancial(): bool { return $this->category === 'financial'; }
    public function isOperational(): bool { return $this->category === 'operational'; }

    public function scopeActive($query) { return $query->where('is_active', true); }
    public function scopeForModule($query, string $module) { return $query->where('module', $module); }
    public function scopeFinancial($query) { return $query->where('category', 'financial'); }
    public function scopeOperational($query) { return $query->where('category', 'operational'); }

    /**
     * Validate a list of permission keys against catalog.
     */
    public static function validateKeys(array $keys): array
    {
        $valid = self::active()->whereIn('key', $keys)->pluck('key')->toArray();
        $invalid = array_diff($keys, $valid);
        return ['valid' => $valid, 'invalid' => $invalid];
    }
}
