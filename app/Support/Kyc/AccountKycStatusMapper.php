<?php

namespace App\Support\Kyc;

use App\Models\KycVerification;

class AccountKycStatusMapper
{
    public const STATUS_NOT_SUBMITTED = 'not_submitted';
    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';

    public static function fromVerificationStatus(?string $status): string
    {
        return match (self::normalize($status)) {
            'verified',
            KycVerification::STATUS_APPROVED => self::STATUS_VERIFIED,
            'pending',
            'pending_review',
            KycVerification::STATUS_PENDING => self::STATUS_PENDING,
            'rejected',
            KycVerification::STATUS_REJECTED => self::STATUS_REJECTED,
            default => self::STATUS_NOT_SUBMITTED,
        };
    }

    public static function toVerificationStatus(?string $status): string
    {
        return match (self::normalize($status)) {
            self::STATUS_VERIFIED,
            KycVerification::STATUS_APPROVED => KycVerification::STATUS_APPROVED,
            self::STATUS_PENDING,
            'pending_review',
            KycVerification::STATUS_PENDING => KycVerification::STATUS_PENDING,
            self::STATUS_REJECTED,
            KycVerification::STATUS_REJECTED => KycVerification::STATUS_REJECTED,
            default => KycVerification::STATUS_UNVERIFIED,
        };
    }

    private static function normalize(?string $status): string
    {
        return strtolower(trim((string) $status));
    }
}
