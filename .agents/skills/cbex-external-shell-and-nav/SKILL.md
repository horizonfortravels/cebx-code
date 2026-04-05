---
name: cbex-external-shell-and-nav
description: Shared external shell, sidebar and topbar polish, icon strategy, and premium SaaS navigation direction for CBEX. Use when editing `resources/views/layouts/app.blade.php`, shared external navigation, topbar messaging, reusable page chrome, or the shared CSS that makes B2C and B2B feel premium without becoming generic.
---

# Goal
Polish the shared external shell so B2C and B2B feel cohesive, premium, and easy to navigate without losing their portal-specific differences.

# When to Use
- Rework the external sidebar, topbar, page chrome, or icon system.
- Improve shared navigation labels, groups, or active-state behavior.
- Introduce safer shared shell tokens or CSS patterns for external pages.
- Audit whether external navigation looks too internal, too technical, or too generic.

# When Not to Use
- Deep per-page workflow design that does not affect the shared shell.
- Internal admin shell redesign.
- Backend-only permission changes with no browser chrome impact.

# Inputs
- Shared layout files and shared CSS.
- Current route groups and navigation rules for B2C and B2B.
- Existing reusable components and icon patterns.

# Outputs
- A cleaner shared shell and navigation direction.
- Better grouping, labels, icon choices, and topbar messaging.
- Safe notes about shared changes that can affect both portals.

# Instructions
1. Start from `resources/views/layouts/app.blade.php`, shared components, and `public/css/app.css`.
2. Design the shell as one external family with two portal personalities: lighter and more personal for B2C, denser and more operational for B2B.
3. Use product-facing Arabic labels. Replace terse technical abbreviations in visible navigation when the interface can say the same thing more clearly.
4. Keep icon usage intentional. Prefer a consistent icon logic over a mix of unrelated badges, initials, and decorative marks.
5. Use the topbar to reinforce current portal context, but do not let it imply internal authority or carrier ownership.
6. Keep navigation groups short and scannable. Favor a few strong groups over long undifferentiated lists.
7. Preserve role-aware visibility for B2B developer tools without making hidden features feel like broken navigation.
8. Treat shared shell changes as high-regression surfaces. Check both portals before assuming a shared improvement is safe.

# Guardrails
- Do not make the external shell feel like the internal admin panel.
- Do not expose internal ops, admin, or carrier-management terms in external navigation.
- Do not rely on technical shorthand for primary navigation labels.
- Do not create shared shell changes that only work for one portal and regress the other.

# Verification
- Check B2C and B2B side by side for family resemblance without role confusion.
- Check active states, group labels, and topbar copy against real routes.
- Check that icons, labels, and spacing feel coherent across the shell.
- Check that mobile and narrow layouts still preserve portal context cleanly.
