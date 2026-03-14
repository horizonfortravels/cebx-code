# FR-IAM-012 — إخفاء البيانات المالية الحساسة (Financial Data Masking)

## ملخص الميزة

| البند | التفاصيل |
|-------|---------|
| **المعرف** | FR-IAM-012 |
| **العنوان** | إخفاء البيانات المالية الحساسة |
| **الأولوية** | Must (Priority 1) |
| **الحالة** | ✅ مكتمل |
| **الاعتمادية** | FR-IAM-003 (RBAC), FR-IAM-006 (Audit Log) |
| **الاختبارات** | 48 اختبار (31 وحدة + 17 تكامل) |

---

## العمارة والتصميم

### مبدأ الإخفاء: Permission-Based Field-Level Authorization

```
┌──────────────────────────────────────────────────┐
│            DataMaskingService                     │
│   (Central masking — ALL modules use this)        │
├──────────────────────────────────────────────────┤
│  maskCardNumber()    → •••• •••• •••• 1234       │
│  maskIban()          → SA03••••••••7519          │
│  maskEmail()         → a••••d@example.com        │
│  maskPhone()         → •••••••4567               │
│  filterFinancialData() → Permission-based filter │
│  sanitizeForAuditLog() → Always-safe for logs    │
│  visibilityMap()     → What can this user see?   │
└──────────────────────────────────────────────────┘
```

### تسلسل الصلاحيات (Permission Hierarchy)

```
Owner (implicit ALL)
  ├── financial:profit.view → Net Rate, Retail, Profit, Pricing Breakdown
  ├── financial:view       → Totals, Tax, COD, Wallet Balance
  └── financial:cards.view → Unmasked Card/IBAN data

Printer (NO financial permissions):
  ├── Net/Profit:    ██████ (masked/null)
  ├── Totals/Tax:    ██████ (masked/null)
  └── Card Number:   •••• •••• •••• 1234 (last 4 only)

Viewer (financial:view only):
  ├── Net/Profit:    ██████ (masked/null)
  ├── Totals/Tax:    ✅ visible
  └── Card Number:   •••• •••• •••• 1234 (last 4 only)

Accountant (financial:view + profit.view + cards.view):
  ├── Net/Profit:    ✅ visible
  ├── Totals/Tax:    ✅ visible
  └── Card Number:   ✅ visible (full)
```

---

## الملفات الجديدة/المعدلة (8 ملفات)

```
shipping-gateway/
├── app/
│   ├── Services/
│   │   └── DataMaskingService.php           # ★ خدمة الإخفاء المركزية (377 سطر)
│   ├── Http/Controllers/Api/V1/
│   │   └── FinancialDataController.php      # 4 API endpoints
│   ├── Rbac/
│   │   └── PermissionsCatalog.php           # +2 صلاحيات جديدة
│   └── Security/
│       └── FinancialDataSecurityReview.php  # مراجعة أمنية موثقة
├── routes/
│   └── api.php                              # +4 مسارات مالية
└── tests/
    ├── Unit/DataMaskingTest.php             # 30 اختبار وحدة
    └── Feature/FinancialDataApiTest.php     # 18 اختبار تكامل
```

---

## API Endpoints (4 endpoints)

| Method | Endpoint | الوصف |
|--------|----------|-------|
| GET | `/api/v1/financial/visibility` | خريطة ما يمكن للمستخدم رؤيته |
| GET | `/api/v1/financial/sensitive-fields` | قائمة الحقول الحساسة وصلاحياتها |
| POST | `/api/v1/financial/mask-card` | إخفاء رقم بطاقة |
| POST | `/api/v1/financial/filter` | تصفية بيانات مالية حسب الصلاحيات |

### أمثلة

**خريطة الرؤية (طابعة):**
```json
GET /api/v1/financial/visibility

{
  "success": true,
  "data": {
    "financial_general": false,
    "financial_profit": false,
    "financial_cards": false,
    "masked_fields": ["net_rate", "profit", "total_amount", "card_number", ...],
    "visible_fields": []
  }
}
```

**تصفية بيانات (محاسب):**
```json
POST /api/v1/financial/filter
{
  "data": {
    "net_rate": 150, "profit": 50, "total_amount": 500,
    "card_number": "4111111111111234", "tracking": "TRACK123"
  }
}

→ Response:
{
  "data": {
    "net_rate": 150, "profit": 50, "total_amount": 500,
    "card_number": "4111111111111234", "tracking": "TRACK123"
  }
}
```

**تصفية بيانات (طابعة):**
```json
Same request → Response:
{
  "data": {
    "net_rate": null, "profit": null, "total_amount": null,
    "card_number": "•••• •••• •••• 1234", "tracking": "TRACK123"
  }
}
```

---

## الصلاحيات الجديدة

| الصلاحية | الوصف |
|----------|-------|
| `financial:profit.view` | عرض بيانات الربح والتكلفة الصافية (Net/Retail/Profit) |
| `financial:cards.view` | عرض بيانات بطاقات الدفع بدون إخفاء |

### القوالب المحدثة

| القالب | `financial:view` | `financial:profit.view` | `financial:cards.view` |
|--------|:---:|:---:|:---:|
| Admin | ✅ | ✅ | ✅ |
| Accountant | ✅ | ✅ | ✅ |
| Warehouse | ❌ | ❌ | ❌ |
| Viewer | ❌ | ❌ | ❌ |
| Printer | ❌ | ❌ | ❌ |

---

## قواعد الأعمال

| # | القاعدة |
|---|--------|
| 1 | أرقام البطاقات تُعرض كـ `•••• •••• •••• XXXX` (آخر 4 فقط) |
| 2 | الـ IBAN يُعرض كـ `SA03••••••••7519` (أول 4 + آخر 4) |
| 3 | بيانات الربح (Net/Profit/Pricing) تتطلب `financial:profit.view` |
| 4 | البيانات المالية العامة (Total/Tax) تتطلب `financial:view` |
| 5 | بدون أي صلاحية مالية → جميع الحقول المالية مخفية |
| 6 | Owner يرى كل شيء (ضمنياً) |
| 7 | Fail-Safe: عند فشل الإخفاء → البيانات تبقى مخفية |
| 8 | سجلات التدقيق تُنظَّف تلقائياً من أرقام البطاقات والكلمات السرية |
| 9 | كل محاولة وصول للبيانات المالية تُسجَّل في Audit Log |
| 10 | الحقول غير المالية لا تتأثر بالإخفاء |

---

## الحقول المحمية

### تتطلب `financial:profit.view`
`net_rate`, `net_cost`, `retail_rate`, `retail_cost`, `profit`, `profit_margin`, `margin_percentage`, `pricing_breakdown`, `cost_breakdown`, `carrier_cost`, `markup`, `markup_percentage`, `fees_breakdown`

### تتطلب `financial:view`
`total_amount`, `subtotal`, `tax_amount`, `discount_amount`, `balance`, `wallet_balance`, `invoice_total`, `cod_amount`

### تتطلب `financial:cards.view`
`card_number`, `card_holder_name`, `card_expiry`, `iban`, `bank_account`, `bank_name`, `payment_token`

---

## تغطية الاختبارات (48 اختبار)

### وحدة (31 اختبار) — DataMaskingTest

| المجموعة | العدد | التغطية |
|----------|-------|---------|
| Card Number Masking | 9 | 16-digit, spaces, dashes, Amex, short, null, empty, last4 |
| IBAN Masking | 3 | Standard, short, null |
| Email Masking | 2 | Standard, short |
| Phone Masking | 1 | Standard |
| Financial Field Filtering | 7 | Owner, accountant, viewer, printer, null user, stars, non-financial |
| Collection Filtering | 1 | Array of items |
| Permission Checks | 3 | canViewProfit, canViewFinancial, canViewCards |
| Visibility Map | 2 | Owner (all true), printer (all false) |
| Audit Sanitization | 3 | Card in audit, password redact, IBAN in audit |

### تكامل (17 اختبار) — FinancialDataApiTest

| المجموعة | العدد | التغطية |
|----------|-------|---------|
| Visibility Endpoint | 4 | Owner, printer, viewer, accountant |
| Mask Card Endpoint | 3 | Success, audit log, validation |
| Filter Data Endpoint | 5 | Owner, printer, viewer, meta, audit |
| Sensitive Fields | 1 | Lists all categories |
| Edge Cases | 2 | Failure safety, unusual card length |
| Template Validation | 2 | Printer (no financial), accountant (full) |

---

## استخدام الخدمة (للمطورين)

```php
use App\Services\DataMaskingService;

// === في أي Resource أو Controller ===

// 1. تصفية بيانات شحنة حسب صلاحيات المستخدم
$shipmentData = [
    'tracking' => 'TRACK123',
    'net_rate' => 150.00,
    'profit'   => 50.00,
    'total_amount' => 500.00,
    'card_number' => '4111111111111234',
];

$filtered = DataMaskingService::filterFinancialData($shipmentData, auth()->user());
// Printer → net_rate=null, profit=null, total_amount=null, card=masked

// 2. إخفاء رقم بطاقة
$masked = DataMaskingService::maskCardNumber('4111111111111234');
// → "•••• •••• •••• 1234"

// 3. تنظيف بيانات قبل حفظها في Audit Log
$safeValues = DataMaskingService::sanitizeForAuditLog($dirtyValues);

// 4. التحقق من الصلاحية قبل عرض عمود
if (DataMaskingService::canViewProfitData($user)) {
    // Show profit column
}

// 5. خريطة الرؤية للـ Frontend
$map = DataMaskingService::visibilityMap($user);
// { financial_general: true, financial_profit: false, ... }
```

---

## تشغيل الاختبارات

```bash
# جميع اختبارات FR-IAM-012
php artisan test tests/Unit/DataMaskingTest.php tests/Feature/FinancialDataApiTest.php

# اختبارات الوحدة
php artisan test tests/Unit/DataMaskingTest.php

# اختبارات التكامل
php artisan test tests/Feature/FinancialDataApiTest.php
```
