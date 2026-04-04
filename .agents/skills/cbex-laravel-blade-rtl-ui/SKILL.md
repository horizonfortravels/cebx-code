---
name: cbex-laravel-blade-rtl-ui
description: Blade and public-CSS UI work for this Laravel 11 shipping gateway. Use when editing server-rendered Blade views, shared layouts, reusable Blade components, Arabic-first copy, RTL behavior, or `public/css/app.css` for internal/admin portal changes without introducing a new frontend stack, SPA, Tailwind migration, or heavy build tooling.
---

# Goal
Implement internal portal UI changes in the existing Laravel Blade plus public CSS stack while keeping Arabic-first, RTL-first behavior intact.

# When to Use
- Edit `resources/views/layouts/app.blade.php` or internal/admin Blade pages.
- Refactor repeated internal markup into Blade components or partials.
- Improve `public/css/app.css` tokens, layout classes, spacing, or responsive behavior.
- Fix Arabic readability, RTL layout, or internal portal visual consistency.

# When Not to Use
- Pure backend work with no Blade or CSS surface.
- API-only permission, routing, or data-model changes.
- B2C or B2B navigation redesign beyond safe shared token/component cleanup.
- Any task that depends on introducing React, Vue, Tailwind, or a new asset pipeline.

# Inputs
- Target Blade files, CSS files, and any related controller outputs.
- Internal portal roles or personas affected by the UI change.
- Browser review notes from `docs/browser_ux_review_round2.md` and `docs/browser_ux_final_verdict.md` when relevant.

# Outputs
- Updated Blade templates, partials, components, or CSS classes in the existing stack.
- Clear Arabic-facing labels and RTL-safe layouts.
- A short note on reused components, shared CSS classes, and regression-sensitive surfaces.

# Instructions
1. Start from the shared shell in `resources/views/layouts/app.blade.php`, the shared component layer in `resources/views/components`, and `public/css/app.css`.
2. Prefer extracting reusable Blade partials/components or shared CSS classes over adding more inline styles.
3. Keep `lang="ar"` and `dir="rtl"` behavior intact; verify spacing, alignment, and reading order in RTL.
4. Preserve the server-rendered Blade architecture. Use simple HTML, Blade control flow, and CSS only unless the repo already has a lighter existing pattern for the same surface.
5. Keep visible labels product-facing Arabic. Replace terse technical shorthand in UI copy when the task calls for polish.
6. Confine redesign work to internal pages unless a shared CSS token or component improvement is obviously safe for external portals.
7. Reuse existing primitives such as `x-card`, `x-stat-card`, timelines, grid helpers, and shared content shells before inventing new structures.
8. Use the browser UX docs as regression history so past Arabic, navigation, and error-page issues do not reappear.

# Guardrails
- Do not introduce a SPA, Tailwind migration, or new heavy frontend tooling.
- Do not weaken RBAC, tenant isolation, or route safety in the name of polish.
- Do not ship placeholder numbers, fake charts, or lorem ipsum.
- Do not treat B2C and B2B surfaces as redesign targets unless the change is a safe shared foundation update.

# Verification
- Check the changed internal pages in desktop and narrow/mobile widths.
- Verify Arabic text renders cleanly and RTL alignment still feels natural.
- Confirm shared components/classes are reused instead of duplicating inline layout code.
- If navigation or CTAs changed, confirm the routes still exist and remain permission-appropriate.
