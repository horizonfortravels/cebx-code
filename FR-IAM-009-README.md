# FR-IAM-009 — إدارة المتاجر وقنوات البيع (Multi-Store Management)

## ملخص الميزة

| البند | التفاصيل |
|-------|---------|
| **المعرف** | FR-IAM-009 |
| **العنوان** | دعم حسابات متعددة المتاجر |
| **الأولوية** | Must (Priority 1) |
| **الحالة** | ✅ مكتمل |
| **الاعتمادية** | FR-IAM-001 (Accounts), FR-IAM-003 (RBAC), FR-IAM-008 (Settings), FR-IAM-006 (Audit) |
| **الاختبارات** | 42 اختبار (22 وحدة + 20 تكامل) |

---

## العمارة

### نموذج البيانات

```
Account (1) ──→ (N) Store
                  │
                  ├── Manual (direct shipping)
                  ├── Shopify (OAuth)
                  ├── WooCommerce (API Keys)
                  ├── Salla
                  ├── Zid
                  └── Custom API
```

### دورة حياة المتجر

```
┌──────────┐     ┌──────────┐     ┌──────────┐
│  Create   │────→│  Active   │←───→│ Inactive  │
│ (auto     │     │           │     │           │
│  default) │     └─────┬─────┘     └───────────┘
└──────────┘           │
                       ↓
                  ┌──────────┐
                  │  Deleted  │ (soft delete)
                  │ (metadata │
                  │  retained)│
                  └──────────┘
```

### صلاحيات الوصول

```
┌─────────────────┬──────────┬──────────────┬─────────┐
│ الإجراء          │ Owner    │ store:manage │ Member  │
├─────────────────┼──────────┼──────────────┼─────────┤
│ عرض القائمة      │ ✅       │ ✅           │ ✅      │
│ عرض متجر واحد   │ ✅       │ ✅           │ ✅      │
│ إنشاء متجر      │ ✅       │ ✅           │ ❌ 403  │
│ تعديل متجر      │ ✅       │ ✅           │ ❌ 403  │
│ حذف متجر        │ ✅       │ ✅           │ ❌ 403  │
│ تعيين افتراضي   │ ✅       │ ✅           │ ❌ 403  │
│ تبديل الحالة    │ ✅       │ ✅           │ ❌ 403  │
│ إحصائيات        │ ✅       │ ✅           │ ✅      │
└─────────────────┴──────────┴──────────────┴─────────┘
```

---

## الملفات (10 ملفات)

```
shipping-gateway/
├── database/
│   ├── migrations/
│   │   └── 2026_02_12_000010_create_stores_table.php    # جدول المتاجر
│   └── factories/
│       └── StoreFactory.php                              # Factory
├── app/
│   ├── Models/
│   │   ├── Store.php          # ★ New: model + scopes + helpers
│   │   └── Account.php        # Updated: +stores() relationship
│   ├── Services/
│   │   └── StoreService.php   # ★ New: CRUD + validation + audit
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   └── StoreController.php    # 8 endpoints
│   │   └── Requests/
│   │       └── StoreRequest.php       # Validation
│   └── Exceptions/
│       └── BusinessException.php      # +3 store error codes
├── routes/
│   └── api.php                        # +8 store routes
└── tests/
    ├── Unit/StoreTest.php             # 22 tests
    └── Feature/StoreApiTest.php       # 20 tests
```

---

## API Endpoints (8 endpoints)

| Method | Endpoint | الوصف | الصلاحية |
|--------|----------|-------|---------|
| GET | `/api/v1/stores` | قائمة المتاجر (مع فلاتر) | Any authenticated |
| GET | `/api/v1/stores/stats` | إحصائيات المتاجر | Any authenticated |
| GET | `/api/v1/stores/{id}` | تفاصيل متجر | Any authenticated |
| POST | `/api/v1/stores` | إنشاء متجر جديد | `store:manage` أو Owner |
| PUT | `/api/v1/stores/{id}` | تعديل متجر | `store:manage` أو Owner |
| DELETE | `/api/v1/stores/{id}` | حذف متجر (soft) | `store:manage` أو Owner |
| POST | `/api/v1/stores/{id}/set-default` | تعيين كافتراضي | `store:manage` أو Owner |
| POST | `/api/v1/stores/{id}/toggle-status` | تبديل الحالة | `store:manage` أو Owner |

### فلاتر القائمة

```
GET /api/v1/stores?status=active&platform=shopify&search=متجري
```

### أمثلة

**إنشاء متجر:**
```json
POST /api/v1/stores
{
  "name": "متجري على شوبيفاي",
  "platform": "shopify",
  "city": "الرياض",
  "country": "SA",
  "currency": "SAR"
}
→ 201 Created
```

**إحصائيات:**
```json
GET /api/v1/stores/stats
{
  "data": {
    "total": 5,
    "active": 4,
    "inactive": 1,
    "connected": 2,
    "max_allowed": 20,
    "remaining": 15,
    "by_platform": { "manual": 2, "shopify": 2, "woocommerce": 1 }
  }
}
```

---

## المنصات المدعومة

| المنصة | الكود | النوع |
|--------|-------|------|
| متجر يدوي | `manual` | شحن مباشر من المنصة |
| Shopify | `shopify` | OAuth integration |
| WooCommerce | `woocommerce` | API Keys |
| سلة | `salla` | API integration |
| زد | `zid` | API integration |
| API مخصص | `custom_api` | Webhook/REST |

---

## أكواد الأخطاء

| الكود | HTTP | الوصف |
|-------|------|-------|
| `ERR_STORE_EXISTS` | 422 | اسم المتجر مكرر داخل نفس الحساب |
| `ERR_MAX_STORES_REACHED` | 422 | تجاوز الحد الأقصى (20 متجر) |
| `ERR_CANNOT_DELETE_DEFAULT` | 422 | لا يمكن حذف المتجر الافتراضي |

---

## قواعد الأعمال

| # | القاعدة |
|---|--------|
| 1 | كل متجر مربوط بحساب واحد فقط |
| 2 | لا يمكن نقل متجر لحساب آخر بدون موافقة |
| 3 | اسم المتجر فريد داخل نفس الحساب |
| 4 | الحد الأقصى: 20 متجر لكل حساب |
| 5 | أول متجر يُنشأ يصبح تلقائياً الافتراضي |
| 6 | لا يمكن حذف المتجر الافتراضي إذا وجدت متاجر أخرى |
| 7 | الحذف soft delete (البيانات الوصفية محفوظة) |
| 8 | بيانات الاتصال (OAuth tokens, API keys) مشفرة |
| 9 | connection_config مخفي دائماً من API responses |
| 10 | كل عملية إنشاء/تعديل/حذف → سجل تدقيق |
| 11 | محاولة وصول بدون صلاحية → 403 + audit warning |

---

## تغطية الاختبارات (42 اختبار)

### وحدة (22 اختبار)

| المجموعة | العدد |
|----------|-------|
| Create Store | 7 |
| Duplicate Name | 2 |
| Max Limit | 1 |
| List & Filter | 3 |
| Update Store | 2 |
| Set Default | 1 |
| Delete Store | 3 |
| Toggle Status & Stats | 2 |
| Permission enforcement | 1 |

### تكامل (20 اختبار)

| المجموعة | العدد |
|----------|-------|
| POST /stores | 7 |
| GET /stores | 3 |
| GET /stores/{id} | 1 |
| PUT /stores/{id} | 2 |
| DELETE /stores/{id} | 2 |
| POST set-default | 1 |
| POST toggle-status | 1 |
| GET /stores/stats | 1 |
| Audit logging | 1 |
| Validation | 1 |

---

## تشغيل الاختبارات

```bash
php artisan test tests/Unit/StoreTest.php tests/Feature/StoreApiTest.php
```
