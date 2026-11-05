# GAP-113 — Labour cost per project (labour cost part 3/4): discovery evidence

Date: 2026-11-05. Base: `main` at `da686ca5faf42f8a02d4daeab7ccb3d81d956089` (after GAP-112).

Sources: the Owner's in-session direction (recorded in GAP-109 evidence §1 and GAP-112 evidence §1) and the code on
`main`. Method: static reading; no production data was used.

## 1. Owner direction (binding)

- The company's full monthly cost for a person (salary, insurance, bonus, overtime, …) ÷ the month's hours gives the
  labour cost charged to each project that person worked on.
- When a project is finished, its total labour cost is known.
- Agreed advice: hourly rate = full monthly cost ÷ **standard** hours, so hours not logged on any project show as cost
  not charged to a project (handled as overhead in part 4). Office staff salaries go to overhead.

This GAP is part 3: money for project labour. Overhead allocation and project P&L are part 4.

## 2. What exists

| Item | Detail |
|---|---|
| Payroll line (GAP-109) | Per person and month: `company_cost`, `standard_hours`, `hourly_cost` (= company_cost ÷ standard_hours, rounded to the cent), integer-cent arithmetic. Recomputed on every save and again at close. |
| Payroll period (GAP-109) | `draft` / `closed`; `close` needs `payroll.close`; `reopen` needs a reason and is audited. Only a closed month has final figures. |
| Staff register (GAP-109) | `staff_members.user_id` links a login; `is_overhead` marks office staff; staff without a login cannot log time. |
| Time entries (GAP-112) | Per user, day, project / task or non-project category, minutes, status `running / submitted / approved / rejected`. Entries of a closed payroll month are locked. Reports show hours only. |
| Project page / OPPM | Shows component `planned_cost` / `actual_cost` and Treasury totals (with `treasury.view-project`). No labour line. |
| Labour cost per project | None (no table, service, or report). |

Matching key: `time_entries.user_id` = `staff_members.user_id`, same tenant, `work_date` in the payroll month.

## 3. Open points for Gate 2

- **When money is computed:** frozen at payroll close (stored allocation rows, cleared on reopen) vs computed live from
  draft figures.
- **Which hours count:** approved only; what to do with entries still submitted / running at close (block close, warn,
  or leave out).
- **Rate method:** hours × hourly rate (cost on unlogged hours stays outside projects) vs spreading the full monthly
  cost by share of logged hours; how to treat logged hours above standard hours.
- **Office staff (`is_overhead`):** all cost to overhead even if they log project hours, or charge their project hours.
- **Paid people without a login / without hours:** whole cost outside projects.
- **Where it shows:** a labour-cost report (by project, by person, by month, CSV) and a labour total on the project /
  OPPM page.
- **Who sees money:** per-person cost reveals salary; owner / accountant vs project managers (project totals only).

## 4. Exclusions

Overhead allocation, project P&L, accounting journal entries, Treasury postings, deploy.
