<?php
namespace App\Enums;

enum NotificationType: string
{
    case SHIPMENT_CREATED = 'shipment_created';
    case SHIPMENT_SHIPPED = 'shipment_shipped';
    case SHIPMENT_DELIVERED = 'shipment_delivered';
    case SHIPMENT_EXCEPTION = 'shipment_exception';
    case ORDER_NEW = 'order_new';
    case ORDER_CANCELLED = 'order_cancelled';
    case WALLET_LOW_BALANCE = 'wallet_low_balance';
    case WALLET_TOPUP = 'wallet_topup';
    case KYC_APPROVED = 'kyc_approved';
    case KYC_REJECTED = 'kyc_rejected';
    case INVITATION_SENT = 'invitation_sent';
    case TICKET_REPLY = 'ticket_reply';
    case SYSTEM_ALERT = 'system_alert';

    public function label(): string
    {
        return match ($this) {
            self::SHIPMENT_CREATED => 'شحنة جديدة',
            self::SHIPMENT_SHIPPED => 'تم الشحن',
            self::SHIPMENT_DELIVERED => 'تم التسليم',
            self::SHIPMENT_EXCEPTION => 'استثناء شحنة',
            self::ORDER_NEW => 'طلب جديد',
            self::ORDER_CANCELLED => 'طلب ملغي',
            self::WALLET_LOW_BALANCE => 'رصيد منخفض',
            self::WALLET_TOPUP => 'شحن محفظة',
            self::KYC_APPROVED => 'KYC مقبول',
            self::KYC_REJECTED => 'KYC مرفوض',
            self::INVITATION_SENT => 'دعوة مرسلة',
            self::TICKET_REPLY => 'رد على تذكرة',
            self::SYSTEM_ALERT => 'تنبيه نظام',
        };
    }

    public function channel(): string
    {
        return match ($this) {
            self::SHIPMENT_EXCEPTION, self::WALLET_LOW_BALANCE, self::SYSTEM_ALERT => 'email,push',
            self::TICKET_REPLY => 'email',
            default => 'push',
        };
    }
}
