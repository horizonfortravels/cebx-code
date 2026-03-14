# Workflow Implementation Phases

## Purpose
This document defines the phased rollout for aligning the codebase with the canonical shipment workflow and corrected business model.

## Phase 0: Terminology And Drift Lock
### Goal
Freeze canonical names, capture implementation drift, and prevent new work from being built on legacy role language.

### Scope
- documentation only
- naming matrix
- drift inventory
- no schema changes

### Outputs
- canonical workflow document
- RBAC mapping
- page mapping
- gap report
- phased plan

## Phase A: Account Model Rules
### Goal
Enforce `individual` vs `organization` correctly.

### Required outcomes
- `individual` = exactly one external user
- no invite/user/role management for `individual`
- organization roles renamed to canonical names
- remove `api_developer` as an external business persona

### Likely files
- [database/seeders/RolesAndPermissionsSeeder.php](c:/Users/Ahmed/Desktop/cebx-code/database/seeders/RolesAndPermissionsSeeder.php)
- [database/seeders/E2EUserMatrixSeeder.php](c:/Users/Ahmed/Desktop/cebx-code/database/seeders/E2EUserMatrixSeeder.php)
- [routes/api_external.php](c:/Users/Ahmed/Desktop/cebx-code/routes/api_external.php)
- [routes/web_b2c.php](c:/Users/Ahmed/Desktop/cebx-code/routes/web_b2c.php)
- [routes/web_b2b.php](c:/Users/Ahmed/Desktop/cebx-code/routes/web_b2b.php)
- user/role/invitation controllers, policies, tests
- [docs/test-accounts.md](c:/Users/Ahmed/Desktop/cebx-code/docs/test-accounts.md)

### PR-sized batches
- A1 rename product-facing role names and update fixtures/tests
- A2 enforce individual single-user rule in API and browser
- A3 organization-only team management rules

## Phase B: Shipment Wizard + Validation + KYC Restrictions
### Goal
Unify draft creation, validation, and KYC restrictions into one guided external flow.

### Required outcomes
- B2C and B2B shipment wizard shells
- integrated validation summary
- KYC restriction feedback before pricing
- explicit failure guidance for blocked shipments

### Likely files
- [app/Http/Controllers/Api/V1/ShipmentController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Api/V1/ShipmentController.php)
- [app/Services/ShipmentService.php](c:/Users/Ahmed/Desktop/cebx-code/app/Services/ShipmentService.php)
- [app/Http/Controllers/Api/V1/KycController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Api/V1/KycController.php)
- new B2C/B2B shipment browser controllers and views

### PR-sized batches
- B1 draft shipment wizard and save/resume
- B2 validation hardening and UX surfacing
- B3 KYC and usage restriction gate integration

## Phase C: Pricing/Rates/Offer Selection + Pricing Breakdown Persistence
### Goal
Expose the existing pricing engine as a first-class user workflow.

### Required outcomes
- browser offer list/comparison UI
- explicit quote lifecycle
- stored pricing breakdown visible for audit/support

### Likely files
- [app/Http/Controllers/Api/V1/RateController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Api/V1/RateController.php)
- [app/Services/RateService.php](c:/Users/Ahmed/Desktop/cebx-code/app/Services/RateService.php)
- [app/Services/PricingEngineService.php](c:/Users/Ahmed/Desktop/cebx-code/app/Services/PricingEngineService.php)
- pricing/rates browser views

### PR-sized batches
- C1 offer-fetch API/browser wiring
- C2 offer comparison and selection UX
- C3 reprice, quote expiry, and pricing-breakdown visibility

## Phase D: DG Declaration + Legal Audit Trail
### Goal
Formalize dangerous-goods and legal disclaimer handling as compliance gates.

### Required outcomes
- browser declaration UI
- legal disclaimer acknowledgement capture
- DG hold / requires-action path
- immutable audit evidence

### Likely files
- [app/Http/Controllers/Api/V1/ContentDeclarationController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Api/V1/ContentDeclarationController.php)
- [app/Services/CarrierService.php](c:/Users/Ahmed/Desktop/cebx-code/app/Services/CarrierService.php)
- declaration models/audit models/browser views

### PR-sized batches
- D1 declaration and disclaimer capture
- D2 audit persistence and evidence model
- D3 DG hold/review workflow

## Phase E: Wallet Pre-flight Hold + Payment Gating + Idempotent Carrier Issuance
### Goal
Block issuance unless funds and prerequisites are satisfied.

### Required outcomes
- explicit reservation/hold step
- clear insufficient-balance UX
- issuance only after payment and declaration gates pass
- idempotent carrier create boundary

### Likely files
- [app/Http/Controllers/Api/V1/WalletBillingController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Api/V1/WalletBillingController.php)
- [app/Services/WalletBillingService.php](c:/Users/Ahmed/Desktop/cebx-code/app/Services/WalletBillingService.php)
- [app/Http/Controllers/Api/V1/CarrierController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Api/V1/CarrierController.php)
- [app/Services/CarrierService.php](c:/Users/Ahmed/Desktop/cebx-code/app/Services/CarrierService.php)

### PR-sized batches
- E1 reservation model and API contract
- E2 browser pre-flight confirmation UX
- E3 issuance finalization and retry/error handling

## Phase F: Label/Docs/Timeline/Tracking/Notifications UX
### Goal
Complete the post-purchase customer experience.

### Required outcomes
- shipment confirmation page
- label/docs workspace
- normalized timeline UI
- notification preferences and delivery events

### Likely files
- [app/Http/Controllers/Api/V1/TrackingController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Api/V1/TrackingController.php)
- [app/Http/Controllers/Api/V1/NotificationController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Api/V1/NotificationController.php)
- B2C/B2B shipment/tracking browser pages

### PR-sized batches
- F1 confirmation and docs workspace
- F2 timeline/tracking UX
- F3 notifications UX and exception visibility

## Phase G: Internal Carrier Management Surfaces And Permissions
### Goal
Move carrier ownership fully inside the platform.

### Required outcomes
- internal-only carrier catalog and status surfaces
- carrier activation/deactivation controls
- rename internal role to `carrier_manager`
- remove external implication of carrier ownership
- if external API keys/webhooks remain, clearly frame them as merchant API access to the platform only

### Likely files
- [app/Http/Controllers/Api/V1/AdminController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Api/V1/AdminController.php)
- [app/Http/Controllers/Api/V1/IntegrationController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Api/V1/IntegrationController.php)
- [routes/api_internal.php](c:/Users/Ahmed/Desktop/cebx-code/routes/api_internal.php)
- [routes/api_external.php](c:/Users/Ahmed/Desktop/cebx-code/routes/api_external.php)
- [app/Http/Controllers/Web/InternalAdminWebController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Web/InternalAdminWebController.php)
- [app/Http/Controllers/Web/PortalWorkspaceController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Web/PortalWorkspaceController.php)
- [routes/web_b2b.php](c:/Users/Ahmed/Desktop/cebx-code/routes/web_b2b.php)

### PR-sized batches
- G1 internal carrier role/permission cleanup
- G2 internal carrier admin API/pages
- G3 external merchant API tools reframing or reduction

## Implementation Defaults Locked
- No future phase may reintroduce `api_developer` as a canonical external business persona.
- No future phase may use carrier configuration as an external account capability.
- Account type remains only `individual` and `organization`.
- Individual-account enforcement is Phase A, not optional follow-up work.
- Carrier activation/configuration is internal-only.
- Merchant API keys/webhooks, if retained externally, represent platform API access only.
- All future schema changes must be forward-only through new migrations.
- Previously-run historical migrations must not be edited again.

## Validation Scenarios For Future Implementation
### Business-model scenarios
- individual account cannot invite another user
- organization account can add team users
- organization owner can manage roles
- staff cannot manage organization ownership
- external user cannot activate carriers
- internal carrier manager can enable/disable carrier availability

### Workflow scenarios
- shipment cannot advance to rate fetching before validation
- shipment cannot advance to offer selection if KYC/usage restriction fails
- shipment cannot be issued before DG/disclaimer and wallet pre-flight pass
- identical issuance retries remain idempotent
- confirmed shipment yields tracking number and label/doc outputs

### UX scenarios
- B2C shipment creation feels individual-oriented
- B2B shipment creation feels business/team-oriented
- internal carrier manager has internal-only carrier management surfaces
- external API consumers see platform API access only, not carrier-management copy
