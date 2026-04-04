---
name: cbex-internal-routing-guardrails
description: Internal portal routing and entry-point guardrails for the CBEX Laravel shipping gateway. Use when changing admin or internal login redirects, wrong-portal handling, `AuthWebController`, `InternalAdminWebController`, internal route middleware, or permission-gated entry flows while preserving RBAC, tenant isolation, and internal versus external separation.
---

# Goal
Keep internal login, redirect, wrong-portal, and tenant-context entry flows safe, explicit, and role-aware without weakening access controls.

# When to Use
- Edit `app/Http/Controllers/Web/AuthWebController.php`.
- Edit `app/Http/Controllers/Web/InternalAdminWebController.php`.
- Change `/admin` versus `/internal` entry behavior.
- Adjust internal route middleware, wrong-portal guidance, or tenant-context-dependent redirects.

# When Not to Use
- Dashboard-only composition work with no redirect or entry-flow impact.
- Pure styling changes with no route or role consequence.
- External-only auth work unrelated to internal portal boundaries.

# Inputs
- `app/Http/Controllers/Web/AuthWebController.php`
- `app/Http/Controllers/Web/InternalAdminWebController.php`
- `app/Support/Internal/InternalControlPlane.php`
- Internal route definitions in `routes/web.php`
- The roles or personas affected by the entry-flow change

# Outputs
- Safe internal redirects and route-entry behavior.
- Friendly wrong-portal or inactive-user guidance where appropriate.
- A short explanation of the permission, user-type, and tenant-context assumptions behind the change.

# Instructions
1. Start by tracing the full entry path: login page, login submission, post-login redirect, and target route middleware.
2. Preserve the internal split:
   - `super_admin` should land on the admin control plane route from `InternalControlPlane::landingRouteName()`
   - lower-privilege internal roles should land on `/internal`
3. Keep B2C and B2B rules explicit:
   - `B2C` is individual external only
   - `B2B` is organization external only
   - internal users are separate from both
4. When a user hits the wrong portal, return clear branded guidance rather than a silent bounce or raw exception.
5. Treat selected-account context as session-scoped internal browsing state. Do not rewrite it into permanent ownership or identity rules.
6. Keep `userType`, `permission`, and `internalSurface` middleware aligned with the redirect and entry logic.
7. Prefer explicit HTML guidance for inactive, disabled, suspended, wrong-portal, or missing-tenant-context cases when those states are browser-visible.

# Guardrails
- Do not weaken security or bypass permission checks to smooth UX.
- Do not merge internal and external auth flows into a shared fuzzy path.
- Do not assume internal users should inherit external tenant context automatically.
- Do not create route churn unless the change is necessary for the entry-flow problem being solved.

# Verification
- Test login and redirect behavior for internal, B2C, and B2B users.
- Verify wrong-portal submissions show clear guidance.
- Verify logout returns users to the correct login surface.
- Confirm tenant-context-required internal flows still behave correctly with and without a selected account.
