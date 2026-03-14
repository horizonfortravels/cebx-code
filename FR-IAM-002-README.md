# 🚀 FR-IAM-002: إدارة المستخدمين ضمن الحساب

## Shipping Gateway — Identity & Access Management Module

---

## 📋 Feature Summary

| Field | Value |
|-------|-------|
| **ID** | FR-IAM-002 |
| **Title** | إدارة المستخدمين ضمن الحساب (User Management) |
| **Priority** | Must |
| **Status** | ✅ Implemented |
| **Depends On** | FR-IAM-001 (Multi-Tenancy) |

---

## 🏗️ Architecture Overview

```
┌──────────────────────────────────────────────────────────────┐
│                     API Endpoints                             │
├──────────────────────────────────────────────────────────────┤
│  GET    /api/v1/users              → List users (paginated)  │
│  GET    /api/v1/users/{id}         → Show user details       │
│  POST   /api/v1/users              → Add/invite user         │
│  PUT    /api/v1/users/{id}         → Update user profile     │
│  PATCH  /api/v1/users/{id}/disable → Disable (revoke tokens) │
│  PATCH  /api/v1/users/{id}/enable  → Re-enable user          │
│  DELETE /api/v1/users/{id}         → Soft delete user         │
│  GET    /api/v1/users/changelog    → User audit trail         │
├──────────────────────────────────────────────────────────────┤
│  Middleware: auth:sanctum → TenantMiddleware                  │
├──────────────────────────────────────────────────────────────┤
│                     UserService                               │
│  ┌────────────────────────────────────────────────────┐      │
│  │ addUser()     → Validate → Create → AuditLog → Event│     │
│  │ disableUser() → Check perms → Update → Revoke tokens│     │
│  │ enableUser()  → Check status → Reactivate           │     │
│  │ deleteUser()  → Check responsibilities → Soft delete │     │
│  │ updateUser()  → Track old/new values → AuditLog     │     │
│  │ listUsers()   → Filter/Search/Paginate              │     │
│  └────────────────────────────────────────────────────┘      │
├──────────────────────────────────────────────────────────────┤
│  Events → Listeners (Async Queued)                            │
│  UserInvited  → SendUserInvitationListener (Email/SMS)        │
│  UserDisabled → (Future: notification)                        │
│  UserDeleted  → (Future: cleanup)                             │
└──────────────────────────────────────────────────────────────┘
```

---

## 📁 New/Modified Files (FR-IAM-002)

```
shipping-gateway/
├── app/
│   ├── Events/
│   │   ├── UserInvited.php                 ✨ NEW
│   │   ├── UserDisabled.php                ✨ NEW
│   │   └── UserDeleted.php                 ✨ NEW
│   ├── Exceptions/
│   │   └── BusinessException.php           ✨ NEW (reusable error codes)
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   └── UserController.php          ✨ NEW (8 endpoints)
│   │   ├── Requests/
│   │   │   ├── AddUserRequest.php          ✨ NEW
│   │   │   ├── UpdateUserRequest.php       ✨ NEW
│   │   │   └── ListUsersRequest.php        ✨ NEW
│   │   └── Resources/
│   │       ├── UserResource.php            ✨ NEW
│   │       └── AuditLogResource.php        ✨ NEW
│   ├── Listeners/
│   │   └── SendUserInvitationListener.php  ✨ NEW (queued)
│   ├── Providers/
│   │   └── EventServiceProvider.php        ✨ NEW
│   └── Services/
│       └── UserService.php                 ✨ NEW (core business logic)
├── routes/
│   └── api.php                             📝 UPDATED (added user routes)
└── tests/
    ├── Unit/
    │   └── UserManagementTest.php          ✨ NEW (20 tests)
    └── Feature/
        └── UserManagementApiTest.php       ✨ NEW (18 tests)
```

---

## 🔌 API Endpoints Detail

### POST `/api/v1/users` — Add/Invite User

**Request:**
```json
{
  "name": "موظف جديد",
  "email": "employee@company.com",
  "password": "Str0ng!Pass",
  "password_confirmation": "Str0ng!Pass",
  "phone": "+966501234567",
  "timezone": "Asia/Riyadh"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "تمت إضافة المستخدم بنجاح وتم إرسال الدعوة.",
  "data": {
    "id": "uuid-here",
    "name": "موظف جديد",
    "email": "employee@company.com",
    "status": "active",
    "is_owner": false
  }
}
```

### PATCH `/api/v1/users/{id}/disable` — Disable User

**Response (200):**
```json
{
  "success": true,
  "message": "تم تعطيل المستخدم بنجاح. لن يتمكن من الدخول.",
  "data": { "id": "...", "status": "inactive" }
}
```

### DELETE `/api/v1/users/{id}` — Delete User

**Without force (409 if responsibilities exist):**
```json
{
  "success": false,
  "error_code": "ERR_RESPONSIBILITY_TRANSFER_REQUIRED",
  "message": "يجب نقل مسؤوليات هذا المستخدم أولاً قبل الحذف."
}
```

**With `?force_transfer=true` (200):**
```json
{
  "success": true,
  "message": "تم حذف المستخدم بنجاح."
}
```

### GET `/api/v1/users?status=active&search=محمد&sort_by=name&per_page=15`

Supports: filtering by status, searching by name/email, sorting, pagination.

---

## 🔒 Business Rules Implemented

| Rule | Implementation |
|------|---------------|
| Owner/Admin only can manage users | `assertCanManageUsers()` checks `is_owner` |
| Disabling prevents login **immediately** | All Sanctum tokens revoked on disable |
| Cannot disable/delete self | `cannotModifySelf()` check |
| Cannot modify account owner | `cannotModifyOwner()` check |
| Delete requires responsibility transfer | `hasActiveResponsibilities()` check |
| Force transfer bypasses check | `?force_transfer=true` parameter |
| All changes logged | AuditLog created for every action |
| Events fired for notifications | `UserInvited`, `UserDisabled`, `UserDeleted` |

---

## ⚠️ Error Codes

| Code | HTTP | Description |
|------|------|-------------|
| `ERR_USER_NOT_FOUND` | 404 | المستخدم غير موجود في هذا الحساب |
| `ERR_PERMISSION` | 403 | لا يملك صلاحية كافية لإدارة المستخدمين |
| `ERR_DUPLICATE_EMAIL` | 422 | البريد الإلكتروني مستخدم بالفعل |
| `ERR_SELF_MODIFICATION` | 422 | لا يمكن تعديل حسابك الخاص |
| `ERR_OWNER_PROTECTED` | 422 | لا يمكن تعديل حالة مالك الحساب |
| `ERR_RESPONSIBILITY_TRANSFER_REQUIRED` | 409 | يجب نقل المسؤوليات أولاً |
| `ERR_ALREADY_ACTIVE` | 422 | المستخدم نشط بالفعل |

---

## ✅ Test Coverage (38 Tests Total)

### Unit Tests — UserManagementTest (20 tests)
- ✅ Owner can add user to account
- ✅ Added user gets invitation event
- ✅ Adding user creates audit log
- ✅ Cannot add duplicate email in same account
- ✅ Owner can disable user
- ✅ Disabling user revokes all tokens
- ✅ Cannot disable self
- ✅ Cannot disable account owner
- ✅ Owner can enable disabled user
- ✅ Cannot enable already active user
- ✅ Owner can delete user without responsibilities
- ✅ Cannot delete self
- ✅ Cannot delete account owner
- ✅ Deleting user with responsibilities requires transfer
- ✅ Force transfer bypasses responsibility check
- ✅ Disable nonexistent user throws not found
- ✅ Non-owner cannot manage users
- ✅ Owner can update user info
- ✅ Update creates audit log with old and new values

### Integration Tests — UserManagementApiTest (18 tests)
- ✅ Owner can add user via API (201)
- ✅ Duplicate email returns ERR_DUPLICATE_EMAIL
- ✅ Owner can list users with pagination
- ✅ Can filter users by status
- ✅ Can search users by name or email
- ✅ Owner can disable user via API
- ✅ **Disabled user cannot access API** (token revoked = 401)
- ✅ Disabling nonexistent user returns 404
- ✅ Owner can enable disabled user
- ✅ Owner can delete user via API
- ✅ Delete with responsibilities returns 409
- ✅ Delete with force_transfer succeeds
- ✅ Owner can update user via API
- ✅ Non-owner cannot add users (403)
- ✅ Non-owner cannot disable users (403)
- ✅ Owner can view user changelog
- ✅ Changelog only shows current account logs
- ✅ **Owner cannot manage users from another account** (tenant isolation)

---

## ⚡ Run Tests

```bash
# Unit tests only
php artisan test tests/Unit/UserManagementTest.php

# Integration tests only
php artisan test tests/Feature/UserManagementApiTest.php

# All FR-IAM-002 tests
php artisan test --filter=UserManagement

# All IAM tests (FR-IAM-001 + FR-IAM-002)
php artisan test tests/Unit/ tests/Feature/
```

---

## 🔗 Traceability

| From | To |
|------|----|
| SRS 4.2.1 — FR-IAM-002 | UserService + UserController |
| AC: نجاح (add user) | `owner_can_add_user_*` tests |
| AC: فشل شائع (user not found) | `disable_nonexistent_user_*` tests |
| AC: حالة حدية (delete with privileges) | `deleting_user_with_responsibilities_*` tests |
| ERR_USER_NOT_FOUND | BusinessException::userNotFound() |
| ERR_PERMISSION | BusinessException::permissionDenied() |
| Dependency: Email service | UserInvited event + SendUserInvitationListener |
