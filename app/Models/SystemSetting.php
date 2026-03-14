<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * SystemSetting — FR-ADM-001
 */
class SystemSetting extends Model
{
    use HasUuids;

    protected $fillable = ['group', 'key', 'value', 'type', 'description', 'is_sensitive', 'updated_by'];

    protected $casts = ['is_sensitive' => 'boolean'];

    protected $hidden = [];

    public function getTypedValue()
    {
        return match ($this->type) {
            'integer'   => (int) $this->value,
            'boolean'   => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json'      => json_decode($this->value, true),
            'encrypted' => decrypt($this->value),
            default     => $this->value,
        };
    }

    public static function getValue(string $group, string $key, $default = null)
    {
        $setting = self::where('group', $group)->where('key', $key)->first();
        return $setting ? $setting->getTypedValue() : $default;
    }

    public static function setValue(string $group, string $key, $value, string $type = 'string', ?string $updatedBy = null): self
    {
        $storeValue = $type === 'json' ? json_encode($value) : ($type === 'encrypted' ? encrypt($value) : (string) $value);

        return self::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $storeValue, 'type' => $type, 'updated_by' => $updatedBy, 'is_sensitive' => $type === 'encrypted']
        );
    }

    public function scopeForGroup($query, string $group) { return $query->where('group', $group); }
}
