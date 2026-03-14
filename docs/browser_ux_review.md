# Browser UX Review

## 1) Environment and URLs used

- Review date: 2026-03-09
- Base URL: `http://127.0.0.1:8000`
- Seed command used: `SEED_E2E_MATRIX=true php artisan migrate:fresh --seed`
- Web entry points discovered:
  - `/login` gateway chooser
  - `/b2c/login`
  - `/b2b/login`
  - `/admin/login`
- Screenshot folder: `docs/browser_ux_screenshots/20260309/`

## 2) Users tested

- External B2C tenant owner: `e2e.a.tenant_owner@example.test`
- External B2C staff: `e2e.a.staff@example.test`
- External B2B tenant owner: `e2e.c.tenant_owner@example.test`
- External B2B API developer: `e2e.c.api_developer@example.test`
- External suspended user: `e2e.a.suspended@example.test`
- External disabled user: `e2e.a.disabled@example.test`
- Internal super admin: `e2e.internal.super_admin@example.test`
- Internal support: `e2e.internal.support@example.test`

Password used for seeded E2E accounts during this review: `Password123!`

## 3) Executive summary

The system is security-first and most access boundaries behave correctly from a browser session. The product UX is not yet ready for a live demo because several core pages are still shell pages, several deny states are technically correct but not friendly, and the internal admin interface currently renders large parts of Arabic copy as broken characters.

## 4) User-by-user review

### 4.1 External B2C tenant owner

- Login experience:
  - Entry page is easy to understand. The three-way portal chooser is one of the clearest parts of the product.
  - B2C login page is visually clear and the form is easy to use.
- Landing result:
  - Successful login to `/b2c/dashboard`.
  - The landing does feel like an individual portal because the navigation is focused on shipments, tracking, wallet, addresses, support, and settings.
- Pages visited:
  - `/login`
  - `/b2c/login`
  - `/b2c/dashboard`
  - `/b2c/shipments`
  - `/b2c/wallet`
  - `/b2c/tracking`
  - `/admin`
- UX issues found:
  - `P0`: `/b2c/shipments`, `/b2c/wallet`, and `/b2c/tracking` all render the same dashboard shell instead of task-specific pages. Navigation looks real, but the experience does not progress.
  - `P1`: Visiting `/admin` is correctly denied, but the page is a raw 403-style screen with broken Arabic characters. It feels technical, not helpful.
  - `P2`: The gateway chooser logs a missing logo asset (`/images/logo.png`), which weakens polish on the first screen.
- Screenshots:
  - `docs/browser_ux_screenshots/20260309/00_gateway_entry.png`
  - `docs/browser_ux_screenshots/20260309/01_b2c_owner_dashboard.png`
  - `docs/browser_ux_screenshots/20260309/02_b2c_owner_admin_denied.png`
- Scores:
  - Clarity: 4/5
  - Navigation: 2/5
  - Error friendliness: 1/5
  - Role appropriateness: 4/5

### 4.2 External B2C staff

- Login experience:
  - Login succeeds and lands on the B2C dashboard without friction.
- Landing result:
  - Successful login to `/b2c/dashboard`.
- Pages visited:
  - `/b2c/login`
  - `/b2c/dashboard`
  - `/b2b/reports`
  - `/admin`
- UX issues found:
  - `P1`: Visiting `/b2b/reports` does not explain that the user is in the wrong portal. It silently sends the user back to the B2C dashboard. This avoids exposure, but the experience is ambiguous.
  - `P1`: Visiting `/admin` is denied correctly, but the screen is not friendly and the Arabic text is broken.
  - `P1`: Staff navigation looked identical to tenant owner navigation from the browser. The role distinction is not visible in the web experience.
- Screenshots:
  - Reused deny pattern from `02_b2c_owner_admin_denied.png`
- Scores:
  - Clarity: 3/5
  - Navigation: 2/5
  - Error friendliness: 1/5
  - Role appropriateness: 2/5

### 4.3 External B2B tenant owner

- Login experience:
  - B2B login page is clear and visually distinct from B2C.
- Landing result:
  - Successful login to `/b2b/dashboard`.
  - The landing feels like a business portal because it references shipments, orders, stores, users, wallet, and reports.
- Pages visited:
  - `/b2b/login`
  - `/b2b/dashboard`
  - `/b2b/shipments`
  - `/b2b/orders`
  - `/b2b/wallet`
  - `/b2b/reports`
  - `/b2b/users`
  - `/b2b/roles`
- UX issues found:
  - `P0`: All major B2B sections tested above render the same dashboard shell. The URLs change, but the content does not become task-specific.
  - `P1`: The portal offers many navigation items, but because the pages are identical, the navigation is visually promising and operationally misleading.
  - `BLOCKED`: A realistic browser-level cross-tenant object review could not be completed because there were no seeded web detail flows with distinct tenant-specific record pages to navigate. The visible web pages stayed at dashboard-shell level.
- Screenshots:
  - `docs/browser_ux_screenshots/20260309/03_b2b_owner_dashboard.png`
- Scores:
  - Clarity: 4/5
  - Navigation: 2/5
  - Error friendliness: 2/5
  - Role appropriateness: 4/5

### 4.4 External B2B API developer

- Login experience:
  - Login succeeds and lands in the B2B portal.
- Landing result:
  - Successful login to `/b2b/dashboard`.
- Pages visited:
  - `/b2b/login`
  - `/b2b/dashboard`
  - `/b2b/settings`
- UX issues found:
  - `P0`: No dedicated web UI was discovered for integrations, API keys, or webhook management. The role exists, but its core browser tasks are not available as web pages.
  - `P1`: The visible navigation is the same business dashboard used by other roles. The browser UX does not help this user understand where to manage developer-facing capabilities.
  - `BLOCKED`: Integrations/API keys/webhooks browser review was blocked because route discovery showed API endpoints only, not web pages.
- Screenshots:
  - Reused B2B landing pattern from `03_b2b_owner_dashboard.png`
- Scores:
  - Clarity: 2/5
  - Navigation: 1/5
  - Error friendliness: 2/5
  - Role appropriateness: 1/5

### 4.5 External suspended user

- Login experience:
  - Login is rejected as expected.
- Landing result:
  - User remains on `/b2c/login`.
- Pages visited:
  - `/b2c/login`
- UX issues found:
  - `P0`: The rejection banner text is visually broken, so the user does not receive a readable explanation.
  - `P1`: The page stays stable and does not create a session, which is correct behavior.
- Screenshots:
  - `docs/browser_ux_screenshots/20260309/04_b2c_suspended_disabled_login_denied.png`
- Scores:
  - Clarity: 2/5
  - Navigation: 3/5
  - Error friendliness: 1/5
  - Role appropriateness: 3/5

### 4.6 External disabled user

- Login experience:
  - Login is rejected as expected.
- Landing result:
  - User remains on `/b2c/login`.
- Pages visited:
  - `/b2c/login`
- UX issues found:
  - `P0`: Same issue as suspended user: the explanatory message is not readable because the text renders incorrectly.
- Screenshots:
  - `docs/browser_ux_screenshots/20260309/04_b2c_suspended_disabled_login_denied.png`
- Scores:
  - Clarity: 2/5
  - Navigation: 3/5
  - Error friendliness: 1/5
  - Role appropriateness: 3/5

### 4.7 Internal super admin

- Login experience:
  - Admin login works.
  - Internal dashboard loads without requiring a linked account, which is the right product behavior.
- Landing result:
  - Successful login to `/admin`.
- Pages visited:
  - `/admin/login`
  - `/admin`
  - `/admin/users`
  - `/admin/roles`
  - `/admin/reports`
  - `/admin/tenant-context`
- UX issues found:
  - `P0`: Large portions of the internal admin UI render Arabic text as broken characters. This affects headings, labels, notices, and sidebar content. It is the single most damaging UX problem in the admin area.
  - `P1`: The tenant-selection flow is conceptually correct and functionally works, but because of broken text the guidance is harder to trust.
  - `P1`: Once a tenant is selected, the selected tenant is visible in the sidebar/header, which is good. Switching context appears possible via the tenant-context page link, which is also good.
  - `P1`: `/admin/users`, `/admin/roles`, and `/admin/reports` all redirect correctly to tenant selection when no tenant is chosen. This is a good pattern.
- Screenshots:
  - `docs/browser_ux_screenshots/20260309/05_admin_tenant_selector.png`
  - `docs/browser_ux_screenshots/20260309/06_admin_users_with_tenant.png`
  - `docs/browser_ux_screenshots/20260309/07_admin_reports_with_tenant.png`
  - `docs/browser_ux_screenshots/20260309/09_admin_dashboard.png`
- Scores:
  - Clarity: 1/5
  - Navigation: 3/5
  - Error friendliness: 3/5
  - Role appropriateness: 4/5

### 4.8 Internal support (lower privilege internal)

- Login experience:
  - Authentication succeeds.
- Landing result:
  - Immediately lands on a raw forbidden page at `/admin`.
- Pages visited:
  - `/admin/login`
  - `/admin`
- UX issues found:
  - `P0`: There is no clear lower-privilege internal web landing. The user can log in, but the next screen is a blank-feeling 403 page.
  - `P1`: The denial message is in plain English (`PERMISSION DENIED.`) and provides no next step, no navigation, and no explanation of available areas.
  - `BLOCKED`: No dedicated support web entry point was discovered in the web route inventory.
- Screenshots:
  - `docs/browser_ux_screenshots/20260309/08_internal_support_admin_denied.png`
- Scores:
  - Clarity: 1/5
  - Navigation: 1/5
  - Error friendliness: 1/5
  - Role appropriateness: 1/5

## 5) Major flow and page scoring

| Flow / Page | Clarity | Navigation | Error friendliness | Role appropriateness | Notes |
| --- | ---: | ---: | ---: | ---: | --- |
| Gateway chooser `/login` | 5 | 5 | 4 | 5 | Strong first screen, clear separation of personas, only polish issue is missing logo asset |
| B2C login and landing | 4 | 2 | 2 | 4 | Good first impression, but task pages collapse back to dashboard |
| B2B login and landing | 4 | 2 | 2 | 4 | Business framing is clear, but navigation depth is not real yet |
| Suspended/disabled login handling | 2 | 3 | 1 | 3 | Correct deny behavior, unreadable message |
| Internal admin dashboard | 1 | 3 | 3 | 4 | Functional structure is there, text rendering is badly broken |
| Internal tenant selector | 3 | 3 | 3 | 4 | Good product idea, weakened heavily by rendering issues |
| Lower-privilege internal access | 1 | 1 | 1 | 1 | No friendly internal landing or guidance |
| Denied access UX overall | 1 | 1 | 1 | 3 | Security is correct, but browser-facing denial experience is raw and technical |

## 6) Best UX areas

- The gateway chooser at `/login` is strong and easy to understand.
- B2C vs B2B visual separation is immediately clear.
- Internal tenant-context selection is the right product concept and works functionally.
- Selected tenant visibility inside internal admin is present, which is important and useful.

## 7) Most confusing areas

- The internal admin portal renders much of its Arabic copy as broken characters.
- Many core B2C/B2B pages are only shell routes and show the same dashboard content.
- Lower-privilege internal users have no friendly web landing.
- Denied access pages feel technical instead of guided.
- Developer-focused B2B users have no visible web home for integrations/API keys/webhooks.

## 8) Security is correct but UX needs polish

- External users were correctly denied from `/admin`.
- Internal tenant-bound pages correctly redirected to tenant selection when no tenant was chosen.
- Suspended/disabled users were correctly prevented from entering.
- The issue is not correctness. The issue is presentation, guidance, and the fact that several browser paths are not mature product pages yet.

## 9) Pages that should redirect or guide instead of showing raw deny screens

- `/admin` for lower-privilege internal users should show a friendly internal landing or a support-specific home, not a bare 403 page.
- `/admin` for external users should preferably redirect to the correct portal or show a branded deny page with next steps.
- Any wrong-portal access such as a B2C user opening B2B pages should explain what happened instead of silently bouncing back.

## 10) Top UX Problems

### P0 — blocks usability

1. Internal admin pages render large parts of Arabic content as broken characters.
2. Core B2C and B2B task pages (`shipments`, `orders`, `wallet`, `reports`, `users`, `roles`) mostly render the same dashboard shell instead of usable task screens.
3. Suspended and disabled login failure messages are unreadable.
4. Internal support can authenticate but lands on a raw forbidden page with no guided next step.
5. API developer role has no discoverable web UI for integrations/API keys/webhooks.

### P1 — confusing but usable

1. External `/admin` denial is correct but not friendly.
2. B2C user opening B2B pages is silently redirected instead of being clearly informed.
3. Internal tenant selector works, but text/rendering issues make the flow feel less trustworthy.
4. Role-specific navigation differences are not obvious in the web UI.

### P2 — polish improvements

1. Gateway chooser missing logo asset on first load.
2. A stronger page title and breadcrumbs strategy would help users orient across portals.
3. More visible tenant-context switch affordance in internal admin would reduce confusion.

## 11) Recommended fixes (product/UX language)

1. Fix text rendering in internal/admin screens first.
   - If users cannot read the screen, every other admin improvement loses value.
2. Replace placeholder shell pages with at least basic task-specific empty states.
   - Even if the feature is still early, each destination should say clearly what the page is for and what to do next.
3. Convert all deny pages into branded guidance states.
   - A good deny page should answer: why am I blocked, what can I do next, where should I go instead.
4. Give lower-privilege internal users their own landing page.
   - Support and read-only operations users should not hit a dead-end after login.
5. Add browser-visible integration tools for the API developer persona, or explicitly label them as API-only.
   - Right now the role exists, but the browser experience gives no clue where that work happens.
6. Make wrong-portal navigation explicit.
   - If a B2C user opens a B2B page, show a short explanation and a button back to the correct portal.
7. Add real tenant summaries and clearer tenant switching in internal admin.
   - The product concept is sound; it needs better signposting and readable copy.

## 12) Overall verdict

**NOT READY FOR DEMO**

Reason:
- The access-control model behaves correctly in the browser.
- The user experience is not demo-ready because key business pages are still shell pages, deny states are raw, and internal admin text rendering is currently broken enough to block confident use.
