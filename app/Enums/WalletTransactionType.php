<?php
namespace App\Enums;

enum WalletTransactionType: string
{
    case TOPUP = 'topup';
    case CHARGE = 'charge';
    case REFUND = 'refund';
    case HOLD = 'hold';
    case CAPTURE = 'capture';
    case RELEASE = 'release';
    case ADJUSTMENT = 'adjustment';
    case PROMO = 'promo';

    public function label(): string
    {
        return match ($this) {
            self::TOPUP => 'شحن',
            self::CHARGE => 'خصم',
            self::REFUND => 'استرداد',
            self::HOLD => 'حجز',
            self::CAPTURE => 'تحصيل',
            self::RELEASE => 'إفراج',
            self::ADJUSTMENT => 'تعديل',
            self::PROMO => 'رمز ترويجي',
        };
    }

    public function isCredit(): bool
    {
        return in_array($this, [self::TOPUP, self::REFUND, self::RELEASE, self::PROMO]);
    }
}
