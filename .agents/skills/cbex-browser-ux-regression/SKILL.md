---
name: cbex-browser-ux-regression
description: Browser UX regression review for the Arabic-first CBEX internal portal. Use when validating internal navigation, tenant-context flows, wrong-portal guidance, branded deny/error pages, Arabic readability, or replaying issues captured in `docs/browser_ux_review_round2.md` and `docs/browser_ux_final_verdict.md`.
---

# Goal
Catch internal portal browser regressions before they ship, with special attention to Arabic readability, internal routing guidance, permission-safe navigation, and branded failure states.

# When to Use
- Review internal portal changes in browser or Playwright.
- Re-check `/admin`, `/internal`, admin login, tenant selector, or role-specific internal landings.
- Validate that internal redesign work did not reintroduce raw 403/404/500 pages or broken Arabic text.
- Compare new internal UX against prior documented findings.

# When Not to Use
- Pure code implementation without any regression review.
- API-only security tests with no browser surface.
- External-only UX reviews unless a shared internal change may have affected them.

# Inputs
- The changed internal routes, roles, and expected entry flows.
- `docs/browser_ux_review_round2.md`
- `docs/browser_ux_final_verdict.md`
- Relevant internal Playwright specs under `tests/e2e/`

# Outputs
- A concise findings list with severity, affected route, persona, and reproduction steps.
- Notes on Arabic/RTL quality, navigation regressions, and branded error handling.
- Suggested follow-up tests when coverage is missing.

# Instructions
1. Start from the known regression history in the two browser UX docs so you retest the routes most likely to slip.
2. Exercise the internal entry points:
   - `/admin/login`
   - `/admin`
   - `/internal`
   - tenant selector flows
   - role-limited internal landings
3. Validate role-aware navigation and selected-account behavior for the relevant personas.
4. Check for Arabic mojibake, placeholder glyphs, clipped labels, and RTL alignment issues.
5. Check deny and failure handling. Internal and wrong-portal flows must show branded HTML guidance, not raw framework output or debug traces.
6. Record concrete evidence: route, persona, action taken, what appeared, and the expected safer outcome.
7. Keep the review focused on regressions, risks, and missing tests rather than broad redesign advice unless the user asks for critique.

# Guardrails
- Treat raw framework/debug output in the browser as a serious regression.
- Do not approve dead links, broken selected-account flows, or inaccessible next actions.
- Do not let internal redesign work accidentally widen external portal scope.
- Keep findings evidence-based and tied to concrete routes or files.

# Verification
- Re-run the most relevant Playwright or browser smoke flows after changes.
- Confirm reviewed flows render readable Arabic and correct RTL spacing.
- Verify wrong-portal and insufficient-permission routes still land on branded guidance.
- Check that selected-account-required pages remain understandable when no account is selected.
