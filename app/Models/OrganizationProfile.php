<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationProfile extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'account_id',
        'legal_name',
        'trade_name',
        'registration_number',
        'tax_id',
        'industry',
        'company_size',
        'country',
        'city',
        'address_line_1',
        'address_line_2',
        'postal_code',
        'phone',
        'email',
        'website',
        'billing_currency',
        'billing_cycle',
        'billing_email',
        'logo_path',
    ];

    // ─── Relationships ────────────────────────────────────────────

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────

    public function isComplete(): bool
    {
        return !empty($this->legal_name)
            && !empty($this->registration_number)
            && !empty($this->country);
    }

    /**
     * Get required KYC documents for organizations.
     */
    public static function requiredKycDocuments(): array
    {
        return [
            'commercial_registration' => 'السجل التجاري',
            'tax_certificate'         => 'شهادة الضريبة',
            'national_address'        => 'العنوان الوطني',
            'authorization_letter'    => 'خطاب تفويض',
        ];
    }
}
