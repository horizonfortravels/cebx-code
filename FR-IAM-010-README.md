# 🏢 FR-IAM-010: دعم أنواع الحساب (فردي/منظمة)

## Shipping Gateway — Identity & Access Management Module

---

## 📋 Feature Summary

| Field | Value |
|-------|-------|
| **ID** | FR-IAM-010 (+ FR-ORG-001) |
| **Title** | دعم أنواع الحساب (فردي/منظمة) |
| **Priority** | Must |
| **Status** | ✅ Implemented |
| **Depends On** | FR-IAM-001, FR-IAM-002, FR-IAM-003 |

---

## 🏗️ Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│  Registration Flow                                               │
│  ┌──────────┐       ┌──────────────┐       ┌────────────────┐   │
│  │ Register │──────▶│ AccountService│──────▶│AccountTypeService│  │
│  │ API      │       │ .createAccount│      │.initializeType  │   │
│  └──────────┘       └──────────────┘       └────────┬───────┘   │
│                                                       │           │
│                    ┌──────────────────────────────────┤           │
│                    ▼                                  ▼           │
│  ┌─────────────────────────┐    ┌─────────────────────────────┐  │
│  │  type = "individual"     │    │  type = "organization"       │  │
│  │  ─────────────────────── │    │  ─────────────────────────── │  │
│  │  • No Org Profile       │    │  • Auto-create Org Profile   │  │
│  │  • KYC: national_id     │    │  • KYC: CR, Tax, Address,    │  │
│  │         address_proof    │    │         Authorization Letter │  │
│  │  • Same RBAC capability │    │  • Same RBAC capability      │  │
│  └─────────────────────────┘    └─────────────────────────────┘  │
│                                                                   │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  KYC Flow: Unverified → Pending → Approved/Rejected         │ │
│  │  Type Change: Allowed ONLY before active usage               │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

---

## 📁 New/Modified Files

```
shipping-gateway/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   └── AccountTypeController.php         ✨ NEW (6 endpoints)
│   │   ├── Requests/
│   │   │   ├── RegisterAccountRequest.php        📝 UPDATED (+org fields)
│   │   │   └── UpdateOrganizationProfileRequest.php ✨ NEW
│   │   └── Resources/
│   │       └── OrganizationProfileResource.php   ✨ NEW
│   ├── Models/
│   │   ├── Account.php                           📝 UPDATED (+relations, +kyc)
│   │   ├── OrganizationProfile.php               ✨ NEW
│   │   └── KycVerification.php                   ✨ NEW
│   └── Services/
│       ├── AccountService.php                    📝 UPDATED (calls initializeType)
│       └── AccountTypeService.php                ✨ NEW (core logic)
├── database/
│   ├── factories/
│   │   ├── AccountFactory.php                    📝 UPDATED (+kyc_status)
│   │   └── OrganizationProfileFactory.php        ✨ NEW
│   └── migrations/
│       └── ..._create_organization_kyc_tables.php ✨ NEW
├── routes/
│   └── api.php                                   📝 UPDATED (+6 routes)
└── tests/
    ├── Unit/
    │   └── AccountTypeTest.php                   ✨ NEW (18 tests)
    └── Feature/
        └── AccountTypeApiTest.php                ✨ NEW (15 tests)
```

---

## 🔌 API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/account/type` | معلومات نوع الحساب + ملف المنظمة + حالة KYC |
| `GET` | `/api/v1/account/organization` | تفاصيل ملف المنظمة |
| `PUT` | `/api/v1/account/organization` | تحديث ملف المنظمة |
| `POST` | `/api/v1/account/type-change` | طلب تغيير نوع الحساب |
| `GET` | `/api/v1/account/kyc` | حالة التحقق KYC |
| `POST` | `/api/v1/account/kyc/submit` | إرسال وثائق KYC للمراجعة |

---

## 📊 Database Schema

### organization_profiles
| Column | Type | Description |
|--------|------|-------------|
| id | UUID | PK |
| account_id | UUID | FK → accounts (unique) |
| legal_name | varchar(200) | الاسم القانوني |
| trade_name | varchar(200) | الاسم التجاري |
| registration_number | varchar(100) | السجل التجاري |
| tax_id | varchar(100) | الرقم الضريبي |
| industry | varchar(100) | القطاع |
| company_size | varchar(50) | حجم الشركة |
| country/city/address | - | العنوان |
| billing_currency | char(3) | عملة الفوترة (SAR) |
| billing_cycle | varchar(20) | دورة الفوترة |

### kyc_verifications
| Column | Type | Description |
|--------|------|-------------|
| id | UUID | PK |
| account_id | UUID | FK → accounts |
| status | varchar(30) | unverified/pending/approved/rejected/expired |
| verification_type | varchar(50) | individual/organization |
| required_documents | JSON | قائمة الوثائق المطلوبة |
| submitted_documents | JSON | مراجع الوثائق المقدمة |
| rejection_reason | text | سبب الرفض |

---

## 🔐 Business Rules

| Rule | Implementation |
|------|---------------|
| **Auto Org Profile** | تسجيل منظمة → إنشاء ملف تلقائي |
| **KYC Initialization** | كل حساب يبدأ بـ `unverified` |
| **Different Documents** | منظمة: 4 وثائق، فرد: 2 وثيقة |
| **Type Change Restriction** | ممنوع بعد استخدام الخدمات (مستخدمين/شحنات) |
| **Same RBAC** | الفرد والمنظمة لهما نفس قدرات RBAC |
| **Document Validation** | `ERR_MISSING_DOCUMENTS` إذا ناقصة |
| **Idempotent** | تغيير الاختيار قبل الإرسال = الاختيار النهائي فقط |

---

## 🔴 Error Codes

| Code | HTTP | Description |
|------|------|-------------|
| `ERR_NOT_ORGANIZATION` | 422 | العملية خاصة بحسابات المنظمات فقط |
| `ERR_MISSING_DOCUMENTS` | 422 | وثائق KYC ناقصة |
| `ERR_TYPE_CHANGE_NOT_ALLOWED` | 409 | لا يمكن تغيير النوع بعد الاستخدام |
| `ERR_SAME_TYPE` | 422 | الحساب من نفس النوع بالفعل |
| `ERR_PROFILE_NOT_FOUND` | 404 | ملف المنظمة غير موجود |

---

## ✅ Test Coverage (33 Tests)

### Unit Tests — AccountTypeTest (18 tests)
- ✅ Organization auto-creates profile
- ✅ Organization starts with KYC unverified
- ✅ Organization KYC has required documents list
- ✅ Individual does NOT create org profile
- ✅ Individual has individual KYC documents
- ✅ Default type is individual
- ✅ Can update organization profile
- ✅ Cannot update org profile on individual account
- ✅ Can change type before active usage
- ✅ Type change creates profile and resets KYC
- ✅ Cannot change type after active usage (ERR_TYPE_CHANGE_NOT_ALLOWED)
- ✅ Correct error code for blocked type change
- ✅ Cannot change to same type
- ✅ Missing KYC documents fails (ERR_MISSING_DOCUMENTS)
- ✅ Complete KYC submission sets status pending
- ✅ Type change is audit logged
- ✅ KYC submission is audit logged

### Integration Tests — AccountTypeApiTest (15 tests)
- ✅ Register org → creates profile + KYC
- ✅ Register individual → no org profile
- ✅ Get account type info (org)
- ✅ Get account type info (individual)
- ✅ Get organization profile
- ✅ Individual cannot access org profile (ERR_NOT_ORGANIZATION)
- ✅ Update organization profile
- ✅ Org profile update is audit logged
- ✅ Change type: individual → organization
- ✅ Cannot change type after adding users (409)
- ✅ Cannot change to same type (422)
- ✅ Get KYC status
- ✅ Submit all KYC docs → pending
- ✅ Submit incomplete KYC docs → ERR_MISSING_DOCUMENTS
- ✅ Individual KYC requires different documents
- ✅ Tenant isolation for org profiles

---

## 🔗 Traceability

| SRS Requirement | Implementation |
|-----------------|---------------|
| FR-IAM-010 (أنواع الحساب) | AccountTypeService + Account model |
| FR-ORG-001 (إنشاء نوع الحساب تلقائياً) | Auto-create in registration flow |
| FR-IAM-014 (حالة KYC) | KycVerification model + API |
| AC: منظمة + مستندات → KYC معلق | `submitKycDocuments()` + tests |
| AC: وثائق ناقصة → رفض | `ERR_MISSING_DOCUMENTS` validation |
| AC: تغيير النوع بعد الاستخدام → ممنوع | `ERR_TYPE_CHANGE_NOT_ALLOWED` |
