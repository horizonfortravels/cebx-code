---
name: cbex-auth-entry-and-error-pages
description: External auth entry, portal chooser, wrong-portal guidance, and branded HTML error-page work for CBEX. Use when editing `AuthWebController`, `resources/views/pages/auth/*`, `resources/views/errors/*`, shared auth layout, or other browser-facing entry and failure pages for B2C, B2B, and safe external guidance.
---

# Goal
Make external entry, login, wrong-portal, deny, and safe error experiences clear, branded, and secure.

# When to Use
- Redesign the portal chooser or login pages.
- Improve wrong-portal guidance between B2C, B2B, and internal admin entry points.
- Rework branded 403 or 500 browser-facing pages.
- Audit whether auth and failure states leak confusing or unsafe information.

# When Not to Use
- Signed-in dashboard work unrelated to entry or failure states.
- Internal admin-only error-page work.
- Backend-only auth logic changes with no browser surface.

# Inputs
- Entry routes in `routes/web.php`.
- `AuthWebController` methods for portal selection, login, logout, and portal mismatch handling.
- Shared auth layout plus auth and error Blade views.
- Browser guidance tests and smoke coverage.

# Outputs
- Better portal-entry and login page direction.
- Safer wrong-portal and deny guidance.
- Branded HTML error pages that preserve trust.

# Instructions
1. Treat `/login`, `/b2c/login`, `/b2b/login`, and `/admin/login` as separate doors with explicit purposes.
2. Keep the portal chooser simple: help users pick the right door without exposing internal terminology to external users.
3. When a user reaches the wrong portal, explain why and point them to the right place. Prefer explicit guidance over silent redirects that hide account-type problems.
4. Keep login pages visually related but clearly specialized for B2C versus B2B.
5. Use branded HTML for deny and failure states. External users should never see raw framework exceptions, stack traces, or debug dumps.
6. Preserve security language and account-state messaging for suspended, disabled, or unauthorized users without leaking sensitive detail.
7. Keep password reset and recovery surfaces within the same trust system as the main auth pages.

# Guardrails
- Do not weaken auth, permission, or account-type checks for the sake of convenience.
- Do not expose seeded credentials, internal guidance, or debug information in browser-facing pages.
- Do not make wrong-portal handling ambiguous or silent.
- Do not blur internal admin entry with external customer entry.

# Verification
- Check each login route and the chooser flow in browser or test coverage.
- Check wrong-portal cases for B2C, B2B, and external-to-admin attempts.
- Check that 403 and 500 pages stay branded HTML and remain readable in Arabic.
- Check that failure copy remains clear without exposing unsafe detail.
