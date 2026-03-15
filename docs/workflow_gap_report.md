# Workflow Gap Report

## Executive Summary
The current product already contains major backend building blocks for shipment creation, pricing, carrier issuance, tracking, wallet usage, and notifications.

The current product is not yet aligned to the canonical business model. The biggest misalignments are:
- role naming drift
- individual-account rule drift
- carrier ownership boundary drift
- missing browser-first shipment workflow stages

## Terminology Lock
- B2C = the platform portal and flows for `individual` external accounts only.
- B2B = the platform portal and flows for `organization` external accounts only.
- Internal = the separate internal portal for platform staff.
- The platform remains the contracting party with carriers for both B2C and B2B.
- External accounts consume the platform carrier network; they do not own carrier integrations.

## Current Database Drift
### Role drift
- External roles still seeded as:
  - `tenant_owner`
  - `tenant_admin`
  - `api_developer`
- Internal role still seeded as:
  - `integration_admin`
- Evidence:
  - [database/seeders/RolesAndPermissionsSeeder.php](c:/Users/Ahmed/Desktop/cebx-code/database/seeders/RolesAndPermissionsSeeder.php)
  - [database/seeders/E2EUserMatrixSeeder.php](c:/Users/Ahmed/Desktop/cebx-code/database/seeders/E2EUserMatrixSeeder.php)

### Individual-account rule drift
- Individual E2E accounts still have multiple external users.
- Evidence:
  - [database/seeders/E2EUserMatrixSeeder.php](c:/Users/Ahmed/Desktop/cebx-code/database/seeders/E2EUserMatrixSeeder.php)

### Global-default drift
- Defaults still lean to:
  - locale `ar`
  - currency `SAR`
  - timezone `Asia/Riyadh`
  - country `SA`
- Evidence:
  - [config/app.php](c:/Users/Ahmed/Desktop/cebx-code/config/app.php)
  - [database/seeders/SystemSettingsSeeder.php](c:/Users/Ahmed/Desktop/cebx-code/database/seeders/SystemSettingsSeeder.php)
  - [database/migrations/2026_02_12_000009_add_account_settings_columns.php](c:/Users/Ahmed/Desktop/cebx-code/database/migrations/2026_02_12_000009_add_account_settings_columns.php)
  - [database/migrations/2026_02_12_000010_create_stores_table.php](c:/Users/Ahmed/Desktop/cebx-code/database/migrations/2026_02_12_000010_create_stores_table.php)

### Schema drift
- `audit_logs.auditable_id` bigint
- `orders.shipment_id` bigint
- `kyc_requests.reviewer_id` bigint
- `content_declarations.shipment_id` varchar(100)
- Evidence:
  - [database/migrations/0001_01_01_000002_create_admin_logistics_tables.php](c:/Users/Ahmed/Desktop/cebx-code/database/migrations/0001_01_01_000002_create_admin_logistics_tables.php)
  - [database/migrations/0001_01_01_000001_create_core_business_tables.php](c:/Users/Ahmed/Desktop/cebx-code/database/migrations/0001_01_01_000001_create_core_business_tables.php)
  - [database/migrations/2026_02_12_000025_create_dg_module_tables.php](c:/Users/Ahmed/Desktop/cebx-code/database/migrations/2026_02_12_000025_create_dg_module_tables.php)

## Gap Matrix
| Gap ID | Description | Severity | Impacted Actors | Category | Current Evidence | Likely Files Involved | Fix Direction |
|---|---|---|---|---|---|---|---|
| GAP-P0-01 | External carrier management boundary is wrong | P0 | external org users, internal carrier ops | product logic / RBAC / carrier integration | [app/Http/Controllers/Api/V1/IntegrationController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Api/V1/IntegrationController.php), [routes/api_external.php](c:/Users/Ahmed/Desktop/cebx-code/routes/api_external.php), [routes/web_b2b.php](c:/Users/Ahmed/Desktop/cebx-code/routes/web_b2b.php) | external integration routes/pages, internal admin routes/pages, seed roles | move carrier ownership/configuration to internal-only surfaces |
| GAP-P0-02 | Individual single-user rule is not enforced | P0 | external individual | product logic / RBAC | [database/seeders/E2EUserMatrixSeeder.php](c:/Users/Ahmed/Desktop/cebx-code/database/seeders/E2EUserMatrixSeeder.php), [routes/api_external.php](c:/Users/Ahmed/Desktop/cebx-code/routes/api_external.php) | seeders, user/role/invitation routes/controllers, browser routes | enforce exactly one external user for individual accounts |
| GAP-P0-03 | Canonical browser shipment workflow is missing | P0 | all external users | browser UX / product logic | [routes/web_b2c.php](c:/Users/Ahmed/Desktop/cebx-code/routes/web_b2c.php), [routes/web_b2b.php](c:/Users/Ahmed/Desktop/cebx-code/routes/web_b2b.php), [app/Http/Controllers/Web/PortalWorkspaceController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Web/PortalWorkspaceController.php) | new browser shipment flow controllers/views, shipment/rate/content declaration surfaces | add staged shipment wizard and post-purchase flow |
| GAP-P1-01 | External KYC approval/rejection ownership is wrong | P1 | external users, compliance/support | audit/compliance / RBAC | [app/Http/Controllers/Api/V1/KycController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Api/V1/KycController.php), [routes/api_external.php](c:/Users/Ahmed/Desktop/cebx-code/routes/api_external.php) | KYC routes/controllers/policies/internal roles | external can submit/view only; internal decides |
| GAP-P1-02 | Wallet pre-flight hold is not productized | P1 | external users | wallet/payment | [app/Services/ShipmentService.php](c:/Users/Ahmed/Desktop/cebx-code/app/Services/ShipmentService.php), [app/Services/WalletBillingService.php](c:/Users/Ahmed/Desktop/cebx-code/app/Services/WalletBillingService.php), [app/Http/Controllers/Api/V1/WalletBillingController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Api/V1/WalletBillingController.php) | wallet models/services/controllers, shipment purchase path | introduce explicit reservation/hold stage and UX |
| GAP-P1-03 | DG legal disclaimer and declaration audit trail are incomplete as product flow | P1 | external users, support, compliance | audit/compliance | [app/Http/Controllers/Api/V1/ContentDeclarationController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Api/V1/ContentDeclarationController.php), [app/Services/CarrierService.php](c:/Users/Ahmed/Desktop/cebx-code/app/Services/CarrierService.php) | declaration UI, audit models, carrier gating | persist disclaimer evidence and formal hold rules |
| GAP-P1-04 | Persona naming still confuses product boundaries | P1 | product, engineering, QA, demo users | product logic / browser UX | [docs/test-accounts.md](c:/Users/Ahmed/Desktop/cebx-code/docs/test-accounts.md), [tests/Feature/Web/ApiDeveloperWorkspaceTest.php](c:/Users/Ahmed/Desktop/cebx-code/tests/Feature/Web/ApiDeveloperWorkspaceTest.php) | docs, tests, seeders, browser copy | rename to canonical roles and reframe external API access |
| GAP-P2-01 | Locale/currency/country defaults are too region-specific for a global gateway | P2 | all actors | product logic / browser UX | [config/app.php](c:/Users/Ahmed/Desktop/cebx-code/config/app.php), [database/seeders/SystemSettingsSeeder.php](c:/Users/Ahmed/Desktop/cebx-code/database/seeders/SystemSettingsSeeder.php) | config, seeders, onboarding/account settings, store defaults | move to globally configurable defaults |
| GAP-P2-02 | Schema drift cleanup remains unfinished | P2 | engineering, data, compliance | schema / audit | migration files listed above | migrations, models, serializers, audit consumers | normalize remaining legacy FK/reference fields |
| GAP-P2-03 | External developer workspace terminology is misleading | P2 | external org users | browser UX / product logic | [routes/web_b2b.php](c:/Users/Ahmed/Desktop/cebx-code/routes/web_b2b.php), [app/Http/Controllers/Web/PortalWorkspaceController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Web/PortalWorkspaceController.php) | browser copy/routes, docs, tests | either rename as merchant API tools or narrow/remove |

## Priority Ordering
1. P0: fix actor model and carrier boundary before building more workflow UI.
2. P1: formalize gating stages already implied by the backend.
3. P2: clean global-default posture and remaining schema drift after product model is stable.

## Migration Policy Lock
- All future schema changes must be forward-only through new migrations.
- Previously-run historical migrations must not be edited again.

## Terminology Cleanup List
- Replace `tenant_owner` with `organization_owner`.
- Replace `tenant_admin` with `organization_admin`.
- Remove `api_developer` as a business persona.
- Rename `integration_admin` to `carrier_manager`.
- Clarify that `customer_api_keys` and merchant webhooks are platform-access surfaces only, not carrier-ownership surfaces, and that they live only under the B2B organization portal.
