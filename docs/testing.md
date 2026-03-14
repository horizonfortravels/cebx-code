# Testing (MySQL)

The test suite is configured to run on MySQL by default via `phpunit.xml`.

Key rules:

- Run tests on MySQL, not sqlite.
- Run security and feature filters sequentially, not in parallel.
- Use `SEED_E2E_MATRIX=true` only when you need the full browser/API smoke identities.
- Internal admin browsing does not use a permanent `account_id`; it uses session-based tenant context selection.
- Internal platform access relies on `user_type=internal` plus internal RBAC. It does not rely on `account.type=admin`.

## Create the test database

Run this once in MySQL:

```sql
CREATE DATABASE IF NOT EXISTS cebx_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## Example `.env.testing`

```dotenv
APP_ENV=testing

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cebx_test
DB_USERNAME=root
DB_PASSWORD=

CACHE_DRIVER=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
```

## Run tests locally with MySQL

```bash
php artisan test
```

Or a focused run:

```bash
php artisan test --filter=TenantScopeCoverageTest
```

For smoke and authorization filters, keep the runs sequential:

```bash
php artisan test --filter=PermissionResolverTest
php artisan test --filter=TenantContextPermissionTest
php artisan test --filter=BolaIdorMatrixTest
```

## Seed E2E user matrix

Run the full E2E user/account matrix seeder explicitly:

```bash
SEED_E2E_MATRIX=true php artisan migrate:fresh --seed
```

This creates stable external and internal smoke users with known emails and `Password123!`.

## Create the internal super admin

Create or repair the internal super admin login with the canonical internal `super_admin` role:

```bash
php artisan app:create-internal-super-admin
```

Canonical internal admin login:

- Email: `internal.admin@example.test`
- Password: `Password123!`
- User type: `internal`
- Account: `NULL`
- Role: `super_admin`
- Account type dependency: none

## Test account list

See the stable browser/API test logins in [test-accounts.md](./test-accounts.md).

## Internal Tenant Context Selection (Web)

Internal browsing now works differently from external tenant browsing:

- External web users browse with their own linked tenant as before.
- Internal web users can open internal admin pages without `account_id`.
- When an internal user opens a tenant-bound admin page, the system asks them to choose a tenant context first.
- The selected tenant is stored in the web session as the current browsing context.
- This keeps internal browsing usable without permanently linking internal users to a tenant.

Typical flow:

```bash
php artisan app:create-internal-super-admin
```

1. Log in as `internal.admin@example.test`
2. Open `/admin`
3. If you move to a tenant-bound internal page, select the tenant in `/admin/tenant-context`
4. Continue browsing that tenant's admin pages under the selected session context

## Browser E2E smoke (Playwright, headless, non-parallel)

This repo uses Playwright browser smoke tests for login and portal navigation.

```bash
SEED_E2E_MATRIX=true php artisan migrate:fresh --seed
npx playwright install chromium
npx playwright test tests/e2e/login-and-navigation.smoke.spec.js --workers=1 --headed=false
```

Recommended browser smoke prep:

```bash
SEED_E2E_MATRIX=true php artisan migrate:fresh --seed
php artisan app:create-internal-super-admin
php artisan serve --host=127.0.0.1 --port=8000
```
