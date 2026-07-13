# Contract-Centric Management (R-CTR) — Design Spec

Date: 2026-07-13
Status: approved by user (recommendation A, 2026-07-13)
Depends on: `docs/superpowers/specs/2026-07-13-design-pm-completion-design.md` (R-DPM — its project-page design section is reused on design-contract pages)

## Purpose

Manage design progress per design contract, construction per construction contract, and thu–chi (receivables/expenses) per contract. The contract becomes the organizing spine: type, progress, money.

## Verified current state

- `App\Models\Contract`: belongs to project, `total_value`, `currency`, statuses draft/active/closed/cancelled, web UI exists (`ContractPageController` index/create/show/pdf, views `resources/views/contracts/*`). **No `contract_type` column.**
- `App\Models\ContractPayment`: installments per contract (`amount`, `due_date`, `status` planned/paid/overdue, `paid_at`) — this IS "thu". Granular permissions `contract.payment.*` already seeded.
- **"Chi" does not exist as a concept**, EXCEPT material cost: `Api\ContractController::costSummary()` already sums `MaterialReceiptLine` costs for receipts mapped to the contract, and `contracts.show` renders it ("Tổng hợp chi phí").
- Progress artifacts all exist: DesignItems + revisions + blockers (R-DPM), Tasks/phases, `QcInspection`, `Ncr`, `MaterialReceipt`.

## Decisions (user-approved)

1. **Contract typing** via a `contract_type` column: `design` | `construction` | `other`; existing rows default `other`, editable.
2. **Progress mapping — option A**: contract pages show the project's work filtered by artifact NATURE (design contract → DesignItems/review cycle; construction contract → tasks by phase, inspections, NCRs, material receipts). No `contract_id` tagging on tasks/phases (upgrade path to option B preserved, needed only if one project ever runs two same-type contracts).
3. **Manual expenses** via new `ContractExpense`; **materials are deliberately NOT a manual category** — material cost is already computed automatically from receipt lines, and a manual "vật tư" category would double-count. Manual categories: `labor` (nhân công), `subcontractor` (thầu phụ), `design_outsource` (thuê ngoài thiết kế), `misc` (khác).

## Component 1 — Contract type

- Migration: `contract_type` string default `'other'` on `contracts`; add to `$fillable`; constants `TYPE_DESIGN`/`TYPE_CONSTRUCTION`/`TYPE_OTHER` + `VALID_TYPES` on the model.
- Create form gains a type select (validated `Rule::in(VALID_TYPES)`); store path passes it through. Type badge on `contracts.index` rows and `contracts.show` header.
- No status/workflow changes.

## Component 2 — ContractExpense (chi)

Table `contract_expenses`: ULID `id`, `tenant_id` (indexed, + `TenantScope`), `contract_id` (indexed), `expense_date` (date), `amount` (decimal 15,2), `category` (string, one of the 4 manual categories), `description` (string 1000), `recorded_by` (nullable), timestamps.

Model `App\Models\ContractExpense` (`HasUlids`, `TenantScope`, `belongsTo(Contract)`); `Contract::expenses(): HasMany`.

Permissions `contract.expense.view/create/delete` added to `ZenaPermissionsSeeder` and `TestDatabaseSeeder`, granted alongside the existing `contract.payment.*` grants. No `update` permission/endpoint: correcting an expense = delete + re-enter (YAGNI; expenses are small atomic rows).

Web endpoints on the contract page (direct writes in `ContractPageController`, tenant-scoped fetch, rbac middleware):
- `POST /contracts/{id}/expenses` (`rbac:contract.expense.create`) — validates date, amount > 0, category in list, description required.
- `POST /contracts/{id}/expenses/{expense}/delete` (`rbac:contract.expense.delete`) — POST-with-verb form pattern, matching the app's existing non-DELETE web mutations.

## Component 3 — Finance block on `contracts.show`

Replaces/extends the existing "Tổng hợp chi phí" card:

- **Thu**: table of `ContractPayment` rows (name, amount, due_date, status badge, paid_at) + rollups: tổng giá trị HĐ (`total_value`), đã thu (sum paid), còn phải thu (total_value − paid), **quá hạn** (planned/overdue rows with `due_date < today` and not paid).
- **Chi**: manual expenses table grouped by category with add/delete forms (per permissions), + one distinct automatic line "Chi vật tư theo phiếu nhận (tự động)" fed by the existing `costSummary` value.
- **Số dư**: Thu (đã thu) − Chi (manual + auto material) — labeled "Đã thu − đã chi", explicitly NOT profit (no revenue recognition).

All money rendered with the app's existing `number_format` pattern and the contract's `currency`.

## Component 4 — Progress block on `contracts.show`

Below the info card, one block chosen by `contract_type`:

- **design**: the project's DesignItems list exactly as the R-DPM project section renders them (name, review-status badge, "Sửa lần N", "Vướng", assignee, due-to-client) + the blocked-items callout. Implemented by extracting the R-DPM section into a Blade partial (`resources/views/projects/_design-progress.blade.php`) included by both pages — no logic duplication.
- **construction**: tasks grouped by phase (title, assignee, status, progress %, "Vướng") + counts row: inspections by status (`QcInspection` where project), open NCRs (`Ncr`), material receipts mapped to this contract.
- **other**: neither block; a one-line note "Hợp đồng chưa phân loại — chọn loại để xem tiến độ."

## Component 5 — Project rollup card

On `projects.show` (below the R-DPM section): card "Hợp đồng & tài chính" — one row per contract (code linked to `contracts.show`, type badge, status, `total_value`, đã thu, đã chi, còn phải thu) + a totals row summing the project. Read-only projection.

## Error handling

- Expense create on another tenant's contract: 404 (tenant-scoped `findOrFail` + TenantScope).
- Expense delete only within the same contract (`where('contract_id', ...)` on the fetch) — a valid expense id under a different contract 404s.
- `costSummary` unavailable (existing degraded path): auto-material line renders "không tải được" and is excluded from the Chi total, with a visible note — never silently zero.

## Testing

- Type: create-with-type flow, default `other` on legacy rows (migration test via fresh row), invalid type rejected.
- Expenses: create requires all fields + positive amount; RBAC denied without permission; cross-tenant 404; delete clears; category outside the 4 rejected.
- Finance math: seeded payments (paid/planned/overdue mix) + expenses → asserted rollup numbers on the page (đã thu, còn phải thu, quá hạn count, chi total).
- Progress switch: design contract page shows the design partial; construction contract page shows tasks/counts; `other` shows the note.
- Project rollup: two contracts with different types → both rows + correct totals.
- Guard: `ContractExpense` added to the TenantScope guard list.

## Out of scope (YAGNI)

- Aging 30/60/90, invoices, expense attachments/approval workflow, multi-currency conversion, shared-cost allocation across contracts, BusinessKpiService/dashboard integration (separate slice), option B task-level contract tagging, expense update endpoint.
