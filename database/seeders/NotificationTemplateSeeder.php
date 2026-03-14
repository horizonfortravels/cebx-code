<?php
namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['event_type' => 'shipment.created', 'channel' => 'email', 'subject' => 'تم إنشاء الشحنة {{shipment_id}}', 'body' => 'تم إنشاء شحنة جديدة برقم {{shipment_id}} إلى {{destination}}.'],
            ['event_type' => 'shipment.delivered', 'channel' => 'email', 'subject' => 'تم تسليم الشحنة {{tracking_number}}', 'body' => 'تم تسليم شحنتك {{tracking_number}} بنجاح.'],
            ['event_type' => 'shipment.delivered', 'channel' => 'sms', 'subject' => null, 'body' => 'تم تسليم شحنتك {{tracking_number}}.'],
            ['event_type' => 'order.created', 'channel' => 'email', 'subject' => 'طلب جديد #{{order_id}}', 'body' => 'تم استلام طلب جديد #{{order_id}} من {{store_name}}.'],
            ['event_type' => 'wallet.low_balance', 'channel' => 'email', 'subject' => 'تنبيه: رصيد المحفظة منخفض', 'body' => 'رصيد محفظتك {{balance}} ر.س أقل من الحد الأدنى.'],
            ['event_type' => 'kyc.approved', 'channel' => 'email', 'subject' => 'تم التحقق من هويتك', 'body' => 'تم قبول وثائق التحقق الخاصة بك. حسابك مفعّل بالكامل.'],
            ['event_type' => 'invitation.sent', 'channel' => 'email', 'subject' => 'دعوة للانضمام إلى {{company_name}}', 'body' => 'تمت دعوتك للانضمام إلى {{company_name}} كـ {{role}}.'],
            ['event_type' => 'support.reply', 'channel' => 'email', 'subject' => 'رد جديد على التذكرة #{{ticket_id}}', 'body' => 'تم إضافة رد جديد على تذكرة الدعم #{{ticket_id}}.'],
        ];

        foreach ($templates as $t) {
            NotificationTemplate::firstOrCreate(
                [
                    'account_id' => null,
                    'event_type' => $t['event_type'],
                    'channel' => $t['channel'],
                    'language' => 'ar',
                ],
                array_merge($t, [
                    'account_id' => null,
                    'language' => 'ar',
                    'is_active' => true,
                    'is_system' => true,
                ])
            );
        }
    }
}
