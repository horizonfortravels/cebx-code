# 🚀 FR-IAM-001: Multi-Tenant Account System

## Shipping Gateway — Identity & Access Management Module

---

## 📋 Feature Summary

| Field | Value |
|-------|-------|
| **ID** | FR-IAM-001 |
| **Title** | حساب متعدد المستأجرين (Multi-Tenancy Account) |
| **Priority** | Must |
| **Status** | ✅ Implemented |

---

## 🏗️ Architecture Overview

```
┌─────────────────────────────────────────────────────┐
│                    API Request                       │
├─────────────────────────────────────────────────────┤
│  Route: POST /api/v1/register                       │
│         GET  /api/v1/account (auth + tenant)        │
├─────────────────────────────────────────────────────┤
│  Middleware Layer                                    │
│  ┌───────────────┐  ┌──────────────────┐           │
│  │  auth:sanctum  │→│  TenantMiddleware │           │
│  │  (Laravel)     │  │  (sets account)  │           │
│  └───────────────┘  └──────────────────┘           │
├─────────────────────────────────────────────────────┤
│  Controller → Service → Model                       │
│  ┌─────────────────┐                                │
│  │ AccountService   │──→ DB Transaction              │
│  │  createAccount() │    ├─ Create Account (UUID)    │
│  │                  │    ├─ Create Owner User         │
│  │                  │    └─ Create Audit Log          │
│  └─────────────────┘                                │
├─────────────────────────────────────────────────────┤
│  Data Isolation Layer                                │
│  ┌──────────────────────────────────────┐           │
│  │  BelongsToAccount Trait              │           │
│  │  → AccountScope (Global Scope)       │           │
│  │  → Auto-filter by account_id         │           │
│  │  → PostgreSQL Row-Level Security     │           │
│  └──────────────────────────────────────┘           │
└─────────────────────────────────────────────────────┘
```

---

## 📁 File Structure

```
shipping-gateway/
├── app/
│   ├── Exceptions/
│   │   └── Handler.php                    # Custom error codes (ERR_DUPLICATE_EMAIL, etc.)
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   └── AccountController.php      # Register + Show endpoints
│   │   ├── Middleware/
│   │   │   └── TenantMiddleware.php       # Resolves current tenant from user
│   │   ├── Requests/
│   │   │   └── RegisterAccountRequest.php # Validation rules
│   │   └── Resources/
│   │       └── AccountResource.php        # API response transformer
│   ├── Models/
│   │   ├── Account.php                    # Core tenant model (UUID PK)
│   │   ├── AuditLog.php                   # Tenant-scoped audit trail
│   │   ├── User.php                       # User with account_id FK
│   │   ├── Scopes/
│   │   │   └── AccountScope.php           # Global scope: WHERE account_id = ?
│   │   └── Traits/
│   │       └── BelongsToAccount.php       # Apply to any tenant-scoped model
│   └── Services/
│       └── AccountService.php             # Business logic (DB transaction)
├── bootstrap/
│   └── app.php                            # Middleware registration
├── database/
│   ├── factories/
│   │   ├── AccountFactory.php
│   │   └── UserFactory.php
│   └── migrations/
│       ├── 2026_02_12_000001_create_accounts_table.php
│       ├── 2026_02_12_000002_create_users_table.php
│       └── 2026_02_12_000003_create_audit_logs_table.php
├── routes/
│   └── api.php                            # API v1 routes
└── tests/
    ├── Unit/
    │   └── AccountCreationTest.php        # 8 unit tests
    └── Feature/
        ├── AccountRegistrationApiTest.php # 5 API tests
        └── TenantIsolationTest.php        # 5 isolation tests
```

---

## 🔌 API Endpoints

### POST `/api/v1/register` — Create Account

**Request:**
```json
{
  "account_name": "شركة الشحن الدولي",
  "account_type": "organization",
  "name": "محمد أحمد",
  "email": "mohammed@shipping.com",
  "password": "Str0ng!Pass",
  "password_confirmation": "Str0ng!Pass",
  "timezone": "Asia/Riyadh",
  "locale": "ar"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "تم إنشاء الحساب بنجاح.",
  "data": {
    "account": {
      "id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
      "name": "شركة الشحن الدولي",
      "type": "organization",
      "status": "active",
      "slug": "shrk-alshhn-aldwly",
      "settings": {
        "currency": "USD",
        "timezone": "Asia/Riyadh",
        "locale": "ar"
      }
    },
    "user": {
      "id": "...",
      "name": "محمد أحمد",
      "email": "mohammed@shipping.com",
      "is_owner": true
    },
    "token": "1|abc123..."
  }
}
```

### Error Responses

| Code | Error Code | Description |
|------|-----------|-------------|
| 422 | `ERR_DUPLICATE_EMAIL` | البريد الإلكتروني مستخدم بالفعل |
| 422 | `ERR_INVALID_INPUT` | إدخال غير صالح (اسم طويل، كلمة مرور ضعيفة) |
| 401 | `ERR_UNAUTHENTICATED` | يرجى تسجيل الدخول |

---

## 🔒 Multi-Tenancy Isolation Strategy

1. **UUID Account ID**: كل حساب يحصل على `account_id` فريد (UUID v4)
2. **Global Scope**: كل model يستخدم `BelongsToAccount` trait يتم فلترته تلقائياً
3. **Middleware**: `TenantMiddleware` يحدد الحساب الحالي من المستخدم المسجّل
4. **Row-Level Security**: مفعّل على PostgreSQL كطبقة حماية إضافية
5. **DB Transaction**: إنشاء الحساب + المستخدم + Audit Log في transaction واحد

---

## ⚡ Setup & Run

```bash
# 1. Install dependencies
composer install

# 2. Configure .env
cp .env.example .env
# Set DB_CONNECTION=pgsql and configure PostgreSQL

# 3. Generate key
php artisan key:generate

# 4. Run migrations
php artisan migrate

# 5. Install Sanctum
php artisan install:api

# 6. Run tests
php artisan test --filter=AccountCreation
php artisan test --filter=AccountRegistrationApi
php artisan test --filter=TenantIsolation

# 7. Run all FR-IAM-001 tests
php artisan test tests/Unit/AccountCreationTest.php tests/Feature/AccountRegistrationApiTest.php tests/Feature/TenantIsolationTest.php
```

---

## ✅ Test Coverage (18 Tests)

### Unit Tests (8)
- ✅ إنشاء حساب بمعلومات صحيحة
- ✅ توليد `account_id` فريد (UUID)
- ✅ إنشاء مستخدم مالك مرتبط بالحساب
- ✅ النوع الافتراضي `individual`
- ✅ تسجيل Audit Log عند الإنشاء
- ✅ إعدادات افتراضية صحيحة
- ✅ معالجة Slug مكرر
- ✅ UUID كمفتاح أساسي

### Integration Tests — API (5)
- ✅ تسجيل حساب جديد عبر API (201)
- ✅ رفض بريد مكرر (ERR_DUPLICATE_EMAIL)
- ✅ رفض اسم حساب طويل (ERR_INVALID_INPUT)
- ✅ التحقق من الحقول المطلوبة
- ✅ التحقق من قوة كلمة المرور

### Integration Tests — Tenant Isolation (5)
- ✅ Tenant A لا يرى بيانات Tenant B
- ✅ API يعيد بيانات الحساب الحالي فقط
- ✅ Audit Logs معزولة لكل tenant
- ✅ كل حساب يحصل على UUID فريد
- ✅ رفض الوصول بدون تسجيل دخول

---

## 🔗 Traceability

| From | To |
|------|----|
| SRS 4.2.1 — FR-IAM-001 | This implementation |
| Acceptance Criteria | Test cases (18 tests) |
| Error Codes | Exception Handler mapping |
| Business Rules | AccountService + Middleware |
