# CEBX Shipping Gateway

CEBX Shipping Gateway is a Laravel 11 logistics platform that combines customer-facing portals, internal operations tooling, and a versioned API in one codebase.

It includes:

- public shipment tracking
- external B2C and B2B portals
- an internal operations and admin surface
- authenticated API workflows for accounts, users, shipments, billing, compliance, reporting, and integrations

## Core Surfaces

| Surface | Audience | Main paths |
| --- | --- | --- |
| Public tracking | End customers | `/track/{token}`, `/api/v1/track/{token}` |
| B2C portal | External `individual` accounts | `/b2c/*` |
| B2B portal | External `organization` accounts | `/b2b/*` |
| External workspace | Authenticated external users on the legacy Blade surface | `/`, `/shipments`, `/orders`, `/wallet`, `/reports` |
| Internal platform | Internal staff and admins | `/internal/*`, `/admin/*` |

## Tech Stack

- PHP 8.2+ and Laravel 11
- Blade-based server-rendered UI
- Laravel Sanctum for API auth
- Spatie packages for permissions, query building, data objects, activity logs, and media handling
- MySQL or PostgreSQL for application data
- Redis support for cache and queue-backed workflows
- PHPUnit/Pest for PHP testing
- Playwright for browser smoke and workflow coverage

## Repository Layout

| Path | Purpose |
| --- | --- |
| `app/Http/Controllers/Api/V1` | Versioned API controllers |
| `app/Http/Controllers/Web` | Web, portal, and internal controllers |
| `app/Models` | Domain models for accounts, shipments, billing, compliance, and supporting entities |
| `bootstrap/app.php` | Laravel 11 bootstrap plus route registration for the extra portal route files |
| `routes/api.php` | Public API entry points and shared route wiring |
| `routes/api_external.php` | Authenticated external API routes |
| `routes/api_internal.php` | Authenticated internal API routes |
| `routes/web.php` | Main web routes, internal surfaces, and legacy external workspace |
| `routes/web_b2c.php` | B2C portal routes |
| `routes/web_b2b.php` | B2B portal routes |
| `resources/views` | Blade layouts, pages, components, and portal views |
| `database/seeders` | Demo data, permissions, E2E matrix accounts, and system seeders |
| `tests` | Feature, unit, security, web, tenancy, and Playwright coverage |
| `docs` | Testing notes, route references, workflow docs, and browser reports |

## Local Setup

### Prerequisites

- PHP 8.2 or newer
- Composer
- A database server
- Node.js only if you plan to run Playwright tests

### Install

```bash
composer install
```

Create a local `.env` file before booting the app. The repository now includes a committed `.env.example` you can copy and adjust for your environment.

Then generate the app key, run migrations, and seed demo data:

```bash
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

The app will be available at `http://127.0.0.1:8000` by default.

## Seeded Accounts And Portals

The codebase supports three main browser entry patterns:

- `/b2c/login` for external individual-account users
- `/b2b/login` for external organization-account users
- `/admin/login` and `/internal/*` for internal staff

For test and demo credentials, use the checked-in docs instead of hardcoding them into this README:

- `docs/test-accounts.md`
- `docs/ROUTES-BY-USER-TYPE.md`

If you need the canonical internal admin login for smoke coverage, create or repair it with:

```bash
php artisan app:create-internal-super-admin
```

## Testing

Run the PHP test suite:

```bash
php artisan test
```

Run formatting:

```bash
composer lint
```

The default PHPUnit configuration uses MySQL and expects a database named `cebx_test`. See `docs/testing.md` for the full local testing conventions, `.env.testing` example, and sequential security-filter guidance.

### Browser E2E

Install Playwright once:

```bash
npm install
npx playwright install chromium
```

Seed the stable E2E identity matrix, start the app, then run the smoke suite:

```bash
SEED_E2E_MATRIX=true php artisan migrate:fresh --seed
php artisan app:create-internal-super-admin
php artisan serve --host=127.0.0.1 --port=8000
npm run e2e
```

The configured npm script runs `tests/e2e/login-and-navigation.smoke.spec.js` with a single worker.

## Operational Notes

- Internal platform users are represented by `user_type=internal` and use session-based tenant context selection for tenant-bound admin pages.
- B2C is reserved for external `individual` accounts.
- B2B is reserved for external `organization` accounts.
- External developer-facing API keys and webhooks represent platform API access. Carrier integrations remain platform-managed.

## Documentation

Start here for deeper project references:

- `docs/testing.md`
- `docs/test-accounts.md`
- `docs/ROUTES-BY-USER-TYPE.md`
- `docs/canonical_shipment_workflow.md`
- `docs/workflow_implementation_phases.md`
- `docs/workflow_page_mapping.md`

Feature-specific requirement notes also exist in the repository root and `docs/` as `FR-*.md` files.

## Server Deployment

### Transfer-And-Run Workspace Checklist

If you plan to move the workspace to a server and run it there without downloading application dependencies again:

- keep `vendor/` with the transferred files
- keep `.env` or prepare it from `.env.example`
- provide a MySQL database and Redis instance, or use the included Docker Compose stack
- run migrations on the target environment

The checked-in public assets already live under `public/css` and `public/js`, so there is no frontend build step required for production web pages.

### Docker Compose

The repository now includes a server-ready `Dockerfile`, `docker-compose.yml`, and `docker/entrypoint.sh`.

Quick start:

```bash
cp .env.example .env
docker compose up --build -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan preflight:check
```

Notes:

- the `app` container serves HTTP on port `8000`
- the `queue` container runs `php artisan queue:work`
- MySQL and Redis are included in the Compose stack
- set `RUN_MIGRATIONS=true` in the environment if you want the app container to auto-run migrations on start

### Native PHP Server

If you are not using Docker on the server:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan preflight:check
```
