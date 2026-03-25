# Browser E2E Full Report

## 1. Environment Used
- Date: 2026-03-17
- Worktree: `c:\Users\Ahmed\Desktop\cebx-code`
- Branch: `phase-f1-documents`
- App start method: `php artisan serve --host=127.0.0.1 --port=8000`
- Browser automation: Playwright browser automation
- Seed command: `SEED_E2E_MATRIX=true php artisan migrate:fresh --seed`
- Internal super admin repair/create command: `php artisan app:create-internal-super-admin`
- Canonical terminology used during verification:
  - B2C = `individual` external portal only
  - B2B = `organization` external portal only
  - Internal = platform staff only

## 2. Base URL
- `http://127.0.0.1:8000`

## 3. Users Tested

### External
- `e2e.a.individual@example.test` (`individual`, active)
- `e2e.b.individual@example.test` (`individual`, active, cross-tenant check target)
- `e2e.c.organization_owner@example.test` (`organization_owner`, active)
- `e2e.c.organization_admin@example.test` (`organization_admin`, active)
- `e2e.c.staff@example.test` (`staff`, active)
- `e2e.c.suspended@example.test` (`staff`, suspended)
- `e2e.c.disabled@example.test` (`staff`, disabled)

### Internal
- `e2e.internal.super_admin@example.test` (`super_admin`, active)
- `e2e.internal.support@example.test` (`e2e_internal_support`, active)
- `carrier_manager`: NOT SEEDED as a dedicated E2E user in the current documented matrix

### Legacy/demo users present but not used as primary acceptance actors
- Demo accounts such as `sultan@techco.sa`, `mohammed@example.sa`, `admin@system.sa`
- Deprecated legacy personas in old docs were intentionally ignored as primary acceptance actors

## 4. Browser Entry Points Discovered
- Portal chooser: `/login`
- B2C login: `/b2c/login`
- B2B login: `/b2b/login`
- Internal login: `/admin/login`

## 5. Browser Flows Executed
- Portal chooser and portal discovery
- B2C login success and wrong-portal checks
- B2B login success and wrong-portal checks
- Suspended and disabled login denial checks
- Internal super admin login, tenant-context selection, and internal pages
- Internal support login, internal landing, and admin denial UX
- B2C shipment browser flow:
  - draft create
  - invalid submit / validation behavior
  - ready-for-rates state
  - offers fetch
  - offer comparison
  - offer selection
  - DG declaration (`no` + disclaimer success)
  - shipment show / timeline surface
  - documents page
  - notifications page
- B2B shipment browser flow:
  - owner create page and submit
  - admin create page access
  - staff create page access and submit
- Cross-tenant shipment access check in browser

## 6. Persona Results

### B2C `individual`
- Login result: PASS
- Landing result: PASS, lands on `/b2c/dashboard`
- Pages tested:
  - `/b2c/dashboard`
  - `/b2c/shipments/create`
  - `/b2c/shipments/{id}/offers`
  - `/b2c/shipments/{id}/declaration`
  - `/b2c/shipments/{id}`
  - `/b2c/shipments/{id}/documents`
  - `/notifications`
- Allowed flows:
  - login
  - open shipment draft page
  - create valid draft
  - fetch offers
  - compare offers
  - select one offer
  - complete DG=`no` declaration with legal disclaimer acceptance
  - view shipment status page
  - view documents page shell for own shipment
  - view notifications surface
- Denied flows:
  - opening B2B dashboard returns branded wrong-portal `403`
  - opening `/admin` returns branded external/internal separation `403`
- Observed UX quality:
  - good overall for portal routing and request-flow progression
  - validation uses native browser validation messages rather than rich inline form feedback
  - shipment create page contains mojibake/garbled text in later workflow step labels

### B2B `organization_owner`
- Login result: PASS
- Landing result: PASS, lands on `/b2b/dashboard`
- Pages tested:
  - `/b2b/dashboard`
  - `/b2b/shipments/create`
  - `/b2c/dashboard` (wrong portal)
- Allowed flows:
  - login
  - open shipment draft page
  - wrong-portal guidance from B2B -> B2C is clear and branded
- Denied / blocked flows:
  - submitting a valid B2B shipment draft is BLOCKED
  - exact browser behavior: branded `500` page at `/b2b/shipments`
  - reason visible to user: generic unexpected error page, no raw debug output
- Observed UX quality:
  - dashboard is distinct and role-appropriate
  - request flow is blocked by server-side failure after submit

### B2B `organization_admin`
- Login result: PASS
- Landing result: PASS, lands on `/b2b/dashboard`
- Pages tested:
  - `/b2b/dashboard`
  - `/b2b/shipments/create`
- Allowed flows:
  - login
  - open shipment draft page
- Denied / blocked flows:
  - did not complete full shipment flow because the same B2B submit path is already broken for owner/staff and blocks confidence in the shared B2B request path
- Observed UX quality:
  - page access is correct
  - request flow remains effectively blocked at the shared B2B create-submit layer

### B2B `staff`
- Login result: PASS
- Landing result: PASS, lands on `/b2b/dashboard`
- Pages tested:
  - `/b2b/dashboard`
  - `/b2b/shipments/create`
- Allowed flows:
  - login
  - open shipment draft page
- Denied / blocked flows:
  - submitting a valid B2B shipment draft is BLOCKED
  - exact browser behavior: branded `500` page at `/b2b/shipments`
- Observed UX quality:
  - current product rule that staff can enter the shipment request flow is reflected in page access
  - the shared B2B submission bug prevents confirming downstream parity

### Suspended external user
- Login result: PASS as denial behavior
- Landing result: stays on `/b2b/login`
- Pages tested:
  - `/b2b/login`
- Allowed flows:
  - none beyond login form
- Denied flows:
  - login denied with readable message: account suspended temporarily
- Observed UX quality:
  - denial copy is readable and non-technical

### Disabled external user
- Login result: PASS as denial behavior
- Landing result: stays on `/b2b/login`
- Pages tested:
  - `/b2b/login`
- Allowed flows:
  - none beyond login form
- Denied flows:
  - login denied with readable message: account disabled
- Observed UX quality:
  - denial copy is readable and non-technical

### Internal `super_admin`
- Login result: PASS
- Landing result: PASS, lands on `/admin`
- Pages tested:
  - `/admin`
  - `/admin/tenant-context`
  - `/admin/users`
  - `/admin/roles`
  - `/admin/reports`
- Allowed flows:
  - login
  - tenant-context selection
  - account-specific users/roles/reports browsing after selecting `E2E Account C`
- Denied flows:
  - none in tested scope
- Observed UX quality:
  - strong internal landing
  - tenant-context selection is understandable and session-scoped
  - account-bound pages load cleanly once context is selected

### Internal `support`
- Login result: PASS
- Landing result: PASS, lands on `/internal`
- Pages tested:
  - `/internal`
  - `/admin`
  - `/internal/tenant-context`
- Allowed flows:
  - login
  - internal landing
  - tenant-context selection entry
- Denied flows:
  - `/admin` denied with branded `403`
- Observed UX quality:
  - no raw forbidden page
  - support user is kept in a support-oriented internal space with clear next steps

## 7. Shipment Workflow Coverage

### Draft
- B2C: PASS
- B2B: BLOCKED after submit on owner/staff due branded `500` page

### Validation
- B2C: PARTIAL PASS
  - empty submit triggers native browser validation messages such as `����� ��� ��� �����.`
  - visible inline server-side validation UX was not observed on the page itself
- B2B: BLOCKED by shared create-submit failure before meaningful downstream validation evidence

### KYC / restriction behavior if visible
- Not explicitly surfaced in the tested browser path for active seeded users
- No KYC block was hit in the successful B2C run

### Rates
- B2C: PASS
  - shipment reached `ready_for_rates`
  - offers fetched successfully
- B2B: BLOCKED before rates because draft submit fails

### Offers
- B2C: PASS
  - comparison page is real
  - multiple offers shown with carrier/service/price/delivery tags
- B2B: BLOCKED before offers because draft submit fails

### Selection
- B2C: PASS
  - one offer selected successfully
  - browser redirected to declaration step
- B2B: BLOCKED before offer selection because draft submit fails

### DG declaration
- B2C: PASS
  - `DG=no` without disclaimer is rejected with a clear message
  - `DG=no` with disclaimer acceptance succeeds and moves shipment to `declaration_complete`
- `DG=yes` browser path: NOT VERIFIED end-to-end because B2B request flow is blocked before reaching declaration, and the successful B2C run was preserved for downstream checks

### Wallet preflight if visible
- NOT EXPOSED in browser routes
- Current browser flow stops after declaration with copy indicating readiness for a later payment stage
- This appears to remain API-only / not yet productized in browser UX

### Issuance if visible
- NOT EXPOSED in browser routes
- No browser-visible carrier creation action was found in the tested B2C/B2B pages

### Documents
- B2C: PARTIAL PASS
  - documents page exists and is accessible for own shipment
  - browser-created shipment had no artifacts because issuance was not reached
- B2B: not reached because no successful browser issuance path

### Timeline
- B2C: PASS as foundation
  - shipment show page exists
  - current normalized status and timeline area are visible
  - browser-created shipment shows `unknown` / no events before issuance, which is consistent with not reaching carrier creation
- B2B: not reached because request flow blocked before issuance

### Notifications
- Notification surface exists and is accessible in browser
- No shipment notifications were observed in this run because browser flow did not reach purchased/documents/tracking events that trigger canonical notification fanout

## 8. Security / Isolation Findings
- B2C user opening B2B dashboard: branded `403` wrong-portal guidance, no raw framework page
- B2C user opening `/admin`: branded `403`, no internal leakage
- B2B user opening B2C dashboard: branded `403` wrong-portal guidance
- Internal support opening `/admin`: branded `403`, not a raw forbidden page
- Cross-tenant shipment access in browser:
  - `e2e.b.individual@example.test` opening shipment `a1514b5a-3a16-4312-a064-23afa6b691ef` owned by `e2e.a.individual@example.test`
  - result: `404 Not Found`
- No cross-tenant leakage was observed in shipment pages, timeline view, or notifications surface during this run

## 9. Top Blockers Found
1. B2B shipment draft submit is broken for external organization users.
   - Evidence: both `organization_owner` and `staff` reach `/b2b/shipments/create` and then hit a branded `500` page after valid submit to `/b2b/shipments`.
   - Impact: organization browser demo path is not usable.
2. Browser wallet preflight / issuance step is not exposed.
   - Evidence: B2C flow reaches `declaration_complete`, but no browser route/button for wallet reservation or carrier issuance is present.
   - Impact: full browser end-to-end purchase/issuance/documents/notifications cannot be completed from the UI.
3. As a result of missing browser issuance, shipment notifications for purchased/documents/tracking events could not be observed in a real browser-created shipment.

## 10. Top Polish Issues Found
1. Shipment create pages contain garbled/mojibake text in later workflow step labels.
2. Login forms do not expose clean accessibility labels; automation had to fall back to DOM selectors/placeholders.
3. Portal chooser still logs a missing logo asset (`/images/logo.png`).
4. B2B dashboard/sidebar still uses compact codes such as `HOME`, `SH`, `USR`, `ROL`, `RPT`, `DEV`, `INT`, `KEY`, `WH` rather than fully human-friendly labels.
5. B2C validation feedback is mostly native browser validation, not rich inline product guidance.

## 11. Final Verdict
NOT READY FOR DEMO

Reasoning:
- The B2C browser request flow is materially usable through draft -> rates -> offers -> selection -> declaration.
- The B2B browser request flow is broken at a core step for organization users.
- The browser still lacks wallet preflight and issuance UX, so a true full user-journey demo cannot be completed end-to-end from the browser.

## 12. Screenshot Folder
- `docs/browser_e2e_screenshots/20260317/`

Key screenshots saved include:
- `01_portal_chooser.png`
- `02_b2c_individual_dashboard.png`
- `03_b2c_wrong_portal_b2b.png`
- `04_b2c_admin_denial.png`
- `05_b2b_owner_dashboard.png`
- `06_b2b_wrong_portal_b2c.png`
- `09_b2b_suspended_denied.png`
- `10_b2b_disabled_denied.png`
- `11_internal_super_admin.png`
- `12_internal_support.png`
- `13_b2c_shipment_create.png`
- `14_b2c_validation_errors.png`
- `15_b2c_after_draft_submit.png`
- `17_b2c_offers_after_fetch.png`
- `18_b2c_offer_selected.png`
- `19_b2c_declaration_missing_disclaimer.png`
- `20_b2c_declaration_complete.png`
- `21_b2b_owner_after_submit_actual.png`
- `24_b2b_admin_create_page.png`
- `25_b2b_staff_create_page.png`
- `26_cross_tenant_b2c_shipment_denied.png`
- `27_b2c_shipment_show_timeline.png`
- `28_b2c_notifications_surface.png`
- `29_internal_super_admin_dashboard.png`
- `30_internal_tenant_context.png`
- `31_internal_context_selected.png`
- `32_internal_users.png`
- `32_internal_roles.png`
- `32_internal_reports.png`
- `33_internal_support_admin_denial.png`
- `34_internal_support_context.png`
- `35_b2c_documents_page_empty.png`
- `36_b2b_staff_submit_result.png`
