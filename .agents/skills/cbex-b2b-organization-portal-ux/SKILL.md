---
name: cbex-b2b-organization-portal-ux
description: Organization and team portal UX for CBEX external business accounts. Use when designing or refining B2B dashboards, shipments, orders, wallet, reports, users, roles, and developer tools so the portal feels operational, role-aware, and organization-focused without leaking internal admin concepts.
---

# Goal
Shape the B2B portal as a confident organization workspace that supports teams, reporting, and platform integration tools where permissions allow them.

# When to Use
- Redesign B2B dashboard or navigation.
- Improve B2B shipments, orders, reports, users, roles, wallet, or settings pages.
- Refine B2B developer workspaces for integrations, API keys, or webhooks.
- Audit whether a B2B surface feels too consumer-like, too internal, or too carrier-centric.

# When Not to Use
- Individual B2C pages.
- Internal admin tooling.
- Pure backend permission work with no UX impact.

# Inputs
- B2B routes in `routes/web_b2b.php`.
- Active B2B views, especially dashboard, reports, team management, and developer surfaces.
- Permission rules that affect visible B2B sections.

# Outputs
- A stronger B2B page and navigation structure.
- Clearer role-aware copy and hierarchy for organization users.
- Notes on which tools belong in general navigation versus permission-gated workspaces.

# Instructions
1. Speak to an organization using the platform through multiple workflows, people, and permissions.
2. Prioritize the B2B jobs that matter most:
   - manage shipments and orders
   - review wallet and reporting health
   - coordinate users and roles
   - access platform integration tools when permitted
3. Keep dashboards denser than B2C, but still task-led. Favor operational clarity over vanity metrics.
4. Use organization-facing Arabic labels. Make it obvious when a page serves team management, reporting, or developer workflows.
5. Treat developer pages as platform API and integration tools for the organization. They are not carrier ownership, carrier credential, or internal operations pages.
6. Let permissions shape visibility and hierarchy. Avoid dead-end navigation for features the current user cannot use.
7. Keep a strong family resemblance with B2C through shared shell and design language while allowing B2B to be more information-dense.

# Guardrails
- Do not expose internal ops or admin concepts to B2B users.
- Do not present carrier integrations as if the customer owns or configures the carriers themselves.
- Do not collapse B2B into a consumer-style experience that hides team and reporting needs.
- Do not expose developer tools unless permissions already allow them.

# Verification
- Check that the B2B portal reads as an organization workspace, not a personal dashboard.
- Check that reports, users, roles, and developer tools appear only where they make sense.
- Check that Arabic labels explain the job to be done rather than internal schema names.
- Check that permission-aware navigation behaves predictably for limited users.
