# Canonical Shipment Workflow For The Global Shipping Gateway

## Purpose
This document defines the formal product workflow for shipment creation, pricing, payment gating, carrier issuance, label retrieval, tracking, and notifications.

It is the product source of truth for shipment flow design. Existing implementation modules remain reference material only:
- [docs/FR-SH-README.md](c:/Users/Ahmed/Desktop/cebx-code/docs/FR-SH-README.md)
- [docs/FR-RT-README.md](c:/Users/Ahmed/Desktop/cebx-code/docs/FR-RT-README.md)
- [docs/FR-CR-README.md](c:/Users/Ahmed/Desktop/cebx-code/docs/FR-CR-README.md)
- [app/Services/ShipmentService.php](c:/Users/Ahmed/Desktop/cebx-code/app/Services/ShipmentService.php)
- [app/Services/RateService.php](c:/Users/Ahmed/Desktop/cebx-code/app/Services/RateService.php)
- [app/Services/PricingEngineService.php](c:/Users/Ahmed/Desktop/cebx-code/app/Services/PricingEngineService.php)
- [app/Services/CarrierService.php](c:/Users/Ahmed/Desktop/cebx-code/app/Services/CarrierService.php)
- [app/Http/Controllers/Api/V1/TrackingController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Api/V1/TrackingController.php)

## Platform Positioning
- Terminology lock:
  - B2C = the platform portal and browser flows for `individual` external accounts only.
  - B2B = the platform portal and browser flows for `organization` external accounts only.
  - Internal = a separate internal portal for platform staff only.
- The platform is the contracting party with carriers.
- Carriers are integrated and operated by the platform.
- Both B2C and B2B external users buy shipping through the platform's carrier network.
- External users do not own carrier integrations.
- If merchant-facing API keys or webhooks remain externally available, they represent platform API access only, not carrier ownership or carrier configuration.

## Actor Model
### External
- B2C portal audience: `individual` external accounts only.
- B2B portal audience: `organization_owner`, `organization_admin`, and `staff` under `organization` external accounts only.
- `individual`
- `organization_owner`
- `organization_admin`
- `staff`

### Internal
- `super_admin`
- `support`
- `ops_readonly`
- `carrier_manager`

### Note On Implementation Drift
The current repo still contains legacy names in:
- [database/seeders/RolesAndPermissionsSeeder.php](c:/Users/Ahmed/Desktop/cebx-code/database/seeders/RolesAndPermissionsSeeder.php)
- [database/seeders/E2EUserMatrixSeeder.php](c:/Users/Ahmed/Desktop/cebx-code/database/seeders/E2EUserMatrixSeeder.php)

Those names are implementation drift, not target-state product language.

## Canonical Workflow Stages
### A. Create Shipment Request
- Purpose: capture sender, recipient, parcel, and shipment-property inputs.
- Actor: `individual`, `organization_owner`, `organization_admin`, `staff`.
- Inputs: sender data, recipient data, parcel data, shipment properties, account context, user context.
- Processing: create a shipment request in draft state with tenant linkage and actor linkage.
- Outputs: shipment request record.
- Allowed next states: `validated`, `cancelled`.
- Failure points: missing fields, invalid actor/account context, malformed parcel set.
- Audit points: draft creation timestamp, actor ID, account ID, request origin.

### B. Validation
- Purpose: ensure shipment data is structurally and operationally valid.
- Actor: external user initiating shipment, platform validation engine.
- Inputs: shipment draft.
- Processing: validate address, phone, postal code, country, dimensions, restrictions, shipment completeness.
- Outputs: valid shipment or structured validation failures.
- Allowed next states: `validated`, `draft`.
- Failure points: invalid route, bad postal code, unsupported country, invalid dimensions, restricted content.
- Audit points: validation attempt, rules triggered, failure reasons.

### C. KYC & Usage Restriction Check
- Purpose: block or restrict shipments based on customer verification and usage policy.
- Actor: external user, internal compliance rules engine.
- Inputs: account KYC state, shipment geography, shipment value, usage caps.
- Processing: apply KYC state and usage restrictions.
- Outputs: allow, block, or requires action.
- Allowed next states: `rates_fetched`, `kyc_blocked`.
- Failure points: unverified account, pending verification, rejected verification, usage cap breach, international restriction.
- Audit points: KYC state snapshot, decision reason, cap/restriction result.

### D. Fetch Net Rates From Supported Carriers
- Purpose: gather internal carrier-network cost options.
- Actor: platform rate engine.
- Inputs: validated shipment, active supported carriers, internal carrier availability.
- Processing: query supported carriers for net rates through platform-owned integrations.
- Outputs: normalized net-rate candidates.
- Allowed next states: `rates_fetched`, `exception`.
- Failure points: no carrier available, timeout, unsupported service, upstream failure.
- Audit points: correlation ID, carriers queried, latency, upstream failure summary.

### E. Calculate Retail Rates
- Purpose: convert internal net rates into customer-facing commercial offers.
- Actor: platform pricing engine.
- Inputs: net rates, pricing rules, markup rules, service fee rules, rounding rules, minimum charge rules.
- Processing: calculate retail options and store pricing breakdown.
- Outputs: priced offers with persisted pricing breakdown.
- Allowed next states: `offer_selected`, `exception`.
- Failure points: pricing rule conflict, no valid fallback, invalid pricing config.
- Audit points: pricing rule used, markup, service fee, rounding, minimum charge adjustments, pricing breakdown ID.

### F. Show Options And Select One Offer
- Purpose: present external users with platform-generated shipping offers and capture selection.
- Actor: external user.
- Inputs: priced offers, quote expiry.
- Processing: display available offers and store selected offer.
- Outputs: selected offer bound to shipment.
- Allowed next states: `declaration_required`, `payment_ready`.
- Failure points: quote expired, stale shipment, unauthorized selection.
- Audit points: quote ID, selected option ID, selected carrier/service, actor ID.

### G. Dangerous Goods / Content Declaration
- Purpose: collect dangerous-goods declaration or mandatory legal disclaimer evidence.
- Actor: external user, internal compliance/support if escalation is needed.
- Inputs: content declaration, DG flag, waiver/disclaimer acknowledgement.
- Processing: if DG is declared, hold shipment for further action; if DG is not declared, require legal disclaimer acknowledgement.
- Outputs: declaration record and workflow gate result.
- Allowed next states: `declaration_complete`, `declaration_required`, `exception`.
- Failure points: DG hold, incomplete declaration, missing legal acknowledgement.
- Audit points: disclaimer version, timestamp, IP address, user ID, shipment ID, declaration decision.

### H. Payment / Wallet Pre-flight
- Purpose: confirm financial readiness before carrier issuance.
- Actor: external user, platform billing engine.
- Inputs: estimated shipment cost, wallet state, payment capability.
- Processing: verify balance and create reservation/hold where required.
- Outputs: payment-ready result or payment-blocked result.
- Allowed next states: `payment_ready`, `payment_blocked`.
- Failure points: insufficient balance, failed hold, unavailable wallet, billing issue.
- Audit points: hold/reservation ID, reserved amount, shipment/account linkage.

### I. Create Shipment At Carrier
- Purpose: issue the selected shipment through the platform-owned carrier integration.
- Actor: platform carrier service.
- Inputs: selected offer, verified declaration state, payment clearance, idempotency context.
- Processing: create the shipment at carrier with idempotency and correlation tracking.
- Outputs: carrier shipment confirmation or normalized failure.
- Allowed next states: `confirmed`, `exception`.
- Failure points: carrier API error, retry collision, idempotency conflict.
- Audit points: idempotency key, correlation ID, carrier status, retriable flag.

### J. Receive Label & Docs
- Purpose: store and expose printable and downloadable shipment documents.
- Actor: platform carrier/document service.
- Inputs: carrier response, carrier shipment identifier.
- Processing: fetch/store label and related documents.
- Outputs: label, documents, download references.
- Allowed next states: `label_ready`, `exception`.
- Failure points: document unavailable, download failure, storage failure.
- Audit points: document type, format, storage reference, retrieval count.

### K. Tracking & Notifications
- Purpose: keep shipment lifecycle visible after issuance.
- Actor: platform tracking service and notifications service.
- Inputs: webhooks, polling events, shipment status transitions.
- Processing: normalize carrier events, update timeline, trigger notifications.
- Outputs: normalized timeline, status, and customer notifications.
- Allowed next states: `in_transit`, `delivered`, `exception`, `returned`.
- Failure points: unmapped status, rejected webhook, delayed polling, notification failure.
- Audit points: raw carrier event, normalized event, notification dispatch record.

### L. Final Outputs
- Purpose: provide the customer and platform with the completed shipment outputs.
- Actor: external user and internal operators.
- Inputs: confirmed shipment, tracking updates, label/docs.
- Processing: expose shipment record, tracking number, label/docs, and timeline.
- Outputs: confirmed shipment, tracking number, documents, timeline/events.
- Allowed next states: terminal operational states only.
- Failure points: reconciliation mismatch, missing downstream document/timeline entry.
- Audit points: final shipment state, delivery completion, charge reconciliation if applicable.

## Canonical State Model
- `draft`
- `validated`
- `kyc_blocked`
- `rates_fetched`
- `offer_selected`
- `declaration_required`
- `declaration_complete`
- `payment_blocked`
- `payment_ready`
- `carrier_creating`
- `confirmed`
- `label_ready`
- `in_transit`
- `delivered`
- `exception`
- `cancelled`
- `returned`

## Failure Classes
- validation failure
- KYC/restriction failure
- pricing/rates failure
- declaration/compliance failure
- wallet/payment failure
- carrier issuance failure
- tracking/notification failure

## Audit Requirements
The canonical workflow requires auditable evidence for:
- shipment draft creation
- validation outcome
- KYC decision input/result
- quote generation and quote selection
- pricing breakdown persistence
- DG declaration or legal disclaimer evidence
- payment hold/reservation
- carrier issuance attempt
- correlation ID
- idempotency key
- label/doc retrieval
- normalized tracking events

## Current Database Drift
The target workflow defined above is canonical. The current repo still contains implementation and schema drift.

### Role drift
- External roles are still seeded with legacy names in:
  - [database/seeders/RolesAndPermissionsSeeder.php](c:/Users/Ahmed/Desktop/cebx-code/database/seeders/RolesAndPermissionsSeeder.php)
  - [database/seeders/E2EUserMatrixSeeder.php](c:/Users/Ahmed/Desktop/cebx-code/database/seeders/E2EUserMatrixSeeder.php)
- Internal carrier role is still named `integration_admin` in:
  - [database/seeders/RolesAndPermissionsSeeder.php](c:/Users/Ahmed/Desktop/cebx-code/database/seeders/RolesAndPermissionsSeeder.php)

### Individual-account rule drift
- Individual E2E accounts still violate the single-user rule in:
  - [database/seeders/E2EUserMatrixSeeder.php](c:/Users/Ahmed/Desktop/cebx-code/database/seeders/E2EUserMatrixSeeder.php)

### Global-default drift
- Current defaults still lean Saudi-first in:
  - [config/app.php](c:/Users/Ahmed/Desktop/cebx-code/config/app.php)
  - [database/seeders/SystemSettingsSeeder.php](c:/Users/Ahmed/Desktop/cebx-code/database/seeders/SystemSettingsSeeder.php)
  - [database/migrations/2026_02_12_000009_add_account_settings_columns.php](c:/Users/Ahmed/Desktop/cebx-code/database/migrations/2026_02_12_000009_add_account_settings_columns.php)
  - [database/migrations/2026_02_12_000010_create_stores_table.php](c:/Users/Ahmed/Desktop/cebx-code/database/migrations/2026_02_12_000010_create_stores_table.php)

### Schema drift
- `audit_logs.auditable_id` is still bigint in:
  - [database/migrations/0001_01_01_000002_create_admin_logistics_tables.php](c:/Users/Ahmed/Desktop/cebx-code/database/migrations/0001_01_01_000002_create_admin_logistics_tables.php)
- `orders.shipment_id` is still bigint in:
  - [database/migrations/0001_01_01_000001_create_core_business_tables.php](c:/Users/Ahmed/Desktop/cebx-code/database/migrations/0001_01_01_000001_create_core_business_tables.php)
- `kyc_requests.reviewer_id` is still bigint in:
  - [database/migrations/0001_01_01_000002_create_admin_logistics_tables.php](c:/Users/Ahmed/Desktop/cebx-code/database/migrations/0001_01_01_000002_create_admin_logistics_tables.php)
- `content_declarations.shipment_id` is still string/varchar in:
  - [database/migrations/2026_02_12_000025_create_dg_module_tables.php](c:/Users/Ahmed/Desktop/cebx-code/database/migrations/2026_02_12_000025_create_dg_module_tables.php)
