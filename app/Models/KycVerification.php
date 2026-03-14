<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycVerification extends Model
{
    use HasUuids, HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'account_id',
        'status',
        'verification_type',
        'verification_level',
        'required_documents',
        'submitted_documents',
        'rejection_reason',
        'review_notes',
        'review_count',
        'reviewed_by',
        'submitted_at',
        'reviewed_at',
        'expires_at',
    ];

    protected $casts = [
        'required_documents'  => 'array',
        'submitted_documents' => 'array',
        'submitted_at'        => 'datetime',
        'reviewed_at'         => 'datetime',
        'expires_at'          => 'datetime',
        'review_count'        => 'integer',
    ];

    // ─── Status Constants ─────────────────────────────────────────
    public const STATUS_UNVERIFIED = 'unverified';
    public const STATUS_PENDING    = 'pending';
    public const STATUS_APPROVED   = 'approved';
    public const STATUS_REJECTED   = 'rejected';
    public const STATUS_EXPIRED    = 'expired';

    public const ALL_STATUSES = [
        self::STATUS_UNVERIFIED,
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_EXPIRED,
    ];

    // ─── Relationships ────────────────────────────────────────────

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function documents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(KycDocument::class);
    }

    // ─── Verification Levels ─────────────────────────────────────

    public const LEVEL_BASIC    = 'basic';
    public const LEVEL_ENHANCED = 'enhanced';
    public const LEVEL_FULL     = 'full';

    // ─── Helpers ──────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isUnverified(): bool
    {
        return $this->status === self::STATUS_UNVERIFIED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    /**
     * FR-IAM-014: Capabilities based on KYC status.
     * Returns what the account CAN and CANNOT do at current status.
     */
    public function capabilities(): array
    {
        return match ($this->status) {
            self::STATUS_APPROVED => [
                'can_ship_domestic'      => true,
                'can_ship_international' => true,
                'can_use_cod'            => true,
                'can_access_api'         => true,
                'can_export_reports'     => true,
                'can_add_payment_method' => true,
                'shipping_limit'         => null, // Unlimited
                'daily_shipment_limit'   => null,
                'message'                => 'الحساب موثّق بالكامل.',
            ],
            self::STATUS_PENDING => [
                'can_ship_domestic'      => true,
                'can_ship_international' => false,
                'can_use_cod'            => false,
                'can_access_api'         => true,
                'can_export_reports'     => true,
                'can_add_payment_method' => true,
                'shipping_limit'         => 50,
                'daily_shipment_limit'   => 10,
                'message'                => 'الحساب قيد المراجعة. بعض الميزات محدودة.',
            ],
            self::STATUS_REJECTED => [
                'can_ship_domestic'      => true,
                'can_ship_international' => false,
                'can_use_cod'            => false,
                'can_access_api'         => true,
                'can_export_reports'     => false,
                'shipping_limit'         => 10,
                'daily_shipment_limit'   => 5,
                'can_add_payment_method' => false,
                'message'                => 'تم رفض التحقق. يرجى إعادة التقديم مع تصحيح الملاحظات.',
            ],
            default => [ // unverified, expired
                'can_ship_domestic'      => true,
                'can_ship_international' => false,
                'can_use_cod'            => false,
                'can_access_api'         => false,
                'can_export_reports'     => false,
                'can_add_payment_method' => false,
                'shipping_limit'         => 5,
                'daily_shipment_limit'   => 3,
                'message'                => 'يرجى إكمال التحقق لفتح جميع الميزات.',
            ],
        };
    }

    /**
     * Get status display info (label, color, icon).
     */
    public function statusDisplay(): array
    {
        return match ($this->status) {
            self::STATUS_UNVERIFIED => [
                'label'   => 'غير مفعّل',
                'label_en'=> 'Unverified',
                'color'   => 'gray',
                'icon'    => 'shield-off',
            ],
            self::STATUS_PENDING => [
                'label'   => 'قيد المراجعة',
                'label_en'=> 'Pending Review',
                'color'   => 'yellow',
                'icon'    => 'clock',
            ],
            self::STATUS_APPROVED => [
                'label'   => 'مقبول',
                'label_en'=> 'Verified',
                'color'   => 'green',
                'icon'    => 'shield-check',
            ],
            self::STATUS_REJECTED => [
                'label'   => 'مرفوض',
                'label_en'=> 'Rejected',
                'color'   => 'red',
                'icon'    => 'shield-x',
            ],
            self::STATUS_EXPIRED => [
                'label'   => 'منتهي الصلاحية',
                'label_en'=> 'Expired',
                'color'   => 'orange',
                'icon'    => 'shield-alert',
            ],
            default => [
                'label'   => $this->status,
                'label_en'=> $this->status,
                'color'   => 'gray',
                'icon'    => 'shield',
            ],
        };
    }

    /**
     * Get required documents based on account type.
     */
    public static function requiredDocumentsFor(string $accountType): array
    {
        return match ($accountType) {
            'organization' => [
                'commercial_registration' => 'السجل التجاري',
                'tax_certificate'         => 'شهادة الضريبة',
                'national_address'        => 'العنوان الوطني',
                'authorization_letter'    => 'خطاب تفويض',
            ],
            'individual' => [
                'national_id'    => 'الهوية الوطنية',
                'address_proof'  => 'إثبات العنوان',
            ],
            default => [],
        };
    }
}
