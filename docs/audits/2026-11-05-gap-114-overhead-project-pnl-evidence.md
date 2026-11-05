# GAP-114 — Overhead allocation and project P&L (labour cost part 4/4): discovery evidence

Date: 2026-11-05. Base: `main` at `b4a9174f104a84be8e1af30c3d8dd2fbb4eb263a` (after GAP-113).

Sources: the Owner's in-session direction (GAP-109 evidence §1) and the code on `main`. Method: static reading; no
production data was used.

## 1. Owner direction (binding)

- Fixed company costs (office rent, electricity, water, …) must also be charged to projects. The Owner asked for advice
  on how; the agreed advice was to allocate overhead **by hours**.
- Office staff salaries go to overhead (GAP-109 `is_overhead`, GAP-113 O-1).
- Goal: know profit / loss per project in detail.

## 2. What exists

| Item | Detail |
|---|---|
| Labour cost per project (GAP-113) | `labour_cost_allocations`, frozen at payroll close. A row without project holds cost outside projects: office staff, idle / non-project hours, staff without a login. |
| Project revenue | `contracts` per project (contract value) and `contract_payments` (`status`, `paid_at`, `amount`): money received. The OPPM page already shows "Giá trị hợp đồng" and "Đã thu". |
| Project direct cost | `contract_expenses` (`expense_date`, `amount`, `category`): cost recorded, not necessarily paid ("Chi phí ghi nhận"). Treasury (`treasury_financial_documents`, type `expense`, posted) holds project money actually paid. Each Treasury document has a required `project_id`. |
| Company overhead (rent, power, …) | **No place to record it.** Treasury documents and wallets are per project; `contract_expenses` hang off a contract. No company-level cost register exists. |
| Project P&L | None. OPPM shows revenue and cost lines separately, with no margin and no overhead. |

## 3. Open points for Gate 2

- **Where overhead is entered:** a monthly company-cost sheet (cost lines per month, such as rent, electricity, internet,
  software), or company-level Treasury documents.
- **What goes into the overhead pool:** entered company costs, plus the labour cost outside projects from GAP-113.
- **Allocation basis:**
  - share of approved project hours in the month (agreed direction);
  - alternatives: share of revenue, or share of direct cost.
- **When numbers freeze:** with payroll close (same month) or a separate month close.
- **Project costs and double counting:** `contract_expenses` (recorded cost) and posted Treasury expenses (paid money)
  can describe the same spend. Gate 2 must choose one direct-cost source, or a rule for using both.
- **Revenue:** contract value (earned), payments received (cash), or both shown.
- **P&L layout:**
  - per project: revenue − direct cost − labour − allocated overhead = profit / margin;
  - per month and cumulative;
  - a company summary, including overhead not allocated in months with no project hours.
- **Who sees it:** owner / accountant only, vs PM (project totals).

## 4. Exclusions

Accounting journal entries, tax / VAT, budgets and forecasts, Treasury posting changes, deploy.
