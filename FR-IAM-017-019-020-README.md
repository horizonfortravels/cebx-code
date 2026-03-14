# FR-IAM-017 + FR-IAM-019 + FR-IAM-020 — المحفظة والفوترة وإخفاء بيانات الدفع

## ملخص الميزة

| البند | التفاصيل |
|-------|---------|
| **المعرفات** | FR-IAM-017, FR-IAM-019, FR-IAM-020 |
| **العناوين** | صلاحيات المحفظة والفوترة / RBAC للمحفظة / إخفاء بيانات البطاقة للحسابات المعطلة |
| **الأولوية** | Must (017: Should, 019: Must, 020: Must) |
| **الحالة** | ✅ مكتمل |
| **الاعتمادية** | FR-IAM-001, FR-IAM-003 (RBAC), FR-IAM-006 (Audit), FR-IAM-012 (Financial Masking) |
| **الاختبارات** | 46 اختبار (26 وحدة + 20 تكامل) |

---

## العمارة

### نموذج البيانات

```
Account (1) ──→ (1) Wallet ──→ (N) WalletLedgerEntry (append-only)
    │
    └──→ (N) PaymentMethod (cards, bank transfers)
```

### صلاحيات المحفظة والفوترة (6 صلاحيات منفصلة)

```
┌───────────────────────┬────────────────────────────────────────┐
│ الصلاحية               │ الوصف                                  │
├───────────────────────┼────────────────────────────────────────┤
│ wallet:balance        │ عرض رصيد المحفظة                       │
│ wallet:ledger         │ عرض كشف الحساب (سجل المعاملات)          │
│ wallet:topup          │ شحن الرصيد                             │
│ wallet:configure      │ إعدادات المحفظة (حد التنبيه)            │
│ billing:view          │ عرض وسائل الدفع                        │
│ billing:manage        │ إضافة/إزالة وسائل الدفع                 │
└───────────────────────┴────────────────────────────────────────┘
```

### مصفوفة الصلاحيات

```
┌─────────────────────┬──────┬─────────┬──────────┬─────────┐
│ الإجراء              │ Owner │ Finance │ Viewer   │ Member  │
├─────────────────────┼──────┼─────────┼──────────┼─────────┤
│ عرض المحفظة (ملخص)  │ Full │ Full    │ Balance  │ Masked  │
│ عرض كشف الحساب      │ ✅    │ ✅       │ ❌       │ ❌ 403  │
│ شحن الرصيد          │ ✅    │ ✅       │ ❌       │ ❌ 403  │
│ إعداد حد التنبيه    │ ✅    │ ✅       │ ❌       │ ❌ 403  │
│ عرض وسائل الدفع     │ ✅    │ ✅       │ ❌       │ ❌ 403  │
│ إدارة وسائل الدفع   │ ✅    │ ✅       │ ❌       │ ❌ 403  │
└─────────────────────┴──────┴─────────┴──────────┴─────────┘
```

---

## FR-IAM-020: إخفاء بيانات الدفع

### دورة الحياة

```
Account Active ──→ [Suspend/Close] ──→ maskPaymentDataForDisabledAccount()
  ✅ Cards visible                       ❌ Cards masked (••••)
  ✅ Can add methods                     ❌ Cannot add methods
  ✅ Payments operational                ❌ Payments blocked

Account Suspended ──→ [Reactivate] ──→ restorePaymentDataForReactivatedAccount()
  ❌ is_masked_override = true            ✅ is_masked_override = false
  ❌ is_active = false                    ⚠️ is_active remains false (re-validate!)
```

### ما يراه المستخدم عند تعطيل الحساب

```json
{
  "provider": "••••",
  "last_four": "••••",
  "expiry": "••/••••",
  "cardholder": "•••••••••",
  "is_masked": true,
  "mask_reason": "account_disabled",
  "is_active": false
}
```

---

## API Endpoints (8 endpoints)

### المحفظة

| Method | Endpoint | الوصف | الصلاحية |
|--------|----------|-------|---------|
| GET | `/api/v1/wallet` | عرض المحفظة | أي مستخدم (مستوى التفصيل حسب الصلاحية) |
| GET | `/api/v1/wallet/ledger` | كشف الحساب | `wallet:ledger` أو Owner |
| POST | `/api/v1/wallet/topup` | شحن رصيد | `wallet:topup` أو Owner |
| PUT | `/api/v1/wallet/threshold` | حد التنبيه | `wallet:configure` أو Owner |
| GET | `/api/v1/wallet/permissions` | قائمة الصلاحيات المتاحة | أي مستخدم |

### الفوترة

| Method | Endpoint | الوصف | الصلاحية |
|--------|----------|-------|---------|
| GET | `/api/v1/billing/methods` | قائمة وسائل الدفع | `billing:view` أو Owner |
| POST | `/api/v1/billing/methods` | إضافة وسيلة دفع | `billing:manage` أو Owner |
| DELETE | `/api/v1/billing/methods/{id}` | إزالة وسيلة دفع | `billing:manage` أو Owner |

---

## الملفات (12 ملف)

```
shipping-gateway/
├── database/
│   ├── migrations/
│   │   └── 2026_02_12_000011_create_wallet_billing_tables.php
│   └── factories/
│       ├── WalletFactory.php
│       └── PaymentMethodFactory.php
├── app/
│   ├── Models/
│   │   ├── Wallet.php              # ★ New
│   │   ├── WalletLedgerEntry.php   # ★ New (append-only)
│   │   ├── PaymentMethod.php       # ★ New (FR-IAM-020 masking)
│   │   └── Account.php             # Updated: +wallet(), +paymentMethods()
│   ├── Services/
│   │   ├── WalletBillingService.php  # ★ New: 6 wallet + billing permissions
│   │   └── AuditService.php         # +12 wallet/billing audit actions
│   ├── Http/Controllers/Api/V1/
│   │   └── WalletBillingController.php  # 8 endpoints
│   └── Exceptions/
│       └── BusinessException.php     # +5 error codes
├── routes/api.php                    # +8 routes
└── tests/
    ├── Unit/WalletBillingTest.php    # 26 tests
    └── Feature/WalletBillingApiTest.php  # 20 tests
```

---

## أكواد الأخطاء

| الكود | HTTP | الوصف |
|-------|------|-------|
| `ERR_INVALID_AMOUNT` | 422 | المبلغ غير صالح |
| `ERR_WALLET_FROZEN` | 422 | المحفظة مجمدة |
| `ERR_INSUFFICIENT_BALANCE` | 422 | رصيد غير كافٍ |
| `ERR_ACCOUNT_DISABLED` | 422 | الحساب معطل |
| `ERR_DATA_RESTORE_FAIL` | 500 | فشل استرجاع البيانات |

---

## تشغيل الاختبارات

```bash
php artisan test tests/Unit/WalletBillingTest.php tests/Feature/WalletBillingApiTest.php
```
