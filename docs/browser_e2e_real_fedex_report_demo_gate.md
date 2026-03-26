# Browser E2E Demo-Gate Report

Date: 2026-03-26
Workspace: `C:\Users\Ahmed\Desktop\cebx-code`
HEAD: `37c57260`
Mode: current live workspace only, no code changes, no commit
Base URL: `http://127.0.0.1:8000`

## Scope Confirmation
In scope:
- Browser verification on current live workspace after `37c57260`
- Seeded E2E matrix personas only
- Real FedEx-backed browser shipment journeys

Out of scope local artifacts left untouched:
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
- `carrier_manager`: not seeded

Password used for seeded personas: `Password123!`

## Runtime Preflight
- `SEED_E2E_MATRIX=true php artisan migrate:fresh --seed` -> PASS
- PHP server restarted -> PASS
- `/login` -> 200
- `/b2c/login` -> 200
- `/b2b/login` -> 200
- `/admin/login` -> 200
- `/internal/login` -> not present in current workspace; internal support login still works via `/admin/login` and lands on `/internal`

## Funded Wallet Confirmation
- B2C funded wallet: `e2e.a.individual@example.test` -> `USD 1000.00`, `active`
- B2B funded wallet: `e2e.c.organization_owner@example.test` -> `USD 1000.00`, `active`

## A) B2C Funded Individual Full Browser-Created Shipment Journey
Persona: `e2e.a.individual@example.test`
Shipment: `SHP-20260001`
Shipment ID: `a164bc40-354a-4080-a732-323ddd7b5d5a`
Tracking: `794792812888`

| Step | Result | Notes |
|---|---|---|
| Login | PASS | Landed in B2C portal |
| Create shipment draft | PASS | Valid US->US draft created |
| Validation | PASS | Draft saved and advanced to ready-for-rates |
| Real FedEx offers | PASS | Real offers loaded |
| Select one offer | PASS | First FedEx offer selected |
| DG declaration | PASS | `DG=no` + legal disclaimer saved |
| Wallet preflight | PASS | Wallet reservation created successfully |
| Carrier issuance | PASS | FedEx creation succeeded |
| Purchased state visible | PASS | Issued state visible on shipment page |
| Tracking/AWB visible | PASS | `794792812888` |
| Document preview works in new tab | PASS | Inline PDF preview opened in a new tab |
| Document download works | PASS | Suggested filename `label_SHP-20260001.pdf` |
| Timeline visible | PASS | Issuance + documents events visible |
| Notifications visible | PASS | Shipment notifications visible on shipment page |

Observed localized offer labels:
- `فيدكس / فيدكس الأرضي`
- `فيدكس / فيدكس إكسبريس سيفر`
- `فيدكس / فيدكس خلال يومين`
- `فيدكس / فيدكس صباح اليوم الثاني`
- `فيدكس / فيدكس الليلة التالية القياسي`
- `فيدكس / فيدكس الليلة التالية بالأولوية`
- `فيدكس / فيدكس الليلة التالية الأولى`

## B) B2B Funded organization_owner Full Browser-Created Shipment Journey
Persona: `e2e.c.organization_owner@example.test`
Shipment: `SHP-20260002`
Shipment ID: `a164bdd8-84c3-4cb2-b2eb-2a6860238e39`
Tracking: `794792813531`

| Step | Result | Notes |
|---|---|---|
| Login | PASS | Landed in B2B portal |
| Create shipment draft | PASS | Valid US->US draft created |
| Real FedEx offers | PASS | Real offers loaded |
| Select one offer | PASS | First FedEx offer selected |
| DG declaration | PASS | `DG=no` + legal disclaimer saved |
| Wallet preflight | PASS | Wallet reservation created successfully |
| Carrier issuance | PASS | FedEx creation succeeded |
| Purchased state visible | PASS | Issued state visible on shipment page |
| Tracking/AWB visible | PASS | `794792813531` |
| Document preview works in new tab | PASS | Inline PDF preview opened in a new tab |
| Document download works | PASS | Suggested filename `label_SHP-20260002.pdf` |
| Timeline visible | PASS | Issuance + documents events visible |
| Notifications visible | PASS | Shipment notifications visible on shipment page and `/notifications` |

Observed declaration/localization labels:
- carrier: `فيدكس`
- service: `فيدكس الأرضي`
- timeline location: `فيدكس`

## C) Same-Tenant Role Coverage
| Persona | Result | Notes |
|---|---|---|
| `e2e.c.organization_admin@example.test` | PASS | Login succeeded; issued shipment page visible; docs visible; timeline visible; `/notifications` showed shipment-related notifications |
| `e2e.c.staff@example.test` | PASS | Login succeeded; issued shipment page visible; docs visible; timeline visible; `/notifications` showed shipment-related notifications |

## D) Access-Control Coverage
| Check | Result | Notes |
|---|---|---|
| Suspended external denied | PASS | Readable suspended message shown on B2B login |
| Disabled external denied | PASS | Readable disabled message shown on B2B login |
| External `/admin` denied | PASS | External user saw branded 403-safe page, no internal leakage |
| Internal `super_admin` landing | PASS | `/admin/login` -> `/admin` |
| Internal `support` landing | PASS | `/admin/login` -> `/internal` |
| Cross-tenant shipment/details denied | PASS | Branded 404 |
| Cross-tenant documents denied | PASS | Branded 404 |
| Cross-tenant document preview denied | PASS | Branded 404 |
| Cross-tenant notifications denied | PASS | No org C notifications leaked into org D `/notifications` |
| No raw framework/debug leakage | PASS | Only branded 403/404 surfaces observed |

## E) Polish Confirmation
| Check | Result | Notes |
|---|---|---|
| Arabic shipment pages render correctly | PASS | Shipment pages, offers, declaration, issued pages rendered readable Arabic |
| Raw service names gone where localized mappings exist | PASS | Offers/declaration showed localized carrier/service labels |
| Raw status/event tokens gone where localized mappings exist | PASS | Issued shipment and timeline labels were localized |
| Timeline location no longer shows raw `FEDEX` | PASS | Rendered as `فيدكس` |
| Branded cross-tenant 404 still renders correctly | PASS | Branded Arabic 404 page shown |
| Document preview/download UI remains correct | PASS | Both `عرض المستند` and `تنزيل المستند` worked |

## Artifacts
Screenshots folder:
- `docs/browser_e2e_real_fedex_screenshots_demo_gate/20260326/`

Saved screenshots:
- `b2c-issued.png`
- `b2c-preview.png`
- `b2b-declaration.png`
- `b2b-issued.png`
- `b2b-preview.png`
- `cross-tenant-404.png`

Saved label PDFs:
- `b2c-label.pdf`
- `b2b-label.pdf`

## Top Blockers
- None observed in the funded external demo journey on this workspace.

## Top Polish Issues
- No meaningful shipment-portal polish blocker remains in the scope of this demo gate.
- Exact note: document metadata still displays `PDF` in Latin script, but this did not affect usability or the demo flow.
- Exact note: there is no dedicated `/internal/login` route in the current workspace; internal support login is routed through `/admin/login` and lands correctly on `/internal`.

## Final Verdict
READY FOR DEMO

## Recommendation
Freeze for demo / release candidate
