# وحدة CR — تكامل الناقل وإصدار الملصقات (Carrier Integration & Labels)

## FR-CR-001 → FR-CR-008 (8 متطلبات)

---

## 📋 جدول المتطلبات

| ID | المتطلب | الأولوية | الحالة |
|----|---------|---------|--------|
| FR-CR-001 | إنشاء الشحنة لدى DHL واستلام Tracking/AWB | Must | ✅ |
| FR-CR-002 | استلام وتخزين Label/Docs (PDF/ZPL) | Must | ✅ |
| FR-CR-003 | Idempotency لمنع التكرار عند الإنشاء | Must | ✅ |
| FR-CR-004 | نموذج أخطاء موحد (Normalized Error Model) | Must | ✅ |
| FR-CR-005 | إعادة جلب الملصق (Re-fetch Label) | Must | ✅ |
| FR-CR-006 | إلغاء الشحنة لدى الناقل | Should | ✅ |
| FR-CR-007 | تنسيقات طباعة متعددة (PDF/ZPL) | Should | ✅ |
| FR-CR-008 | تنزيل آمن للملصقات بدون بيانات مالية | Should | ✅ |

---

## 🏗️ نموذج البيانات

```
┌─────────────────────┐       ┌──────────────────────┐
│   carrier_shipments  │──1:N──│  carrier_documents    │
│                     │       │                      │
│ • carrier_code      │       │ • type (label/CI/...)│
│ • tracking_number   │       │ • format (pdf/zpl)   │
│ • awb_number        │       │ • content_base64     │
│ • status            │       │ • print_count        │
│ • idempotency_key   │       │ • download_count     │
│ • label_format      │       │ • is_available       │
│ • cancellation_*    │       └──────────────────────┘
│ • correlation_id    │
└─────────┬───────────┘
          │ 1:N
          ▼
┌─────────────────────┐
│   carrier_errors     │
│                     │
│ • operation         │
│ • internal_code     │
│ • carrier_error_*   │
│ • is_retriable      │
│ • retry_attempt     │
│ • was_resolved      │
└─────────────────────┘
```

---

## 🔄 حالات CarrierShipment (State Machine)

```
pending → creating → created → label_pending → label_ready
                                    │                │
                                    └────────────────┼──→ cancel_pending → cancelled
                                                     │                 → cancel_failed
creating → failed (retriable)
```

---

## 🌐 API Endpoints (8 routes)

| Method | Endpoint | FR | الوصف |
|--------|----------|-----|-------|
| POST | `/shipments/{id}/carrier/create` | CR-001 | إنشاء لدى الناقل |
| POST | `/shipments/{id}/carrier/refetch` | CR-005 | إعادة جلب الملصق |
| POST | `/shipments/{id}/carrier/cancel` | CR-006 | إلغاء لدى الناقل |
| POST | `/shipments/{id}/carrier/retry` | CR-003 | إعادة محاولة الإنشاء |
| GET | `/shipments/{id}/carrier/status` | — | حالة الناقل |
| GET | `/shipments/{id}/carrier/errors` | CR-004 | عرض الأخطاء |
| GET | `/shipments/{id}/documents` | CR-008 | قائمة المستندات |
| GET | `/shipments/{id}/documents/{docId}` | CR-008 | تنزيل مستند |

---

## 🔥 Error Code Mapping (FR-CR-004)

| DHL HTTP | Internal Code | Retriable | الوصف |
|----------|--------------|-----------|-------|
| 504 | ERR_CR_NETWORK_TIMEOUT | ✅ | انتهاء مهلة الاتصال |
| 429 | ERR_CR_RATE_LIMITED | ✅ | تجاوز حد الطلبات |
| 500 | ERR_CR_CARRIER_INTERNAL | ✅ | خطأ داخلي في الناقل |
| 502/503 | ERR_CR_SERVICE_UNAVAILABLE | ✅ | الخدمة غير متاحة مؤقتاً |
| 401/403 | ERR_CR_AUTH_FAILED | ❌ | فشل المصادقة |
| 400 | ERR_CR_VALIDATION | ❌ | خطأ في البيانات |
| 404 | ERR_CR_SHIPMENT_NOT_FOUND | ❌ | شحنة غير موجودة |

---

## ✅ تغطية الاختبارات

| الفئة | الملف | عدد الاختبارات |
|-------|-------|----------------|
| Unit Tests | tests/Unit/CarrierTest.php | 45 |
| API Tests | tests/Feature/CarrierApiTest.php | 20 |
| **المجموع** | | **65** |

### توزيع FR:
- FR-CR-001 (Create): 5 unit + 4 API = 9
- FR-CR-002 (Documents): 5 unit = 5
- FR-CR-003 (Idempotency): 5 unit + 1 API = 6
- FR-CR-004 (Errors): 6 unit + 2 API = 8
- FR-CR-005 (Re-fetch): 4 unit + 2 API = 6
- FR-CR-006 (Cancel): 5 unit + 2 API = 7
- FR-CR-007 (Formats): 3 unit + 1 API = 4
- FR-CR-008 (Download): 5 unit + 4 API = 9
- Models/Helpers: 7 unit + 2 API = 9

---

## 📁 هيكل الملفات

```
app/
├── Models/
│   ├── CarrierShipment.php      (155 lines)
│   ├── CarrierDocument.php      (163 lines)
│   └── CarrierError.php         (211 lines)
├── Services/
│   ├── CarrierService.php       (420 lines)
│   └── Carriers/
│       └── DhlApiService.php    (195 lines)
├── Http/Controllers/Api/V1/
│   └── CarrierController.php    (200 lines)
database/
├── migrations/
│   └── ..._create_cr_module_tables.php  (160 lines)
├── factories/
│   ├── CarrierShipmentFactory.php
│   ├── CarrierDocumentFactory.php
│   └── CarrierErrorFactory.php
tests/
├── Unit/CarrierTest.php         (45 tests, 680 lines)
└── Feature/CarrierApiTest.php   (20 tests, 340 lines)
```

---

## 🔗 الاعتماديات

### يعتمد على:
- **IAM** (FR-IAM): RBAC، صلاحيات المستخدم
- **SH** (FR-SH): نموذج الشحنة، الطرود
- **RT** (FR-RT): RateQuote → carrier/service info
- **PAY**: الدفع المسبق قبل الإنشاء

### يعتمد عليها:
- **TR** (FR-TR): التتبع وتحديث الحالات
- **NTF** (FR-NTF): إشعارات عند إنشاء/إلغاء الشحنة
- **RPT** (FR-RPT): تقارير الشحنات والأداء
