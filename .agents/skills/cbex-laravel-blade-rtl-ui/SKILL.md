---
name: cbex-laravel-blade-rtl-ui
description: Blade and public-CSS UI work for this Laravel 11 shipping gateway. Use when editing server-rendered Blade views, shared layouts, reusable Blade components, Arabic-first copy, RTL behavior, or `public/css/app.css` for the external B2C and B2B portals, shipment journeys, login or error pages, or safe shared foundations without introducing a new frontend stack, SPA, Tailwind migration, or heavy build tooling.
---

# Goal
Implement external portal UI changes in the existing Laravel Blade plus public CSS stack while keeping Arabic-first, RTL-first behavior intact.

# When to Use
- Edit `resources/views/layouts/app.blade.php`, `resources/views/layouts/auth.blade.php`, or external/auth/error Blade pages.
- Refactor repeated external markup into Blade components or partials.
- Improve `public/css/app.css` tokens, layout classes, spacing, or responsive behavior.
- Fix Arabic readability, RTL layout, or external portal visual consistency.

# When Not to Use
- Pure backend work with no Blade or CSS surface.
- API-only permission, routing, or data-model changes.
- Internal admin redesign beyond safe shared token or component cleanup.
- Any task that depends on introducing React, Vue, Tailwind, or a new asset pipeline.

# Inputs
- Target Blade files, CSS files, and any related controller outputs.
- Portal type, role, or persona affected by the UI change.
- Relevant browser findings or regression tests when auth, tracking, or shipment surfaces are involved.

# Outputs
- Updated Blade templates, partials, components, or CSS classes in the existing stack.
- Clear Arabic-facing labels and RTL-safe layouts.
- A short note on reused components, shared CSS classes, and regression-sensitive surfaces.

# Instructions
1. Start from the shared shells in `resources/views/layouts/app.blade.php` and `resources/views/layouts/auth.blade.php`, the shared component layer in `resources/views/components`, and `public/css/app.css`.
2. Confirm the active route and Blade surface before editing because `resources/views/pages/portal/*` and older `resources/views/b2c/*` or `resources/views/b2b/*` views both exist in the repo.
3. Prefer extracting reusable Blade partials, components, or shared CSS classes over adding more inline styles.
4. Keep `lang="ar"` and `dir="rtl"` behavior intact; verify spacing, alignment, and reading order in RTL.
5. Preserve the server-rendered Blade architecture. Use simple HTML, Blade control flow, and CSS only unless the repo already has a lighter existing pattern for the same surface.
6. Keep visible labels product-facing Arabic. Replace terse technical shorthand in UI copy when the task calls for polish.
7. Build one shared external design language, then adapt density and messaging for B2C versus B2B instead of creating unrelated visual systems.
8. Treat internal pages as out of scope unless the change is a safe shared foundation update that clearly will not regress internal UX.
9. Reuse existing primitives such as `x-card`, `x-page-header`, `x-stat-card`, timelines, and shared content shells before inventing new structures.
10. Use browser regression tests and documented browser issues as history so Arabic, navigation, and error-page problems do not reappear.

# Guardrails
- Do not introduce a SPA, Tailwind migration, or new heavy frontend tooling.
- Do not weaken RBAC, tenant isolation, or route safety in the name of polish.
- Do not ship placeholder numbers, fake charts, or lorem ipsum.
- Do not blur B2C, B2B, and internal portal boundaries.

# Verification
- Check the changed pages in desktop and narrow/mobile widths.
- Verify Arabic text renders cleanly and RTL alignment still feels natural.
- Confirm shared components/classes are reused instead of duplicating inline layout code.
- If navigation or CTAs changed, confirm the routes still exist and remain permission-appropriate.
