# Test Accounts

This file lists the stable internal and external accounts dedicated to browser and API smoke testing.

## Preparation

Seed the E2E matrix accounts:

```bash
SEED_E2E_MATRIX=true php artisan migrate:fresh --seed
```

Create or repair the standalone internal super admin account:

```bash
php artisan app:create-internal-super-admin
```

## Default Passwords

- E2E matrix users: `Password123!`
- Standalone internal super admin: `Password123!`

## Demo Users

These come from the standard demo seed path and are useful for lightweight manual checks. They are not the canonical RBAC smoke matrix.

| Email | Password | User Type | Account | Account Type | Status | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| `admin@system.sa` | `admin` | `internal` | `NULL` | `NULL` | `active` | Demo internal login only; not the canonical E2E super admin |
| `sultan@techco.sa` | `password` | `external` | `Advanced Technology Company` | `organization` | `active` | Demo organization user |
| `hind@techco.sa` | `password` | `external` | `Advanced Technology Company` | `organization` | `active` | Demo organization user |
| `majed@techco.sa` | `password` | `external` | `Advanced Technology Company` | `organization` | `active` | Demo organization user |
| `lama@techco.sa` | `password` | `external` | `Advanced Technology Company` | `organization` | `inactive` | Demo inactive user |
| `mohammed@example.sa` | `password` | `external` | `Mohammed Al-Omari` | `individual` | `active` | Demo individual user |

## External Accounts

| Email | Password | User Type | Account | Account Type | Status | Role / Model | Primary Use |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `e2e.a.individual@example.test` | `Password123!` | `external` | E2E Account A | `individual` | `active` | single-user individual account | Canonical B2C smoke coverage |
| `e2e.b.individual@example.test` | `Password123!` | `external` | E2E Account B | `individual` | `active` | single-user individual account | Cross-tenant isolation target for individual accounts |
| `e2e.c.organization_owner@example.test` | `Password123!` | `external` | E2E Account C | `organization` | `active` | `organization_owner` | Full B2B same-tenant success coverage |
| `e2e.c.organization_admin@example.test` | `Password123!` | `external` | E2E Account C | `organization` | `active` | `organization_admin` | Merchant API tools and delegated admin coverage |
| `e2e.c.staff@example.test` | `Password123!` | `external` | E2E Account C | `organization` | `active` | `staff` | Limited-access and denial scenarios |
| `e2e.c.suspended@example.test` | `Password123!` | `external` | E2E Account C | `organization` | `suspended` | `staff` | Negative login and suspension checks |
| `e2e.c.disabled@example.test` | `Password123!` | `external` | E2E Account C | `organization` | `disabled` | `staff` | Negative login and disabled-user checks |
| `e2e.d.organization_owner@example.test` | `Password123!` | `external` | E2E Account D | `organization` | `active` | `organization_owner` | Cross-tenant isolation target for organization accounts |
| `e2e.d.organization_admin@example.test` | `Password123!` | `external` | E2E Account D | `organization` | `active` | `organization_admin` | Secondary organization admin scenarios |
| `e2e.d.staff@example.test` | `Password123!` | `external` | E2E Account D | `organization` | `active` | `staff` | Organization read-limited secondary tenant |

## Internal Accounts

| Email | Password | User Type | Account | Status | Role | Primary Use |
| --- | --- | --- | --- | --- | --- | --- |
| `e2e.internal.super_admin@example.test` | `Password123!` | `internal` | `NULL` | `active` | `super_admin` | Full internal admin smoke coverage with tenant-context selection |
| `e2e.internal.support@example.test` | `Password123!` | `internal` | `NULL` | `active` | `e2e_internal_support` | Support ticket read/manage with tenant-context selection |
| `e2e.internal.ops_readonly@example.test` | `Password123!` | `internal` | `NULL` | `active` | `e2e_internal_ops_readonly` | Internal read-only analytics and reports |
| `internal.admin@example.test` | `Password123!` | `internal` | `NULL` | `active` | `super_admin` | Standalone platform admin created by Artisan command |

## Role Notes

- `individual`: account model, not a team persona. The account supports exactly one external user and cannot access user, role, or invitation management.
- `organization_owner`: highest canonical external role. Used for full same-tenant success paths across organization resources and merchant platform API access.
- `organization_admin`: delegated organization operator. Can manage day-to-day organization operations and merchant API surfaces, but is not the top ownership role.
- `staff`: reduced organization role used for denial and limited-access scenarios.
- `super_admin`: highest internal platform role. Grants internal admin access and tenant-context selection through internal RBAC only.
- `e2e_internal_support`: internal support permissions with tenant-context selection.
- `e2e_internal_ops_readonly`: internal read-only role for analytics and reports.

## Important Notes

- These accounts are for testing only.
- Internal accounts must remain `user_type=internal` with `account_id=NULL`.
- No account in this file relies on `account.type=admin`.
- Internal platform administration is represented only by internal users plus RBAC role assignment.
- Merchant API keys and webhooks, where exposed externally, represent access to the platform API only. They do not represent carrier ownership or carrier configuration.
- External cross-tenant checks should return `404`, not `403`.
- Same-tenant requests without the required permission should return `403`.
