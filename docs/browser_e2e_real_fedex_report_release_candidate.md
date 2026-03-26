# Browser E2E Release Candidate Report

Date: 2026-03-26
Workspace: current live workspace at `C:\Users\Ahmed\Desktop\cebx-code`
Execution source of truth: live workspace only
Server base URL: `http://127.0.0.1:8000`

## Scope Guard
Confirmed before execution that these unrelated local files remained outside this verification scope and were not modified by this run:
- `resources/views/pages/auth/login-admin.blade.php`
- `resources/views/pages/auth/login-b2b.blade.php`
- `resources/views/pages/auth/login-b2c.blade.php`
- `docs/browser_e2e_real_fedex_report_final.md`
- `docs/browser_e2e_real_fedex_screenshots_final/`
- `storage/app/carrier-documents/...`

## Personas Used
- `e2e.a.individual@example.test`
- `e2e.c.organization_owner@example.test`
- `e2e.c.organization_admin@example.test`
- `e2e.c.staff@example.test`
- `e2e.c.suspended@example.test`
- `e2e.c.disabled@example.test`
- `e2e.internal.super_admin@example.test`
- `e2e.internal.support@example.test`
- `e2e.d.organization_owner@example.test`
- `carrier_manager`: NOT SEEDED

Password used for seeded E2E personas:
- `Password123!`

## Funded Wallet Confirmation
Seed output confirmed deterministic funded E2E wallets in `USD` for:
- B2C account A: `1000.00 USD`
- B2B account C: `1000.00 USD`

Browser/runtime confirmation:
- B2C wallet preflight succeeded on a browser-created `USD` shipment
- B2B wallet preflight succeeded on a browser-created `USD` shipment

## Entry Paths
- `/login` -> PASS
- `/b2c/login` -> PASS
- `/b2b/login` -> PASS
- `/admin/login` -> PASS
- internal users authenticate through `/admin/login`; support lands on `/internal` after login -> PASS

## A) B2C Funded Individual Full Browser-Created Shipment Journey
Actor: `e2e.a.individual@example.test`
Shipment reference: `SHP-20260001`
Shipment id: `a16497d5-45ba-412f-b895-5b0e4400e4a9`
Tracking: `794792775880`

| Step | Result | Notes |
|---|---|---|
| login | PASS | landed in B2C dashboard |
| create shipment draft | PASS | US->US browser-created shipment saved |
| validation | PASS | success message shown after draft validation |
| real FedEx offers | PASS | offers fetched from FedEx-backed path |
| select one offer | PASS | first offer selected |
| DG declaration | PASS | `DG = no` + legal declaration accepted |
| wallet preflight | PASS | reservation succeeded |
| carrier issuance | PASS | issuance succeeded |
| purchased state visible | PASS | issued shipment page loaded correctly |
| tracking/AWB visible | PASS | `794792775880` visible |
| document preview works in new tab | PASS | preview URL ended with `.pdf`, content type `application/pdf` |
| document download works | PASS | suggested filename `label_SHP-20260001.pdf`, saved file non-empty, signature `%PDF` |
| timeline visible | PASS | issuance and documents events visible |
| notifications visible | PASS | shipment-issued and documents-available notifications visible on shipment page |

Observed FedEx services on offers page:
- `FedEx Ground®`
- `FedEx Express Saver®`
- `FedEx 2Day®`
- `FedEx 2Day® AM`
- `FedEx Standard Overnight®`
- `FedEx Priority Overnight®`
- `FedEx First Overnight®`

## B) B2B Funded Organization Owner Full Browser-Created Shipment Journey
Actor: `e2e.c.organization_owner@example.test`
Shipment reference: `SHP-20260002`
Shipment id: `a16498f3-d39a-435a-bbf5-9d63ab31df72`
Tracking: `794792776875`

| Step | Result | Notes |
|---|---|---|
| login | PASS | landed in B2B dashboard |
| create shipment draft | PASS | US->US browser-created shipment saved |
| real FedEx offers | PASS | offers fetched from FedEx-backed path |
| select one offer | PASS | first offer selected |
| DG declaration | PASS | `DG = no` + legal declaration accepted |
| wallet preflight | PASS | reservation succeeded |
| carrier issuance | PASS | issuance succeeded |
| purchased state visible | PASS | issued shipment page loaded correctly |
| tracking/AWB visible | PASS | `794792776875` visible |
| document preview works in new tab | PASS | preview URL ended with `.pdf`, content type `application/pdf` |
| document download works | PASS | suggested filename `label_SHP-20260002.pdf`, saved file non-empty, signature `%PDF` |
| timeline visible | PASS | issuance and documents events visible |
| notifications visible | PASS | shipment-issued and documents-available notifications visible on shipment page |

## C) Same-Tenant Role Coverage
| Persona | Result | Notes |
|---|---|---|
| `organization_admin` | PASS | login succeeded; same-tenant issued shipment page, documents, timeline, and notifications all visible |
| `staff` | PASS | login succeeded; same-tenant issued shipment page, documents, timeline, and notifications all visible |

## D) Access-Control Coverage
| Check | Result | Notes |
|---|---|---|
| suspended external denied | PASS | readable suspended message on B2B login page |
| disabled external denied | PASS | readable disabled message on B2B login page |
| external `/admin` denied | PASS | branded Arabic 403 page, no admin access |
| internal `super_admin` landing | PASS | landed on `/admin` |
| internal `support` landing | PASS | landed on `/internal` |
| cross-tenant shipment details denied | PASS | branded Arabic 404 |
| cross-tenant documents denied | PASS | branded Arabic 404 |
| cross-tenant document preview denied | PASS | branded Arabic 404 |
| cross-tenant notifications leakage | PASS | org D notifications page did not show org C shipment or tracking values |
| no raw framework/debug leakage | PASS | no framework exception/debug page observed in browser surfaces exercised |

## E) Portal Polish Confirmation
| Check | Result | Notes |
|---|---|---|
| shipment pages Arabic text renders correctly | PASS | issued shipment pages and branded error pages rendered correct Arabic |
| raw service names are gone where localized mappings exist | PARTIAL | issued shipment pages showed localized labels such as `فيدكس الأرضي`; offers and declaration surfaces still show raw FedEx English labels and tokens like `FedEx Ground®` and `fedex / FEDEX_GROUND` |
| raw status/event tokens are gone where localized mappings exist | PASS | issued shipment pages showed Arabic labels instead of raw canonical event/status ids |
| cross-tenant 404 is branded and portal-safe | PASS | branded Arabic 404 page rendered without tenant/resource leakage |

Additional exact observations from current rendering:
- issued shipment document metadata now appears as `فيدكس / PDF`
- issued shipment pages show localized status text such as `تم الإصدار لدى الناقل`
- issued shipment timeline labels are localized, but the `الموقع` field still shows raw `FEDEX`

## Artifacts
Screenshots folder:
- `docs/browser_e2e_real_fedex_screenshots_release_candidate/20260326/`

Saved artifacts from this run:
- `b2c-issued-shipment.png`
- `b2b-issued-shipment.png`
- `cross-tenant-404.png`
- `label_SHP-20260001.pdf`
- `label_SHP-20260002.pdf`

## Top Blockers
- None in the funded B2C/B2B external demo journey exercised in this run.

## Top Polish Issues
1. B2C/B2B offers pages still show raw FedEx service names and internal token pairs such as `fedex / FEDEX_GROUND`.
2. Declaration pages still show raw English service names such as `FedEx Ground®`.
3. Issued shipment timeline `الموقع` field still renders raw `FEDEX` instead of a localized display label.

## Final Verdict
READY WITH MINOR GAPS
