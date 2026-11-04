# GAP-109 — Staff register and monthly payroll cost: discovery evidence

Date: 2026-11-04. Base: `main` at `f0fad9ccce66e620829870f9998705b1ca23fedf`.

Sources: the Owner's in-session direction of 2026-11-04 (company fixed-cost sheet; per-person payroll for employees and
collaborators; hours logged per task / project; labour cost per project; overhead allocation), and the code on `main`.
Method: static reading; no production data was used.

## 1. Owner direction (in-session, 2026-11-04)

- Fixed monthly costs today (Owner sheet): salaries 110,000,000; insurance 18,520,000; rent 30,000,000; power + water
  5,500,000; stationery 3,000,000; copier rental 2,000,000; courier 1,000,000; office misc 2,000,000; total 172,020,000.
- Payroll must be **per person**, for employees and collaborators (CTV).
- Each person logs which task / project they are working on; the company's full monthly cost of a person (salary, social /
  health insurance employer part, bonus, overtime, …) divided by hours gives the labour cost charged to each project;
  at completion the project's total labour cost is known.
- Agreed advice (Owner: "ok đồng ý với đề xuất của bạn"):
  - hourly rate = full monthly cost ÷ **standard hours** of the month; unlogged hours are reported as "staff cost not
    charged to any project";
  - fixed overhead (rent, utilities, office staff salaries, …) is allocated to projects **by logged hours**;
  - office staff salaries go into **overhead**;
  - work split into four GAPs: (1) staff + monthly payroll; (2) time logging per task; (3) month close → labour cost per
    project; (4) overhead + project profit-and-loss. Parts 1 and 2 first.

This GAP is part 1.

## 2. What exists

- **Users** (`users`): `tenant_id`, `name`, `email`, `job_title`, `department`; no employment type, salary, standard
  hours or start / end date. Collaborators without a login are not users.
- **Treasury financial parties** (`treasury_financial_parties`): types include `employee` and `labour`, optional
  `linked_user_id`. A party is who money is paid to; it carries no payroll data.
- **Treasury** is project-scoped: every `treasury_financial_documents` row requires `project_id`; wallets may have a null
  `project_id`, but no company-level money document exists. A salary payment cannot be posted today without picking a
  project.
- **Tasks**: `Task` / `ProjectTask` belong to projects (input for part 2). No time-entry, timesheet or payroll table
  exists anywhere.
- **Permissions**: Treasury uses `treasury.*` codes seeded by `TreasuryRolePermissionSeeder`; no payroll permission exists.
- The canonical Treasury spec lists "payroll calculation" as out of scope for Treasury itself.

## 3. Open points for Gate 2

1. **Staff register:** one register for employees and collaborators (type, linked user optional, linked Treasury party
   optional, start / end date, active flag); collaborators paid per job stay as direct project expenses (already
   possible in Treasury).
2. **Monthly payroll sheet:** per person, per month: base salary, allowances, overtime, bonus, employer social / health /
   unemployment insurance, other company costs, deductions (employee insurance, personal income tax, advances), net pay;
   **entered numbers** vs computed; "full company cost" = everything the company bears.
3. **Large bonuses** (13th month, project bonus): spread 1/12 per month or charged in the paid month.
4. **Standard hours** per month: company default (e.g. working days × 8) with per-person override (part-time).
5. **Hourly rate** = full company cost ÷ standard hours, stored at month close.
6. **Lock:** draft → closed month; reopening needs a reason (audited).
7. **Payment:** record only, or pay through Treasury — which needs a company-level money document (Treasury today
   requires a project).
8. **Who sees salaries:** a new permission (Owner + accountant); project managers never see individual pay.
9. **Office staff flag:** mark a person as office / overhead so part 4 moves their cost into overhead.

## 4. Constraints

- Tenant isolation; salary data visible only to the new permission; every change audited.
- No change to existing Treasury ledger rows; MySQL parity.
- Statutory payroll (tax / insurance calculation rules, payslips for authorities, bank payroll files) is not assumed.

## 5. Out of scope (later GAPs)

Time logging (part 2), labour cost per project (part 3), overhead and project P&L (part 4), automatic tax / insurance
calculation, payslip e-mail, bank payroll export, deployment.
