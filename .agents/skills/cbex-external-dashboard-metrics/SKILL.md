---
name: cbex-external-dashboard-metrics
description: Real external dashboard metrics, cards, charts, and empty-state guidance for CBEX. Use when designing or refining B2C or B2B dashboards, wallet summaries, report surfaces, or portal stat cards that must be backed by existing models, account-scoped queries, and real data rather than placeholders.
---

# Goal
Design external dashboard and summary surfaces around real, account-scoped metrics that help the user act.

# When to Use
- Redesign B2C or B2B dashboards.
- Add or refine stat cards, trend summaries, or report blocks.
- Improve empty states for dashboards, wallets, or reporting pages.
- Audit whether a summary surface is too shallow, too noisy, or built on fake data.

# When Not to Use
- Pure visual shell polish with no metric content.
- Internal analytics surfaces.
- Placeholder-wireframe work with no real data source.

# Inputs
- The relevant dashboard or summary routes and views.
- Controller methods that assemble current dashboard data.
- Existing models, tables, and services that can safely provide account-scoped data.

# Outputs
- Real dashboard metrics and summary decisions.
- Better cards, trends, empty states, or comparison blocks.
- Notes about data sourcing, scoping, and portal-specific density.

# Instructions
1. Start from the current controller method and confirm which queries already exist for the target dashboard or summary.
2. Keep all metrics real and account-scoped. Use existing models and tables such as shipments, orders, users, roles, wallets, webhooks, or notifications only where they apply to the portal.
3. Match density to portal type:
   - B2C dashboards should be lean and action-oriented.
   - B2B dashboards can support broader operational summaries, team context, and report entry points.
4. Prefer metrics that drive the next user action, not decorative numbers.
5. Use empty states to teach the next step when the account has no data yet.
6. When charts are appropriate, keep them simple, accurate, and explainable. Do not add chart chrome without a clear user question it answers.
7. Treat wallet and reporting surfaces as trust-sensitive. Numbers must reconcile with the real account context.

# Guardrails
- Do not use fake numbers, placeholder charts, or lorem ipsum.
- Do not borrow internal metrics that external users should not see.
- Do not expose B2B operational summaries inside B2C.
- Do not ship misleading trend language when the underlying data is not available.

# Verification
- Check every metric against a real model, query, or service.
- Check that the dashboard still reads clearly in Arabic and RTL.
- Check that empty states guide the next action instead of feeling unfinished.
- Check that each stat card or chart matches the portal's real permissions and scope.
