# FR-IAM-008 — إدارة إعدادات الحساب (Account Settings)

## ملخص الميزة

| البند | التفاصيل |
|-------|---------|
| **المعرف** | FR-IAM-008 |
| **العنوان** | إدارة إعدادات الحساب |
| **الأولوية** | Must (Priority 1) |
| **الحالة** | ✅ مكتمل |
| **الاعتمادية** | FR-IAM-001 (Accounts), FR-IAM-003 (RBAC), FR-IAM-006 (Audit) |
| **الاختبارات** | 37 اختبار (20 وحدة + 17 تكامل) |

---

## العمارة

### تخزين الإعدادات (Hybrid Approach)

```
┌──────────────────────────────────────────────────────┐
│  Dedicated Columns (indexed, type-safe)              │
│  language, currency, timezone, country,              │
│  contact_phone, contact_email, address,              │
│  date_format, weight_unit, dimension_unit            │
├──────────────────────────────────────────────────────┤
│  JSONB Column (flexible, extensible)                 │
│  settings: { notification_email, theme, ... }        │
└──────────────────────────────────────────────────────┘
```

**لماذا Hybrid؟**
- الأعمدة المخصصة: أسرع في الاستعلام، تدعم الفهرسة، type-safe
- JSONB: مرن للإعدادات المخصصة والمستقبلية بدون migration

### التدفق

```
Owner/Admin ──→ PUT /settings ──→ Validation ──→ Service ──→ DB + Audit Log
                                    │                         │
                                    └─ 422 if invalid ─────── └─ Log old → new values
```

---

## الملفات (8 ملفات)

```
shipping-gateway/
├── database/migrations/
│   └── 2026_02_12_000009_add_account_settings_columns.php   # 13 عمود جديد
├── app/
│   ├── Models/
│   │   └── Account.php                                       # ★ Enhanced: constants, helpers
│   ├── Services/
│   │   └── AccountSettingsService.php                        # ★ New: get/update/reset/options
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   └── AccountSettingsController.php                 # 4 endpoints
│   │   └── Requests/
│   │       └── UpdateAccountSettingsRequest.php              # Validation rules
│   └── database/factories/
│       └── AccountFactory.php                                # Updated with new fields
├── routes/
│   └── api.php                                               # +4 settings routes
└── tests/
    ├── Unit/AccountSettingsTest.php                           # 20 tests
    └── Feature/AccountSettingsApiTest.php                     # 17 tests
```

---

## API Endpoints (4 endpoints)

| Method | Endpoint | الوصف | الصلاحية |
|--------|----------|-------|---------|
| GET | `/api/v1/account/settings` | عرض جميع الإعدادات | Any authenticated |
| PUT | `/api/v1/account/settings` | تحديث جزئي/كامل | `account:manage` أو Owner |
| POST | `/api/v1/account/settings/reset` | إعادة الضبط للافتراضي | `account:manage` أو Owner |
| GET | `/api/v1/account/settings/options` | الخيارات المدعومة | Any authenticated |

### أمثلة

**عرض الإعدادات:**
```json
GET /api/v1/account/settings
{
  "data": {
    "language": "ar",
    "currency": "SAR",
    "timezone": "Asia/Riyadh",
    "country": "SA",
    "contact_phone": "+966501234567",
    "contact_email": "info@company.sa",
    "address": {
      "line_1": "شارع الملك فهد",
      "city": "الرياض",
      "postal_code": "12345",
      "country": "SA"
    },
    "date_format": "Y-m-d",
    "weight_unit": "kg",
    "dimension_unit": "cm",
    "extended": {}
  }
}
```

**تحديث جزئي:**
```json
PUT /api/v1/account/settings
{
  "language": "en",
  "currency": "USD",
  "weight_unit": "lb"
}
→ 200 OK (updated settings)
```

**عملة غير مدعومة:**
```json
PUT /api/v1/account/settings
{ "currency": "XYZ" }
→ 422 { "errors": { "currency": ["العملة المختارة غير مدعومة."] } }
```

---

## القيم المدعومة

| الإعداد | الخيارات |
|---------|---------|
| **اللغات** | ar (العربية), en (English), fr (Français), tr (Türkçe), ur (اردو) |
| **العملات** | SAR, AED, USD, EUR, GBP, EGP, KWD, BHD, OMR, QAR, JOD, TRY |
| **المناطق الزمنية** | Asia/Riyadh, Asia/Dubai, Asia/Kuwait, Europe/London, UTC, +8 أخرى |
| **الدول** | SA, AE, KW, BH, QA, OM, JO, EG, TR, US, GB |
| **صيغة التاريخ** | Y-m-d, d/m/Y, m/d/Y, d-m-Y, d.m.Y |
| **وحدة الوزن** | kg, lb |
| **وحدة الأبعاد** | cm, in |

---

## قواعد الأعمال

| # | القاعدة |
|---|--------|
| 1 | القيم الافتراضية: ar, SAR, Asia/Riyadh, SA, Y-m-d, kg, cm |
| 2 | التعديل مسموح لـ Owner أو `account:manage` فقط |
| 3 | العرض مسموح لأي مستخدم مصادق |
| 4 | كل تغيير يُسجَّل في Audit Log مع old → new |
| 5 | التحديث جزئي (partial) — فقط الحقول المرسلة تتغير |
| 6 | القيم المرسلة يجب أن تكون من القوائم المدعومة |
| 7 | الإعدادات الممتدة (extended) تُدمج ولا تُستبدل |
| 8 | إعادة الضبط تُعيد الأعمدة الأساسية فقط (لا تمسح extended) |
| 9 | الإعدادات تُطبَّق افتراضياً في الشحنات والتقارير |
| 10 | محاولة تعديل بدون صلاحية → 403 + audit warning |

---

## تغطية الاختبارات (37 اختبار)

### وحدة (20 اختبار)

| المجموعة | العدد |
|----------|-------|
| Get Settings | 2 |
| Update Settings | 9 |
| Extended Settings | 2 |
| Audit Logging | 2 |
| Reset to Defaults | 2 |
| Supported Options | 2 |
| No-change detection | 1 |

### تكامل (17 اختبار)

| المجموعة | العدد |
|----------|-------|
| GET /settings | 2 |
| PUT /settings | 10 |
| POST /settings/reset | 2 |
| GET /settings/options | 2 |
| Audit verification | 1 |

---

## تشغيل الاختبارات

```bash
php artisan test tests/Unit/AccountSettingsTest.php tests/Feature/AccountSettingsApiTest.php
```
