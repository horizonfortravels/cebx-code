# 🚀 Shipping Gateway — Laravel + Blade (Full Stack PHP)

## الهيكل
```
Laravel 11 + Blade — كل شيء PHP بدون React/JS frameworks
```

## التقنيات
| الطبقة | التقنية |
|--------|---------|
| Backend | PHP 8.3 + Laravel 11 |
| Frontend | Blade Templates + Vanilla CSS |
| Database | PostgreSQL / MySQL |
| Auth | Laravel Auth (session-based) |
| Views | Blade Components + Layouts |

## الشاشات (38 صفحة)
### الرئيسية
- لوحة التحكم (Dashboard)
- الشحنات (Shipments) + تفاصيل الشحنة
- الطلبات (Orders)
- المتاجر (Stores)
- التتبع المباشر (Live Tracking)
- التسعير (Pricing)

### المالية
- المحفظة (Wallet) — كشف حساب + وسائل دفع
- المالية (Financial Reports)

### الإدارة
- المستخدمين (Users)
- الأدوار والصلاحيات (Roles & Permissions)
- الدعوات (Invitations)
- المنظمات (Organizations)

### النظام
- الإشعارات (Notifications)
- التقارير (Reports)
- سجل التدقيق (Audit Log)
- التحقق KYC
- البضائع الخطرة DG
- الدعم الفني (Support) + تفاصيل التذكرة
- العناوين (Addresses)
- الإعدادات (Settings)
- لوحة الإدارة (Admin Panel)

### Phase 2
- الحاويات (Containers)
- الجمارك (Customs)
- السائقين (Drivers)
- المطالبات (Claims)
- المخاطر (Risk Assessment)
- السفن (Vessels)
- جداول السفن (Schedules)
- الفروع (Branches)
- الشركات الناقلة (Companies)
- أكواد HS (HS Codes)

## التشغيل
```bash
# 1. تثبيت المتطلبات
composer install

# 2. إعداد البيئة
cp .env.example .env
php artisan key:generate

# 3. قاعدة البيانات
php artisan migrate --seed

# 4. التشغيل
php artisan serve
```

## هيكل الملفات
```
app/
├── Http/Controllers/
│   ├── Api/V1/          # 56 API Controller (للتطبيقات)
│   └── Web/             # 8 Web Controllers (Blade)
│       ├── AuthWebController.php
│       ├── DashboardController.php
│       ├── ShipmentWebController.php
│       ├── OrderWebController.php
│       ├── StoreWebController.php
│       ├── WalletWebController.php
│       ├── UserWebController.php
│       ├── SupportWebController.php
│       └── PageController.php (25 sub-pages)
├── Models/              # 126 Model
├── Services/            # 40 Service
├── Enums/               # 11 Enum
├── Events/              # 7 Event
├── Listeners/           # 2 Listener
├── Mail/                # 7 Mailable
├── Notifications/       # 6 Notification
├── Policies/            # 8 Policy
└── Middleware/           # 5 Middleware

resources/views/
├── layouts/
│   ├── app.blade.php    # Main layout (sidebar + topbar)
│   └── auth.blade.php   # Auth layout
├── components/          # 7 Blade Components
│   ├── badge.blade.php
│   ├── card.blade.php
│   ├── info-row.blade.php
│   ├── modal.blade.php
│   ├── page-header.blade.php
│   ├── stat-card.blade.php
│   └── toast.blade.php
└── pages/               # 32 Page directories
    ├── auth/login.blade.php
    ├── dashboard/index.blade.php
    ├── shipments/index.blade.php + show.blade.php
    ├── orders/index.blade.php
    ├── stores/index.blade.php
    ├── wallet/index.blade.php
    ├── users/index.blade.php
    ├── support/index.blade.php + show.blade.php
    └── ... (25 more pages)

routes/
├── api.php              # 325 API Routes
└── web.php              # 66 Web Routes

database/
├── migrations/          # 27 migrations (128 tables)
├── seeders/             # 11 seeders
└── factories/           # 50 factories

tests/                   # 51 tests (26 Feature + 25 Unit)
```

## الإحصائيات
| المكون | العدد |
|--------|-------|
| Models | 126 |
| Controllers (API) | 56 |
| Controllers (Web) | 8 |
| Services | 40 |
| Blade Views | 40+ |
| Blade Components | 7 |
| API Routes | 325 |
| Web Routes | 66 |
| Migrations | 27 (128 tables) |
| Tests | 51 |
| Total PHP Files | 500+ |
