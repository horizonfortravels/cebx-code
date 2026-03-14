<?php
namespace Database\Seeders;

use App\Models\{Account, User, Shipment, Order, Store, Notification, AuditLog, SupportTicket, KycVerification};
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $account = Account::where('slug', 'demo-company')->first();
        if (!$account) return;
        $aid = $account->id;
        $createdBy = User::where('account_id', $aid)->value('id');
        if (!$createdBy) return;

        // ── Stores ──
        $stores = [
            ['name' => 'متجر سلة', 'platform' => 'salla', 'website_url' => 'https://mystore.salla.sa', 'status' => 'active', 'connection_status' => 'connected', 'last_synced_at' => now()->subHours(2)],
            ['name' => 'متجر زد', 'platform' => 'zid', 'website_url' => 'https://mystore.zid.sa', 'status' => 'active', 'connection_status' => 'connected', 'last_synced_at' => now()->subHours(5)],
            ['name' => 'Shopify Store', 'platform' => 'shopify', 'website_url' => 'https://mystore.myshopify.com', 'status' => 'active', 'connection_status' => 'connected', 'last_synced_at' => now()->subDay()],
        ];
        foreach ($stores as $s) {
            Store::firstOrCreate(
                ['account_id' => $aid, 'name' => $s['name']],
                array_merge($s, ['account_id' => $aid])
            );
        }

        // ── Shipments ──
        $carriers = [['dhl', 'DHL'], ['aramex', 'Aramex'], ['smsa', 'SMSA'], ['fedex', 'FedEx']];
        $statuses = ['payment_pending', 'purchased', 'in_transit', 'delivered', 'delivered', 'delivered', 'cancelled'];
        $cities = [['الرياض', 'جدة'], ['جدة', 'الدمام'], ['الرياض', 'دبي'], ['الدمام', 'الكويت'], ['جدة', 'القاهرة']];
        $customers = ['محمد أحمد', 'سارة خالد', 'عبدالله فهد', 'ريم سعود', 'ياسر حمد', 'هند علي', 'فيصل عمر', 'لمى بدر'];

        for ($i = 0; $i < 25; $i++) {
            $city = $cities[$i % count($cities)];
            $status = $statuses[$i % count($statuses)];
            $destCountry = in_array($city[1], ['دبي']) ? 'AE' : (in_array($city[1], ['الكويت']) ? 'KW' : (in_array($city[1], ['القاهرة']) ? 'EG' : 'SA'));
            $ref = 'SHP-' . str_pad((string)($i + 1), 8, '0', STR_PAD_LEFT);
            $tracking = sprintf('TRK%03d', $i + 1);
            Shipment::firstOrCreate(
                ['account_id' => $aid, 'reference_number' => $ref],
                [
                    'account_id' => $aid,
                    'reference_number' => $ref,
                    'source' => 'direct',
                    'carrier_code' => $carriers[$i % 4][0],
                    'carrier_name' => $carriers[$i % 4][1],
                    'service_code' => ['express', 'standard', 'economy'][$i % 3],
                    'service_name' => ['Express', 'Standard', 'Economy'][$i % 3],
                    'tracking_number' => $tracking,
                    'status' => $status,
                    'sender_name' => 'شركة Demo',
                    'sender_phone' => '+966500000000',
                    'sender_address_1' => 'الرياض',
                    'sender_city' => $city[0],
                    'sender_country' => 'SA',
                    'recipient_name' => $customers[$i % count($customers)],
                    'recipient_phone' => '+9665' . rand(10000000, 99999999),
                    'recipient_address_1' => $city[1],
                    'recipient_city' => $city[1],
                    'recipient_country' => $destCountry,
                    'total_weight' => rand(1, 50) + rand(0, 9) / 10,
                    'parcels_count' => rand(1, 3),
                    'total_charge' => rand(25, 350) + .00,
                    'shipping_rate' => rand(15, 200) + .00,
                    'currency' => 'SAR',
                    'is_insured' => $i % 5 === 0,
                    'created_by' => $createdBy,
                    'created_at' => now()->subDays(rand(0, 30)),
                    'picked_up_at' => !in_array($status, ['payment_pending', 'cancelled']) ? now()->subDays(rand(0, 25)) : null,
                    'actual_delivery_at' => $status === 'delivered' ? now()->subDays(rand(0, 10)) : null,
                ]
            );
        }

        // ── Orders ──
        $storeId = Store::where('account_id', $aid)->value('id');
        if ($storeId) {
            $orderStatuses = ['pending', 'ready', 'shipped', 'delivered', 'cancelled'];
            for ($i = 0; $i < 15; $i++) {
                $extId = 'ORD-' . str_pad((string)($i + 1), 5, '0', STR_PAD_LEFT);
                Order::firstOrCreate(
                    ['account_id' => $aid, 'store_id' => $storeId, 'external_order_id' => $extId],
                    [
                        'account_id' => $aid,
                        'store_id' => $storeId,
                        'external_order_id' => $extId,
                        'external_order_number' => $extId,
                        'customer_name' => $customers[$i % count($customers)],
                        'customer_email' => 'customer' . ($i + 1) . '@email.com',
                        'customer_phone' => '+9665' . rand(10000000, 99999999),
                        'status' => $orderStatuses[$i % 5],
                        'total_amount' => rand(50, 2000) + .00,
                        'items_count' => rand(1, 5),
                        'source' => ['salla', 'zid', 'shopify', 'manual'][$i % 4],
                        'created_at' => now()->subDays(rand(0, 20)),
                    ]
                );
            }
        }

        // ── Notifications ──
        $notifRows = [
            ['subject' => 'تم تسليم الشحنة TRK000001', 'event_type' => 'shipment.delivered', 'body' => 'تم تسليم الشحنة بنجاح.'],
            ['subject' => 'طلب جديد من متجر سلة #ORD-00003', 'event_type' => 'order.created', 'body' => 'طلب جديد من المتجر.'],
            ['subject' => 'تنبيه: رصيد المحفظة أقل من 5000 ر.س', 'event_type' => 'wallet.low_balance', 'body' => 'رصيد المحفظة منخفض.'],
            ['subject' => 'تم تحديث حالة KYC', 'event_type' => 'kyc.updated', 'body' => 'تم تحديث حالة التحقق.'],
            ['subject' => 'فشل تتبع الشحنة TRK000005 — DHL API', 'event_type' => 'shipment.tracking_failed', 'body' => 'فشل في جلب التتبع.'],
        ];
        foreach ($notifRows as $i => $n) {
            Notification::firstOrCreate(
                ['account_id' => $aid, 'event_type' => $n['event_type'], 'subject' => $n['subject']],
                array_merge($n, [
                    'account_id' => $aid,
                    'channel' => 'in_app',
                    'destination' => '',
                    'status' => 'sent',
                    'read_at' => $i > 2 ? now()->subHours($i * 3) : null,
                    'sent_at' => now()->subHours($i * 3),
                ])
            );
        }

        // ── Support Tickets ──
        $tickets = [
            ['subject' => 'شحنة متأخرة TRK000003', 'description' => 'الشحنة لم تصل في الموعد.', 'priority' => 'high', 'status' => 'open'],
            ['subject' => 'خطأ في عنوان التسليم', 'description' => 'تم إدخال عنوان خاطئ.', 'priority' => 'medium', 'status' => 'resolved'],
            ['subject' => 'استفسار عن التسعير', 'description' => 'نريد معرفة أسعار الشحن.', 'priority' => 'low', 'status' => 'open'],
        ];
        foreach ($tickets as $j => $t) {
            $ticketNumber = 'TKT-' . str_pad((string)($j + 1), 6, '0', STR_PAD_LEFT);
            SupportTicket::firstOrCreate(
                ['account_id' => $aid, 'ticket_number' => $ticketNumber],
                array_merge($t, [
                    'account_id' => $aid,
                    'user_id' => $createdBy,
                    'ticket_number' => $ticketNumber,
                    'created_at' => now()->subDays(rand(1, 10)),
                ])
            );
        }

        // ── Audit Log ──
        $actions = [
            ['action' => 'account.login', 'entity_type' => 'User'],
            ['action' => 'shipment.created', 'entity_type' => 'Shipment'],
            ['action' => 'settings.updated', 'entity_type' => 'SystemSetting'],
            ['action' => 'store.connected', 'entity_type' => 'Store'],
            ['action' => 'wallet.deposit', 'entity_type' => 'Wallet'],
        ];
        foreach ($actions as $i => $a) {
            AuditLog::firstOrCreate(
                ['account_id' => $aid, 'action' => $a['action'], 'entity_type' => $a['entity_type'], 'created_at' => now()->subHours($i * 2)],
                [
                    'account_id' => $aid,
                    'user_id' => $createdBy,
                    'action' => $a['action'],
                    'entity_type' => $a['entity_type'],
                    'ip_address' => '192.168.1.' . rand(1, 254),
                    'user_agent' => 'Mozilla/5.0',
                    'created_at' => now()->subHours($i * 2),
                ]
            );
        }

        // ── KYC ──
        KycVerification::firstOrCreate(
            ['account_id' => $aid],
            [
                'account_id' => $aid,
                'status' => 'pending',
                'verification_type' => 'organization',
                'submitted_at' => now()->subDays(3),
            ]
        );
    }
}
