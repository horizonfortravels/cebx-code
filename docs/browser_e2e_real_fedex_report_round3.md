# Browser E2E Real FedEx Report — Round 3

## 1. Environment Used
- Date: 2026-03-25
- Base URL: `http://127.0.0.1:8000`
- Seed mode: `SEED_E2E_MATRIX=true php artisan migrate:fresh --seed`
- Carrier mode: real FedEx Sandbox-backed browser path
- Browser method: Playwright-driven browser verification
- Working rule for this run: use funded E2E personas only for external shipment journey proof

## 2. Funded Personas Actually Used
- B2C funded individual: `e2e.a.individual@example.test`
- B2B funded organization owner: `e2e.c.organization_owner@example.test`
- B2B same-tenant organization admin: `e2e.c.organization_admin@example.test`
- B2B same-tenant staff: `e2e.c.staff@example.test`
- Suspended external: `e2e.c.suspended@example.test`
- Disabled external: `e2e.c.disabled@example.test`
- Internal super admin: `e2e.internal.super_admin@example.test`
- Internal support: `e2e.internal.support@example.test`
- Cross-tenant security check owner: `e2e.d.organization_owner@example.test`
- Internal carrier_manager: NOT SEEDED

## 3. Wallet Currency And Balance Confirmed Before Browser Flow
Confirmed from the E2E matrix seeder output and then proven in-browser by successful wallet preflight on browser-created shipments.

| Persona | Account | Seeded wallet currency | Seeded available balance | Browser preflight result |
|---|---|---:|---:|---|
| `e2e.a.individual@example.test` | funded individual account A | `USD` | `1000.00` | PASS |
| `e2e.c.organization_owner@example.test` | funded organization account C | `USD` | `1000.00` | PASS |

## 4. Browser Flows Executed
- Portal availability checks: `/login`, `/b2c/login`, `/b2b/login`
- B2C funded browser-created US→US shipment through real FedEx offers, declaration, wallet preflight, issuance, docs, timeline, notifications
- B2B funded owner browser-created US→US shipment through real FedEx offers, declaration, wallet preflight, issuance, docs, timeline, notifications
- Same-tenant B2B admin visibility checks on issued shipment
- Same-tenant B2B staff visibility checks on issued shipment
- Suspended and disabled external login denial checks
- Internal super admin login and landing checks
- Internal support login and landing checks
- External `/admin` denial check
- Cross-tenant shipment/doc visibility checks using a different organization owner
- Cross-tenant notification leakage check

## 5. Persona Results
| Persona | Login | Landing | Pages Tested | Allowed Flows | Denied Flows | UX Notes |
|---|---|---|---|---|---|---|
| B2C funded individual | PASS | `/b2c/dashboard` | create, offers, declaration, show, docs, notifications | full shipment journey to purchased | B2B area denied | FedEx services still surface in English service labels |
| B2B funded organization_owner | PASS | `/b2b/dashboard` | create, offers, declaration, show, docs, notifications | full shipment journey to purchased | `/admin` denied | Post-issuance page is coherent and actionable |
| B2B organization_admin | PASS | `/b2b/dashboard` | dashboard, issued shipment show | same-tenant issued shipment visibility incl. docs/timeline/notifications | no cross-tenant access tested from same persona | current role visibility looked appropriate |
| B2B staff | PASS | `/b2b/dashboard` | dashboard, issued shipment show | same-tenant issued shipment visibility incl. docs/timeline/notifications | no cross-tenant access tested from same persona | current role visibility looked appropriate |
| Suspended external | PASS as denial | stayed on login | `/b2b/login` | none | login denied with readable message | good UX |
| Disabled external | PASS as denial | stayed on login | `/b2b/login` | none | login denied with readable message | good UX |
| Internal super_admin | PASS | `/admin` | admin dashboard | internal admin landing worked | external-only portals not exercised | correct internal landing |
| Internal support | PASS | `/internal` | internal workspace | support-oriented internal landing worked | not dropped on raw forbidden page | correct internal landing |
| Internal carrier_manager | NOT SEEDED | n/a | n/a | n/a | n/a | seed gap only |
| Cross-tenant org D owner | PASS | `/b2b/dashboard` | target shipment URL, documents URL, notifications | own org dashboard + notifications | org C shipment/details/docs returned 404 | correct tenant isolation |

## 6. Shipment Journey Coverage

### A. B2C funded individual — browser-created US→US shipment
- Shipment draft id: `a1629b92-0b27-4ff9-9855-daf8cd37932c`
- Shipment reference: `SHP-20260001`
- Tracking number after issuance: `794792329364`

| Step | Result | Evidence |
|---|---|---|
| Login | PASS | landed in B2C dashboard |
| Create draft | PASS | draft saved and moved to ready-for-rates |
| Validation | PASS | valid submit accepted with success banner |
| Real FedEx offers visible | PASS | `FedEx` only |
| Exact carrier/services shown | PASS | `FEDEX_GROUND`, `FEDEX_EXPRESS_SAVER`, `FEDEX_2_DAY`, `FEDEX_2_DAY_AM`, `STANDARD_OVERNIGHT`, `PRIORITY_OVERNIGHT`, `FIRST_OVERNIGHT` |
| Select one offer | PASS | first FedEx offer selected |
| DG declaration | PASS | `DG = no` + legal disclaimer saved |
| Wallet preflight | PASS | hold created successfully |
| Carrier issuance | PASS | real FedEx issuance succeeded |
| Purchased state visible | PASS | page shows normalized status `purchased` and Arabic issued copy |
| Tracking/AWB visible | PASS | `794792329364` |
| Documents visible | PASS | label shown inline and downloadable |
| Timeline visible | PASS | `shipment.purchased`, `carrier.documents_available` |
| Notifications visible | PASS | inline shipment notifications + `/notifications` page |

### B. B2B funded organization_owner — browser-created US→US shipment
- Shipment draft id: `a1629bfb-1d5a-451a-b75a-099a4b4d521b`
- Shipment reference: `SHP-20260002`
- Tracking number after issuance: `794792325690`

| Step | Result | Evidence |
|---|---|---|
| Login | PASS | landed in B2B dashboard |
| Create draft | PASS | draft saved and moved to ready-for-rates |
| Validation | PASS | valid submit accepted with success banner |
| Real FedEx offers | PASS | `FedEx` only |
| Exact carrier/services shown | PASS | `FEDEX_GROUND`, `FEDEX_EXPRESS_SAVER`, `FEDEX_2_DAY`, `FEDEX_2_DAY_AM`, `STANDARD_OVERNIGHT`, `PRIORITY_OVERNIGHT`, `FIRST_OVERNIGHT` |
| Select one offer | PASS | first FedEx offer selected |
| DG declaration | PASS | `DG = no` + legal disclaimer saved |
| Wallet preflight | PASS | hold created successfully, `21.17 USD` reserved |
| Carrier issuance | PASS | real FedEx issuance succeeded |
| Purchased state visible | PASS | page shows normalized status `purchased` and Arabic issued copy |
| Tracking/AWB visible | PASS | `794792325690` |
| Documents visible | PASS | docs page shows stored label `label_SHP-20260002.pdf` |
| Timeline visible | PASS | `shipment.purchased`, `carrier.documents_available` |
| Notifications visible | PASS | inline shipment notifications + `/notifications` page |

## 7. Internal And Security Checks
- `/admin` for external B2B owner: PASS as denial with readable 403 guidance
- B2C user opening B2B area: PASS as wrong-portal denial with readable guidance
- Cross-tenant B2B owner (account D) opening org C shipment details: PASS as `404`
- Cross-tenant B2B owner (account D) opening org C shipment documents: PASS as `404`
- Cross-tenant notification leakage: PASS; org D notifications page showed no org C notifications
- Raw framework/debug pages: not observed in this round

## 8. Explicit Statement On The Prior Blocker
No. The only prior blocker was **not** persona/seed mismatch alone.

Two separate issues existed across the previous failed rounds:
1. local FedEx OAuth runtime drift caused the app to resolve the wrong OAuth host
2. proof runs then used non-funded generic/demo personas, which made wallet preflight fail with `ERR_WALLET_NOT_AVAILABLE`

In this round, with the local OAuth runtime corrected and the funded E2E personas used consistently, the browser journeys completed successfully.

## 9. Top Blockers
- No blocker remained in the targeted funded E2E browser path for this round.

## 10. Top Polish Issues
1. FedEx service names still appear in English on Arabic workflow surfaces.
2. Raw canonical status tokens such as `purchased` still appear alongside Arabic labels.
3. Generic 404 page for cross-tenant access is technically correct but not branded/readable for end users.

## 11. Final Verdict
READY WITH MINOR GAPS
