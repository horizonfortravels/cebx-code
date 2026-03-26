# Browser E2E Real FedEx Report Final

- Date: 2026-03-25
- Workspace: `c:\Users\Ahmed\Desktop\cebx-code`
- Branch: `phase-f1-documents`
- Base URL: `http://127.0.0.1:8000`
- Seed mode: `SEED_E2E_MATRIX=true`
- Scope: final demo-readiness verification on the live workspace exactly as executed

## Personas Used
- `e2e.a.individual@example.test` (funded B2C)
- `e2e.c.organization_owner@example.test` (funded B2B owner)
- `e2e.c.organization_admin@example.test`
- `e2e.c.staff@example.test`
- `e2e.c.suspended@example.test`
- `e2e.c.disabled@example.test`
- `e2e.internal.super_admin@example.test`
- `e2e.internal.support@example.test`
- `e2e.d.organization_owner@example.test` (cross-tenant security actor)
- `internal carrier_manager`: NOT SEEDED in `database/seeders/E2EUserMatrixSeeder.php`

## Funded Wallet Confirmation
- B2C funded wallet: `USD 1000.00`, status `active`
- B2B funded wallet: `USD 1000.00`, status `active`
- Both were confirmed before browser actions and then validated again by successful wallet preflight in the browser journey.

## A) B2C funded individual full browser-created shipment journey
- Login: PASS
- Create shipment draft: PASS
- Validation: PASS
- Real FedEx offers: PASS
- Select one offer: PASS
- DG declaration: PASS
- Wallet preflight: PASS
- Carrier issuance: PASS
- Purchased state visible: PASS
- Tracking/AWB visible: PASS
- Document preview works in new tab: PASS
- Document download works: PASS
- Timeline visible: PASS
- Notifications visible: PASS

Evidence summary:
- Shipment reference: `SHP-20260001`
- Tracking/AWB: `794792437273`
- Offers shown: `FedEx` only
- Services shown: `FEDEX_GROUND`, `FEDEX_EXPRESS_SAVER`, `FEDEX_2_DAY`, `FEDEX_2_DAY_AM`, `STANDARD_OVERNIGHT`, `PRIORITY_OVERNIGHT`, `FIRST_OVERNIGHT`
- Downloaded label file: `label_SHP-20260001.pdf`
- Downloaded file signature: `%PDF`

## B) B2B funded organization_owner full browser-created shipment journey
- Login: PASS
- Create shipment draft: PASS
- Real FedEx offers: PASS
- Select one offer: PASS
- DG declaration: PASS
- Wallet preflight: PASS
- Carrier issuance: PASS
- Purchased state visible: PASS
- Tracking/AWB visible: PASS
- Document preview works in new tab: PASS
- Document download works: PASS
- Timeline visible: PASS
- Notifications visible: PASS

Evidence summary:
- Shipment reference: `SHP-20260002`
- Tracking/AWB: `794792437950`
- Offers shown: `FedEx` only
- Services shown: `FEDEX_GROUND`, `FEDEX_2_DAY_AM`, `STANDARD_OVERNIGHT`, `PRIORITY_OVERNIGHT`, `FIRST_OVERNIGHT`
- Downloaded label file: `label_SHP-20260002.pdf`
- Downloaded file signature: `%PDF`

## C) Same-tenant role coverage
- `organization_admin`
  - Login: PASS
  - Issued shipment visibility: PASS
  - Docs visibility: PASS
  - Timeline visibility: PASS
  - Notifications visibility: PASS
- `staff`
  - Login: PASS
  - Issued shipment visibility: PASS
  - Docs visibility: PASS
  - Timeline visibility: PASS
  - Notifications visibility: PASS

## D) Access-control coverage
- Suspended external denied: PASS
  - Browser message: `تم تعليق هذا الحساب مؤقتًا. تواصل مع الدعم أو مدير الحساب لمراجعة حالة الوصول.`
- Disabled external denied: PASS
  - Browser message: `تم إيقاف هذا الحساب حاليًا. تواصل مع الدعم أو مدير الحساب لإعادة التفعيل.`
- External `/admin` denied: PASS
  - Returned branded Arabic `403` page
  - Heading: `هذه الصفحة مخصصة لفريق التشغيل الداخلي في المنصة`
- Internal super_admin lands correctly: PASS
  - Landed on `/admin`
- Internal support lands correctly: PASS
  - Landed on `/internal`
- Cross-tenant org D -> org C shipment details denial: PASS
  - HTTP semantic observed in browser: `404`
- Cross-tenant org D -> org C shipment documents denial: PASS
  - HTTP semantic observed in browser: `404`
- Cross-tenant org D -> org C document preview denial: PASS
  - HTTP semantic observed in browser: `404`
- Cross-tenant org D notifications leakage: PASS
  - `/notifications` opened for org D and showed `لا توجد إشعارات`
  - No org C shipment/event leakage observed
- No raw framework/debug leakage: FAIL
  - Cross-tenant `404` is still rendered as a generic raw page with title `Not Found` and body `404 / Not Found`
  - This is not a debug stack trace, but it is not branded or portal-safe

## E) Portal polish observations (observed exactly as rendered)
- Shipment pages Arabic text now renders correctly on the tested shipment surfaces.
- Raw service/status tokens are still visible on Arabic shipment surfaces in places:
  - Offer/service labels still appear as FedEx English/branded names such as `FedEx Ground®`
  - Issued shipment status shows Arabic `تم إصدار الشحنة` but also raw token `purchased`
  - Timeline shows Arabic event labels, but raw event ids also remain visible, such as `shipment.purchased` and `carrier.documents_available`
  - Document metadata still includes raw/English values such as `fedex / pdf`
- Cross-tenant `404` currently renders as:
  - page title: `Not Found`
  - body text: `404` and `Not Found`
  - no tenant/resource details leaked, but the page is not branded in the external portal style

## Top Blockers
- No workflow blocker remained for the funded B2C/B2B external demo journeys in this run.

## Top Polish Issues
1. Cross-tenant `404` remains a generic raw `Not Found` page instead of a branded portal-safe Arabic error surface.
2. Raw service names remain visible on Arabic shipment surfaces (`FedEx Ground®`, similar service labels).
3. Raw normalized status/event tokens remain visible on Arabic shipment surfaces (`purchased`, `shipment.purchased`, `carrier.documents_available`).
4. Document metadata still shows raw English tokens like `fedex / pdf`.

## Final Verdict
READY WITH MINOR GAPS