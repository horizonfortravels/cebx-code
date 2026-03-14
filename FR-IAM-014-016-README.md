# FR-IAM-014 + FR-IAM-016 — حالة التحقق KYC + تقييد وصول وثائق KYC

## ملخص الميزة

| البند | التفاصيل |
|-------|---------|
| **المعرف** | FR-IAM-014, FR-IAM-016 |
| **العنوان** | إظهار حالة التحقق + تقييد وصول وثائق KYC |
| **الأولوية** | Must (Priority 1) |
| **الحالة** | ✅ مكتمل |
| **الاعتمادية** | FR-IAM-010 (Account Types), FR-IAM-003 (RBAC), FR-IAM-006 (Audit) |
| **الاختبارات** | 44 اختبار (24 وحدة + 20 تكامل) |

---

## العمارة

### FR-IAM-014: دورة حياة KYC

```
Unverified → [Submit Documents] → Pending → [Review] → Approved (1 year validity)
                                           → Rejected → [Resubmit] → Pending
Approved → [Expiry] → Expired → [Resubmit] → Pending
```

### FR-IAM-016: نموذج صلاحيات الوصول للوثائق

```
┌─────────────────────────────────────────────────────────────┐
│  KYC Document Access Matrix                                 │
├─────────────────┬──────────┬─────────┬───────┬─────────────┤
│ الإجراء          │ Owner    │ kyc:doc │ kyc:manage │ Member │
├─────────────────┼──────────┼─────────┼───────┼─────────────┤
│ عرض الحالة       │ ✅       │ ✅      │ ✅    │ ✅          │
│ قائمة الوثائق    │ ✅       │ ✅      │ ✅    │ ❌ → 403    │
│ تنزيل وثيقة     │ ✅       │ ✅      │ ✅    │ ❌ → 403    │
│ رفع وثيقة       │ ✅       │ ✅      │ ✅    │ ✅ (own)    │
│ حذف (Purge)     │ ✅       │ ❌      │ ✅    │ ❌ → 403    │
│ الموافقة/الرفض  │ ✅       │ ❌      │ ✅    │ ❌ → 403    │
└─────────────────┴──────────┴─────────┴───────┴─────────────┘
```

### القدرات حسب حالة KYC

| القدرة | Unverified | Pending | Approved | Rejected |
|--------|:---:|:---:|:---:|:---:|
| شحن محلي | ✅ (5) | ✅ (50) | ✅ (∞) | ✅ (10) |
| شحن دولي | ❌ | ❌ | ✅ | ❌ |
| الدفع عند الاستلام | ❌ | ❌ | ✅ | ❌ |
| API | ❌ | ✅ | ✅ | ✅ |
| التقارير | ❌ | ✅ | ✅ | ❌ |
| إضافة بطاقة | ❌ | ✅ | ✅ | ❌ |
| شحنات يومية | 3 | 10 | ∞ | 5 |

---

## الملفات (11 ملف جديد/معدل)

```
shipping-gateway/
├── database/
│   ├── migrations/
│   │   └── 2026_02_12_000008_create_kyc_documents_table.php  # جدول الوثائق
│   └── factories/
│       ├── KycVerificationFactory.php                         # Factory
│       └── KycDocumentFactory.php                             # Factory
├── app/
│   ├── Models/
│   │   ├── KycVerification.php    # ★ Enhanced: capabilities, statusDisplay, docs
│   │   └── KycDocument.php        # ★ New: secure document model
│   ├── Services/
│   │   └── KycService.php         # ★ New: 476 lines — status, review, documents
│   ├── Http/Controllers/Api/V1/
│   │   └── KycController.php      # ★ New: 8 endpoints
│   └── Exceptions/
│       └── BusinessException.php  # +6 KYC error codes
├── routes/
│   └── api.php                    # +8 KYC routes
└── tests/
    ├── Unit/KycTest.php           # 24 unit tests
    └── Feature/KycApiTest.php     # 20 integration tests
```

---

## API Endpoints (8 endpoints جديدة)

### FR-IAM-014: الحالة والمراجعة

| Method | Endpoint | الوصف | الصلاحية |
|--------|----------|-------|---------|
| GET | `/api/v1/kyc/status` | حالة KYC + القدرات + معلومات العرض | Any authenticated |
| POST | `/api/v1/kyc/approve` | الموافقة على KYC معلق | `kyc:manage` أو Owner |
| POST | `/api/v1/kyc/reject` | رفض KYC معلق مع سبب | `kyc:manage` أو Owner |
| POST | `/api/v1/kyc/resubmit` | إعادة تقديم بعد الرفض/الانتهاء | Account user |

### FR-IAM-016: إدارة الوثائق

| Method | Endpoint | الوصف | الصلاحية |
|--------|----------|-------|---------|
| GET | `/api/v1/kyc/documents` | قائمة الوثائق | `kyc:documents` أو Owner |
| POST | `/api/v1/kyc/documents/upload` | رفع وثيقة | Authenticated |
| GET | `/api/v1/kyc/documents/{id}/download` | تنزيل مؤقت (15 دقيقة) | `kyc:documents` أو Owner |
| DELETE | `/api/v1/kyc/documents/{id}` | حذف محتوى (Purge) | `kyc:manage` أو Owner |

### أمثلة

**حالة KYC (معتمد):**
```json
GET /api/v1/kyc/status

{
  "success": true,
  "data": {
    "status": "approved",
    "status_display": {
      "label": "مقبول",
      "label_en": "Verified",
      "color": "green",
      "icon": "shield-check"
    },
    "capabilities": {
      "can_ship_domestic": true,
      "can_ship_international": true,
      "can_use_cod": true,
      "shipping_limit": null,
      "message": "الحساب موثّق بالكامل."
    },
    "documents_count": 3,
    "expires_at": "2027-02-12T..."
  }
}
```

**تنزيل وثيقة (رابط مؤقت):**
```json
GET /api/v1/kyc/documents/{id}/download

{
  "data": {
    "document_id": "uuid",
    "document_type": "national_id",
    "download_url": "signed-url...",
    "expires_at": "2026-02-12T10:15:00Z",
    "ttl_minutes": 15
  }
}
```

---

## أكواد الأخطاء

| الكود | HTTP | الوصف |
|-------|------|-------|
| `ERR_KYC_NOT_FOUND` | 404 | سجل التحقق غير موجود |
| `ERR_KYC_STATUS_INVALID` | 422 | حالة غير صالحة (مثل: الموافقة على طلب غير معلق) |
| `ERR_KYC_SERVICE_UNAVAILABLE` | 503 | خدمة التحقق غير متاحة |
| `ERR_DOCUMENT_NOT_FOUND` | 404 | الوثيقة غير موجودة |
| `ERR_DOCUMENT_PURGED` | 410 | محتوى الوثيقة محذوف |
| `ERR_UNAUTHORIZED_ACCESS` | 403 | لا تملك صلاحية الوصول |

---

## قواعد الأعمال

| # | القاعدة |
|---|--------|
| 1 | الحالة الافتراضية عند التسجيل: `unverified` |
| 2 | لكل حالة KYC قدرات محددة (شحن، COD، API، حدود) |
| 3 | الموافقة تمنح صلاحية لمدة سنة واحدة (`expires_at`) |
| 4 | الرفض يتطلب سبب ويسمح بإعادة التقديم |
| 5 | إعادة التقديم متاحة فقط بعد الرفض أو الانتهاء |
| 6 | كل عملية وصول لوثيقة → سجل تدقيق |
| 7 | محاولة وصول غير مصرح → 403 + سجل تدقيق `warning` |
| 8 | Purge: يحذف المحتوى ويحتفظ بالبيانات الوصفية للتدقيق |
| 9 | الوثائق المحذوفة لا تظهر في القائمة |
| 10 | روابط التنزيل مؤقتة (15 دقيقة) |
| 11 | كل وثيقة تحمل `is_sensitive` flag لتحديد الوثائق الحساسة |
| 12 | Owner يملك جميع صلاحيات KYC ضمنياً |

---

## إجراءات التدقيق الجديدة (11 إجراء)

| الإجراء | التصنيف | الشدة |
|---------|---------|-------|
| `kyc.approved` | kyc | info |
| `kyc.rejected` | kyc | warning |
| `kyc.resubmitted` | kyc | info |
| `kyc.expired` | kyc | warning |
| `kyc.document_uploaded` | kyc | info |
| `kyc.documents_listed` | kyc | info |
| `kyc.document_accessed` | kyc | info |
| `kyc.document_purged` | kyc | warning |
| `kyc.access_denied` | kyc | warning |
| `kyc.document_access_denied` | kyc | warning |

---

## تغطية الاختبارات (44 اختبار)

### وحدة (24 اختبار)

| المجموعة | العدد | التغطية |
|----------|-------|---------|
| Status & Capabilities | 5 | unverified, pending, approved, rejected, display |
| Approve | 4 | Success, audit log, non-pending blocked, permission |
| Reject | 2 | Success, non-pending blocked |
| Resubmit | 3 | After rejection, after expiry, pending blocked |
| Document Upload | 2 | Success, audit log |
| Document List | 3 | Owner list, member blocked, access denied logged |
| Document Download | 2 | URL generation, access logged |
| Document Purge | 3 | Success, already purged, purged excluded from list |

### تكامل (20 اختبار)

| المجموعة | العدد | التغطية |
|----------|-------|---------|
| Status Endpoint | 5 | All states, capabilities, document count |
| Approve/Reject/Resubmit | 7 | Success, permissions, validation |
| Document List | 3 | Owner, blocked member, audit |
| Document Upload | 1 | Upload creation |
| Document Download | 2 | URL, audit logging |
| Document Purge | 2 | Owner purge, member blocked |

---

## تشغيل الاختبارات

```bash
php artisan test tests/Unit/KycTest.php tests/Feature/KycApiTest.php
```
