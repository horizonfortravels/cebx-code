<?php
namespace App\Enums;

enum TicketStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case WAITING_CUSTOMER = 'waiting_customer';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'مفتوحة', self::IN_PROGRESS => 'قيد المعالجة',
            self::WAITING_CUSTOMER => 'بانتظار العميل', self::RESOLVED => 'محلولة',
            self::CLOSED => 'مغلقة',
        };
    }
}
