# وحدة ST — تكامل المتاجر ومزامنة الطلبات

## ملخص الوحدة

| البند | التفاصيل |
|-------|---------|
| **المعرفات** | FR-ST-001 → FR-ST-010 |
| **الوحدة** | ST: Store Integrations & Order Sync |
| **الأولوية** | Must (معظم المتطلبات) |
| **الحالة** | ✅ مكتمل |
| **الاعتمادية** | IAM Module (Accounts, Stores, RBAC, Audit) |
| **الاختبارات** | 48 اختبار (28 وحدة + 20 تكامل) |

---

## المتطلبات المنفذة

| FR | العنوان | الأولوية | الحالة |
|----|---------|---------|:-----:|
| FR-ST-001 | ربط المتاجر وتأسيس الاتصال | Must | ✅ |
| FR-ST-002 | تسجيل Webhooks المطلوبة | Must | ✅ |
| FR-ST-003 | مزامنة دورية (Polling) | Must | ✅ |
| FR-ST-004 | تحويل الطلب إلى Canonical Order | Must | ✅ |
| FR-ST-005 | منع التكرار (Deduplication) | Must | ✅ |
| FR-ST-006 | قواعد مزامنة قابلة للتهيئة | Should | ⚡ أساسي |
| FR-ST-007 | تحويل الطلب إلى شحنة (Manual/Auto) | Must | ✅ |
| FR-ST-008 | محرك القواعد الذكي (Basic) | Must | ✅ |
| FR-ST-009 | تحديث المتجر بالتتبع والحالة | Must | ✅ |
| FR-ST-010 | إعادة المحاولة وتسجيل الأسباب | Must | ✅ |

---

## العمارة

### نموذج البيانات

```
Account (1) ──→ (N) Store ──→ (N) Order ──→ (N) OrderItem
                       │
                       ├──→ (N) WebhookEvent (dedup + audit)
                       └──→ (N) StoreSyncLog (polling + retry tracking)
```

### Canonical Order — النموذج الموحّد

```
Order
├── المعرّفات: id, account_id, store_id, external_order_id
├── المصدر: source (manual|shopify|woocommerce|salla|zid|custom_api)
├── الحالة: status (pending → ready → processing → shipped → delivered)
├── العميل: customer_name, customer_email, customer_phone
├── عنوان الشحن: shipping_name, shipping_address_line_1, ..., shipping_country
├── المالي: subtotal, shipping_cost, tax_amount, discount_amount, total_amount
├── البنود: items[] (name, quantity, unit_price, weight, sku, hs_code)
├── الربط: shipment_id (بعد التحويل)
├── القواعد: auto_ship_eligible, hold_reason, rule_evaluation_log
└── الأصلي: raw_payload (المحفوظ من المنصة)
```

### دورة حياة الطلب

```
┌─────────┐   Import/    ┌─────────┐   Rules     ┌───────┐
│ Platform │ ──────────→  │ Pending │ ──Engine──→ │ Ready │
└─────────┘   Webhook     └────┬────┘             └───┬───┘
                               │                      │
                          [Rules Hold]           [Create Shipment]
                               │                      │
                          ┌────▼────┐           ┌─────▼──────┐
                          │ On Hold │           │ Processing │
                          └────┬────┘           └─────┬──────┘
                               │                      │
                          [Resolve]              [Label Issued]
                               │                      │
                               └──→ Ready        ┌────▼────┐
                                                 │ Shipped │
                                                 └────┬────┘
                                                      │
                                                 ┌────▼─────┐
                                                 │ Delivered│
                                                 └──────────┘
```

### Platform Adapters (Strategy Pattern)

```
PlatformAdapterInterface
├── ShopifyAdapter      → OAuth + HMAC signature verification
├── WooCommerceAdapter  → API Keys + HMAC verification
└── (extensible: SallaAdapter, ZidAdapter, ...)

PlatformAdapterFactory::make($store) → PlatformAdapterInterface
```

### محرك القواعد الذكي (FR-ST-008)

3 قواعد أساسية منفذة:

| القاعدة | الشرط | النتيجة |
|---------|-------|--------|
| address_validation | عنوان شحن غير مكتمل | Hold |
| high_value_check | المبلغ > 5000 ريال | Hold |
| phone_required | لا يوجد رقم هاتف | Hold |

→ إذا نجحت كل القواعد: `status = ready`, `auto_ship_eligible = true`
→ إذا فشلت أي قاعدة: `status = on_hold`, `hold_reason = ...`

---

## API Endpoints (9 authenticated + 1 public)

### الطلبات (Authenticated)

| Method | Endpoint | الوصف | الصلاحية |
|--------|----------|-------|---------|
| GET | `/api/v1/orders` | قائمة الطلبات (فلاتر) | أي مستخدم |
| GET | `/api/v1/orders/stats` | إحصائيات الطلبات | أي مستخدم |
| GET | `/api/v1/orders/{id}` | تفاصيل طلب | أي مستخدم |
| POST | `/api/v1/orders` | إنشاء طلب يدوي | `orders:manage` أو Owner |
| PUT | `/api/v1/orders/{id}/status` | تحديث حالة الطلب | `orders:manage` أو Owner |
| POST | `/api/v1/orders/{id}/cancel` | إلغاء الطلب | `orders:manage` أو Owner |

### اتصال المتاجر والمزامنة (Authenticated)

| Method | Endpoint | الوصف | الصلاحية |
|--------|----------|-------|---------|
| POST | `/api/v1/stores/{id}/test-connection` | اختبار الاتصال | `orders:manage` أو Owner |
| POST | `/api/v1/stores/{id}/register-webhooks` | تسجيل Webhooks | `orders:manage` أو Owner |
| POST | `/api/v1/stores/{id}/sync` | مزامنة يدوية | `orders:manage` أو Owner |

### Webhooks (Public — verified via signature)

| Method | Endpoint | الوصف |
|--------|----------|-------|
| POST | `/api/v1/webhooks/{platform}/{storeId}` | استقبال أحداث المنصات |

---

## أكواد الأخطاء

| الكود | HTTP | الوصف |
|-------|------|-------|
| `ERR_DUPLICATE_ORDER` | 422 | طلب مكرر |
| `ERR_ORDER_ALREADY_SHIPPED` | 422 | لا يمكن إلغاء طلب تم شحنه |
| `ERR_INVALID_STATUS_TRANSITION` | 422 | تغيير حالة غير مسموح |
| `ERR_SYNC_NOT_SUPPORTED` | 422 | المتجر لا يدعم المزامنة |
| `ERR_MISSING_REQUIRED_FIELDS` | 422 | حقول مفقودة |

---

## الملفات (18 ملف)

```
shipping-gateway/
├── database/
│   ├── migrations/
│   │   └── 2026_02_12_000012_create_st_module_tables.php   # 4 tables
│   └── factories/
│       └── OrderFactory.php
├── app/
│   ├── Models/
│   │   ├── Order.php              # ★ Canonical Order
│   │   ├── OrderItem.php          # ★ Line items
│   │   ├── WebhookEvent.php       # ★ Event tracking + dedup
│   │   ├── StoreSyncLog.php       # ★ Sync audit trail
│   │   ├── Store.php              # Updated: +orders()
│   │   └── Account.php            # Updated: +orders()
│   ├── Services/
│   │   ├── OrderService.php         # ★ Core: import, create, status, sync
│   │   ├── AuditService.php         # +8 ST audit actions
│   │   └── Platforms/
│   │       ├── PlatformAdapterInterface.php  # ★ Abstract interface
│   │       ├── ShopifyAdapter.php            # ★ Shopify transformer
│   │       ├── WooCommerceAdapter.php        # ★ WooCommerce transformer
│   │       └── PlatformAdapterFactory.php    # ★ Factory pattern
│   ├── Http/Controllers/Api/V1/
│   │   ├── OrderController.php      # ★ 9 endpoints
│   │   └── WebhookController.php    # ★ Public webhook receiver
│   └── Exceptions/
│       └── BusinessException.php     # +5 error codes
├── routes/api.php                    # +10 routes (9 auth + 1 public)
└── tests/
    ├── Unit/OrderTest.php            # 28 tests
    └── Feature/OrderApiTest.php      # 20 tests
```

---

## تشغيل الاختبارات

```bash
php artisan test tests/Unit/OrderTest.php tests/Feature/OrderApiTest.php
```
