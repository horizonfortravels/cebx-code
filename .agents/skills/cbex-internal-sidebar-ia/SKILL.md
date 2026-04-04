---
name: cbex-internal-sidebar-ia
description: Internal sidebar information architecture for this CBEX Laravel portal. Use when reordering internal admin navigation, regrouping sidebar sections, improving selected-account context panels, or making internal navigation permission-aware in `resources/views/layouts/app.blade.php` and related internal portal surfaces.
---

# Goal
Shape the internal sidebar and adjacent context panels so internal users see the right tasks, in the right order, with the right account context and no dead or unauthorized links.

# When to Use
- Reorder or rename internal sidebar sections and links.
- Group internal surfaces into clearer task-based sections.
- Improve the selected-account context panel for internal users.
- Make internal navigation more role-aware for `super_admin`, `support`, `ops_readonly`, or carrier/integration-focused roles.

# When Not to Use
- External B2C or B2B navigation redesign.
- Pure content work inside a dashboard card that does not affect navigation or context.
- Backend permission changes that do not affect internal IA.

# Inputs
- Current menu assembly in `resources/views/layouts/app.blade.php`.
- Route names and middleware from `routes/web.php`.
- `App\Support\Internal\InternalControlPlane` surface definitions and role metadata.
- The target internal role and whether selected-account context is required.

# Outputs
- Updated internal sidebar order, group labels, and context treatment.
- Navigation that matches role capabilities and selected-account state.
- Notes on route dependencies, hidden surfaces, and tenant-context-sensitive links.

# Instructions
1. Inventory the current internal menu, then map each item to its route name, permission gate, and `internalSurface` requirement before changing order or labels.
2. Group navigation around internal jobs, not controller names. Favor clear sections such as platform overview, customer operations, carriers and integrations, support, reporting, and platform administration.
3. Keep `/admin` and `/internal` distinct in purpose. `super_admin` can see the broader control plane, while lower-privilege roles should see a guided, narrower task list.
4. Use the selected-account panel to explain current tenant context. When no account is selected, make the next safe action obvious instead of showing tenant-bound links without explanation.
5. Hide links that the current role cannot use. Do not render dead links or rely on the target page to explain that access is missing.
6. Keep labels product-facing Arabic and avoid shorthand like raw route codes or abbreviations as visible navigation copy.
7. Preserve internal and external separation. No internal grouping should leak into B2C or B2B sidebars.

# Guardrails
- Do not add or surface links that bypass permissions or `internalSurface` rules.
- Do not make tenant-bound pages look globally scoped when they require selected-account context.
- Do not redesign B2C or B2B navigation as part of internal sidebar work.
- Do not let visual grouping imply carrier ownership by external customers; carrier integrations remain platform-managed.

# Verification
- Review the resulting menu for `super_admin`, `support`, `ops_readonly`, and carrier/integration-focused internal roles.
- Verify behavior with and without a selected account in session.
- Confirm every visible item points to a real route and sits behind the expected permission/surface gates.
- Check that section names and labels remain readable Arabic in RTL.
