---
name: cbex-b2c-individual-portal-ux
description: One-user portal UX for CBEX individual external accounts. Use when designing or refining B2C dashboards, shipments, tracking, wallet, addresses, support, or settings so the portal feels personal, simple, action-oriented, and free of team, developer, or organization concepts.
---

# Goal
Shape the B2C portal as a clear, trusted, one-person shipping workspace for an individual external account.

# When to Use
- Redesign B2C dashboard or navigation.
- Improve B2C shipment, tracking, wallet, address book, support, or settings pages.
- Rewrite B2C labels, empty states, or page hierarchy.
- Audit whether a B2C surface feels too business-heavy or too technical.

# When Not to Use
- Organization, multi-user, or developer-tool pages.
- Internal admin or support surfaces.
- Shared shell work that does not change the B2C experience itself.

# Inputs
- B2C routes in `routes/web_b2c.php`.
- Active B2C views and any legacy B2C views still in use.
- Current user goals, such as sending a shipment, tracking one, or checking wallet readiness.

# Outputs
- A more personal B2C page structure and copy direction.
- Cleaner B2C navigation, hierarchy, and next actions.
- Notes on concepts that should stay out of the B2C portal.

# Instructions
1. Speak to one person using the portal for their own account. Keep the experience direct, calm, and task-first.
2. Prioritize the core B2C jobs:
   - start or continue a shipment
   - track current shipments
   - confirm wallet readiness
   - reuse saved addresses
   - contact support when needed
3. Keep dashboards and summaries lean. Show the most relevant next action instead of broad operational control panels.
4. Use reassuring, product-facing Arabic labels. Favor clarity and trust over dense status jargon.
5. Let tracking and shipment status feel central because they are often the highest-anxiety moments for individual users.
6. Keep settings and support present but secondary to active shipping work.
7. Verify that any shared-shell decision still feels personal enough for B2C after it inherits the common external system.

# Guardrails
- Do not expose B2B concepts such as teams, orders, users, roles, reports, or developer tools.
- Do not expose internal ops or admin concepts.
- Do not make the B2C dashboard look like a business analytics console.
- Do not overcomplicate the journey with dense menus or enterprise copy.

# Verification
- Check that a first-time individual user can identify the primary next action quickly.
- Check that B2C navigation stays compact and personal.
- Check that shipment and tracking states feel easy to understand in Arabic.
- Check that no organization, team, or developer concepts leak into the portal.
