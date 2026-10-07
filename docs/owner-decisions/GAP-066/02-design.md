---
work_id: GAP-066
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-07-gap-066-treasury-s3-expenses-readiness.md
  plan: null
  branch: docs/GAP-066-treasury-s3-expenses
  pr: https://github.com/kha997/zenamanagephp/pull/339
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-07T18:36:07+07:00"
  updated_at: "2026-10-07T18:36:07+07:00"
generated_by: agent
---

# GAP-066 — Treasury S3 expenses & approvals: Gate 2 design

## Owner Summary

Thiết kế S3 theo thiết kế đã duyệt (v17) và 4 câu trả lời của Owner. **Ba điểm
Owner cần biết khi duyệt:**

1. **Nháp cần chỗ lưu "dự định gắn chi phí".** Theo v17, dòng gắn chi phí là sự
   kiện bất biến, chỉ được tạo khi ghi sổ — nên khi còn nháp, phần gắn chi phí dự
   định được lưu tạm trong một cột mới `expense_plan` (chỉ thêm cột).
2. **Đảo khoản chi không xoá chi phí hợp đồng.** Nếu khoản chi đã tạo kèm một dòng
   chi phí hợp đồng mới, đảo khoản chi chỉ gỡ phần "đã trả"; dòng chi phí vẫn
   còn (chi phí có thật, chỉ chưa trả) — đúng nguyên tắc tách Chi phí ≠ Tiền.
3. **Biểu mẫu web gắn tối đa 3 chi phí cho một khoản chi** (giao diện không dùng
   JavaScript động); API không giới hạn.

Đề xuất **Phương án A**.

## Options

| Option | Content | Verdict |
|---|---|---|
| **A. Plan column + allocations at posting** | As below | **Recommended** — allocations stay immutable facts created only when money moves |
| B. Create allocations at draft time | No new column | Rejected/edited drafts would leave `apply` rows counted as "paid" — breaks v17 §6 and immutability |
| C. Separate draft-allocation table | Normalised | More schema and code for the same effect |

## Design: Option A (exact allowlist)

### 1. Migration (additive)

`treasury_financial_documents.expense_plan` (json, nullable): for an `expense`
before posting — `allocations`: list of {`cost_source_type`
(`contract_expense`|`material_receipt_line`), `cost_source_id`, `amount`};
optional `new_contract_expense`: {`contract_id`, `category`, `description`,
`amount`}. Classified `expand`. Read-only after posting (kept for audit).

### 2. `App\Services\Treasury\TreasuryExpenseService`

| Action | Who | Effect |
|---|---|---|
| Create / edit draft | `create_expense`; source wallet held by the user unless `manage_wallets` (Owner answer 4) | `expense` document `draft`, source wallet → payee party (§2.3), plan validated: allocations sum (+ new contract expense amount) = expense amount; each cost source in the project (§14); amount editable while `posting_path` is NULL |
| Submit | `submit_expense` (creator) | `draft → submitted`; approval row `submitted` |
| Approve = post (Owner answer 2) | `approve_expense`; if approver = creator also `self_approve_expense` (X only) | one transaction, locks **0 → 2 → 4 → 5**: source wallet (S2 class 0), cost-source rows (`contract_expenses` then `material_receipt_lines`, by id), shared-locked balance read, document. Creates the planned `ContractExpense` (A3, same transaction); checks §6.3 cap per cost source (`net_allocation + amount ≤ incurred`) and wallet balance; `submitted → approved → posted_unreconciled`, `posting_path = direct`; one `debit`; one `apply` allocation per planned line; approval rows `approved` (context `approval_mode` = `self_approval` or `standard`) and `posted` (§10.1 gate satisfied) |
| Reject | `approve_expense` | `submitted → rejected` (terminal, §2.1a); note required |
| Copy to new draft (Owner answer 3) | creator or `create_expense` holder | new `draft` with the same fields and plan; original untouched |
| Reverse posted expense | `reverse` (S2 rules) | S2 reversal + §2.2b: one `reverse` allocation per active `apply`, `financial_document_id` = reversal; locks 2 → 5; the `ContractExpense` stays |

Incurred amount: `ContractExpense.amount`; `MaterialReceiptLine.quantity_received
× unit_cost`. Every action writes `audit_logs` (as S2).

### 3. Read side

Per cost record: incurred, paid (net allocation), remaining; summary gains
"Đã chi" (posted expenses) and "Chi tự duyệt" (self-approved, PR #245 §5.3.8).

### 4. API (`/api/zena/projects/{project}/treasury/expenses…`)

create, update, submit, approve, reject, copy; list with status filter
(approval queue); `GET payables` (cost records with remaining). Same error
mapping as S2 (403/404/422/409).

### 5. Web (project Treasury page)

"Tạo khoản chi" form (≤ 3 cost lines or a new contract expense), "Chờ duyệt"
queue with Duyệt / Từ chối, "Tự duyệt" badge in the register, "Chi phí phải trả"
table, copy button on rejected expenses.

### 6. Files (allowlist)

Migration + `classifications.json` entry; `TreasuryExpenseService`; small
extensions of `TreasuryPostingService` (expense reversal coupling) and
`TreasuryBalanceService`; `TreasuryPolicy` abilities (create/submit/approve);
`AuthServiceProvider`; `TreasuryFinancialDocument` (cast for the new column);
models' relations as needed (`TreasuryCostSettlementAllocation`,
`TreasuryExpenseApproval` — existing); API controller
`Api\Treasury\TreasuryExpenseController`; web actions in
`TreasuryPageController`; views; routes; tests; one additional real-MySQL
concurrency test in the existing Treasury concurrency job (two approvals racing
for the same cost cap); governed plan file.

### 7. Tests (TDD)

Scenario E (self-approval recorded and reportable); installments on one cost
(§18.6); one payment over several costs; over-allocation rejected (6.3);
new `ContractExpense` created atomically; §10.1 gate (no posting without an
`approved` row); rejected terminal + copy; Z wallet rule; non-X cannot
self-approve; insufficient balance blocks approval; expense reversal coupling
(remaining restored, `ContractExpense` kept); permissions/tenancy; API + web;
MySQL race on the cost cap (and its lock-removal mutation must fail).

## Verification required at Gate 3

All tests green locally and exact-head CI (incl. MySQL concurrency job); local
UI check.

## Out of scope

Advances (S5), routes/reconciliation (S4), reports (S6), editing
`ContractExpense` beyond creating it, deployment.
