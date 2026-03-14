# FR-IAM-006 + FR-IAM-013 — سجل التدقيق الشامل (Comprehensive Audit Log)

## ملخص الميزة

| البند | التفاصيل |
|-------|---------|
| **المعرف** | FR-IAM-006, FR-IAM-013 |
| **العنوان** | سجل التدقيق الشامل + سجلات المنظمات |
| **الأولوية** | Must (Priority 1) |
| **الحالة** | ✅ مكتمل |
| **الاعتمادية** | FR-IAM-001, FR-IAM-002, FR-IAM-003, FR-IAM-010, FR-IAM-011 |
| **الاختبارات** | 50 اختبار (28 وحدة + 22 تكامل) |

---

## العمارة والتصميم

### المبدأ الأساسي: Append-Only + Immutable

```
┌─────────────────────────────────────────────────────────┐
│                    AuditService                         │
│  (Central entry point — all modules log through here)   │
├─────────────────────────────────────────────────────────┤
│  log()        → Core method (account, actor, action,    │
│                 category, severity, entity, values)      │
│  info()       → Informational event                     │
│  warning()    → Suspicious/failed event                 │
│  critical()   → Security-critical event                 │
│  search()     → Filtered + paginated query              │
│  entityTrail()→ All events for one entity               │
│  requestTrace()→ All events in one HTTP request         │
│  statistics() → Summary (by severity/category/action)   │
│  export()     → CSV/JSON export (self-audited)          │
└─────────────────────────────────────────────────────────┘
           ↓ writes to
┌─────────────────────────────────────────────────────────┐
│               AuditLog Model (Immutable)                │
│  ✗ update() → throws ERR_AUDIT_IMMUTABLE                │
│  ✗ delete() → throws ERR_AUDIT_IMMUTABLE                │
│  ✗ forceDelete() → throws ERR_AUDIT_IMMUTABLE           │
└─────────────────────────────────────────────────────────┘
```

### Request Correlation (Tracing)

```
Request → AuditCorrelation Middleware → Sets X-Request-ID
       → Multiple service calls each log with SAME request_id
       → Response includes X-Request-ID header
       → Later: GET /audit-logs/trace/{requestId} shows all events
```

### Severity Classification

| Severity | Usage | Examples |
|----------|-------|---------|
| `info` | Normal operations | user.added, role.created, auth.login |
| `warning` | Suspicious/failed actions | permission.denied, auth.login_failed, user.disabled |
| `critical` | Security-critical events | user.deleted, account.type_changed |

### Category Taxonomy

| Category | Scope |
|----------|-------|
| `auth` | Login, logout, password, 2FA |
| `users` | User CRUD, enable/disable |
| `roles` | Role CRUD, assign/revoke, permission denied |
| `account` | Account creation, updates, type changes |
| `invitation` | Invitation lifecycle |
| `kyc` | KYC submission, approval, rejection |
| `financial` | Financial data access |
| `settings` | Account/system settings |
| `export` | Data/audit exports |
| `system` | System-level events |

---

## الملفات الجديدة (10 ملفات)

```
shipping-gateway/
├── database/
│   ├── migrations/
│   │   └── 2026_02_12_000007_enhance_audit_logs_table.php    # Add severity, category, metadata, request_id
│   └── factories/
│       └── AuditLogFactory.php                                # Test factory
├── app/
│   ├── Models/
│   │   └── AuditLog.php                                       # Enhanced model (append-only enforcement)
│   ├── Services/
│   │   └── AuditService.php                                   # Centralized audit service (7 methods)
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   └── AuditLogController.php                         # 7 API endpoints
│   │   ├── Requests/
│   │   │   └── SearchAuditLogRequest.php                      # Validation for search/export
│   │   ├── Middleware/
│   │   │   └── AuditCorrelation.php                           # Request ID middleware
│   │   └── Resources/
│   │       └── AuditLogResource.php                           # Enhanced API resource
│   └── Exceptions/
│       └── BusinessException.php                              # +4 audit error codes
├── routes/
│   └── api.php                                                # +7 audit routes
└── tests/
    ├── Unit/AuditLogTest.php                                  # 28 unit tests
    └── Feature/AuditLogApiTest.php                            # 22 integration tests
```

---

## API Endpoints (7 endpoints)

### مصادقة مطلوبة (auth:sanctum + tenant)

| Method | Endpoint | الوصف | الصلاحية |
|--------|----------|-------|---------|
| GET | `/api/v1/audit-logs` | بحث وتصفية السجلات | `audit:view` أو Owner |
| GET | `/api/v1/audit-logs/{id}` | عرض سجل واحد | `audit:view` أو Owner |
| GET | `/api/v1/audit-logs/entity/{type}/{id}` | تاريخ كيان محدد | `audit:view` أو Owner |
| GET | `/api/v1/audit-logs/trace/{requestId}` | تتبع طلب واحد | `audit:view` أو Owner |
| GET | `/api/v1/audit-logs/statistics` | إحصائيات ملخصة | `audit:view` أو Owner |
| GET | `/api/v1/audit-logs/categories` | قائمة التصنيفات والإجراءات | `audit:view` أو Owner |
| POST | `/api/v1/audit-logs/export` | تصدير CSV/JSON | `audit:export` أو Owner |

### معاملات البحث (Search Parameters)

```
GET /api/v1/audit-logs?category=users&severity=critical&actor_id={uuid}
    &action=user.*&entity_type=User&from=2026-01-01&to=2026-02-01
    &ip_address=192.168.1.1&request_id={uuid}&search=keyword
    &sort_by=created_at&sort_dir=desc&per_page=25
```

### أمثلة الطلبات والردود

**بحث بالتصنيف:**
```json
GET /api/v1/audit-logs?category=roles&severity=warning

{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "action": "permission.denied",
      "severity": "warning",
      "category": "roles",
      "entity_type": "Role",
      "performer": { "id": "uuid", "name": "Ahmad", "email": "a@test.com" },
      "ip_address": "192.168.1.10",
      "request_id": "corr-uuid",
      "created_at": "2026-02-12T10:30:00.000Z"
    }
  ],
  "meta": { "current_page": 1, "total": 1, "last_page": 1, "per_page": 25 }
}
```

**تصدير JSON:**
```json
POST /api/v1/audit-logs/export
{ "format": "json", "category": "users", "from": "2026-01-01" }

{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "timestamp": "2026-02-12T10:00:00.000Z",
      "actor": "Owner Name",
      "actor_email": "owner@test.com",
      "action": "user.added",
      "severity": "info",
      "category": "users"
    }
  ],
  "meta": { "count": 5 }
}
```

---

## أكواد الأخطاء

| الكود | HTTP | الوصف |
|-------|------|-------|
| `ERR_AUDIT_IMMUTABLE` | 403 | محاولة تعديل/حذف سجل تدقيق |
| `ERR_LOG_ACCESS_DENIED` | 403 | لا تملك صلاحية الوصول لسجل التدقيق |
| `ERR_LOG_WRITE_FAIL` | 500 | فشل في كتابة سجل التدقيق |
| `ERR_EXPORT_FAILED` | 500 | فشل في تصدير سجل التدقيق |

---

## قواعد الأعمال (Business Rules)

| # | القاعدة |
|---|--------|
| 1 | سجلات التدقيق **غير قابلة للتعديل أو الحذف** (Append-Only) |
| 2 | كل سجل يحتوي: actor, action, target, time, IP, user-agent |
| 3 | كل طلب HTTP يحصل على correlation/request ID فريد |
| 4 | التصنيف بالشدة: info / warning / critical |
| 5 | التصنيف بالفئة: auth, users, roles, account, invitation, kyc, financial, settings, export, system |
| 6 | عملية التصدير نفسها تُسجَّل في سجل التدقيق (self-audit) |
| 7 | السجلات معزولة حسب الحساب (tenant isolation) |
| 8 | الحد الأقصى للتصدير: 10,000 سجل في المرة |
| 9 | البحث يدعم: التصنيف، الشدة، الفاعل، الإجراء (prefix)، الكيان، الفترة، IP، نص حر |
| 10 | يمكن تتبع كل أحداث طلب واحد عبر request_id |
| 11 | يمكن عرض تاريخ كيان محدد (entity trail) |
| 12 | Owner أو صلاحية `audit:view` للعرض، `audit:export` للتصدير |
| 13 | لا توجد endpoints للتعديل أو الحذف (HTTP level enforcement) |
| 14 | Action Registry يوثق جميع الإجراءات المعروفة مع تصنيفها |

---

## تغطية الاختبارات (50 اختبار)

### اختبارات الوحدة — 28 اختبار

| # | الاختبار | التحقق |
|---|---------|--------|
| 1 | it_creates_audit_log_entry | تسجيل حدث كامل |
| 2 | it_records_old_and_new_values | حفظ القيم القديمة/الجديدة |
| 3 | it_records_metadata | حفظ بيانات إضافية |
| 4 | it_records_ip_and_user_agent | تسجيل معلومات الطلب |
| 5 | it_supports_system_actions_without_user | أحداث النظام بدون فاعل |
| 6 | it_logs_info_severity | مستوى info |
| 7 | it_logs_warning_severity | مستوى warning |
| 8 | it_logs_critical_severity | مستوى critical |
| 9 | it_blocks_update_on_audit_log | منع التعديل |
| 10 | it_blocks_delete_on_audit_log | منع الحذف |
| 11 | it_blocks_force_delete_on_audit_log | منع الحذف القسري |
| 12 | update_attempt_is_logged_as_tamper | كود الخطأ للتلاعب |
| 13 | it_generates_consistent_request_id | ثبات معرف الطلب |
| 14 | it_accepts_custom_request_id | قبول معرف خارجي |
| 15 | it_resets_request_id_between_requests | إعادة ضبط بين الطلبات |
| 16 | it_searches_by_category | تصفية بالفئة |
| 17 | it_searches_by_severity | تصفية بالشدة |
| 18 | it_searches_by_actor | تصفية بالفاعل |
| 19 | it_searches_by_date_range | تصفية بالفترة |
| 20 | it_searches_by_entity | تصفية بالكيان |
| 21 | it_searches_by_action_prefix | تصفية بالإجراء (prefix) |
| 22 | it_returns_entity_trail | تاريخ كيان محدد |
| 23 | it_returns_request_trace | تتبع طلب واحد |
| 24 | it_returns_statistics | إحصائيات ملخصة |
| 25 | it_isolates_audit_logs_by_tenant | عزل حسب الحساب |
| 26 | it_exports_audit_logs | تصدير السجلات |
| 27 | it_logs_the_export_action_itself | تدقيق ذاتي للتصدير |
| 28 | action_registry_is_available | سجل الإجراءات |

### اختبارات التكامل — 22 اختبار

| # | الاختبار | HTTP | التحقق |
|---|---------|------|--------|
| 1 | owner_can_list_audit_logs | GET 200 | العرض مع الترقيم |
| 2 | can_filter_by_category | GET 200 | تصفية بالفئة |
| 3 | can_filter_by_severity | GET 200 | تصفية بالشدة |
| 4 | can_filter_by_actor | GET 200 | تصفية بالفاعل |
| 5 | can_filter_by_date_range | GET 200 | تصفية بالفترة |
| 6 | can_filter_by_action | GET 200 | تصفية بالإجراء |
| 7 | can_paginate_results | GET 200 | ترقيم الصفحات |
| 8 | can_sort_by_severity | GET 200 | الترتيب |
| 9 | owner_can_view_single_audit_log | GET 200 | عرض سجل واحد |
| 10 | cannot_view_audit_log_from_another_account | GET 404 | عزل الحسابات |
| 11 | owner_can_view_entity_trail | GET 200 | تاريخ كيان |
| 12 | owner_can_view_request_trace | GET 200 | تتبع طلب |
| 13 | owner_can_view_statistics | GET 200 | إحصائيات |
| 14 | can_list_audit_categories | GET 200 | قائمة التصنيفات |
| 15 | owner_can_export_as_json | POST 200 | تصدير JSON |
| 16 | owner_can_export_as_csv | POST 200 | تصدير CSV |
| 17 | export_with_filters | POST 200 | تصدير مع فلاتر |
| 18 | export_action_is_logged | POST 200 | تدقيق ذاتي |
| 19 | member_without_permission_cannot_view | GET 403 | منع غير مصرح |
| 20 | member_with_audit_view_permission_can_view | GET 200 | صلاحية العرض |
| 21 | member_without_export_permission_cannot_export | POST 403 | صلاحية التصدير |
| 22 | no_delete_or_patch_endpoints_exist | DEL/PATCH 404/405 | عدم وجود مسارات تعديل |

---

## مصفوفة التتبع (Traceability)

| متطلب SRS | التنفيذ | الاختبار |
|-----------|---------|---------|
| FR-IAM-006: تسجيل الأحداث الحساسة | AuditService.log() | Unit #1-5 |
| FR-IAM-006: actor, action, target, time, ip | AuditLog fillable fields | Unit #1,4 |
| FR-IAM-006: append-only غير قابل للتعديل | Model blocks update/delete | Unit #9-12, Feature #22 |
| FR-IAM-006: بحث وفلترة | AuditService.search() | Unit #16-21, Feature #1-8 |
| FR-IAM-006: التصدير | AuditService.export() + CSV/JSON | Unit #26-27, Feature #15-18 |
| FR-IAM-013: سياق المنظمة | category + metadata fields | Unit #3, Feature #2 |
| FR-IAM-013: تصفية المنظمة/الفريق | Search by category/entity | Feature #2,11 |
| FR-IAM-013: التصدير | Export with filters | Feature #17 |
| ERR_LOG_ACCESS_DENIED | Permission checks | Feature #19,21 |
| ERR_AUDIT_IMMUTABLE | Model enforcement | Unit #9-12, Feature #22 |

---

## تشغيل الاختبارات

```bash
# جميع اختبارات سجل التدقيق
php artisan test tests/Unit/AuditLogTest.php tests/Feature/AuditLogApiTest.php

# اختبارات الوحدة فقط
php artisan test tests/Unit/AuditLogTest.php

# اختبارات التكامل فقط
php artisan test tests/Feature/AuditLogApiTest.php

# جميع اختبارات المشروع
php artisan test
```

---

## استخدام الخدمة (للمطورين)

```php
use App\Services\AuditService;
use App\Models\AuditLog;

$audit = app(AuditService::class);

// تسجيل حدث بسيط
$audit->info($accountId, $userId, 'user.added', AuditLog::CATEGORY_USERS, 'User', $newUserId);

// تسجيل تغيير مع القيم القديمة والجديدة
$audit->info($accountId, $userId, 'user.updated', AuditLog::CATEGORY_USERS,
    'User', $targetId,
    ['name' => 'Old'], ['name' => 'New']
);

// تسجيل حدث أمني حرج
$audit->critical($accountId, $userId, 'user.deleted', AuditLog::CATEGORY_USERS,
    'User', $deletedId, null, null,
    ['reason' => 'Policy violation']
);

// تسجيل تحذير
$audit->warning($accountId, $userId, 'permission.denied', AuditLog::CATEGORY_ROLES,
    'Endpoint', null, null, null,
    ['attempted_action' => 'delete_user', 'ip' => '192.168.1.1']
);
```
