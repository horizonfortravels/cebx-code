# Workflow Page Mapping

## Purpose
This document maps the canonical shipment workflow to the current browser and API surfaces and classifies each step as:
- `real page`
- `partial page`
- `API only`
- `missing UX entirely`

## Terminology Lock
- B2C = the platform portal and browser flows for `individual` external accounts only.
- B2B = the platform portal and browser flows for `organization` external accounts only.
- Internal = a separate internal portal for platform staff only.
- Both B2C and B2B consume the platform carrier network; neither owns carrier integrations.

## Current Browser/API Evidence
### B2C (`individual` external accounts)
- [routes/web_b2c.php](c:/Users/Ahmed/Desktop/cebx-code/routes/web_b2c.php)
- [app/Http/Controllers/Web/PortalWorkspaceController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Web/PortalWorkspaceController.php)

### B2B (`organization` external accounts)
- [routes/web_b2b.php](c:/Users/Ahmed/Desktop/cebx-code/routes/web_b2b.php)
- [app/Http/Controllers/Web/PortalWorkspaceController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Web/PortalWorkspaceController.php)

### Internal
- [app/Http/Controllers/Web/InternalAdminWebController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Web/InternalAdminWebController.php)

### API
- [routes/api_external.php](c:/Users/Ahmed/Desktop/cebx-code/routes/api_external.php)
- [routes/api_internal.php](c:/Users/Ahmed/Desktop/cebx-code/routes/api_internal.php)

## Workflow Mapping
| Workflow Step | B2C Surface | B2B Surface | Internal Surface | API Surface | Current Coverage | Notes / Evidence |
|---|---|---|---|---|---|---|
| A. Create shipment request | `/b2c/shipments` list page exists | `/b2b/shipments` list page exists | none dedicated | `POST /api/v1/shipments`, `POST /api/v1/shipments/from-order/{orderId}` | `partial page` + `API only` | Create pages remain placeholder-like in [routes/web_b2c.php](c:/Users/Ahmed/Desktop/cebx-code/routes/web_b2c.php) and [routes/web_b2b.php](c:/Users/Ahmed/Desktop/cebx-code/routes/web_b2b.php) |
| B. Validation | no dedicated step | no dedicated step | none | `POST /api/v1/shipments/{id}/validate` | `API only` | Browser workflow step missing |
| C. KYC & usage restriction | no inline shipment step | no inline shipment step | no internal compliance console in browser flow | KYC endpoints under external API currently exist | `partial page` / `API only` | KYC exists in API, not integrated into shipment UX |
| D. Fetch net rates | none | none | none | rates endpoints in [app/Http/Controllers/Api/V1/RateController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Api/V1/RateController.php) | `API only` | |
| E. Calculate retail rates | none | none | none | pricing/rate services | `API only` | Pricing breakdown persists in backend, not in browser UX |
| F. Show options and select one offer | none | none | none | quote show/select API | `API only` | No browser comparison/selection flow |
| G. DG / content declaration | none | none | none dedicated | content declaration endpoints | `API only` | No browser declaration or disclaimer step |
| H. Wallet pre-flight | `/b2c/wallet` exists | `/b2b/wallet` exists | internal reports page shows wallet stats only | wallet and billing APIs exist | `partial page` | Wallet pages exist, but shipment-linked hold UX is missing |
| I. Create shipment at carrier | none | none | no internal carrier ops page | carrier issuance endpoints in [app/Http/Controllers/Api/V1/CarrierController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Api/V1/CarrierController.php) | `API only` | No browser issuance confirmation flow |
| J. Receive label & docs | no dedicated docs page | no dedicated docs page | none | label/doc endpoints exist | `API only` / `missing UX entirely` | No dedicated browser docs workspace |
| K. Tracking & notifications | `/b2c/tracking` exists | no rich B2B tracking page identified | none | tracking and notification APIs exist | `real page` for basic B2C tracking, otherwise `partial page` | [app/Http/Controllers/Api/V1/TrackingController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Api/V1/TrackingController.php) covers timeline/search/dashboard via API |
| L. Final outputs | shipment/tracking pages only | shipment/orders pages only | reports/read-only internal pages | shipment, tracking, docs APIs | `partial page` | No single consolidated post-purchase confirmation UX |

## Required Mapping Decisions
- Shipment request creation:
  - B2C/B2B list pages exist
  - create pages are still placeholder-like or not full workflow pages
  - API create endpoints are real
- Validation:
  - API exists
  - browser workflow step is missing
- KYC restriction:
  - API exists partially
  - browser inline gating is missing
- Rates and offer selection:
  - API exists
  - browser selection flow is missing
- DG declaration:
  - API exists
  - browser flow is missing
- Wallet pre-flight:
  - wallet pages exist
  - shipment-linked hold UX is missing
- Carrier issuance:
  - API exists
  - browser issuance/confirmation flow is missing
- Labels/docs:
  - API exists
  - browser doc workspace is missing or only implicit
- Tracking:
  - B2C tracking page exists
  - broader shipment timeline UX is only partial

## Current Developer Workspace Is Not Carrier Ownership
Current external developer pages are implemented in:
- [routes/web_b2b.php](c:/Users/Ahmed/Desktop/cebx-code/routes/web_b2b.php)
- [app/Http/Controllers/Web/PortalWorkspaceController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Web/PortalWorkspaceController.php)

Interpretation locked in by this document:
- if these pages are retained, they are merchant-facing platform API access only for `organization` accounts in the B2B portal
- they are not evidence that external users own carrier integrations
- carrier activation/configuration belongs to future internal carrier-management surfaces only

## Current Database Drift
Current page naming and persona naming still lag the target model because:
- current browser docs/tests/pages still reference `tenant_owner` and `api_developer`
- target docs must use only canonical role names

Notable evidence:
- [docs/test-accounts.md](c:/Users/Ahmed/Desktop/cebx-code/docs/test-accounts.md)
- [tests/Feature/Web/ApiDeveloperWorkspaceTest.php](c:/Users/Ahmed/Desktop/cebx-code/tests/Feature/Web/ApiDeveloperWorkspaceTest.php)
- [tests/e2e/login-and-navigation.smoke.spec.js](c:/Users/Ahmed/Desktop/cebx-code/tests/e2e/login-and-navigation.smoke.spec.js)
