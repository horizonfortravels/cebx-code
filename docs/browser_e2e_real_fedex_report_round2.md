# Browser E2E Real FedEx Report — Round 2

## Environment Used
- Date: 2026-03-17
- Repo: `c:\Users\Ahmed\Desktop\cebx-code`
- Branch: `phase-f1-documents`
- Base URL: `http://127.0.0.1:8000`
- Seed command: `SEED_E2E_MATRIX=true php artisan migrate:fresh --seed`
- Local server: `php artisan serve --host=127.0.0.1 --port=8000`
- FedEx mode: real sandbox-backed browser offer path enabled

## Users Tested
- B2C individual: `e2e.a.individual@example.test`
- B2B organization_owner: `e2e.c.organization_owner@example.test`
- B2B organization_admin: `e2e.c.organization_admin@example.test`
- B2B staff: `e2e.c.staff@example.test`
- Suspended external: `e2e.c.suspended@example.test`
- Disabled external: `e2e.c.disabled@example.test`
- Internal super_admin: `e2e.internal.super_admin@example.test`
- Internal support: `e2e.internal.support@example.test`
- Internal carrier_manager: not seeded

## Browser Flows Executed
- Portal chooser, B2C login, B2B login, admin login
- Wrong-portal guidance checks
- External admin denial check
- B2C shipment creation attempts:
  - international SA -> US
  - domestic SA -> SA
  - domestic US -> US
- B2B owner shipment creation attempts:
  - international SA -> US
  - domestic US -> US
- B2B owner offer fetch, offer select, DG declaration, wallet preflight, carrier issuance attempt
- B2B admin/staff same-tenant shipment flow visibility checks
- Internal super_admin login and landing
- Internal support login and landing
- Cross-tenant shipment/documents/notifications visibility checks

## Persona Results
### B2C individual
- Login: PASS
- Landing: PASS (`/b2c/dashboard`)
- Wrong portal (`/b2b/dashboard`): PASS, readable guidance page
- External `/admin`: PASS, readable denial page
- Shipment create (international SA -> US): BLOCKED
  - reason: KYC/restriction gate
  - visible reason: `unverified` / `international_restricted`
- Shipment create (domestic SA -> SA): PASS to `ready_for_rates`
- Real FedEx rates (SA -> SA): FAIL
  - visible reason: `ERR_FEDEX_REQUEST_FAILED`
  - message: `FedEx services are not available from this origin ZIP or postal code to this destination ZIP or postal code.`
- Shipment create (domestic US -> US): PASS to `ready_for_rates`
- Real FedEx rates (US -> US): FAIL
  - visible reason: `ERR_FEDEX_REQUEST_FAILED`
  - browser copy: generic unexpected problem message
- Offer comparison / selection / declaration / preflight / issuance: BLOCKED for browser-created B2C shipment because no successful rate quote completed in browser
- Wallet page: PASS (`/wallet` -> `/b2c/wallet`, funded `1,000.00 USD` confirmed in prior wallet smoke)

### B2B organization_owner
- Login: PASS
- Landing: PASS (`/b2b/dashboard`)
- Wrong portal (`/b2c/dashboard`): PASS, readable guidance page
- Shipment create (international SA -> US): BLOCKED
  - reason: KYC/restriction gate
  - visible reason: `unverified` / `international_restricted`
- Shipment create (domestic US -> US): PASS
  - browser-created shipment id: `a151d469-7bf6-4fd4-8b79-47b27c0ae625`
- Real FedEx offers: PASS
  - FedEx-backed offers appeared in browser
  - visible services included `FEDEX_GROUND`, `FEDEX_2_DAY`, `PRIORITY_OVERNIGHT`, `FIRST_OVERNIGHT`
- Offer comparison page: PASS
- Offer selection: PASS
- DG declaration (`DG = no` + disclaimer): PASS
- Wallet preflight: PASS
- Carrier issuance: FAIL
  - visible reason: `ERR_CARRIER_CREATE_FAILED`
  - persisted backend reason for this browser-created shipment:
    - `RECIPIENTS.STATEORPROVINCECODE.INVALID`
    - `SHIPPER.STATEORPROVINCECODE.INVALID`
- Post-issuance purchased state: FAIL
  - shipment persisted as `failed`, not `purchased`
- Tracking / AWB visible: FAIL
- Documents visible/downloadable: FAIL
- Timeline entries visible: PARTIAL
  - timeline surface is visible
  - no timeline events were present for the browser-created shipment because issuance failed
- Shipment notifications visible: FAIL
  - notifications surface is reachable
  - no shipment notification records were visible for the failed browser-created shipment

### B2B organization_admin
- Login: PASS
- Landing: PASS (`/b2b/dashboard`)
- Create draft page: PASS
- Same-tenant shipment offers page: PASS
- Same-tenant shipment details/timeline page: PASS
- Full end-to-end shipment execution: NOT FULLY RERUN in this round
  - role coverage confirmed for same-tenant browser access to request-flow pages

### B2B staff
- Login: PASS
- Landing: PASS (`/b2b/dashboard`)
- Create draft page: PASS
- Same-tenant shipment offers page: PASS
- Same-tenant shipment details/timeline page: PASS
- Full end-to-end shipment execution: NOT FULLY RERUN in this round
  - role coverage confirmed for same-tenant browser access to request-flow pages

### Suspended external user
- Login: PASS (denial behavior)
- Result: denied at login with readable UX
- Message: account suspended, contact support/account manager

### Disabled external user
- Login: PASS (denial behavior)
- Result: denied at login with readable UX
- Message: account disabled, contact support/account manager

### Internal super_admin
- Login: PASS
- Landing: PASS (`/admin`)
- Tenant-context selection surface: PASS
- Internal admin dashboard visible: PASS

### Internal support
- Login: PASS
- Landing: PASS (`/internal`)
- Result: support-oriented internal home, not dropped onto a raw forbidden page

### Internal carrier_manager
- NOT SEEDED

## Shipment Journey Coverage
### Draft
- Browser draft creation works for B2C and B2B owner on tested routes.

### Validation
- Valid submit path works.
- Empty submit did not surface a clear field-level validation summary in the tested browser pass.

### KYC / Restriction Behavior
- Visible and enforced.
- International browser-created shipments were blocked for both tested external personas with readable restriction messaging.

### Rates
- Real FedEx-backed rates are confirmed in the browser for B2B owner on US -> US scenario.
- B2C scenarios tested in this round did not reach a usable quote:
  - SA -> SA returned no available FedEx services
  - US -> US returned generic FedEx request failure in browser

### Offers
- Offer comparison works and shows FedEx services for B2B owner.

### Declaration
- DG declaration step works for B2B owner with `DG = no` and disclaimer acceptance.

### Wallet Preflight
- Works in browser for funded B2B owner shipment.

### Carrier Issuance
- Browser trigger works but the tested browser-created shipment failed at carrier create.
- Persisted carrier error confirms missing/invalid shipper/recipient state codes for US addresses.

### Documents
- For the browser-created shipment in this run: not visible because issuance failed.

### Timeline
- Canonical shipment timeline surface is present.
- For the browser-created failed shipment, no events were shown.

### Notifications
- Notification center surface is present.
- For the browser-created failed shipment, no shipment-related notifications were visible.

## Security / Isolation
- B2C user opening B2B area: PASS, readable wrong-portal guidance
- B2B owner opening B2C area: PASS, readable wrong-portal guidance
- External user opening `/admin`: PASS, readable denial
- Cross-tenant B2B owner opening another tenant shipment/details/offers/documents: PASS (`404`)
- Cross-tenant notifications leakage: PASS
  - other-tenant notification page did not expose reference `SHP-20260004`
- No raw debug/exception page was observed in browser during this round

## Top Blockers
1. Browser-created B2B shipment still cannot complete carrier issuance in the real FedEx path because required US `stateOrProvinceCode` values are missing from the browser-created shipment payload.
2. B2C browser-created shipment did not complete a usable real FedEx quote in any tested scenario:
   - international blocked by KYC
   - SA -> SA returned no FedEx service availability
   - US -> US returned generic `ERR_FEDEX_REQUEST_FAILED`
3. Because issuance failed for the browser-created B2B shipment, purchased state / AWB / documents / timeline events / shipment notifications could not be verified end-to-end from a browser-created shipment.
4. Failed issuance UI is internally inconsistent: the page shows `ERR_CARRIER_CREATE_FAILED` while the same page also says `تم الإصدار لدى الناقل` and `اكتمل الإصدار`.

## Top Polish Issues
1. Some carrier-originated service notes and failure messages are still shown in English inside Arabic workflow pages.
2. Some raw internal status values such as `unknown` and `failed` are still surfaced alongside Arabic status copy.
3. The tested empty-create submit did not expose a clear browser-visible validation summary in this round.

## Final Verdict
NOT READY FOR DEMO
