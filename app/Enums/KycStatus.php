<?php
namespace App\Enums;

enum KycStatus: string
{
    case NOT_STARTED = 'not_started';
    case DOCUMENTS_UPLOADED = 'documents_uploaded';
    case PENDING_REVIEW = 'pending_review';
    case UNDER_REVIEW = 'under_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::NOT_STARTED => 'لم يبدأ',
            self::DOCUMENTS_UPLOADED => 'تم رفع الوثائق',
            self::PENDING_REVIEW => 'بانتظار المراجعة',
            self::UNDER_REVIEW => 'قيد المراجعة',
            self::APPROVED => 'مقبول',
            self::REJECTED => 'مرفوض',
            self::EXPIRED => 'منتهي الصلاحية',
        };
    }

    public function step(): int
    {
        return match ($this) {
            self::NOT_STARTED => 0,
            self::DOCUMENTS_UPLOADED => 1,
            self::PENDING_REVIEW, self::UNDER_REVIEW => 2,
            self::APPROVED => 3,
            self::REJECTED, self::EXPIRED => -1,
        };
    }
}
