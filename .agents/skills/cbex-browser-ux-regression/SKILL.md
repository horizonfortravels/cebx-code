---
name: cbex-browser-ux-regression
description: Browser UX regression review for the Arabic-first CBEX external portals and shared entry flows. Use when validating B2C and B2B navigation, public tracking, shipment journeys, wrong-portal guidance, branded deny or error pages, Arabic readability, or replaying issues covered by the external smoke and browser tests.
---

# Goal
Catch external browser regressions before they ship, with special attention to Arabic readability, account-type routing guidance, permission-safe navigation, public tracking, and branded failure states.

# When to Use
- Review external portal changes in browser or Playwright.
- Re-check `/login`, `/b2c/login`, `/b2b/login`, `/track/{token}`, shipment workspace steps, or B2B developer navigation.
- Validate that external redesign work did not reintroduce raw 403/404/500 pages, broken Arabic text, or silent wrong-portal behavior.
- Compare new external UX against the current feature and end-to-end coverage expectations.

# When Not to Use
- Pure code implementation without any regression review.
- API-only security tests with no browser surface.
- Internal-only portal reviews unless a shared change may have affected external surfaces.

# Inputs
- The changed external routes, roles, and expected entry flows.
- Relevant feature tests under `tests/Feature/Web/`
- Relevant Playwright specs under `tests/e2e/`, especially `login-and-navigation.smoke.spec.js` and `public-tracking.phase4e.spec.js`
- Any recent browser bug notes or repro steps supplied with the task

# Outputs
- A concise findings list with severity, affected route, persona, and reproduction steps.
- Notes on Arabic/RTL quality, navigation regressions, and branded error handling.
- Suggested follow-up tests when coverage is missing.

# Instructions
1. Start from the current route map and existing smoke coverage so you retest the entry points most likely to slip.
2. Exercise the external entry points:
   - `/login`
   - `/b2c/login`
   - `/b2b/login`
   - `/track/{token}`
   - B2C and B2B dashboard entry routes
3. Validate account-type boundaries and wrong-portal handling. B2C users must not silently land inside B2B, and external users must not see internal admin pages.
4. Walk the shipment browser journey when the change touches it: create, address validation, offers, declaration, wallet preflight, issue, documents, and timeline.
5. Validate role-aware B2B navigation, especially developer tools that depend on permissions.
6. Check for Arabic mojibake, placeholder glyphs, clipped labels, broken breadcrumbs, and RTL alignment issues.
7. Check deny and failure handling. Wrong-portal, 403, and 500 flows must show branded HTML guidance, not raw framework output or debug traces.
8. Record concrete evidence: route, persona, action taken, what appeared, and the expected safer outcome.
9. Keep the review focused on regressions, risks, and missing tests rather than broad redesign advice unless the user asks for critique.

# Guardrails
- Treat raw framework/debug output in the browser as a serious regression.
- Do not approve dead links, cross-portal leakage, or inaccessible next actions.
- Do not let external redesign work expose internal ops or admin concepts.
- Keep findings evidence-based and tied to concrete routes or files.

# Verification
- Re-run the most relevant Playwright or browser smoke flows after changes.
- Confirm reviewed flows render readable Arabic and correct RTL spacing.
- Verify wrong-portal and insufficient-permission routes still land on branded guidance.
- Check that B2B developer surfaces remain hidden or denied when permissions are missing.
