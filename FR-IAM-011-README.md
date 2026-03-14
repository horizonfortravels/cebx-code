# 📨 FR-IAM-011: آلية الدعوات للمستخدمين

## Shipping Gateway — Identity & Access Management Module

---

## 📋 Feature Summary

| Field | Value |
|-------|-------|
| **ID** | FR-IAM-011 (+ FR-IAM-012/SRS, FR-ORG-003) |
| **Title** | آلية الدعوات للمستخدمين |
| **Priority** | Must |
| **Status** | ✅ Implemented |
| **Depends On** | FR-IAM-001, FR-IAM-002, FR-IAM-003 |

---

## 🏗️ Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│  Invitation Lifecycle                                            │
│                                                                   │
│  ┌───────────┐    ┌──────────────────┐    ┌──────────────────┐  │
│  │ Owner/    │───▶│ InvitationService│───▶│ InvitationCreated│  │
│  │ Admin     │    │ .createInvitation │    │ Event → Email    │  │
│  └───────────┘    └──────────────────┘    └──────────────────┘  │
│                                                                   │
│  ┌───────────┐    ┌──────────────────┐    ┌──────────────────┐  │
│  │ Invitee   │───▶│ InvitationService│───▶│ User Created     │  │
│  │ (public)  │    │ .acceptInvitation │    │ + Role Assigned  │  │
│  └───────────┘    └──────────────────┘    │ + Event Fired    │  │
│                                            └──────────────────┘  │
│  Status Flow:                                                     │
│  ┌─────────┐    ┌──────────┐                                     │
│  │ PENDING │───▶│ ACCEPTED │ (token used, user created)          │
│  │         │───▶│ EXPIRED  │ (TTL passed, auto-updated)          │
│  │         │───▶│CANCELLED │ (admin cancelled)                   │
│  └─────────┘    └──────────┘                                     │
│                                                                   │
│  Security:                                                        │
│  • SHA-256 secure token (128 chars)                              │
│  • TTL-based expiration (default 72h, configurable)              │
│  • One-time use: token invalid after acceptance                  │
│  • Resend generates NEW token + resets TTL                       │
│  • Max 5 resends per invitation (spam prevention)                │
│  • Tenant-isolated: invitations scoped to account_id             │
└─────────────────────────────────────────────────────────────────┘
```

---

## 📁 New Files Created (13 files)

```
app/
├── Events/
│   ├── InvitationCreated.php          ← Event: invitation created
│   ├── InvitationAccepted.php         ← Event: invitation accepted
│   ├── InvitationCancelled.php        ← Event: invitation cancelled
│   └── InvitationResent.php           ← Event: invitation resent
├── Exceptions/
│   └── BusinessException.php          ← UPDATED: +8 error codes
├── Http/
│   ├── Controllers/Api/V1/
│   │   └── InvitationController.php   ← 6 API endpoints
│   ├── Requests/
│   │   ├── CreateInvitationRequest.php
│   │   ├── AcceptInvitationRequest.php
│   │   └── ListInvitationsRequest.php
│   └── Resources/
│       ├── InvitationResource.php     ← Full response (for admins)
│       └── InvitationPreviewResource.php ← Limited response (for invitee)
├── Listeners/
│   └── SendInvitationEmailListener.php
├── Models/
│   └── Invitation.php                 ← Model with status helpers
├── Providers/
│   └── EventServiceProvider.php       ← UPDATED: +4 events
├── Services/
│   └── InvitationService.php          ← Core business logic
database/
├── factories/
│   └── InvitationFactory.php
├── migrations/
│   └── 2026_02_12_000006_create_invitations_table.php
routes/
└── api.php                            ← UPDATED: +7 routes
tests/
├── Unit/InvitationTest.php            ← 28 unit tests
└── Feature/InvitationApiTest.php      ← 20 integration tests
```

---

## 🔌 API Endpoints

### Authenticated Endpoints (Owner/Admin — `auth:sanctum` + `tenant`)

| Method | Endpoint | Description | Status |
|--------|----------|-------------|--------|
| `POST` | `/api/v1/invitations` | إنشاء دعوة جديدة | 201 |
| `GET` | `/api/v1/invitations` | قائمة الدعوات (مع فلترة/بحث) | 200 |
| `GET` | `/api/v1/invitations/{id}` | تفاصيل دعوة محددة | 200 |
| `PATCH` | `/api/v1/invitations/{id}/cancel` | إلغاء دعوة معلقة | 200 |
| `POST` | `/api/v1/invitations/{id}/resend` | إعادة إرسال (رمز جديد + تجديد TTL) | 200 |

### Public Endpoints (Invitee — no authentication)

| Method | Endpoint | Description | Status |
|--------|----------|-------------|--------|
| `GET` | `/api/v1/invitations/preview/{token}` | عرض تفاصيل الدعوة قبل القبول | 200 |
| `POST` | `/api/v1/invitations/accept/{token}` | قبول الدعوة وإنشاء الحساب | 201 |

### Request/Response Examples

**Create Invitation:**
```json
POST /api/v1/invitations
{
  "email": "newuser@example.com",
  "name": "محمد أحمد",
  "role_id": "uuid-of-role",
  "ttl_hours": 48
}
→ 201: { "success": true, "data": { "id": "...", "email": "...", "status": "pending", ... } }
```

**Accept Invitation:**
```json
POST /api/v1/invitations/accept/{token}
{
  "name": "محمد أحمد",
  "password": "SecureP@ss1!",
  "phone": "+966500000000",
  "locale": "ar",
  "timezone": "Asia/Riyadh"
}
→ 201: { "data": { "user": {...}, "invitation": { "status": "accepted" } } }
```

---

## ❌ Error Codes

| Code | HTTP | Description |
|------|------|-------------|
| `ERR_INVITATION_NOT_FOUND` | 404 | الدعوة غير موجودة |
| `ERR_INVITATION_EXPIRED` | 410 | انتهت صلاحية الدعوة |
| `ERR_INVITATION_REVOKED` | 410 | تم إلغاء الدعوة |
| `ERR_INVITATION_ALREADY_ACCEPTED` | 409 | الدعوة مقبولة مسبقاً (لا يمكن إعادة استخدام الرابط) |
| `ERR_INVITATION_ALREADY_EXISTS` | 409 | دعوة نشطة موجودة لنفس البريد |
| `ERR_INVITATION_CANNOT_RESEND` | 422 | لا يمكن إعادة إرسال (ليست معلقة) |
| `ERR_INVITATION_CANNOT_CANCEL` | 422 | لا يمكن إلغاء (ليست معلقة) |
| `ERR_INVITATION_MAX_RESEND` | 429 | تجاوز الحد الأقصى لإعادة الإرسال (5) |
| `ERR_EMAIL_ALREADY_IN_ACCOUNT` | 409 | البريد مسجل بالفعل في الحساب |
| `ERR_ROLE_NOT_FOUND` | 404 | الدور غير موجود في الحساب |

---

## 📏 Business Rules

| Rule | Implementation |
|------|----------------|
| دعوة لها TTL افتراضي 72 ساعة | `InvitationService::DEFAULT_TTL_HOURS` |
| حالات: Pending → Accepted / Expired / Cancelled | `Invitation::STATUS_*` constants |
| لا يجوز استخدام رابط الدعوة بعد القبول | Token check + `ERR_INVITATION_ALREADY_ACCEPTED` |
| إعادة الإرسال مسموحة فقط للمعلقة | `canResend()` + `ERR_INVITATION_CANNOT_RESEND` |
| إعادة الإرسال تولد رمز جديد + تجدد TTL | `resendInvitation()` generates new SHA-256 token |
| حد أقصى 5 إعادات لمنع الإزعاج | `MAX_RESEND_COUNT` + `ERR_INVITATION_MAX_RESEND` |
| منع دعوة مكررة لنفس البريد + نفس الحساب | Pending check + `ERR_INVITATION_ALREADY_EXISTS` |
| منع دعوة مستخدم مسجل بالفعل | Email check + `ERR_EMAIL_ALREADY_IN_ACCOUNT` |
| انتهاء الصلاحية التلقائي | `expireStaleInvitations()` batch job |
| تعيين دور تلقائي عند القبول | `role_id` → user_role pivot on accept |
| Owner أو من لديه `users:invite` يمكنه الدعوة | RBAC-aware `assertCanInvite()` |
| عزل الدعوات حسب الحساب | `account_id` scope on all queries |

---

## ✅ Test Coverage (48 tests)

### Unit Tests — `tests/Unit/InvitationTest.php` (28 tests)

| # | Test | Covers |
|---|------|--------|
| 1 | ✅ Owner can create invitation | AC: نجاح |
| 2 | ✅ Invitation fires created event | Event system |
| 3 | ✅ Invitation creates audit log | Audit trail |
| 4 | ✅ Invitation can include role assignment | Role pre-assignment |
| 5 | ✅ Invitation uses custom TTL | Configurable TTL |
| 6 | ✅ Cannot create duplicate pending invitation | ERR_INVITATION_ALREADY_EXISTS |
| 7 | ✅ Cannot invite existing account user | ERR_EMAIL_ALREADY_IN_ACCOUNT |
| 8 | ✅ Cannot invite with role from another account | Tenant isolation |
| 9 | ✅ Non-owner without permission cannot invite | ERR_PERMISSION |
| 10 | ✅ Invitee can accept valid invitation | AC: نجاح — قبول |
| 11 | ✅ Accepting invitation assigns role | Role assignment |
| 12 | ✅ Accepting invitation fires event | InvitationAccepted event |
| 13 | ✅ Accepting invitation creates audit log | Audit trail |
| 14 | ✅ Cannot accept expired invitation | ERR_INVITATION_EXPIRED |
| 15 | ✅ Cannot accept cancelled invitation | ERR_INVITATION_REVOKED |
| 16 | ✅ Cannot accept already accepted invitation | ERR_INVITATION_ALREADY_ACCEPTED |
| 17 | ✅ Cannot accept with invalid token | ERR_INVITATION_NOT_FOUND |
| 18 | ✅ Owner can cancel pending invitation | Cancel lifecycle |
| 19 | ✅ Cannot cancel accepted invitation | ERR_INVITATION_CANNOT_CANCEL |
| 20 | ✅ Cannot cancel invitation from another account | Tenant isolation |
| 21 | ✅ Owner can resend pending invitation | Resend with new token |
| 22 | ✅ Cannot resend cancelled invitation | ERR_INVITATION_CANNOT_RESEND |
| 23 | ✅ Cannot exceed max resend count | ERR_INVITATION_MAX_RESEND |
| 24 | ✅ Stale invitations are auto-expired | Batch expiration |
| 25 | ✅ Can create new invitation after cancelling previous | Re-invite flow |
| 26 | ✅ Can create new invitation after previous expired | Re-invite flow |
| 27 | ✅ Can preview invitation by token | Public preview |
| 28 | ✅ Preview auto-expires stale invitation | Auto-expiry |

### Integration Tests — `tests/Feature/InvitationApiTest.php` (20 tests)

| # | Test | Covers |
|---|------|--------|
| 1 | ✅ Owner can create invitation via API (201) | POST /invitations |
| 2 | ✅ Create invitation with role | Role pre-assignment |
| 3 | ✅ Duplicate pending returns 409 | ERR_INVITATION_ALREADY_EXISTS |
| 4 | ✅ Invite existing user returns 409 | ERR_EMAIL_ALREADY_IN_ACCOUNT |
| 5 | ✅ Non-owner cannot create (403) | Permission check |
| 6 | ✅ Owner can list invitations | GET /invitations |
| 7 | ✅ Can filter by status | status=pending filter |
| 8 | ✅ Can search by email | search= filter |
| 9 | ✅ Invitations are tenant-isolated | Multi-tenant isolation |
| 10 | ✅ Owner can cancel via API | PATCH /invitations/{id}/cancel |
| 11 | ✅ Cancel accepted returns 422 | ERR_INVITATION_CANNOT_CANCEL |
| 12 | ✅ Owner can resend via API | POST /invitations/{id}/resend |
| 13 | ✅ Resend cancelled returns 422 | ERR_INVITATION_CANNOT_RESEND |
| 14 | ✅ Invitee can preview (public) | GET /invitations/preview/{token} |
| 15 | ✅ Preview invalid token returns 404 | ERR_INVITATION_NOT_FOUND |
| 16 | ✅ Invitee can accept via API (201) | POST /invitations/accept/{token} |
| 17 | ✅ Accept expired returns 410 | ERR_INVITATION_EXPIRED |
| 18 | ✅ Accept with missing password returns 422 | Validation |
| 19 | ✅ Accept assigns role to new user | Role on accept |
| 20 | ✅ Cannot reuse accepted invitation link | One-time use |

---

## ⚡ Setup & Run

```bash
# Run migrations
php artisan migrate

# Run Invitation tests
php artisan test tests/Unit/InvitationTest.php
php artisan test tests/Feature/InvitationApiTest.php

# Run all tests
php artisan test
```

---

## 🔗 Traceability

| From (SRS) | To (Implementation) |
|------------|---------------------|
| SRS 4.2.1 — FR-IAM-012 (دعوات انضمام) | InvitationService + InvitationController |
| FR-ORG-003 (دعوات أعضاء المنظمة) | Same service, tenant-scoped |
| AC: Owner يدعو مستخدم جديد مع دور | `createInvitation()` + role_id |
| AC: قبول الدعوة ينشئ المستخدم | `acceptInvitation()` → User + Role |
| AC: انتهاء الصلاحية يمنع القبول | TTL check + `ERR_INVITATION_EXPIRED` |
| AC: إعادة إرسال مسموحة فقط للمعلقة | `canResend()` check |
| AC: لا يجوز استخدام الرابط بعد القبول | Token + status check |
| FR-IAM-004 (Least Privilege) | New user via invitation gets only assigned role |
| FR-ORG-006 (Unified enforcement) | Same `assertCanInvite()` on UI/API |
