# Browser E2E Real FedEx Report

## 1. Environment Used
- Date: 2026-03-17
- Base URL: `http://127.0.0.1:8000`
- Seed command: `SEED_E2E_MATRIX=true php artisan migrate:fresh --seed`
- Internal super admin ensure command: `php artisan app:create-internal-super-admin`
- Browser method: Playwright browser automation
- Server/runtime note: local FedEx sandbox env was active in this run after restart/cache clear.

## 2. Users Tested

### External
- `e2e.a.individual@example.test` — B2C individual, active
- `e2e.b.individual@example.test` — B2C individual, active, cross-tenant target
- `e2e.c.organization_owner@example.test` — B2B organization owner, active
- `e2e.c.organization_admin@example.test` — B2B organization admin, active
- `e2e.c.staff@example.test` — B2B staff, active
- `e2e.c.suspended@example.test` — B2B suspended external
- `e2e.c.disabled@example.test` — B2B disabled external
- `e2e.d.organization_owner@example.test` — B2B organization owner, active, cross-tenant target

### Internal
- `e2e.internal.super_admin@example.test` — internal super admin
- `e2e.internal.support@example.test` — internal support
- `carrier_manager` — NOT SEEDED as a dedicated E2E browser user in the current matrix

## 3. Browser Flows Executed
- Portal chooser and entry points
- B2C login, landing, wrong-portal guidance, external `/admin` denial
- B2B login, landing, wrong-portal guidance, suspended/disabled denial
- Real browser shipment flow for:
  - one B2B organization owner
  - one B2C individual
- B2B role coverage for:
  - organization_admin
  - staff
- DG=yes hold verification through B2B admin
- Internal portal coverage for:
  - super_admin
  - support
- Cross-tenant browser access checks for B2B and B2C shipment pages
- Notification surface visibility checks

## 4. Exact Results Per Persona

### B2C individual: `e2e.a.individual@example.test`
- Login result: PASS
- Landing result: PASS, landed on `/b2c/dashboard`
- Pages tested:
  - `/b2c/dashboard`
  - `/b2c/shipments/create`
  - `/b2c/shipments/{id}/offers`
  - `/b2c/shipments/{id}/declaration`
  - `/b2c/shipments/{id}`
- Allowed flows:
  - create shipment draft
  - fetch offers
  - select one offer
  - complete DG=no declaration with disclaimer acceptance
  - trigger wallet preflight
- Denied or blocked flows:
  - issuance BLOCKED by `ERR_WALLET_NOT_AVAILABLE`
  - browser flow did not reach purchased state
  - no docs/timeline events/notifications generated for this shipment because issuance never happened
- Observed UX quality:
  - good Arabic routing and step guidance
  - preflight failure message is readable and branded
  - offer source is not aligned with real FedEx expectation

### B2C cross-tenant individual: `e2e.b.individual@example.test`
- Login result: PASS
- Landing result: PASS, landed on `/b2c/dashboard`
- Pages tested:
  - `/b2c/shipments/{foreign-id}`
  - `/notifications`
- Allowed flows:
  - own dashboard and own notification center
- Denied or blocked flows:
  - opening shipment `a151a7fe-18be-44ee-9e07-bdbdb4007b9d` owned by `e2e.a.individual@example.test` returned `404`
- Observed UX quality:
  - no cross-tenant shipment leakage observed
  - notification surface was readable and empty for this user

### B2B organization_owner: `e2e.c.organization_owner@example.test`
- Login result: PASS
- Landing result: PASS, landed on `/b2b/dashboard`
- Pages tested:
  - `/b2b/dashboard`
  - `/b2b/shipments/create`
  - `/b2b/shipments/{id}/offers`
  - `/b2b/shipments/{id}/declaration`
  - `/b2b/shipments/{id}`
  - `/b2b/wallet`
  - `/wallet`
- Allowed flows:
  - create shipment draft
  - fetch offers
  - select one offer
  - complete DG=no declaration with disclaimer acceptance
  - trigger wallet preflight
- Denied or blocked flows:
  - offer fetch returned simulated `Aramex` and `DHL Express` options instead of a real FedEx-backed browser path
  - issuance BLOCKED by `ERR_WALLET_NOT_AVAILABLE`
  - opening full wallet page `/wallet` returned branded `500`
- Observed UX quality:
  - draft -> offers -> declaration flow is readable and stable
  - wallet preflight error is user-readable
  - full wallet remediation path is broken in browser

### B2B organization_admin: `e2e.c.organization_admin@example.test`
- Login result: PASS
- Landing result: PASS, landed on `/b2b/dashboard`
- Pages tested:
  - `/b2b/shipments/create`
  - `/b2b/shipments/{id}/offers`
  - `/b2b/shipments/{id}/declaration`
- Allowed flows:
  - submit valid shipment draft
  - fetch offers
  - select one offer
  - complete DG declaration
  - DG=yes path correctly moves shipment into manual-hold style state
- Denied or blocked flows:
  - offers were again Aramex/DHL, not FedEx-backed
- Observed UX quality:
  - admin request-flow access is correctly enabled
  - DG=yes hold messaging is clear and operationally useful

### B2B staff: `e2e.c.staff@example.test`
- Login result: PASS
- Landing result: PASS, landed on `/b2b/dashboard`
- Pages tested:
  - `/b2b/shipments/create`
- Allowed flows:
  - open shipment draft page
  - submit valid shipment draft successfully
- Denied or blocked flows:
  - deeper same-session flow not repeated because owner/admin already established the downstream behavior and blockers
- Observed UX quality:
  - current product rule is reflected in browser: staff can use the shipment request flow

### Suspended external: `e2e.c.suspended@example.test`
- Login result: PASS as a negative test
- Landing result: DENIED correctly
- Pages tested:
  - `/b2b/login`
- Allowed flows:
  - none
- Denied or blocked flows:
  - login denied with readable suspension message
- Observed UX quality:
  - good branded denial copy

### Disabled external: `e2e.c.disabled@example.test`
- Login result: PASS as a negative test
- Landing result: DENIED correctly
- Pages tested:
  - `/b2b/login`
- Allowed flows:
  - none
- Denied or blocked flows:
  - login denied with readable disabled-user message
- Observed UX quality:
  - good branded denial copy

### Internal super_admin: `e2e.internal.super_admin@example.test`
- Login result: PASS
- Landing result: PASS, landed on `/admin`
- Pages tested:
  - `/admin`
  - `/admin/tenant-context`
  - `/admin/users`
- Allowed flows:
  - open admin dashboard
  - see tenant-context selection flow
  - choose account context and open account users page
- Denied or blocked flows:
  - none in tested internal-admin surfaces
- Observed UX quality:
  - internal tenant-context selection is explicit and understandable

### Internal support: `e2e.internal.support@example.test`
- Login result: PASS
- Landing result: PASS, landed on `/internal`
- Pages tested:
  - `/internal`
  - `/admin`
- Allowed flows:
  - open support-oriented internal landing page
  - see clear next step for tenant-context selection
- Denied or blocked flows:
  - opening `/admin` returned branded `403`, not a raw forbidden/debug page
- Observed UX quality:
  - role-appropriate landing page is clear
  - denial path is readable and not hostile

### Internal carrier_manager
- Result: NOT SEEDED in current documented E2E user matrix

## 5. Shipment Journey Coverage

### Draft
- PASS for B2C individual
- PASS for B2B owner
- PASS for B2B admin
- PASS for B2B staff

### Validation
- Valid submit path works for B2C/B2B tested roles.
- Empty-submit validation on create page was not surfaced cleanly in automation; the form focused the first field but no clear inline Arabic validation copy was observed in the captured DOM.

### KYC / restriction gate
- No explicit KYC-block scenario surfaced in the tested browser shipment flows.
- User-status gating did work:
  - suspended login denied
  - disabled login denied

### Rates
- Functional in browser, but FAIL against the real-FedEx acceptance goal.
- Browser offer fetch succeeded technically, yet returned simulated Aramex/DHL options instead of a real FedEx-backed browser path.

### Offers
- PASS as UI flow
- FAIL as FedEx-path acceptance

### Selection
- PASS for B2C individual and B2B owner/admin

### DG declaration
- PASS for DG=no with disclaimer acceptance
- PASS for DG=yes hold behavior
  - shipment moved into a requires-action style state with clear hold guidance

### Wallet preflight
- PASS as UI integration and readable business-error handling
- BLOCKED functionally because seeded external accounts have no wallets in the shipment currency

### Carrier issuance
- BLOCKED in browser because wallet preflight cannot succeed with the current seed data

### Documents
- Surface exists pre-issuance on shipment page
- Post-issuance document availability could not be validated from a browser-created shipment because issuance was blocked upstream

### Timeline
- Surface exists on shipment details page
- Pre-issuance view shows empty timeline correctly
- Post-issuance timeline events could not be validated from a browser-created shipment because issuance was blocked upstream

### Notifications
- Notification center exists and is readable
- No shipment-event notifications became visible for browser-created shipments because issuance never completed
- No cross-tenant notification leakage was observed in tested browser surfaces

## 6. Security And Isolation Findings
- PASS: B2C user opening B2B area got readable wrong-portal guidance
- PASS: B2B user opening B2C area got readable wrong-portal guidance
- PASS: external `/admin` denial worked in earlier external checks
- PASS: B2B cross-tenant shipment page returned `404`
- PASS: B2B cross-tenant documents page returned `404`
- PASS: B2C cross-tenant shipment page returned `404`
- PASS: support user opening `/admin` got branded `403`
- PASS: no raw framework/debug pages were exposed in the tested denial paths
- FAIL: `/wallet` full page for external B2B owner returned a branded `500`, which is still a browser-visible server error

## 7. Representative Screenshots
- `docs/browser_e2e_real_fedex_screenshots/20260317/01_portal_chooser.png`
- `docs/browser_e2e_real_fedex_screenshots/20260317/02_b2c_login_success.png`
- `docs/browser_e2e_real_fedex_screenshots/20260317/03_b2c_wrong_portal_b2b.png`
- `docs/browser_e2e_real_fedex_screenshots/20260317/04_external_admin_denied.png`
- `docs/browser_e2e_real_fedex_screenshots/20260317/05_b2b_owner_login_success.png`
- `docs/browser_e2e_real_fedex_screenshots/20260317/06_b2b_wrong_portal_b2c.png`
- `docs/browser_e2e_real_fedex_screenshots/20260317/07_b2b_suspended_denied.png`
- `docs/browser_e2e_real_fedex_screenshots/20260317/08_b2b_disabled_denied.png`
- `docs/browser_e2e_real_fedex_screenshots/20260317/09_b2b_offers_non_fedex.png`
- `docs/browser_e2e_real_fedex_screenshots/20260317/10_wallet_full_page_500.png`

## 8. Top Blockers Found
1. Browser shipment offer flow is still not using the real FedEx-backed path. Offer fetch succeeds but returns simulated Aramex/DHL options in both tested B2C and B2B flows.
2. Seeded external accounts have no wallets, so wallet preflight fails with `ERR_WALLET_NOT_AVAILABLE` and the browser journey cannot reach carrier issuance.
3. `/wallet` full page returns a branded `500` for the tested B2B owner flow, so the user cannot self-remediate funding from the browser.
4. Because issuance is blocked, the browser-created shipment cannot reach purchased state, post-issuance documents, post-issuance timeline events, or shipment-event notifications.

## 9. Top Polish Issues Found
1. B2B shipment list page still shows mojibake/garbled Arabic in title and body sections.
2. Empty-submit validation on shipment create page was not clearly surfaced in the tested browser capture.

## 10. Final Verdict
NOT READY FOR DEMO
