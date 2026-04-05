# CBEX External Portal Agent Guide

## Repo Architecture Summary
- Laravel 11 application with server-rendered Blade views and PHP 8.2.
- Shared shells live in `resources/views/layouts/app.blade.php` and `resources/views/layouts/auth.blade.php`; shared styling lives in `public/css/app.css`.
- External entry, portal chooser, login, logout, password reset, and wrong-portal messaging start in `app/Http/Controllers/Web/AuthWebController.php` and `routes/web.php`.
- Current external portal routes are split between `routes/web_b2c.php` and `routes/web_b2b.php`, while `routes/web.php` still contains public tracking plus legacy external surfaces behind `legacyExternalSurface`.
- External dashboard, navigation, and shipment-journey logic primarily live in `app/Http/Controllers/Web/PortalWorkspaceController.php` and `app/Http/Controllers/Web/ShipmentDocumentWebController.php`.
- Public shipment tracking starts in `app/Http/Controllers/Web/PublicTrackingPortalController.php`.
- Active external views are mainly under `resources/views/pages/auth`, `resources/views/pages/portal/b2c`, `resources/views/pages/portal/b2b`, `resources/views/pages/portal/shipments`, and shared components under `resources/views/components`.
- Older external views still exist under `resources/views/b2c` and `resources/views/b2b`; verify the active route/view pair before editing because some legacy surfaces may still be present.
- Relevant browser and feature coverage already exists in `tests/Feature/Web/*`, especially shipment, tracking, browser guidance, and external support tests, plus `tests/e2e/login-and-navigation.smoke.spec.js` and `tests/e2e/public-tracking.phase4e.spec.js`.

## External Portal Files
- `routes/web.php`
- `routes/web_b2c.php`
- `routes/web_b2b.php`
- `app/Http/Controllers/Web/AuthWebController.php`
- `app/Http/Controllers/Web/PortalWorkspaceController.php`
- `app/Http/Controllers/Web/ShipmentDocumentWebController.php`
- `app/Http/Controllers/Web/PublicTrackingPortalController.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/auth.blade.php`
- `resources/views/pages/auth/*.blade.php`
- `resources/views/pages/portal/b2c/*.blade.php`
- `resources/views/pages/portal/b2b/*.blade.php`
- `resources/views/pages/portal/b2b/developer/*.blade.php`
- `resources/views/pages/portal/shipments/*.blade.php`
- `resources/views/errors/403.blade.php`
- `resources/views/errors/500.blade.php`
- `resources/views/components/card.blade.php`
- `resources/views/components/page-header.blade.php`
- `resources/views/components/stat-card.blade.php`
- `resources/views/components/timeline.blade.php`
- `public/css/app.css`
- `tests/Feature/Web/*.php`
- `tests/e2e/login-and-navigation.smoke.spec.js`
- `tests/e2e/public-tracking.phase4e.spec.js`

## Business Rules
- The platform itself contracts with carriers and owns carrier integrations.
- External users do not own carrier integrations.
- `B2C` means an individual external account only.
- `B2B` means an organization external account only.
- Internal/admin is a separate internal portal, not an external account variant.
- Merchant-facing developer pages in B2B are platform API and integration tools for the organization, not carrier ownership or carrier configuration.
- Preserve RBAC, tenant isolation, wrong-portal guidance, and internal/external separation in every change.
- Do not weaken security to improve UX.
- Do not edit historical migrations; use forward-only migrations if schema work is needed later.
- For this redesign initiative, do not redesign internal admin flows except safe shared CSS or component improvements that do not regress internal UX.
- Use real data only for dashboards and summaries. No fake numbers, no placeholder charts, no lorem ipsum.
- Prefer reusable Blade partials, Blade components, and CSS classes over repeated inline styles.
- Keep Arabic readable, product-facing, RTL-correct, and demo-ready.
- Do not introduce a SPA, Tailwind migration, or heavy frontend build chain.
- Do not expose B2B concepts inside B2C navigation, dashboards, or empty states.
- Do not expose internal ops or admin concepts to external users.

## Commands
- `php artisan test`
- `vendor/bin/pint`

## Task Rules For External UI Work
- Start by mapping the real route, controller, Blade view, middleware, account-type gate, and permission boundary before editing UI.
- Verify the actual active route and view before editing because legacy external routes and views still exist. Prefer the active B2C and B2B route files plus `resources/views/pages/portal/*` unless the live surface clearly still uses a legacy view.
- Prefer one shared external design language across shells, tokens, layout rhythm, and motion, then vary IA, density, and messaging between B2C and B2B.
- Treat `/admin` and `/internal` as internal-only. External work may touch shared auth, shared CSS, or branded error pages, but must not redesign internal admin flows.
- Keep wrong-portal guidance explicit and branded. Prefer informative HTML guidance over silent redirects or raw framework error pages.
- Reuse the existing Blade component layer and shared CSS before adding one-off markup.
- Keep tenant and account context explicit. Never imply that B2C users can access organization tools or that B2B users can access internal platform settings.
- Use existing account-scoped queries, models, and services for dashboards and summaries. Keep every metric and action grounded in real data.
- For B2B developer surfaces, frame them as organization-facing platform integration tools with permission-aware access, not as carrier ownership or carrier configuration panels.
- When changing auth, navigation, shipment workflow, public tracking, or browser-facing failure states, check the most relevant feature and end-to-end coverage paths.
- Preserve branded deny, wrong-portal, and safe error experiences. Do not allow raw framework exceptions, stack traces, or debug pages into browser-facing flows.

## Definition Of Done
- Real data only.
- Arabic and RTL remain intact.
- Wrong-portal guidance remains preserved.
- No raw debug or framework error pages appear in browser-facing flows.
- B2C and B2B separation remains correct.
- Relevant tests are updated and run where the change warrants it.
