# Workflow RBAC Mapping

## Purpose
This document maps the canonical shipment workflow to the correct canonical actors and permission keys.

## Canonical Role Model
### External
- `individual`
- `organization_owner`
- `organization_admin`
- `staff`

### Internal
- `super_admin`
- `support`
- `ops_readonly`
- `carrier_manager`

## Legacy-To-Canonical Mapping
| Legacy Term | Canonical Term | Status |
|---|---|---|
| `tenant_owner` | `organization_owner` | deprecated |
| `tenant_admin` | `organization_admin` | deprecated |
| `api_developer` | none | removed as business persona |
| `integration_admin` | `carrier_manager` | rename internal role |

Evidence of current drift:
- [database/seeders/RolesAndPermissionsSeeder.php](c:/Users/Ahmed/Desktop/cebx-code/database/seeders/RolesAndPermissionsSeeder.php)
- [database/seeders/E2EUserMatrixSeeder.php](c:/Users/Ahmed/Desktop/cebx-code/database/seeders/E2EUserMatrixSeeder.php)
- [docs/test-accounts.md](c:/Users/Ahmed/Desktop/cebx-code/docs/test-accounts.md)

## Permission Contract
Target permission families for the canonical workflow:
- `shipments.read`
- `shipments.manage`
- `shipments.print_label`
- `rates.read`
- `quotes.read`
- `quotes.manage`
- `content_declarations.read`
- `content_declarations.manage`
- `wallet.balance`
- `wallet.ledger`
- `wallet.topup`
- `wallet.configure`
- `wallet.manage`
- `billing.view`
- `billing.manage`
- `tracking.read`
- `notifications.read`
- `notifications.manage`
- `kyc.read`
- `kyc.documents.read`
- `kyc.documents.manage`
- `kyc.manage`
- `reports.read`
- `reports.export`
- `analytics.read`
- `carriers.read`
- `carriers.manage`

## Workflow RBAC Matrix
| Workflow Step | `individual` | `organization_owner` | `organization_admin` | `staff` | `super_admin` | `support` | `ops_readonly` | `carrier_manager` | Required Permissions | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Create shipment request | yes, own account only | yes | yes | yes, if delegated | yes | no direct create by default | no | no | `shipments.manage` | `individual` has no team-management rights |
| Validation | yes | yes | yes | yes | yes | read/troubleshoot only | read only | no | `shipments.manage` or internal override | Internal read roles do not mutate |
| KYC check outcome view | yes | yes | yes | limited | yes | yes | yes, read only | no | `kyc.read` | External can view status, not approve/reject |
| KYC decision/approval | no | no | no | no | yes | limited support workflow only if explicitly designed | no | no | `kyc.manage`, `compliance.manage` | Internal-only decision authority |
| Fetch net rates / price offers | yes | yes | yes | yes | yes | read only | read only | carrier-read only | `rates.read`, `quotes.read` | Platform fetches carrier rates; external never owns carriers |
| Select offer | yes | yes | yes | yes if delegated | yes | no | no | no | `quotes.manage` | Offer choice stays external-facing |
| DG/content declaration | yes | yes | yes | yes if delegated | yes | read/support | read only | no | `content_declarations.manage` | DG hold/escalation can involve internal teams |
| Wallet pre-flight view | yes | yes | yes | yes if allowed | yes | read only | read only | no | `wallet.balance`, `wallet.ledger`, `billing.view` | |
| Wallet/payment mutation | yes, own account only | yes | yes | limited by org policy | yes | no | no | no | `wallet.topup`, `wallet.configure`, `wallet.manage`, `billing.manage` | |
| Carrier issuance | no direct carrier ownership; can trigger platform issuance through workflow | same | same | same if delegated | yes | no | no | yes | `shipments.manage`, internal `carriers.manage` | External users trigger shipment purchase, not carrier admin |
| Label/docs retrieval | yes | yes | yes | yes if delegated | yes | yes | yes | yes | `shipments.read`, `shipments.print_label` | |
| Tracking & notifications | yes | yes | yes | yes | yes | yes | yes | yes, operational view only | `tracking.read`, `notifications.read`, `notifications.manage` | |
| Reports / visibility | limited | yes | yes | limited | yes | limited | yes | yes operationally | `reports.read`, `reports.export`, `analytics.read` | |
| Carrier activation/configuration | no | no | no | no | yes | no | no | yes | `carriers.read`, `carriers.manage` | Internal only |

## Carrier Management Is Internal Only
- External users never activate or deactivate carriers.
- External users never configure carrier credentials.
- External users only consume platform-generated offers and labels.
- If external API keys remain, they are platform API keys for merchant access to the platform, not carrier integration credentials.

Related current implementation references:
- [app/Http/Controllers/Api/V1/IntegrationController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Api/V1/IntegrationController.php)
- [app/Http/Controllers/Web/PortalWorkspaceController.php](c:/Users/Ahmed/Desktop/cebx-code/app/Http/Controllers/Web/PortalWorkspaceController.php)
- [routes/web_b2b.php](c:/Users/Ahmed/Desktop/cebx-code/routes/web_b2b.php)

## Current Database Drift
### Role drift
- Current seed data still uses `tenant_owner`, `tenant_admin`, `api_developer`, and `integration_admin`.
- This drift is visible in:
  - [database/seeders/RolesAndPermissionsSeeder.php](c:/Users/Ahmed/Desktop/cebx-code/database/seeders/RolesAndPermissionsSeeder.php)
  - [database/seeders/E2EUserMatrixSeeder.php](c:/Users/Ahmed/Desktop/cebx-code/database/seeders/E2EUserMatrixSeeder.php)
  - [docs/test-accounts.md](c:/Users/Ahmed/Desktop/cebx-code/docs/test-accounts.md)

### Product-language lock
The target product docs and future UI copy must use:
- `organization_owner`
- `organization_admin`
- `staff`
- `super_admin`
- `support`
- `ops_readonly`
- `carrier_manager`

No future product-facing doc should reintroduce `api_developer` as a canonical business persona.