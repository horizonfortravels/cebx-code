---
name: cbex-internal-ops-dashboard
description: Role-aware internal landing and dashboard composition for the CBEX internal portal. Use when designing or restructuring `admin.index`, `internal.home`, and related internal dashboard views for `super_admin`, `support`, `ops_readonly`, and carrier or integration oriented internal roles in this Laravel Blade codebase.
---

# Goal
Build internal landing pages and dashboards that guide each internal role toward real platform work without exposing unauthorized surfaces or thin placeholder layouts.

# When to Use
- Redesign `resources/views/pages/admin/index.blade.php` or `resources/views/pages/admin/internal-home.blade.php`.
- Add or rearrange internal dashboard sections, next actions, summaries, or role-aware empty states.
- Clarify what `super_admin`, `support`, `ops_readonly`, and carrier-focused internal roles should see first.

# When Not to Use
- Deep metrics/query work that is primarily about KPI calculation rather than dashboard composition.
- Sidebar-only restructuring with no dashboard content impact.
- External B2C or B2B dashboard work.

# Inputs
- `app/Http/Controllers/Web/InternalAdminWebController.php`
- `app/Support/Internal/InternalControlPlane.php`
- Relevant internal Blade pages under `resources/views/pages/admin/`
- Known UX findings from `docs/browser_ux_review_round2.md` and `docs/browser_ux_final_verdict.md`

# Outputs
- Internal landing/dashboard layouts that match the role’s real responsibilities.
- Clear primary actions, scoped summaries, and role-appropriate explanations of what is available now.
- A short rationale for why a card or action appears on one role’s dashboard and not another.

# Instructions
1. Treat `/admin` as the broad internal control plane and `/internal` as the guided landing surface for roles that do not need the full admin dashboard.
2. Start from the controller inputs already provided: `roleProfile`, `capabilities`, `surfaces`, selected-account state, and `InternalControlPlane::landingRouteName()`.
3. Compose each dashboard around actual internal workflows:
   - `super_admin`: platform overview, tenant selection, broad control surfaces, account-level jump points.
   - `support`: ticket handling, customer-account lookup, clear next steps into tenant context when needed.
   - `ops_readonly`: operational health, reports, read-only monitoring, exception visibility.
   - Carrier/integration-focused roles: carriers, integrations, shipment-document or SMTP-adjacent tasks as allowed.
4. Prefer a few strong sections with clear Arabic headings and next actions over many weak placeholder cards.
5. Explain why a role can proceed or why a step is unavailable. Lower-privilege users should not land on a blank shell or a disguised deny state.
6. Reuse existing Blade components and shared CSS helpers so the dashboard system stays maintainable.

# Guardrails
- Do not surface cards or actions that the role cannot actually open.
- Do not merge internal, B2C, and B2B mental models into a single dashboard.
- Do not use fake actions, fake data, or filler copy.
- Do not weaken security or tenant context rules to make a role’s landing page feel fuller.

# Verification
- Check the landing route and visible cards for each internal role.
- Verify dashboards behave sensibly with and without a selected account.
- Confirm every primary CTA leads to a route the current role can actually use.
- Compare the result against the known internal UX findings so previously reported issues stay fixed.
