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
            ['event_type' => 'shipment.purchased', 'channel' => 'email', 'subject' => 'تم إصدار الشحنة {{tracking_number}}', 'body' => 'تم إصدار شحنتك لدى الناقل. رقم التتبع: {{tracking_number}}.'],
            ['event_type' => 'shipment.purchased', 'channel' => 'in_app', 'subject' => 'تم إصدار الشحنة {{tracking_number}}', 'body' => 'تم إصدار الشحنة لدى الناقل ويمكنك متابعة الحالة من صفحة الشحنة.'],
            ['event_type' => 'shipment.documents_available', 'channel' => 'email', 'subject' => 'مستندات الشحنة أصبحت متاحة', 'body' => 'أصبحت مستندات الشحنة {{tracking_number}} متاحة للتنزيل والطباعة.'],
            ['event_type' => 'shipment.documents_available', 'channel' => 'in_app', 'subject' => 'مستندات الشحنة أصبحت متاحة', 'body' => 'يمكنك الآن تنزيل وطباعة مستندات الشحنة {{tracking_number}}.'],
            ['event_type' => 'shipment.in_transit', 'channel' => 'email', 'subject' => 'الشحنة في الطريق {{tracking_number}}', 'body' => 'الشحنة {{tracking_number}} أصبحت في الطريق. آخر تحديث: {{event_description}}.'],
            ['event_type' => 'shipment.in_transit', 'channel' => 'in_app', 'subject' => 'الشحنة في الطريق {{tracking_number}}', 'body' => 'تم تحديث حالة الشحنة إلى: {{status_label}}.'],
            ['event_type' => 'shipment.out_for_delivery', 'channel' => 'email', 'subject' => 'الشحنة خرجت للتسليم {{tracking_number}}', 'body' => 'الشحنة {{tracking_number}} خرجت للتسليم.'],
            ['event_type' => 'shipment.out_for_delivery', 'channel' => 'in_app', 'subject' => 'الشحنة خرجت للتسليم {{tracking_number}}', 'body' => 'الشحنة خرجت للتسليم ويمكنك متابعة آخر التحديثات من صفحة الشحنة.'],
            ['event_type' => 'shipment.exception', 'channel' => 'email', 'subject' => 'يوجد استثناء على الشحنة {{tracking_number}}', 'body' => 'يوجد استثناء على الشحنة {{tracking_number}}. آخر تحديث: {{event_description}}.'],
            ['event_type' => 'shipment.exception', 'channel' => 'in_app', 'subject' => 'يوجد استثناء على الشحنة {{tracking_number}}', 'body' => 'تحتاج الشحنة إلى متابعة. راجع صفحة الشحنة لمعرفة التفاصيل.'],
            ['event_type' => 'shipment.delivered', 'channel' => 'in_app', 'subject' => 'تم تسليم الشحنة {{tracking_number}}', 'body' => 'تم تسليم الشحنة {{tracking_number}} بنجاح.'],
            ['event_type' => 'shipment.returned', 'channel' => 'email', 'subject' => 'تمت إعادة الشحنة {{tracking_number}}', 'body' => 'تمت إعادة الشحنة {{tracking_number}}.'],
            ['event_type' => 'shipment.returned', 'channel' => 'in_app', 'subject' => 'تمت إعادة الشحنة {{tracking_number}}', 'body' => 'تم تحديث حالة الشحنة إلى مرتجع.'],
            ['event_type' => 'shipment.cancelled', 'channel' => 'email', 'subject' => 'تم إلغاء الشحنة {{tracking_number}}', 'body' => 'تم إلغاء الشحنة {{tracking_number}}.'],
            ['event_type' => 'shipment.cancelled', 'channel' => 'in_app', 'subject' => 'تم إلغاء الشحنة {{tracking_number}}', 'body' => 'تم إلغاء الشحنة.'],
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
