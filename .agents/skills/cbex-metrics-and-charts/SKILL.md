---
name: cbex-metrics-and-charts
description: Real KPI aggregation and Blade chart rendering for CBEX internal dashboards and report surfaces. Use when adding counts, trends, carrier distributions, shipment status mixes, or empty-state-safe widgets backed by existing Laravel models, tables, and internal report services, with no fake numbers or placeholder charts.
---

# Goal
Add trustworthy internal metrics and simple charts that reflect live application data, stay safe in empty states, and fit the existing Blade plus CSS approach.

# When to Use
- Add KPI cards, totals, or trend summaries to internal dashboards.
- Build Blade-rendered bar charts, distribution widgets, or status breakdowns.
- Move internal metric aggregation out of Blade and into controllers or read services.
- Wire internal dashboards to existing report services or model queries.

# When Not to Use
- Layout-only polish that does not change metric sourcing.
- External marketing or decorative visuals.
- Fake demo stats, mocked numbers, or placeholder chart shells.

# Inputs
- Target internal dashboard or report view.
- Relevant controller/service layer, especially `InternalAdminWebController`, `InternalReportsHubController`, `app/Services/InternalReportDashboardService.php`, `app/Services/InternalExecutiveReportService.php`, and `app/Services/InternalReportsHubService.php` when applicable.
- Existing Eloquent models and tenant-context expectations for the page.

# Outputs
- Real aggregated KPI data prepared in the controller or a read service.
- Blade widgets or charts that render correctly for populated and empty datasets.
- A short note on scope, timeframe, and any role or tenant constraints on the metrics.

# Instructions
1. Identify whether the target metric is platform-wide, internal-role-wide, or selected-account-scoped before writing queries.
2. Prefer putting aggregation logic in a controller or dedicated read service, not in Blade templates.
3. Use real models and existing tables only. If data does not exist yet, report the gap instead of inventing values.
4. Reuse existing dashboard primitives such as `x-stat-card`, `x-card`, simple bar charts, and shared grid helpers.
5. Render timeframe and scope in understandable Arabic so operators know what the number represents.
6. Protect zero and empty cases:
   - no division by zero
   - no misleading full bars for empty datasets
   - clear empty-state copy when there is no data for the selected scope
7. Keep charting simple and dependency-light. Favor Blade plus CSS or small inline markup patterns that match the current stack.
8. When data queries may become heavy, favor an existing internal report service or add a read-focused service later rather than hiding complex logic inside a view.

# Guardrails
- No fake numbers, lorem ipsum, or visual placeholders presented as live data.
- No chart library or frontend dependency just to render basic internal widgets.
- Do not blur platform-wide metrics with selected-account metrics.
- Preserve permission and tenant-context boundaries when sourcing data.

# Verification
- Cross-check counts or aggregates against the underlying models/queries.
- Test zero-data and selected-account-empty cases explicitly.
- Verify charts and cards still render cleanly in RTL and narrow layouts.
- Confirm the metric scope matches the role and route that display it.
