# CBEX Internal Portal Agent Guide

## Repo Architecture Summary
- Laravel 11 application with server-rendered Blade views and PHP 8.2.
- Shared app shell lives in `resources/views/layouts/app.blade.php`; shared styling lives in `public/css/app.css`.
- Internal portal is split between `/internal/*` and `/admin/*` routes in `routes/web.php`, with access shaped by `App\Support\Internal\InternalControlPlane`.
- Internal landing/dashboard logic starts in `app/Http/Controllers/Web/InternalAdminWebController.php`; login and wrong-portal routing starts in `app/Http/Controllers/Web/AuthWebController.php`.
- Internal dashboards and read centers already have strong regression coverage in `tests/Feature/Web/*Internal*.php` and `tests/e2e/internal-*.spec.js`.

## Internal Portal Files
- `resources/views/layouts/app.blade.php`
- `resources/views/pages/admin/index.blade.php`
- `resources/views/pages/admin/internal-home.blade.php`
- `resources/views/pages/admin/*.blade.php`
- `resources/views/components/card.blade.php`
- `resources/views/components/stat-card.blade.php`
- `resources/views/components/timeline.blade.php`
- `app/Http/Controllers/Web/InternalAdminWebController.php`
- `app/Http/Controllers/Web/AuthWebController.php`
- `app/Support/Internal/InternalControlPlane.php`
- `routes/web.php`
- `public/css/app.css`
- `docs/browser_ux_review_round2.md`
- `docs/browser_ux_final_verdict.md`

## Business Rules
- The platform contracts with carriers; external users do not own carrier integrations.
- `B2C` means an individual external account only.
- `B2B` means an organization external account only.
- Internal/admin is a separate internal portal, not an external account variant.
- Preserve RBAC, tenant isolation, and internal/external separation in every change.
- Do not weaken security to improve UX.
- Do not edit historical migrations; use forward-only migrations if schema work is needed later.
- For this redesign initiative, do not redesign B2C or B2B navigation except for safe shared CSS tokens/components.
- Internal dashboards must use real data from existing models and tables. No fake numbers, no placeholder charts, no lorem ipsum.
- Prefer reusable Blade partials/components and reusable CSS classes over repeated inline styles.
- Keep Arabic readable, product-facing, and RTL-correct. Avoid technical shorthand as visible navigation labels.
- Do not introduce a SPA, Tailwind migration, or heavy frontend build chain.

## Commands
- `php artisan test`
- `vendor/bin/pint`

## Task Rules For Internal UI Work
- Start by mapping the route, controller, Blade view, permission middleware, and `internalSurface` gate before editing UI.
- Treat `/admin` as the super-admin control plane and `/internal` as the guided lower-privilege internal landing.
- Keep selected-account context session-based and explicit; never imply permanent tenant ownership for internal users.
- Reuse the existing Blade component layer and shared CSS before adding one-off markup.
- Limit visual redesign scope to the internal portal unless a shared token/component change is clearly safe for external surfaces.
- Use existing report/read services when possible for metrics; keep queries tenant-aware where the selected account matters.
- Preserve branded HTML deny/error experiences and wrong-portal guidance; do not allow raw framework errors into browser-facing flows.

## Definition Of Done
- Real data only.
- Arabic and RTL remain intact.
- Permission gates remain preserved.
- Tenant context behavior remains preserved.
- Relevant tests are updated and run.
