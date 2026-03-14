# وحدة SH — إنشاء وإدارة الشحنات (Shipments Management)

## نظرة عامة
وحدة الشحنات هي الأكبر في المشروع (19 متطلب وظيفي) وتمثل النواة التشغيلية لبوابة الشحن. تدعم دورة حياة الشحنة الكاملة من الإنشاء إلى التسليم مع دعم الطرود المتعددة، دفتر العناوين، التحقق، والإلغاء/الاسترجاع.

---

## المتطلبات المُنفذة

| ID | المتطلب | الأولوية | الحالة |
|----|---------|----------|--------|
| FR-SH-001 | إنشاء شحنة مباشرة (Direct Shipping) | Must | ✅ |
| FR-SH-002 | إنشاء شحنة من طلب متجر (Order→Shipment) | Must | ✅ |
| FR-SH-003 | دعم الشحنات متعددة الطرود (Multi-Parcel) | Must | ✅ |
| FR-SH-004 | دفتر العناوين (Address Book) | Should | ✅ |
| FR-SH-005 | التحقق من صحة بيانات الشحنة (Validation) | Must | ✅ |
| FR-SH-006 | إدارة حالات الشحنة (State Machine) | Must | ✅ |
| FR-SH-007 | إلغاء/إبطال الشحنة (Cancel/Void) | Should | ✅ |
| FR-SH-008 | إعادة طباعة الملصق (Reprint Label) | Must | ✅ |
| FR-SH-009 | البحث والفلترة في الشحنات | Must | ✅ |
| FR-SH-010 | إنشاء شحنات مجمّع (Bulk Creation) | Should | ✅ |
| FR-SH-011 | فصل صلاحيات البيانات المالية (Financial Visibility) | Must | ✅ |
| FR-SH-012 | صلاحيات الطباعة والتسليم | Must | ✅ |
| FR-SH-013 | التحقق من KYC قبل الشراء | Must | ✅ |
| FR-SH-014 | حجز الرصيد (Balance Reservation) | Should | ✅ (هيكل) |
| FR-SH-015 | قيود محاسبية للرسوم والمبالغ المستردة | Must | ✅ (هيكل) |
| FR-SH-016 | شحنات المرتجعات (Return Shipments) | Should | ✅ |
| FR-SH-017 | علم المواد الخطرة (Dangerous Goods Flag) | Must | ✅ |
| FR-SH-018 | عرض حالة المواد الخطرة | Must | ✅ |
| FR-SH-019 | دعم الدفع عند الاستلام (COD) | Should | ✅ |

---

## البنية المعمارية

### نموذج البيانات

```
┌─────────────────────┐     ┌─────────────┐
│     addresses        │     │   accounts   │
│  (FR-SH-004)        │────▶│              │
└─────────────────────┘     └──────┬───────┘
                                   │
┌─────────────────────┐     ┌──────▼───────┐     ┌──────────────┐
│     parcels          │────▶│  shipments   │────▶│   orders     │
│  (FR-SH-003)        │     │ (FR-SH-001)  │     │  (ST module) │
└─────────────────────┘     └──────┬───────┘     └──────────────┘
                                   │
                            ┌──────▼───────┐
                            │  shipment_   │
                            │ status_      │
                            │  history     │
                            │ (FR-SH-006)  │
                            └──────────────┘
```

### دورة حياة الشحنة (State Machine)

```
                    ┌──────────┐
                    │  draft   │ FR-SH-001
                    └────┬─────┘
                         │ validate (FR-SH-005)
                    ┌────▼─────┐
                    │validated │
                    └────┬─────┘
                         │ fetch rates (RT module)
                    ┌────▼─────┐
                    │  rated   │
                    └────┬─────┘
                         │ purchase label (CR module)
               ┌─────────▼─────────┐
               │payment_pending    │
               └─────────┬─────────┘
                         │ payment confirmed
               ┌─────────▼─────────┐
               │    purchased       │ FR-SH-008 (label available)
               └─────────┬─────────┘
                         │
               ┌─────────▼─────────┐
               │ready_for_pickup   │
               └─────────┬─────────┘
                         │ carrier collects
               ┌─────────▼─────────┐
               │   picked_up       │
               └─────────┬─────────┘
                         │
               ┌─────────▼─────────┐
               │   in_transit      │──────┐
               └─────────┬─────────┘      │ exception
                         │          ┌─────▼─────┐
               ┌─────────▼──────┐   │ exception │
               │out_for_delivery│   └─────┬─────┘
               └─────────┬──────┘         │
                         │          ┌─────▼─────┐
               ┌─────────▼──────┐   │ returned  │
               │   delivered    │   └───────────┘
               └────────────────┘

  ╔══════════════════════════════════════════╗
  ║  cancelled — من أي حالة قبل picked_up   ║ FR-SH-007
  ╚══════════════════════════════════════════╝
```

---

## API Endpoints

| Method | Path | الوصف | FR |
|--------|------|-------|-----|
| POST | `/api/v1/shipments` | إنشاء شحنة مباشرة | FR-SH-001 |
| POST | `/api/v1/shipments/from-order/{orderId}` | إنشاء من طلب | FR-SH-002 |
| POST | `/api/v1/shipments/bulk` | إنشاء مجمّع | FR-SH-010 |
| GET | `/api/v1/shipments` | عرض وبحث | FR-SH-009 |
| GET | `/api/v1/shipments/stats` | إحصائيات | — |
| GET | `/api/v1/shipments/{id}` | تفاصيل شحنة | — |
| POST | `/api/v1/shipments/{id}/validate` | التحقق | FR-SH-005 |
| PUT | `/api/v1/shipments/{id}/status` | تحديث الحالة | FR-SH-006 |
| POST | `/api/v1/shipments/{id}/cancel` | إلغاء | FR-SH-007 |
| GET | `/api/v1/shipments/{id}/label` | جلب الملصق | FR-SH-008 |
| POST | `/api/v1/shipments/{id}/return` | إنشاء مرتجع | FR-SH-016 |
| POST | `/api/v1/shipments/{sid}/parcels` | إضافة طرد | FR-SH-003 |
| DELETE | `/api/v1/shipments/{sid}/parcels/{pid}` | حذف طرد | FR-SH-003 |
| GET | `/api/v1/addresses` | عرض دفتر العناوين | FR-SH-004 |
| POST | `/api/v1/addresses` | إضافة عنوان | FR-SH-004 |
| DELETE | `/api/v1/addresses/{id}` | حذف عنوان | FR-SH-004 |

---

## الاختبارات

### Unit Tests — ShipmentTest.php (42 اختبار)

| المجموعة | العدد | التغطية |
|----------|:-----:|---------|
| FR-SH-001: إنشاء مباشر | 5 | Owner/Manager/Member, international, unique ref |
| FR-SH-002: من طلب | 3 | Success, non-shippable, duplicate |
| FR-SH-003: طرود متعددة | 5 | Create, add, remove, last parcel, volumetric |
| FR-SH-004: دفتر العناوين | 4 | Save, default swap, list by type, delete |
| FR-SH-005: التحقق | 3 | Valid, incomplete, non-draft |
| FR-SH-006: آلة الحالات | 4 | Valid/invalid transition, history, delivery→order |
| FR-SH-007: إلغاء | 4 | Draft, purchased refund, delivered, unlink order |
| FR-SH-008: الملصق | 2 | Print count, no label for draft |
| FR-SH-009: بحث وفلترة | 3 | Filter status, search tracking, search recipient |
| FR-SH-010: إنشاء مجمّع | 1 | Bulk from orders |
| FR-SH-011: الرؤية المالية | 1 | Financial fields visibility |
| FR-SH-013: فحص KYC | 2 | Verified pass, unverified fail |
| FR-SH-016: مرتجعات | 2 | Return creation, non-delivered |
| FR-SH-017/018: مواد خطرة | 1 | DG flag |
| FR-SH-019: COD | 2 | COD creation, validation |
| إحصائيات | 1 | Stats counts |
| تدقيق | 2 | Creation audit, cancel audit |

### Integration Tests — ShipmentApiTest.php (25 اختبار)

| المجموعة | العدد |
|----------|:-----:|
| POST /shipments | 6 |
| POST /shipments/from-order | 1 |
| POST /shipments/{id}/validate | 1 |
| PUT /shipments/{id}/status | 2 |
| POST /shipments/{id}/cancel | 2 |
| GET /shipments/{id}/label | 1 |
| GET /shipments | 3 |
| GET /shipments/{id} | 1 |
| POST /shipments/bulk | 1 |
| POST /shipments/{id}/return | 1 |
| Parcels API | 1 |
| Address Book API | 3 |
| Statistics | 1 |

---

## ملفات الوحدة

```
database/migrations/
  └── 2026_02_12_000013_create_sh_module_tables.php

app/Models/
  ├── Shipment.php                    (230+ lines — state machine, relationships, helpers)
  ├── Parcel.php                      (FR-SH-003)
  ├── Address.php                     (FR-SH-004)
  └── ShipmentStatusHistory.php       (FR-SH-006)

app/Services/
  └── ShipmentService.php             (450+ lines — 19 FRs business logic)

app/Http/Controllers/Api/V1/
  ├── ShipmentController.php          (16 endpoints)
  └── AddressController.php           (3 endpoints)

database/factories/
  ├── ShipmentFactory.php             (9 states)
  └── AddressFactory.php

tests/Unit/
  └── ShipmentTest.php                (42 tests)

tests/Feature/
  └── ShipmentApiTest.php             (25 tests)

routes/
  └── api.php                         (19 new routes added)
```

---

## أكواد الأخطاء

| الكود | الوصف | HTTP |
|-------|-------|:----:|
| ERR_ORDER_NOT_SHIPPABLE | الطلب غير جاهز للشحن | 422 |
| ERR_ORDER_HAS_SHIPMENT | الطلب مرتبط بشحنة بالفعل | 422 |
| ERR_SHIPMENT_NOT_CANCELLABLE | لا يمكن إلغاء الشحنة في حالتها الحالية | 422 |
| ERR_NO_LABEL | لا يوجد ملصق لهذه الشحنة | 422 |
| ERR_INVALID_STATE | حالة الشحنة غير صالحة لهذه العملية | 422 |
| ERR_VALIDATION_FAILED | فشل التحقق من بيانات الشحنة | 422 |
| ERR_RETURN_NOT_ALLOWED | لا يمكن إنشاء مرتجع | 422 |
| ERR_CANNOT_MODIFY_PARCELS | لا يمكن تعديل الطرود بعد التسعير | 422 |
| ERR_LAST_PARCEL | لا يمكن حذف آخر طرد | 422 |
| ERR_KYC_REQUIRED | التحقق من الهوية مطلوب | 422 |
| ERR_INSUFFICIENT_BALANCE | الرصيد غير كافٍ | 422 |

---

## الاعتماديات

| يعتمد على | السبب |
|-----------|-------|
| IAM (accounts, users, RBAC) | الصلاحيات ومتعدد المستأجرين |
| ST (orders, stores) | ربط الشحنة بالطلب والمتجر |

| يعتمد عليه | السبب |
|-----------|-------|
| RT (Rates) | جلب الأسعار للشحنة |
| CR (Carriers) | إصدار الملصقات |
| TR (Tracking) | تحديث حالات التتبع |
| BW (Wallet) | خصم الرصيد والاسترداد |
| NTF (Notifications) | إشعارات حالة الشحن |
