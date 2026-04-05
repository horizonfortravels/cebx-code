---
name: cbex-external-portal-ia
description: Information architecture and navigation planning for the CBEX external portals. Use when splitting B2C versus B2B structure, grouping navigation, defining breadcrumbs, deciding role-aware external sections, or auditing portal confusion across `routes/web_b2c.php`, `routes/web_b2b.php`, shared layouts, and external Blade views.
---

# Goal
Define a clear external information architecture that keeps B2C and B2B distinct while still feeling like one coherent CBEX product family.

# When to Use
- Reorganize external navigation or breadcrumbs.
- Decide whether a page belongs in B2C, B2B, public tracking, or shared auth.
- Audit portal confusion, wrong-portal behavior, or overlapping menu labels.
- Plan role-aware B2B navigation without exposing internal concepts.

# When Not to Use
- Pure CSS polish with no IA decision.
- Backend-only permission or policy work.
- Internal admin navigation redesign.

# Inputs
- Active route files and route names.
- Current menu structure in shared layouts and portal views.
- Account-type and permission rules for the affected pages.

# Outputs
- Portal-specific navigation groups and page placement decisions.
- Breadcrumb and page-title rules.
- Notes on active-versus-legacy surfaces and any route-boundary risks.

# Instructions
1. Confirm the active route and Blade surface first. Treat `resources/views/pages/portal/*` as the likely current external UI, but verify because older `resources/views/b2c/*`, `resources/views/b2b/*`, and `legacyExternalSurface` routes still exist.
2. Use one shared external shell language, then split the IA by portal intent:
   - B2C for one-person tasks such as dashboard, shipments, tracking, wallet, addresses, support, and settings.
   - B2B for organization workflows such as dashboard, shipments, addresses, orders, wallet, reports, users, roles, and developer tools.
3. Keep breadcrumbs task-based and literal. Favor clear progress and object context over marketing phrasing.
4. Let B2B become role-aware only where permissions already shape visibility, especially in developer tools.
5. Keep public tracking and login surfaces outside the signed-in portal IA, but consistent with it visually and linguistically.
6. Prefer Arabic labels that describe the user's job to be done, not internal schema names or engineering shorthand.
7. When two portals need the same concept, keep the shell and terminology related while adjusting density and scope to the account type.

# Guardrails
- Do not expose B2B concepts inside B2C navigation or breadcrumbs.
- Do not expose internal ops, admin, or carrier-ownership concepts to external users.
- Do not assume older views are still active just because they exist in the repo.
- Do not solve IA confusion with silent redirects when explicit wrong-portal guidance is safer.

# Verification
- Check that every visible navigation item maps to a real, intended route.
- Check that B2C and B2B page groups are easy to distinguish.
- Check that breadcrumbs reflect user tasks and current object context.
- Check that permission-aware B2B sections remain hidden or denied when not allowed.
