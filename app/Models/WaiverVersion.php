<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * WaiverVersion — FR-DG-006
 *
 * Versioned liability waiver texts with AR/EN locale support.
 * New versions don't modify old records — append-only versioning.
 */
class WaiverVersion extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'version', 'locale', 'waiver_text', 'waiver_hash', 'is_active', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────────

    public function declarations(): HasMany
    {
        return $this->hasMany(ContentDeclaration::class, 'waiver_version_id');
    }

    // ── Factory Methods ──────────────────────────────────────

    public static function publish(string $version, string $locale, string $text, ?string $createdBy = null): self
    {
        // Deactivate previous version for this locale
        static::where('locale', $locale)->where('is_active', true)->update(['is_active' => false]);

        return static::create([
            'version'     => $version,
            'locale'      => $locale,
            'waiver_text' => $text,
            'waiver_hash' => hash('sha256', $text),
            'is_active'   => true,
            'created_by'  => $createdBy,
        ]);
    }

    // ── Queries ──────────────────────────────────────────────

    public static function getActive(string $locale = 'ar'): ?self
    {
        return static::where('locale', $locale)->where('is_active', true)->first();
    }

    public static function getByVersion(string $version, string $locale): ?self
    {
        return static::where('version', $version)->where('locale', $locale)->first();
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForLocale($query, string $locale)
    {
        return $query->where('locale', $locale);
    }
}
