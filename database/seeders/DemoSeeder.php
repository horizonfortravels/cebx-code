<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Store;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\Order;
use App\Models\Address;
use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Models\Notification;
use App\Models\Invitation;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Vessel;
use App\Models\Container;
use App\Models\Schedule;
use App\Models\CustomsDeclaration;
use App\Models\Driver;
use App\Models\Claim;
use App\Models\HsCode;
use App\Models\KycRequest;
use App\Models\DgClassification;
use App\Models\PricingRule;
use App\Models\RiskRule;
use App\Models\RiskAlert;
use App\Models\AuditLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // ═══════════════════════════════════════
        // 1. ACCOUNT + USERS (متوافق مع جدول accounts: name, type, status, slug)
        // ═══════════════════════════════════════
        $account = Account::firstOrCreate(
            ['slug' => 'techco'],
            ['name' => 'شركة التقنية المتقدمة', 'type' => 'organization', 'status' => 'active']
        );

        $admin = User::firstOrCreate(
            ['account_id' => $account->id, 'email' => 'sultan@techco.sa'],
            ['name' => 'سلطان القحطاني', 'password' => Hash::make('password'), 'status' => 'active', 'is_owner' => true, 'is_active' => true, 'last_login_at' => now()]
        );

        $users = collect([
            ['name'=>'هند العتيبي','email'=>'hind@techco.sa'],
            ['name'=>'ماجد السبيعي','email'=>'majed@techco.sa'],
            ['name'=>'لمى الحربي','email'=>'lama@techco.sa','is_active'=>false],
        ])->map(fn($u) => User::firstOrCreate(
            ['account_id' => $account->id, 'email' => $u['email']],
            array_merge($u, [
                'password' => Hash::make('password'),
                'status' => ($u['is_active'] ?? true) ? 'active' : 'inactive',
                'is_active' => $u['is_active'] ?? true,
                'last_login_at' => now()->subHours(rand(1, 168)),
            ])
        ));

        // Platform admin (internal actor; no account.type admin usage)
        User::firstOrCreate(
            ['email' => 'admin@system.sa'],
            ['account_id' => null, 'name' => 'مدير النظام', 'password' => Hash::make('admin'), 'status' => 'active', 'is_active' => true, 'user_type' => 'internal', 'last_login_at' => now()]
        );

        // B2C Individual account
        $b2cAccount = Account::firstOrCreate(
            ['slug' => 'mohammed-individual'],
            ['name' => 'محمد العمري', 'type' => 'individual', 'status' => 'active']
        );
        $b2cUser = User::firstOrCreate(
            ['account_id'=>$b2cAccount->id,'email'=>'mohammed@example.sa'],
            [
                'name' => 'محمد العمري',
                'password' => Hash::make('password'),
                'status' => 'active',
                'is_active' => true,
                'last_login_at' => now(),
            ]
        );
        // B2C Wallet
        Wallet::firstOrCreate(['account_id' => $b2cAccount->id], ['available_balance' => 850.00]);

        // ═══════════════════════════════════════
        // 2. WALLET
        // ═══════════════════════════════════════
        $wallet = Wallet::firstOrCreate(['account_id' => $account->id], ['available_balance' => 12450.00]);

        // إيقاف هنا — باقي السيدر (معاملات المحفظة، الشحنات، إلخ) يتطلب نماذج ب UUID وهيكل جداول مطابق
        return;

        $txns = [
            ['type'=>'credit','description'=>'شحن رصيد — تحويل بنكي','amount'=>5000,'status'=>'completed','payment_method'=>'bank_transfer','created_at'=>now()->subDays(1)],
            ['type'=>'debit','description'=>'شحنة SHP-20261847 — أرامكس','amount'=>-32.50,'status'=>'completed','created_at'=>now()->subDays(1)->subHours(5)],
            ['type'=>'debit','description'=>'شحنة SHP-20261846 — سمسا','amount'=>-28.00,'status'=>'completed','created_at'=>now()->subDays(2)],
            ['type'=>'refund','description'=>'استرداد شحنة ملغاة SHP-20261841','amount'=>45.00,'status'=>'completed','created_at'=>now()->subDays(3)],
            ['type'=>'debit','description'=>'شحنة SHP-20261845 — DHL','amount'=>-55.00,'status'=>'completed','created_at'=>now()->subDays(3)->subHours(8)],
            ['type'=>'credit','description'=>'شحن رصيد — بطاقة ائتمان','amount'=>3000,'status'=>'completed','payment_method'=>'credit_card','created_at'=>now()->subDays(5)],
            ['type'=>'debit','description'=>'شحنة SHP-20261840 — أرامكس','amount'=>-29.50,'status'=>'completed','created_at'=>now()->subDays(5)->subHours(2)],
        ];
        $bal = 12450;
        foreach ($txns as $t) {
            WalletTransaction::create(array_merge($t, [
                'wallet_id' => $wallet->id,
                'account_id' => $account->id,
                'reference_number' => 'TXN-' . str_pad(WalletTransaction::count()+1, 5, '0', STR_PAD_LEFT),
                'balance_after' => $bal,
            ]));
            $bal -= $t['amount'];
        }

        // ═══════════════════════════════════════
        // 3. STORES
        // ═══════════════════════════════════════
        $storeData = [
            ['name'=>'متجر التقنية','platform'=>'salla','orders_count'=>234,'status'=>'connected','last_sync_at'=>now()->subMinutes(5)],
            ['name'=>'أزياء الخليج','platform'=>'zid','orders_count'=>156,'status'=>'connected','last_sync_at'=>now()->subMinutes(12)],
            ['name'=>'Tech Store SA','platform'=>'shopify','orders_count'=>89,'status'=>'connected','last_sync_at'=>now()->subHour()],
            ['name'=>'الأثاث العصري','platform'=>'woocommerce','orders_count'=>45,'status'=>'disconnected','last_sync_at'=>now()->subDays(3)],
        ];
        $storeModels = [];
        foreach ($storeData as $s) {
            $storeModels[] = Store::create(array_merge($s, ['account_id' => $account->id, 'store_url' => 'https://' . fake()->domainName()]));
        }

        // ═══════════════════════════════════════
        // 4. SHIPMENTS
        // ═══════════════════════════════════════
        $carriers = [
            ['code'=>'aramex','name'=>'أرامكس'],
            ['code'=>'smsa','name'=>'سمسا'],
            ['code'=>'dhl','name'=>'DHL'],
            ['code'=>'fedex','name'=>'فيدكس'],
            ['code'=>'jnt','name'=>'J&T'],
        ];
        $cities = ['الرياض','جدة','الدمام','مكة','المدينة','تبوك','أبها','الطائف','حائل','نجران','الخبر','الجبيل'];
        $statuses = ['pending','processing','shipped','in_transit','out_for_delivery','delivered','cancelled','returned'];
        $names = ['أحمد محمد','فاطمة علي','خالد عبدالله','نورة سعد','عمر حسن','سارة يوسف','محمد إبراهيم','ريم أحمد','عبدالرحمن سالم','مريم خالد','يوسف عبدالله','هدى محمد','حسن علي','ليلى سعد','طارق فهد'];

        $shipmentModels = [];
        for ($i = 0; $i < 50; $i++) {
            $carrier = $carriers[array_rand($carriers)];
            $status = $statuses[array_rand($statuses)];
            $cost = rand(2000, 15000) / 100;
            $vat = round($cost * 0.15, 2);
            $createdAt = now()->subDays(rand(0, 30))->subHours(rand(0, 23));

            $shipmentModels[] = Shipment::create([
                'account_id' => $account->id,
                'user_id' => $admin->id,
                'reference_number' => 'SHP-' . (2026) . str_pad(1847 - $i, 4, '0', STR_PAD_LEFT),
                'type' => 'domestic',
                'sender_name' => 'شركة التقنية المتقدمة',
                'sender_phone' => '+966112345678',
                'sender_city' => 'الرياض',
                'recipient_name' => $names[array_rand($names)],
                'recipient_phone' => '+9665' . rand(10000000, 99999999),
                'recipient_city' => $cities[array_rand($cities)],
                'carrier_code' => $carrier['code'],
                'carrier_name' => $carrier['name'],
                'carrier_tracking_number' => strtoupper($carrier['code']) . rand(100000000, 999999999),
                'weight' => rand(5, 300) / 10,
                'pieces' => rand(1, 5),
                'content_description' => fake('ar_SA')->sentence(3),
                'declared_value' => rand(50, 5000),
                'shipping_cost' => $cost,
                'vat_amount' => $vat,
                'total_cost' => $cost + $vat,
                'status' => $status,
                'source' => ['manual','api','store_sync'][rand(0,2)],
                'shipped_at' => in_array($status, ['shipped','in_transit','out_for_delivery','delivered']) ? $createdAt->copy()->addHours(rand(1,6)) : null,
                'delivered_at' => $status === 'delivered' ? $createdAt->copy()->addDays(rand(1,4)) : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        // ═══════════════════════════════════════
        // ═══════════════════════════════════════
        // 4b. B2C SHIPMENTS
        // ═══════════════════════════════════════
        $b2cStatuses = ['pending','in_transit','delivered','delivered','out_for_delivery'];
        for ($i = 0; $i < 5; $i++) {
            $carrier = $carriers[array_rand($carriers)];
            Shipment::create([
                'account_id' => $b2cAccount->id, 'user_id' => $b2cUser->id,
                'reference_number' => 'SHP-B2C-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'type' => 'domestic', 'sender_name' => 'محمد العمري', 'sender_phone' => '+966551234567',
                'sender_city' => 'الرياض', 'recipient_name' => $names[array_rand($names)],
                'recipient_phone' => '+9665' . rand(10000000, 99999999),
                'recipient_city' => $cities[array_rand($cities)],
                'carrier_code' => $carrier['code'], 'carrier_name' => $carrier['name'],
                'weight' => rand(5, 50) / 10, 'pieces' => 1, 'shipping_cost' => 18, 'vat_amount' => 2.70,
                'total_cost' => 20.70, 'status' => $b2cStatuses[array_rand($b2cStatuses)], 'source' => 'manual',
            ]);
        }

        // ═══════════════════════════════════════
        // 5. ORDERS
        // ═══════════════════════════════════════
        $orderStatuses = ['new','processing','shipped','delivered','cancelled'];
        for ($i = 0; $i < 20; $i++) {
            $store = $storeModels[array_rand($storeModels)];
            Order::create([
                'account_id' => $account->id,
                'store_id' => $store->id,
                'order_number' => '#ORD-' . (5521 - $i),
                'customer_name' => $names[array_rand($names)],
                'customer_phone' => '+9665' . rand(10000000, 99999999),
                'customer_city' => $cities[array_rand($cities)],
                'items_count' => rand(1, 8),
                'total_amount' => rand(50, 3000),
                'status' => $orderStatuses[array_rand($orderStatuses)],
                'created_at' => now()->subDays(rand(0, 14)),
            ]);
        }

        // ═══════════════════════════════════════
        // 6. ADDRESSES
        // ═══════════════════════════════════════
        Address::create(['account_id'=>$account->id,'label'=>'المكتب الرئيسي','name'=>'شركة التقنية المتقدمة','phone'=>'+966112345678','city'=>'الرياض','district'=>'العليا','street'=>'شارع العليا العام','postal_code'=>'11564','is_default'=>true]);
        Address::create(['account_id'=>$account->id,'label'=>'المستودع','name'=>'مستودع الشحن','phone'=>'+966112345679','city'=>'الرياض','district'=>'السلي','street'=>'المنطقة الصناعية الثانية','postal_code'=>'14332','is_default'=>false]);

        // ═══════════════════════════════════════
        // 7. SUPPORT TICKETS
        // ═══════════════════════════════════════
        $ticket1 = SupportTicket::create(['account_id'=>$account->id,'user_id'=>$admin->id,'reference_number'=>'TKT-0001','subject'=>'شحنة متأخرة','body'=>'الشحنة SHP-20261845 لم تصل بعد رغم مرور 5 أيام','category'=>'shipment','priority'=>'high','status'=>'open']);
        $ticket2 = SupportTicket::create(['account_id'=>$account->id,'user_id'=>$admin->id,'reference_number'=>'TKT-0002','subject'=>'طلب فاتورة ضريبية','body'=>'نحتاج فاتورة ضريبية لعمليات شهر يناير','category'=>'billing','priority'=>'medium','status'=>'resolved']);
        TicketReply::create(['support_ticket_id'=>$ticket1->id,'user_id'=>$admin->id,'body'=>'يرجى التحقق من حالة الشحنة','is_agent'=>true]);

        // ═══════════════════════════════════════
        // 8. NOTIFICATIONS
        // ═══════════════════════════════════════
        $notifData = [
            ['type'=>'shipment','title'=>'تم تسليم الشحنة','body'=>'الشحنة SHP-20261847 تم تسليمها بنجاح إلى أحمد محمد في جدة'],
            ['type'=>'shipment','title'=>'شحنة خرجت للتوصيل','body'=>'الشحنة SHP-20261845 خرجت للتوصيل عبر DHL'],
            ['type'=>'wallet','title'=>'تم شحن الرصيد','body'=>'تم إضافة SAR 5,000 لرصيد المحفظة عبر تحويل بنكي'],
            ['type'=>'system','title'=>'تحديث الأسعار','body'=>'تم تحديث أسعار الشحن لناقل أرامكس — محلي'],
            ['type'=>'shipment','title'=>'شحنة ملغاة','body'=>'تم إلغاء الشحنة SHP-20261841 واسترداد المبلغ','read_at'=>now()],
        ];
        foreach ($notifData as $j => $n) {
            Notification::create(array_merge($n, ['account_id'=>$account->id,'user_id'=>$admin->id,'created_at'=>now()->subHours($j * 3),'read_at'=>$n['read_at']??null]));
        }

        // ═══════════════════════════════════════
        // 9. INVITATIONS
        // ═══════════════════════════════════════
        Invitation::create(['account_id'=>$account->id,'email'=>'new@techco.sa','token'=>'inv_'.bin2hex(random_bytes(16)),'status'=>'pending','expires_at'=>now()->addDays(7)]);
        Invitation::create(['account_id'=>$account->id,'email'=>'designer@techco.sa','token'=>'inv_'.bin2hex(random_bytes(16)),'status'=>'accepted','expires_at'=>now()->addDays(7),'created_at'=>now()->subDays(5)]);
        Invitation::create(['account_id'=>$account->id,'email'=>'old@techco.sa','token'=>'inv_'.bin2hex(random_bytes(16)),'status'=>'expired','expires_at'=>now()->subDays(20),'created_at'=>now()->subDays(40)]);

        // ═══════════════════════════════════════
        // 10. COMPANIES (carriers)
        // ═══════════════════════════════════════
        foreach ($carriers as $c) {
            Company::create(['name'=>$c['name'],'code'=>$c['code'],'type'=>'carrier','country'=>'SA','is_active'=>true,'rating'=>rand(35,50)/10,'shipments_count'=>rand(100,2000),'contact_email'=>$c['code'].'@logistics.sa']);
        }

        // ═══════════════════════════════════════
        // 11. BRANCHES
        // ═══════════════════════════════════════
        Branch::create(['name'=>'الفرع الرئيسي','code'=>'RUH-01','city'=>'الرياض','region'=>'الوسطى','phone'=>'+966112345678','manager_name'=>'سلطان القحطاني','is_active'=>true,'employees_count'=>25]);
        Branch::create(['name'=>'فرع جدة','code'=>'JED-01','city'=>'جدة','region'=>'الغربية','phone'=>'+966122345678','manager_name'=>'أحمد العمري','is_active'=>true,'employees_count'=>15]);
        Branch::create(['name'=>'فرع الدمام','code'=>'DMM-01','city'=>'الدمام','region'=>'الشرقية','phone'=>'+966132345678','manager_name'=>'فهد الدوسري','is_active'=>true,'employees_count'=>10]);

        // ═══════════════════════════════════════
        // 12. LOGISTICS
        // ═══════════════════════════════════════
        $v1 = Vessel::create(['name'=>'الملك عبدالله','imo_number'=>'IMO9876543','type'=>'Container Ship','capacity_teu'=>4500,'flag'=>'SA','status'=>'at_sea','current_location'=>'البحر الأحمر']);
        $v2 = Vessel::create(['name'=>'جدة إكسبرس','imo_number'=>'IMO9876544','type'=>'Container Ship','capacity_teu'=>2800,'flag'=>'SA','status'=>'docked','current_location'=>'ميناء جدة الإسلامي']);
        Container::create(['container_number'=>'ABCU1234567','type'=>'Standard','size'=>'40ft','vessel_id'=>$v1->id,'origin_port'=>'جدة','destination_port'=>'دبي','status'=>'in_transit']);
        Container::create(['container_number'=>'XYZL7654321','type'=>'High Cube','size'=>'40ft','vessel_id'=>$v2->id,'origin_port'=>'شنغهاي','destination_port'=>'جدة','status'=>'at_port']);
        Schedule::create(['voyage_number'=>'VY-2026-001','vessel_id'=>$v1->id,'origin_port'=>'جدة','destination_port'=>'دبي','departure_date'=>now()->addDays(2),'arrival_date'=>now()->addDays(5),'status'=>'scheduled']);

        // Customs
        CustomsDeclaration::create(['declaration_number'=>'CD-2026-0001','shipment_id'=>$shipmentModels[0]->id??null,'type'=>'export','hs_code'=>'8471.30.00','declared_value'=>5000,'duty_amount'=>250,'port_name'=>'ميناء جدة','status'=>'cleared']);

        // Drivers
        Driver::create(['name'=>'عبدالله الشمري','phone'=>'+966551234567','employee_id'=>'DRV-001','license_number'=>'DL-9876543','vehicle_plate'=>'أ ب ج 1234','region'=>'الرياض','status'=>'on_duty','rating'=>4.8,'deliveries_count'=>1234]);
        Driver::create(['name'=>'سعود العنزي','phone'=>'+966559876543','employee_id'=>'DRV-002','license_number'=>'DL-1234567','vehicle_plate'=>'د هـ و 5678','region'=>'جدة','status'=>'available','rating'=>4.5,'deliveries_count'=>987]);

        // Claims
        Claim::create(['account_id'=>$account->id,'shipment_id'=>$shipmentModels[6]->id??null,'type'=>'damage','amount'=>350,'description'=>'تلف في المنتج أثناء الشحن','status'=>'pending']);

        // HS Codes
        HsCode::create(['code'=>'8471.30.00','description_ar'=>'أجهزة حاسب آلي محمولة','description_en'=>'Portable computers','chapter'=>84,'duty_rate'=>5,'is_restricted'=>false]);
        HsCode::create(['code'=>'6110.20.00','description_ar'=>'ملابس قطنية','description_en'=>'Cotton garments','chapter'=>61,'duty_rate'=>12,'is_restricted'=>false]);

        // KYC
        KycRequest::create(['account_id'=>$account->id,'type'=>'company','status'=>'verified','documents_count'=>4]);

        // DG
        DgClassification::create(['class_number'=>3,'description'=>'سوائل قابلة للاشتعال','un_number'=>'UN1203','packing_group'=>'II','is_allowed'=>true]);
        DgClassification::create(['class_number'=>7,'description'=>'مواد مشعة','un_number'=>'UN2982','is_allowed'=>false,'restrictions'=>'محظور الشحن الجوي']);

        // Pricing
        PricingRule::create(['carrier_code'=>'aramex','carrier_name'=>'أرامكس','service_type'=>'domestic','zone_name'=>'داخل المدينة','base_weight'=>1,'base_price'=>18,'extra_kg_price'=>3,'is_active'=>true]);
        PricingRule::create(['carrier_code'=>'smsa','carrier_name'=>'سمسا','service_type'=>'domestic','zone_name'=>'بين المدن','base_weight'=>1,'base_price'=>22,'extra_kg_price'=>4,'is_active'=>true]);
        PricingRule::create(['carrier_code'=>'dhl','carrier_name'=>'DHL','service_type'=>'international','zone_name'=>'الخليج','base_weight'=>0.5,'base_price'=>55,'extra_kg_price'=>15,'is_active'=>true]);

        // Risk
        $r1 = RiskRule::create(['name'=>'شحنة عالية القيمة','condition_description'=>'إذا تجاوزت قيمة الشحنة 50,000 ريال','risk_level'=>'high','action_description'=>'مراجعة يدوية + إشعار المدير','is_active'=>true]);
        RiskAlert::create(['risk_rule_id'=>$r1->id,'title'=>'شحنة بقيمة 65,000 ريال','description'=>'الشحنة SHP-20261850 تجاوزت حد القيمة المسموح','level'=>'high']);

        // Audit
        AuditLog::create(['user_id'=>$admin->id,'event'=>'login','ip_address'=>'192.168.1.100','created_at'=>now()]);
        AuditLog::create(['user_id'=>$admin->id,'event'=>'create','auditable_type'=>'App\\Models\\Shipment','auditable_id'=>1,'new_values'=>['reference_number'=>'SHP-20261847','status'=>'pending'],'ip_address'=>'192.168.1.100','created_at'=>now()->subHour()]);

        $this->command->info("✅ Demo data seeded: {$account->name}");
        $this->command->info("   B2B Login: sultan@techco.sa / password");
        $this->command->info("   B2C Login: mohammed@example.sa / password");
        $this->command->info("   Admin Login: admin@system.sa / admin");
    }
}

