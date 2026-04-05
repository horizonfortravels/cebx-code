---
name: cbex-shipment-workflow-browser-ux
description: Browser UX for the external shipment journey in CBEX. Use when designing or refining create, address validation, offers, declaration, wallet preflight, issue, documents, and shipment timeline flows across `PortalWorkspaceController`, `ShipmentDocumentWebController`, and the shared shipment Blade views.
---

# Goal
Make the external shipment journey feel continuous, trustworthy, and easy to recover from across every browser-facing step.

# When to Use
- Redesign shipment creation, offers, declaration, issuance, or document pages.
- Improve browser flow continuity across the shipment journey.
- Rework shipment timeline, status presentation, or next-action guidance.
- Audit regressions in the external shipment workflow.

# When Not to Use
- Internal shipment read-center work.
- Unrelated dashboard or auth surfaces.
- Backend shipment logic changes that have no browser UX effect.

# Inputs
- Shipment routes in `routes/web_b2c.php` and `routes/web_b2b.php`.
- The relevant methods in `PortalWorkspaceController` and `ShipmentDocumentWebController`.
- Shared shipment views under `resources/views/pages/portal/shipments`.
- Shipment feature and end-to-end coverage in `tests/Feature/Web/` and `tests/e2e/`.

# Outputs
- A clearer shipment journey structure and browser flow.
- Better step-to-step messaging, CTAs, and recovery guidance.
- Notes on portal-specific differences between B2C and B2B.

# Instructions
1. Map the exact route sequence before changing anything: create, validate address, view offers, select an offer, complete declaration, run wallet preflight, issue shipment, view documents, and review the timeline.
2. Keep the workflow legible as one connected journey. Every step should make the previous step, current status, and next safe action obvious.
3. Use shared workflow language and components where possible so B2C and B2B feel related while still allowing portal-specific tone and density.
4. Treat address validation, preflight, and issue states as trust-sensitive moments. Show actionable guidance, not vague status copy.
5. Keep documents and timeline pages tightly tied to the current shipment. Downloads, previews, and back-links should feel anchored to the same journey.
6. Preserve account scoping and authorization expectations around every shipment action.
7. When empty, blocked, or failed states appear, keep them branded and explicit. Do not drop the user into raw framework output or dead ends.

# Guardrails
- Do not invent fake progress indicators, placeholder metrics, or lorem ipsum.
- Do not break route continuity between workflow steps.
- Do not mix B2C and B2B terminology in the same shipment journey.
- Do not weaken authorization or tenant scoping to simplify the flow.

# Verification
- Check that each shipment step links correctly to the next intended route.
- Check that the current shipment context stays visible across documents and timeline views.
- Check Arabic readability and RTL alignment on dense workflow pages.
- Check the most relevant shipment feature or Playwright coverage after changes.
