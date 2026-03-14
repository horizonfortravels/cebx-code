# وحدة RT — العروض والتسعير (Rates & Pricing)

## نظرة عامة
وحدة التسعير مسؤولة عن جلب أسعار الشحن من الناقلين، تطبيق هوامش الربح والرسوم، وعرض الخيارات للمستخدم. تدعم محرك تسعير حتمي وقابل للتتبع (Explainable Pricing) مع قواعد مشروطة وأولويات.

---

## المتطلبات المُنفذة

| ID | المتطلب | الأولوية | الحالة |
|----|---------|----------|--------|
| FR-RT-001 | جلب الأسعار الصافية من الناقلين (DHL/Aramex) | Must | ✅ |
| FR-RT-002 | حساب السعر النهائي (Retail Rate) مع هامش الربح | Must | ✅ |
| FR-RT-003 | أنواع الهوامش: نسبة، مبلغ ثابت، حد أدنى للربح، حد أدنى/أقصى للسعر | Must | ✅ |
| FR-RT-004 | تقريب الأسعار حسب العملة/القاعدة (ceil/floor/round) | Should | ✅ |
| FR-RT-005 | تخزين تفصيل التسعير (Pricing Breakdown) لكل عرض | Must | ✅ |
| FR-RT-006 | عرض الخيارات مع شارات (أرخص/أسرع/أفضل قيمة/موصى) | Must | ✅ |
| FR-RT-007 | صلاحية عرض الأسعار (Quote TTL) وإعادة التسعير | Should | ✅ |
| FR-RT-008 | قواعد تسعير مشروطة (وجهة/وزن/خدمة/متجر/نوع) | Should | ✅ |
| FR-RT-009 | رسوم إضافية عند انتهاء الاشتراك (Expired Surcharge) | Must | ✅ |
| FR-RT-010 | اختيار أفضل عرض يدوياً أو تلقائياً (cheapest/fastest/best_value) | Should | ✅ |
| FR-RT-011 | عرض تفاصيل التسعير وفق الصلاحيات (RBAC) | Must | ✅ |
| FR-RT-012 | تقييد الخدمات حسب حالة KYC | Must | ✅ |

### متطلبات BRP المغطاة
| FR-BRP-001 | Explainable Pricing + Correlation ID | ✅ |
| FR-BRP-002 | قواعد مشروطة مع Priority + Fallback | ✅ |
| FR-BRP-003 | رسوم خدمة مستقلة (Service Fee) | ✅ |
| FR-BRP-007 | سياسة تسعير بديلة عند انتهاء الاشتراك | ✅ |
| FR-BRP-008 | أولوية القواعد وحل التعارض | ✅ |

---

## البنية المعمارية

### خط أنابيب التسعير (Pricing Pipeline)

```
┌──────────┐    ┌──────────────┐    ┌──────────────┐    ┌──────────┐
│ Shipment │───▶│ CarrierRate  │───▶│  Pricing     │───▶│  Rate    │
│  Data    │    │  Adapter     │    │  Engine      │    │  Quote   │
└──────────┘    │ (FR-RT-001)  │    │ (FR-RT-002)  │    │ (FR-RT-005)│
                └──────────────┘    └──────────────┘    └──────────┘
                 Net Rates           + Markup (003)      + Breakdown
                                     + Fee (BRP-003)     + Badges (006)
                                     + Round (004)       + TTL (007)
                                     + Guards (003)
```

### محرك التسعير — خطوات الحساب

```
1. Net Rate (من الناقل)
   │
2. ├── Match Rule (FR-RT-008)
   │   بحث بالأولوية → أول قاعدة مطابقة أو Fallback
   │
3. ├── Apply Markup (FR-RT-002/003)
   │   percentage / fixed / both
   │
4. ├── Apply Service Fee (FR-BRP-003)
   │   fixed + percentage (مستقل عن الهامش)
   │
5. ├── Apply Expired Surcharge (FR-RT-009)
   │   فقط إذا الاشتراك منتهي
   │
6. ├── Apply Rounding (FR-RT-004)
   │   ceil / floor / round بدقة محددة
   │
7. └── Enforce Guards (FR-RT-003)
       min_profit → min_retail → max_retail
       │
       ▼
   Retail Rate النهائي
```

### نموذج البيانات

```
┌──────────────────┐
│  pricing_rules   │ (FR-RT-008)
│  Conditions +    │
│  Markup Config   │
└────────┬─────────┘
         │ applied to
┌────────▼─────────┐     ┌──────────────┐
│   rate_quotes    │────▶│ rate_options  │
│   (FR-RT-007)    │     │ (FR-RT-005)  │
│   TTL + Status   │     │ Net/Retail/  │
└────────┬─────────┘     │ Breakdown    │
         │               └──────────────┘
         ▼
┌──────────────────┐
│    shipments     │
│ (carrier/rate    │
│  updated on      │
│  selection)      │
└──────────────────┘
```

---

## API Endpoints

| Method | Path | الوصف | FR |
|--------|------|-------|-----|
| POST | `/api/v1/shipments/{id}/rates` | جلب أسعار من الناقلين | FR-RT-001 |
| POST | `/api/v1/shipments/{id}/reprice` | إعادة تسعير (عرض منتهي) | FR-RT-007 |
| GET | `/api/v1/rate-quotes/{id}` | تفاصيل عرض الأسعار | FR-RT-005/011 |
| POST | `/api/v1/rate-quotes/{id}/select` | اختيار خيار (يدوي/تلقائي) | FR-RT-010 |
| GET | `/api/v1/pricing-rules` | عرض قواعد التسعير | FR-RT-008 |
| POST | `/api/v1/pricing-rules` | إنشاء قاعدة تسعير | FR-RT-008 |
| PUT | `/api/v1/pricing-rules/{id}` | تعديل قاعدة | FR-RT-008 |
| DELETE | `/api/v1/pricing-rules/{id}` | حذف قاعدة | FR-RT-008 |

---

## الاختبارات

### Unit Tests — RateTest.php (44 اختبار)

| المجموعة | العدد | التغطية |
|----------|:-----:|---------|
| FR-RT-001: جلب الأسعار | 5 | Domestic, international, specific carrier, invalid state, status transition |
| FR-RT-002: حساب Retail Rate | 3 | Percentage, fixed, both markup types |
| FR-RT-003: Min/Max Guards | 3 | Min profit, min retail, max retail |
| FR-RT-004: Rounding | 3 | Ceil, floor, round to nearest |
| FR-RT-005: Breakdown | 2 | Breakdown stored, correlation ID |
| FR-RT-006: Badges | 2 | All badges assigned, cheapest is lowest |
| FR-RT-007: Quote TTL | 3 | Has expiry, expired rejected, reprice creates new |
| FR-RT-008: Conditional Rules | 5 | Carrier match, shipment type, weight, priority, fallback |
| FR-RT-009: Expired Surcharge | 2 | Surcharge applied, no surcharge when active |
| FR-RT-010: Select Option | 4 | Manual, auto cheapest, auto fastest, updates shipment |
| FR-RT-011: RBAC Visibility | 2 | Owner full, member restricted |
| FR-RT-012: KYC Filter | 1 | Premium services filtered for unverified |
| FR-BRP-003: Service Fee | 1 | Independent fee calculation |
| Rules CRUD | 5 | Create, update, delete, member denied, platform rules |
| Audit | 2 | Fetch audited, select audited |
| Edge Cases | 2 | No rule passthrough, zero net rate |

### Integration Tests — RateApiTest.php (18 اختبار)

| المجموعة | العدد |
|----------|:-----:|
| POST /rates | 3 |
| POST /reprice | 1 |
| GET /rate-quotes | 2 |
| POST /rate-quotes/select | 4 |
| Pricing Rules CRUD | 6 |
| Validation | 2 |

---

## ملفات الوحدة

```
database/migrations/
  └── 2026_02_12_000014_create_rt_module_tables.php

app/Models/
  ├── PricingRule.php         (Conditional rules + markup config)
  ├── RateQuote.php           (Quote with TTL + options)
  └── RateOption.php          (Individual rate + breakdown + badges)

app/Services/
  ├── PricingEngine.php       (Deterministic pricing pipeline)
  ├── RateService.php         (Orchestrator: fetch→price→select)
  └── Carriers/
      └── CarrierRateAdapter.php  (DHL + Aramex simulated rates)

app/Http/Controllers/Api/V1/
  └── RateController.php      (8 endpoints)

database/factories/
  ├── PricingRuleFactory.php  (7 states)
  └── RateQuoteFactory.php

tests/Unit/
  └── RateTest.php            (44 tests)

tests/Feature/
  └── RateApiTest.php         (18 tests)
```

---

## Pricing Rule Schema

| الحقل | الوصف | مثال |
|-------|-------|------|
| carrier_code | تقييد لناقل محدد | `dhl_express` / `null` (أي ناقل) |
| service_code | تقييد لخدمة محددة | `express_worldwide` / `null` |
| shipment_type | نوع الشحنة | `any` / `domestic` / `international` |
| min_weight / max_weight | نطاق الوزن | `0.5` → `30.0` |
| store_id | تقييد لمتجر محدد | UUID / `null` |
| markup_type | نوع الهامش | `percentage` / `fixed` / `both` |
| markup_percentage | نسبة الهامش | `15.0` = 15% |
| markup_fixed | مبلغ ثابت | `5.00` |
| min_profit | حد أدنى للربح | `3.00` |
| min_retail_price | حد أدنى للسعر النهائي | `10.00` |
| max_retail_price | حد أقصى للسعر النهائي | `500.00` |
| service_fee_fixed | رسوم خدمة ثابتة | `2.00` |
| service_fee_percentage | رسوم خدمة نسبية | `1.5` = 1.5% |
| rounding_mode | وضع التقريب | `ceil` / `floor` / `round` / `none` |
| priority | الأولوية (أقل = أعلى) | `10` → `9999` |
| is_default | قاعدة افتراضية (Fallback) | `true` / `false` |

---

## الاعتماديات

| يعتمد على | السبب |
|-----------|-------|
| IAM (accounts, RBAC) | الصلاحيات والحسابات |
| SH (shipments) | بيانات الشحنة للتسعير |

| يعتمد عليه | السبب |
|-----------|-------|
| CR (Carriers) | إصدار الملصقات بناءً على الاختيار |
| BW (Wallet) | حجز المبلغ عند الشراء |
| RPT (Reports) | تقارير الإيرادات والهوامش |
