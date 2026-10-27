# GAP-067 — Treasury S4a reconciliation: Gate-1 evidence

**Date:** 2026-10-08 (+07:00)

**Canonical base:** `a0381f5e3d5abaa37dc2f21f7099a09e6234ab2d` (S3 released, GAP-066)

**Branch:** `docs/GAP-067-treasury-s4a-reconciliation`

**Scope:** Read-only analysis for slice S4a of Issue #244. No code or schema change.

## 1. Slicing decision (Owner, in-session 2026-10-08)

The planned S4 ("routes & ContractPayment traceability & reconciliation",
GAP-063 slicing) is split into two Work IDs:

- **S4a — reconciliation** (this Work ID, first).
- **S4b — via-route payments + ContractPayment traceability** (later Work ID).

The Owner also answered S4b's business questions now; they are recorded in §6
so the later Work ID can cite them, but they authorize nothing in this one.

## 2. Binding rules (GAP-037 v17, approved)

- **§12 tables:** `treasury_reconciliations` (`wallet_id`,
  `reconciliation_type`, `external_reference`, `reconciled_at`,
  `reconciled_by`) and `treasury_reconciliation_entries` (`reconciliation_id`,
  `ledger_entry_id`, `direction` `apply`|`reverse`,
  `reverses_reconciliation_entry_id` unique, `actor_id`). Both already exist on
  main (GAP-038); models exist under `app/Models/Treasury`.
- **§12.1:** a `direct` document moves `posted_unreconciled →
  posted_reconciled` when **every** ledger entry for it has an active `apply`
  row (an internal transfer has two entries in two wallets, so both wallets
  must be reconciled). The `via_route` branch (route `completed` + every
  leg-sourced entry) is not reachable until S4b creates routes.
- **§12.2:** a `reverse` reconciliation entry that breaks §12.1 regresses the
  document to `posted_unreconciled` atomically — **except** when the document
  is `reversed` (terminal; the reconciliation row is still recorded).
- **§2.1a:** the closed status graph already allows
  `posted_unreconciled ↔ posted_reconciled` and `posted_* → reversed`.
- **§11 class 4:** active-reconciliation uniqueness and inverse-regression
  lock the `treasury_ledger_entries` rows (`id` ascending) before class 5
  (documents). There is no database constraint for "at most one active
  `apply` per ledger entry", so the lock is the only guard and needs a
  real-MySQL concurrency proof (as S2/S3 did).
- **§14 / §15:** reconciliation, wallet and ledger entries must share tenant
  (and the wallet's project).

## 3. Repository facts

- Permission `treasury.reconcile` already exists (S1 seeder) and is granted to
  X (Admin/super_admin) and the accountant role (Finance); PM/Site Engineer do
  not have it. This mapping was approved in GAP-063.
- S2/S3 post documents as `posted_unreconciled`; reversal code
  (`TreasuryPostingService`) already accepts `posted_reconciled` originals.
- Balances (`TreasuryBalanceService`) already count both posted statuses, so
  reconciliation changes no balance.
- The project register shows posted/reversed documents with status but has no
  reconciliation view.

## 4. Proposed S4a scope

- **Đối soát một ví:** chọn ví → danh sách giao dịch (bút toán sổ cái) của ví
  chưa đối soát → tick các dòng khớp với chứng từ bên ngoài → nhập loại
  (sao kê ngân hàng / kiểm quỹ tiền mặt / chứng từ khác), số tham chiếu, ngày
  → lưu. Chứng từ nào đủ mọi bút toán đã đối soát thì tự chuyển
  "đã đối soát".
- **Gỡ đối soát** một dòng (bắt buộc ghi lý do, lưu ở nhật ký) → chứng từ quay
  về "chưa đối soát", trừ chứng từ đã đảo (giữ nguyên trạng thái cuối).
- **Xem:** sổ giao dịch có cột/lọc trạng thái đối soát; mỗi ví hiển thị số dư
  đã đối soát và số dư chưa đối soát; lịch sử các lần đối soát.
- Quyền: `treasury.reconcile` (X, kế toán); người khác chỉ xem.
- API + web, kiểm thử feature + bằng chứng đồng thời trên MySQL thật cho quy
  tắc "một bút toán chỉ có một lần đối soát còn hiệu lực".

## 5. Explicit exclusions

Routes/legs, ContractPayment traceability (S4b); advances (S5);
dashboard/reports (S6); bank-statement file import; period lock; no change to
`ContractPayment`, `ReportPageController::cashflow()`; no reopening of approved
design; no deployment.

## 6. Owner answers recorded for S4b (not authorized here)

1. Intermediaries (company account A, courier C) are represented as **transit
   wallets inside the project** (e.g. "TK công ty – trung chuyển", custodian
   the accountant); no company-level wallets.
2. Route legs: X and accountant create routes and record any leg; PM/Site
   Engineer record only legs leaving or entering a wallet they hold (same rule
   as S2 transfers).
3. ContractPayment traceability is **optional**, with a reminder list of paid
   contract payments not fully traced; nothing is blocked on the contract side;
   an amount traced this way must not also be declared as `funding`.

## 7. Risks

- Missing class-4 lock → two reconciliations could both apply the same entry;
  mitigated by the MySQL two-process proof + mutation run.
- Two-wallet documents (internal transfer, reversals of it) only become
  "đã đối soát" after both wallets are reconciled — UI must explain this.
