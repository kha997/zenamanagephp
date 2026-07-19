# Company cashflow report (báo cáo dòng tiền toàn công ty)

Date: 2026-07-19
Status: approved for implementation planning

## Problem

Last gap from the 2026-07-19 management-axis audit: per-contract and per-project finances exist (`contracts/show`, `projects/show` sum paid/expense), and CRM has receivables aging — but **no screen answers "toàn công ty tháng này thu bao nhiêu, chi bao nhiêu, ròng ra sao, tháng tới dự kiến tiền về bao nhiêu"**. The existing `BusinessKpiService::monthlyRevenue` is booking-based (won opportunities), NOT cash — a semantically different number that must not be conflated.

Brainstorm decisions (2026-07-19, with user): actual cash in/out per month PLUS a "chờ thu" (expected-in) column from planned/overdue payments (option B — near-zero extra cost, answers the forward question; full forecasting rejected — no planned-expense data exists); placement = the existing Reports section, gated by the existing `rbac:report.view` (finance-wide numbers should not be visible to everyone with `crm.view`; no new permission).

## Scope

**In scope:**
- New page `GET /reports/cashflow` (route name `operator.reports.cashflow`, middleware `rbac:report.view`), method `cashflow()` on the existing `ReportPageController`, view `reports/cashflow.blade.php`, a link to it from the existing Reports index page.

**Out of scope (deferred):**
- Planned expenses / full cashflow forecasting (no data source).
- CSV export of this table (the Reports export flow covers raw datasets; add later if asked).
- Caching (on-demand page, two small aggregates).
- Any change to `ContractPayment`/`ContractExpense` models, `BusinessKpiService`, or the CRM report.
- Drill-down links per month (later if asked).

## Definitions (pinned)

- **Window:** 12 calendar months = 9 months back → 2 months forward (current month included). Future months naturally have zero thu/chi and show upcoming "chờ thu". Months with no transactions still render as zero rows.
- **Thu thực (cash in):** `contract_payments` with `status = paid`, bucketed by month of `paid_at`; if a paid payment has NULL `paid_at` (someone marked paid without a date), fall back to `due_date`; if both NULL, the row is skipped (cannot be bucketed — never guessed).
- **Chờ thu (expected in):** payments with `status ∈ {planned, overdue}`, bucketed by `due_date`; NULL `due_date` skipped.
- **Chi thực (cash out):** all `contract_expenses`, bucketed by `expense_date`; NULL skipped.
- **Ròng (net):** thu − chi per month. **Lũy kế (cumulative):** running sum of net from the window's first month — labeled explicitly "lũy kế trong kỳ hiển thị" (not all-history).
- A caption distinguishes this page from the KPI revenue number: "Số liệu tiền thực thu/chi theo hợp đồng — khác với doanh số ghi nhận (KPI)."

## Architecture

- `ReportPageController::cashflow(): View` — two tenant-scoped `get()`s (`contract_payments`: amount/status/due_date/paid_at; `contract_expenses`: amount/expense_date), bucketed in PHP into the 12-month window, composed into row view-models `{month, thu, chi, net, cumulative, cho_thu}`. No cache, no JS, no migration, no new controller.
- Tenant isolation: both models use the `TenantScope` trait AND the queries filter `tenant_id` explicitly (belt and braces, same as every recent slice).
- Reports index page gains a small card/link "Dòng tiền" pointing at the new route.

## UX

- Page "Dòng tiền" (title + the two captions above). One table, 12 rows, columns: **Tháng (m/Y) · Thu thực · Chi thực · Ròng · Lũy kế · Chờ thu**. Amounts via `number_format($v, 0, ',', '.')`. Current month's row visually marked (bold or subtle background). Negative net/cumulative in red.
- Empty tenant (no payments and no expenses at all): keep the table of zero rows and add a muted note "Chưa có giao dịch nào được ghi nhận."

## Testing approach

Feature (`CashflowReportTest`): (1) user with `report.view` → 200, sees "Dòng tiền" and correct formatted sums for a fixture set — one paid payment this month (bucketed by `paid_at`), one planned payment next month (appears in next month's chờ thu), one expense this month; net and cumulative assertions included; (2) user without `report.view` → 403; (3) cross-tenant isolation — another tenant's payment/expense sums never appear; (4) paid payment with NULL `paid_at` buckets under its `due_date` month. Fixtures copy the proven pattern from `tests/Feature/Models/ContractExpenseTest.php` (real `Contract::query()->create` field set) and `ContractPaymentFactory`. GET-only — no CSRF/session setup needed.

## Migration safety

None — read-only aggregation over existing tables; purely additive route/method/view.
