# 🚀 FR-IAM-003: إدارة الأدوار والصلاحيات (RBAC)

## Shipping Gateway — Identity & Access Management Module

---

## 📋 Feature Summary

| Field | Value |
|-------|-------|
| **ID** | FR-IAM-003 (+ FR-IAM-004 Least Privilege) |
| **Title** | إدارة الأدوار والصلاحيات (RBAC) |
| **Priority** | Must |
| **Status** | ✅ Implemented |
| **Depends On** | FR-IAM-001, FR-IAM-002 |

---

## 🏗️ Architecture Overview

```
┌──────────────────────────────────────────────────────────────┐
│              Permissions Catalog (System-wide)                │
│  ┌─────────┬─────────────┬──────────┬──────────────┐        │
│  │ users   │ shipments   │ financial│ reports ...   │        │
│  │ :view   │ :view       │ :view    │ :view         │        │
│  │ :manage │ :create     │ :wallet  │ :export       │        │
│  │ :invite │ :print      │ :ledger  │ :create       │        │
│  └─────────┴─────────────┴──────────┴──────────────┘        │
├──────────────────────────────────────────────────────────────┤
│              Role Templates (Pre-configured)                  │
│  ┌────────┬───────────┬───────────┬────────┬─────────┐      │
│  │ admin  │ accountant│ warehouse │ viewer │ printer  │      │
│  │ (all)  │ (finance) │ (shipping)│(read)  │(labels)  │      │
│  └────────┴───────────┴───────────┴────────┴─────────┘      │
├──────────────────────────────────────────────────────────────┤
│  Custom Roles (Per-Account / Tenant-scoped)                   │
│  ┌────────────────────────────────────────────────────┐      │
│  │ Role ←→ Permissions (Many-to-Many)                 │      │
│  │ User ←→ Roles      (Many-to-Many)                 │      │
│  │                                                    │      │
│  │ User.hasPermission('shipments:create')             │      │
│  │   → Check all roles → Union of permissions         │      │
│  │   → Owner = ALL permissions (bypass)               │      │
│  └────────────────────────────────────────────────────┘      │
├──────────────────────────────────────────────────────────────┤
│  Enforcement Layer                                            │
│  ┌──────────────────────────────┐                            │
│  │ CheckPermission Middleware   │                            │
│  │ Route::middleware('permission:shipments:create')   │      │
│  │ → 403 FORBIDDEN if denied   │                            │
│  └──────────────────────────────┘                            │
└──────────────────────────────────────────────────────────────┘
```

---

## 📁 New/Modified Files (FR-IAM-003)

```
shipping-gateway/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   └── RoleController.php              ✨ NEW (10 endpoints)
│   │   ├── Middleware/
│   │   │   └── CheckPermission.php             ✨ NEW (403 enforcement)
│   │   ├── Requests/
│   │   │   ├── CreateRoleRequest.php           ✨ NEW
│   │   │   └── UpdateRoleRequest.php           ✨ NEW
│   │   └── Resources/
│   │       └── RoleResource.php                ✨ NEW
│   ├── Models/
│   │   ├── Permission.php                      ✨ NEW
│   │   ├── Role.php                            ✨ NEW
│   │   └── User.php                            📝 UPDATED (+roles, +hasPermission)
│   ├── Rbac/
│   │   └── PermissionsCatalog.php              ✨ NEW (34 permissions, 5 templates)
│   └── Services/
│       └── RbacService.php                     ✨ NEW (core RBAC engine)
├── bootstrap/
│   └── app.php                                 📝 UPDATED (+permission middleware)
├── database/
│   ├── factories/
│   │   └── RoleFactory.php                     ✨ NEW
│   ├── migrations/
│   │   └── 2026_02_12_000004_create_rbac_tables.php  ✨ NEW
│   └── seeders/
│       └── PermissionsSeeder.php               ✨ NEW
├── routes/
│   └── api.php                                 📝 UPDATED (+10 RBAC routes)
└── tests/
    ├── Traits/
    │   └── SeedsPermissions.php                ✨ NEW
    ├── Unit/
    │   └── RbacTest.php                        ✨ NEW (24 tests)
    └── Feature/
        └── RbacApiTest.php                     ✨ NEW (18 tests)
```

---

## 🔌 API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/permissions` | كتالوج الصلاحيات (مجموعات) |
| `GET` | `/api/v1/roles/templates` | قوالب الأدوار المتاحة |
| `GET` | `/api/v1/roles` | قائمة أدوار الحساب |
| `GET` | `/api/v1/roles/{id}` | تفاصيل دور |
| `POST` | `/api/v1/roles` | إنشاء دور مخصص |
| `POST` | `/api/v1/roles/from-template` | إنشاء دور من قالب |
| `PUT` | `/api/v1/roles/{id}` | تحديث دور وصلاحياته |
| `DELETE` | `/api/v1/roles/{id}` | حذف دور |
| `POST` | `/api/v1/roles/{roleId}/assign/{userId}` | تعيين دور لمستخدم |
| `DELETE` | `/api/v1/roles/{roleId}/revoke/{userId}` | سحب دور من مستخدم |
| `GET` | `/api/v1/users/{id}/permissions` | صلاحيات المستخدم الفعلية |

---

## 🔐 Permissions Catalog (34 permissions in 9 groups)

| Group | Permissions |
|-------|------------|
| **users** | `view`, `manage`, `invite` |
| **roles** | `view`, `manage`, `assign` |
| **account** | `view`, `manage` |
| **shipments** | `view`, `create`, `edit`, `cancel`, `print`, `export` |
| **orders** | `view`, `manage`, `export` |
| **stores** | `view`, `manage` |
| **financial** | `view`, `wallet_topup`, `wallet_view`, `ledger_view`, `invoices_view`, `invoices_manage`, `refund_review`, `threshold` |
| **reports** | `view`, `export`, `create` |
| **kyc** | `view`, `manage`, `documents` |
| **apikeys** | `view`, `manage` |
| **audit** | `view`, `export` |

---

## 📋 Role Templates

| Template | وصف | عدد الصلاحيات |
|----------|-----|--------------|
| **admin** | مدير النظام — كل الصلاحيات | 34 |
| **accountant** | محاسب — مالية وتقارير | 11 |
| **warehouse** | مدير مستودع — شحنات وطلبات | 9 |
| **viewer** | مشاهد — عرض فقط | 9 |
| **printer** | طباعة فقط — بدون بيانات مالية | 3 |

---

## 🔒 Security Rules

| Rule | Implementation |
|------|---------------|
| **Least Privilege** | New role = 0 permissions (FR-IAM-004) |
| **Catalog enforcement** | `PERMISSION_UNKNOWN` if key not in catalog |
| **Anti-escalation** | Users cannot grant permissions they don't have |
| **Owner bypass** | Account owner has ALL permissions implicitly |
| **System roles** | Cannot edit/delete system roles |
| **Role in use** | Cannot delete role with assigned users |
| **Unified enforcement** | Same `CheckPermission` middleware on all routes |
| **Max permissions** | 100 permissions per role (edge case) |

---

## ✅ Test Coverage (42 Tests)

### Unit Tests — RbacTest (24 tests)
- ✅ Owner can create custom role
- ✅ New role starts with zero permissions (Least Privilege)
- ✅ Can create role from template
- ✅ Template permissions modifiable before save
- ✅ Cannot assign permission outside catalog (PERMISSION_UNKNOWN)
- ✅ Duplicate role name rejected (ERR_ROLE_EXISTS)
- ✅ Same name in different accounts allowed
- ✅ Max permissions per role enforced
- ✅ Non-owner cannot escalate permissions (ERR_ESCALATION_DENIED)
- ✅ Owner can assign/revoke role to user
- ✅ Cannot assign same role twice
- ✅ Owner has ALL permissions
- ✅ User without role has NO permissions
- ✅ User gets permissions from assigned role
- ✅ Multiple roles = union of permissions
- ✅ Cannot delete system role
- ✅ Cannot delete role with assigned users
- ✅ Role creation/assignment logged in audit

### Integration Tests — RbacApiTest (18 tests)
- ✅ Create role via API (201)
- ✅ New role starts empty
- ✅ Unknown permission returns PERMISSION_UNKNOWN
- ✅ Duplicate name returns ERR_ROLE_EXISTS
- ✅ Create from template via API
- ✅ List roles
- ✅ Update role permissions
- ✅ Assign/revoke role via API
- ✅ Get permissions catalog
- ✅ Get role templates
- ✅ Get user effective permissions
- ✅ **User without permission gets 403** (core RBAC test)
- ✅ User with correct permission can access
- ✅ **Non-owner cannot escalate permissions**
- ✅ Delete custom role
- ✅ Cannot delete role with users
- ✅ **Roles are tenant-isolated**

---

## ⚡ Setup & Run

```bash
# Seed the permissions catalog
php artisan db:seed --class=PermissionsSeeder

# Run RBAC tests
php artisan test tests/Unit/RbacTest.php
php artisan test tests/Feature/RbacApiTest.php

# Usage in routes (middleware enforcement):
Route::get('/shipments', [ShipmentController::class, 'index'])
     ->middleware('permission:shipments:view');

Route::post('/shipments', [ShipmentController::class, 'store'])
     ->middleware('permission:shipments:create');
```

---

## 🔗 Traceability

| From | To |
|------|----|
| SRS 4.2.1 — FR-IAM-003, FR-IAM-004 | RbacService + CheckPermission Middleware |
| AC: Owner creates custom role | `owner_can_create_custom_role` tests |
| AC: Unknown permission rejected | `PERMISSION_UNKNOWN` error + tests |
| AC: Template-based creation | `can_create_role_from_template` tests |
| FR-IAM-004 Least Privilege | New roles start with 0 permissions |
| FR-ORG-006 Unified enforcement | Same middleware on UI/API/Export |
