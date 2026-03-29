<?php
namespace App\Models;
use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
class Address extends Model {
    use HasFactory, HasUuids, BelongsToAccount;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'is_default' => 'bool',
        'is_default_sender' => 'bool',
    ];

    protected static array $columnCache = [];

    public static function hasSchemaColumn(string $column): bool
    {
        if (! array_key_exists($column, static::$columnCache)) {
            static::$columnCache[$column] = Schema::hasColumn((new static())->getTable(), $column);
        }

        return static::$columnCache[$column];
    }

    public static function supportsTypedAddressBook(): bool
    {
        return static::hasSchemaColumn('type');
    }

    public static function defaultSenderColumn(): string
    {
        return static::hasSchemaColumn('is_default_sender')
            ? 'is_default_sender'
            : 'is_default';
    }

    public function scopeDefaultSender(Builder $query): Builder
    {
        return $query->where(static::defaultSenderColumn(), true);
    }

    public function getTypeAttribute(mixed $value): string
    {
        return $this->normalizeString($value) ?? 'both';
    }

    public function setTypeAttribute(mixed $value): void
    {
        $this->assignIfColumnExists('type', $this->normalizeString($value) ?? 'both');
    }

    public function getContactNameAttribute(mixed $value): ?string
    {
        return $this->normalizeString($value)
            ?? $this->normalizeString($this->attributes['name'] ?? null);
    }

    public function setContactNameAttribute(mixed $value): void
    {
        $normalized = $this->normalizeString($value);

        $this->assignIfColumnExists('contact_name', $normalized);
        $this->attributes['name'] = $normalized;
    }

    public function getCompanyNameAttribute(mixed $value): ?string
    {
        return $this->normalizeString($value);
    }

    public function setCompanyNameAttribute(mixed $value): void
    {
        $this->assignIfColumnExists('company_name', $this->normalizeString($value));
    }

    public function getEmailAttribute(mixed $value): ?string
    {
        return $this->normalizeString($value);
    }

    public function setEmailAttribute(mixed $value): void
    {
        $this->assignIfColumnExists('email', $this->normalizeString($value));
    }

    public function getAddressLine1Attribute(mixed $value): ?string
    {
        return $this->normalizeString($value)
            ?? $this->normalizeString($this->attributes['street'] ?? null)
            ?? $this->normalizeString($this->attributes['district'] ?? null);
    }

    public function setAddressLine1Attribute(mixed $value): void
    {
        $normalized = $this->normalizeString($value);

        $this->assignIfColumnExists('address_line_1', $normalized);
        $this->attributes['street'] = $normalized;
    }

    public function getAddressLine2Attribute(mixed $value): ?string
    {
        return $this->normalizeString($value)
            ?? $this->normalizeString($this->attributes['district'] ?? null);
    }

    public function setAddressLine2Attribute(mixed $value): void
    {
        $normalized = $this->normalizeString($value);

        $this->assignIfColumnExists('address_line_2', $normalized);
        $this->attributes['district'] = $normalized;
    }

    public function getStateAttribute(mixed $value): ?string
    {
        return $this->normalizeString($value);
    }

    public function setStateAttribute(mixed $value): void
    {
        $this->assignIfColumnExists('state', $this->normalizeString($value));
    }

    public function getIsDefaultSenderAttribute(mixed $value): bool
    {
        if ($value !== null) {
            return (bool) $value;
        }

        return (bool) ($this->attributes['is_default'] ?? false);
    }

    public function setIsDefaultSenderAttribute(mixed $value): void
    {
        $normalized = (bool) $value;

        $this->assignIfColumnExists('is_default_sender', $normalized);
        $this->attributes['is_default'] = $normalized;
    }

    public function setIsDefaultAttribute(mixed $value): void
    {
        $normalized = (bool) $value;

        $this->attributes['is_default'] = $normalized;
        $this->assignIfColumnExists('is_default_sender', $normalized);
    }

    public function setNameAttribute(mixed $value): void
    {
        $normalized = $this->normalizeString($value);

        $this->attributes['name'] = $normalized;
        $this->assignIfColumnExists('contact_name', $normalized);
    }

    public function setStreetAttribute(mixed $value): void
    {
        $normalized = $this->normalizeString($value);

        $this->attributes['street'] = $normalized;
        $this->assignIfColumnExists('address_line_1', $normalized);
    }

    public function setDistrictAttribute(mixed $value): void
    {
        $normalized = $this->normalizeString($value);

        $this->attributes['district'] = $normalized;
        $this->assignIfColumnExists('address_line_2', $normalized);
    }

    private function assignIfColumnExists(string $column, mixed $value): void
    {
        if (static::hasSchemaColumn($column)) {
            $this->attributes[$column] = $value;
        }
    }

    private function normalizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $resolved = trim((string) $value);

        return $resolved === '' ? null : $resolved;
    }
}
