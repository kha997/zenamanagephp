---
work_id: GAP-067
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-08-gap-067-treasury-s4a-reconciliation-readiness.md
  plan: null
  branch: docs/GAP-067-treasury-s4a-reconciliation
  pr: https://github.com/kha997/zenamanagephp/pull/340
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-08T04:51:39+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-08, verbatim: 'đồng nhất codebase giữa local và remote trước và tiến hành GAP-067 Gate 2 Option A bằng cloud session'. Reviewed design head: ab782f5ab660c5f312d07305ee397279d2b21713. Approves Option A and its exact allowlist (no migration; undo reason in audit_logs; transfer needs both wallets; reconciliation does not block reversal; reference rules), implementation to run in a cloud session; not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-08T04:50:00+07:00"
  updated_at: "2026-10-08T04:51:39+07:00"
generated_by: agent
---

# GAP-067 — Treasury S4a reconciliation: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-08 against reviewed design head
`ab782f5ab660c5f312d07305ee397279d2b21713`. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Thiết kế S4a theo thiết kế đã duyệt (v17 §12) và câu trả lời của Owner. **Không
cần thay đổi cơ sở dữ liệu** (bảng đối soát đã có từ GAP-038). **Bốn điểm Owner
cần biết khi duyệt:**

1. **Chuyển ví cần đối soát cả hai đầu.** Một chứng từ chuyển ví có 2 bút toán
   ở 2 ví; nó chỉ thành "đã đối soát" khi cả ví đi và ví đến đều đã đối soát
   bút toán của nó.
2. **Lý do gỡ đối soát lưu ở nhật ký hệ thống** (bảng đối soát v17 không có cột
   ghi chú); trang lịch sử đối soát vẫn hiển thị lý do.
3. **Đối soát không chặn việc đảo.** Chứng từ đã đối soát vẫn đảo được (như S2);
   bút toán đảo sinh ra là giao dịch mới, "chưa đối soát", cần đối soát tiếp.
   Bút toán của chứng từ đã đảo vẫn đối soát được (tiền đã thực sự đi và về).
4. **Số tham chiếu bắt buộc** với sao kê ngân hàng và chứng từ; với kiểm quỹ
   tiền mặt thì không bắt buộc. Ngày đối soát không được ở tương lai.

Đề xuất **Phương án A**.

## Options

| Option | Content | Verdict |
|---|---|---|
| **A. No migration; undo reason in `audit_logs`** | As below | **Recommended** — uses v17 tables exactly as approved; reason is still shown and auditable |
| B. Add a `note` column to `treasury_reconciliation_entries` | Reason stored on the row | Extra migration for a value already captured in the audit log |
| C. Reconcile whole documents instead of ledger entries | Simpler UI | Contradicts v17 §12 (reconciliation is per ledger entry, per wallet) |

## Design: Option A (exact allowlist)

### 1. `App\Services\Treasury\TreasuryReconciliationService`

| Action | Who | Effect |
|---|---|---|
| Reconcile (apply) | `treasury.reconcile` + project access | Input: wallet, `reconciliation_type` ∈ `bank_statement` \| `cash_count` \| `voucher`, `external_reference` (required unless `cash_count`), `reconciled_at` (≤ today), ≥ 1 ledger entry id. One transaction: lock the ledger-entry rows **by id ascending (v17 §11 class 4)**, re-check each belongs to the wallet/tenant, is sourced from a financial document (route-leg entries are rejected until S4b) and has **no active `apply`**; create one `treasury_reconciliations` row + one `apply` entry per ledger entry; then lock the affected documents **by id ascending (class 5)** and move each `direct` document from `posted_unreconciled` to `posted_reconciled` when every one of its ledger entries has an active `apply` (§12.1). All-or-nothing: one invalid entry rejects the whole request |
| Undo one line | same | Reason required. Lock the ledger entry (4); its active `apply` gets a `reverse` entry (`reverses_reconciliation_entry_id`, same `reconciliation_id`); lock the document (5): `posted_reconciled → posted_unreconciled`; **no status change if the document is `reversed`** (§12.2) |
| Undo a whole reconciliation | same | Same as above for every still-active `apply` of that reconciliation, one transaction, entries by id |

"Active apply" = an `apply` row with no `reverse` row pointing to it. Every
action writes `audit_logs` (actor, wallet, entry ids, documents whose status
moved, reason). Balances are unchanged (both posted statuses already count).

**Lock order:** 4 → 5 only, consistent with S2/S3 (posting: 0 → shared 4 → 5;
expense: 0 → 2 → 4 → 5; reversal: 2 → 5 and inserts new entries without
locking existing ones). Reconciliation never takes a wallet (0) or cost (2)
lock, so no new ordering is introduced.

### 2. Read side (`TreasuryBalanceService` / reconciliation service)

Per wallet: reconciled balance (entries with an active `apply`) and
unreconciled balance; list of unreconciled entries (date, document, reference,
direction, amount); reconciliation history (type, reference, date, who, lines,
undone lines with reason). Register: status "Đã đối soát"/"Chưa đối soát"
filter.

### 3. API (`/api/zena/projects/{project}/treasury/…`)

- `GET wallets/{wallet}/reconciliation` — balances + unreconciled entries
- `POST wallets/{wallet}/reconciliations` — reconcile
- `GET reconciliations` (filter by wallet) — history
- `POST reconciliations/{treasuryReconciliation}/undo` — undo whole
- `POST reconciliation-entries/{treasuryReconciliationEntry}/undo` — undo one line

Middleware `rbac:treasury.view` / `rbac:treasury.reconcile`; Gate
`treasury.reconcile` (project access, same tenant). Error mapping as S2
(403/404/422/409).

### 4. Web (operator)

Project Treasury page: per wallet "Đã đối soát / Chưa đối soát" amounts and a
"Đối soát" link; new page `/operator/treasury/projects/{project}/wallets/{wallet}/reconcile`
with checkbox list of unreconciled entries (plain form, no JavaScript), the
reconciliation form, and history with "Gỡ" (per line / whole) + reason field;
register status filter. Viewers without `treasury.reconcile` see the page
read-only.

### 5. Files (allowlist)

`TreasuryReconciliationService` (new); small read additions in
`TreasuryBalanceService`; `TreasuryReconciliation` / `TreasuryReconciliationEntry`
/ `TreasuryLedgerEntry` model relations/constants as needed; `TreasuryPolicy`
(`reconcile`); `AuthServiceProvider` (`treasury.reconcile`); API controller
`Api\Treasury\TreasuryReconciliationController`; web actions in
`TreasuryPageController` (or a sibling web controller); views; routes
(`api_zena.php`, web treasury routes); hidden concurrency command
`treasury:concurrency-test-reconcile` + one more method in the existing
real-MySQL Treasury concurrency test/job; tests; skip-inventory baseline line;
governed plan file; this packet and 03-release.

### 6. Tests (TDD)

Single-wallet document becomes reconciled; transfer needs both wallets; partial
selection keeps document unreconciled; an entry cannot be reconciled twice
(sequential 409/422) and **concurrently** (real MySQL, two processes; the
lock-removal mutation must go red); entries of another wallet/project/tenant
rejected; route-leg entries rejected; undo line and whole → status regresses;
undo on a `reversed` document records the row but keeps `reversed`; reversal of
a reconciled document still works and its new entries are unreconciled;
reference/date validation; permissions (Z and viewers cannot reconcile, can
view); API + web; balances unchanged.

## Verification required at Gate 3

All Treasury tests green locally and exact-head CI (incl. the MySQL
concurrency job); mutation run red; local UI check.

## Out of scope

Routes/legs and the §12.1 `via_route` branch (S4b), bank-file import,
period lock, advances (S5), reports (S6), deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-067 Gate 2 Option A` (khuyến nghị) / Option B /
Option C / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy; S4b.
