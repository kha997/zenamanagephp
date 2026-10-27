---
work_id: GAP-096
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-27-gap-096-treasury-s5-advances-readiness.md
  plan: null
  branch: docs/GAP-096-treasury-s5-advances
  pr: https://github.com/kha997/zenamanagephp/pull/375
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-27T13:01:07+07:00"
  updated_at: "2026-10-27T13:01:07+07:00"
generated_by: agent
---

# GAP-096 — Treasury S5 advances and settlements: Gate 2 design

## Owner Summary

Thiết kế theo quy tắc đã duyệt (GAP-037 v17 §6.3, §6.5, §7.4–§7.8, §2.2b, §2.2d, §11) và bốn câu trả lời của Owner ở
Gate 1. **Không đổi cơ sở dữ liệu.** Cách dùng:

- **Tạm ứng:** PM / kỹ sư lập đề nghị tạm ứng (người nhận, ví chi, số tiền, lý do) → gửi duyệt → kế toán / chủ DN
  duyệt thì tiền ra khỏi ví ngay và mở một khoản tạm ứng "còn nợ". Từ chối / sao chép thành nháp mới / tự duyệt:
  **giống hệt chi phí S3** (cùng quyền).
- **Quyết toán bằng chi phí:** trên khoản tạm ứng, người lập đề nghị quyết toán gắn vào các chi phí hợp đồng / dòng
  phiếu nhập vật tư (như phân bổ của S3) → kế toán / chủ DN duyệt hoặc từ chối → khi duyệt, số còn nợ giảm. Không có
  tiền đi ra lần nữa.
- **Hoàn tiền thừa:** kế toán / chủ DN ghi "nộp lại" vào một ví dự án → số còn nợ giảm.
- **Danh sách tạm ứng còn nợ** trên trang ngân quỹ dự án, nhóm theo tuổi nợ (tính từ ngày chi): dưới 30, 30–60, 60–90,
  trên 90 ngày.
- **Gỡ sai:** quyết toán đã duyệt gỡ được (có lý do); "nộp lại" và tạm ứng gỡ bằng nút "Đảo" như S2; tạm ứng chỉ đảo
  được khi đã gỡ hết quyết toán và hoàn tiền.

**Điểm Owner cần quyết:** bảng quyết toán của GAP-038 **không có cột trạng thái**, nên "đề nghị quyết toán chờ duyệt"
cần một chỗ lưu. Đề xuất **Phương án A**: lưu đề nghị trong **nhật ký duyệt của chứng từ tạm ứng** (bảng đã có, dùng
cho duyệt chi phí S3) — chỉ khi duyệt mới tạo dòng quyết toán và phân bổ. Không đổi lược đồ, mọi bước đều có vết.

**Ba điểm khác Owner cần biết:**

1. **Không vượt:** tổng quyết toán + hoàn tiền không vượt số tạm ứng (còn nợ không âm); phân bổ không vượt chi phí phát
   sinh, tính chung với chi phí S3 — kiểm tra lại khi duyệt, có khoá.
2. **Quyền: dùng lại quyền chi phí S3**, không thêm quyền mới (lập/gửi: PM, kỹ sư, kế toán, chủ DN; duyệt: kế toán,
   chủ DN; tự duyệt: chủ DN). Ghi hoàn tiền và gỡ quyết toán: quyền "Đảo" (`treasury.reverse`, kế toán + chủ DN).
3. **Đối soát:** chứng từ tạm ứng / nộp lại đối soát như chứng từ S2 (posted_unreconciled → posted_reconciled).
   Quyết toán bằng chi phí không có bút toán nên không cần đối soát.

## Options

| Option | Content | Verdict |
|---|---|---|
| **A. Request in the advance's approval log** | A settlement request is an append-only `settlement_requested` event (plan in `context`) on the advance document's `treasury_expense_approvals` log; approve / reject events close it; rows in `treasury_advance_settlements` and allocations are written only on approval | **Recommended** — follows the Owner's answer 2, no schema change, fully audited |
| B. New request table | A `treasury_advance_settlement_requests` table with status | Needs a schema change, which Gate 1 excluded; would need a new Gate 1 decision |
| C. No request step | Owner / accountant record a settlement directly; the holder hands in paperwork offline | Contradicts the Owner's answer 2 (the holder files the request) |

## Design: Option A (exact allowlist)

### 1. Rules

- **Advance document (§7.5, §2.3):** `document_type advance`, source = a wallet of the project, destination = a party
  of the tenant (any `party_type`), `posting_path direct`, amount > 0. Graph and actions as S3: draft → submitted →
  approved → posted_unreconciled; rejected terminal; copy a rejected one into a new draft; the approval log and the
  self-approval rule (`approval_mode`) are S3's; a holder of only `create_expense` may spend only from wallets they
  hold (S3 rule). On approval, in one transaction: lock the wallet (0), lock the document (5), re-check the wallet
  balance, post one debit, insert the `treasury_advances` row (party and amount from the document), then move to
  posted_unreconciled. No cost allocation on the advance itself. The S2 duplicate warning does not apply.
- **Outstanding (§6.5):** `amount − Σapply + Σreverse` over the advance's settlements; `0` once the advance document is
  reversed. Every write that changes it locks the `treasury_advances` row (class 3) and re-reads it under that lock.
- **Settlement request (Option A):** on a posted advance with outstanding > 0, a user with `submit_expense` on the
  project files `amount` + allocations (contract expenses / material receipt lines of the project, validated as S3
  allocations, summing to the amount; amount ≤ outstanding at filing) and an optional note; stored as an approval-log
  event `settlement_requested` on the advance document (`from_status = to_status` = the document's status, plan in
  `context`). At most one open request per advance. The filer may withdraw it (`settlement_withdrawn`); an approver
  rejects it with a note (`settlement_rejected`).
- **Settlement approval (§7.4, §6.3):** with `approve_expense` (self-approval as S3): lock cost sources (2) id-ordered,
  lock the advance (3), re-check amount ≤ outstanding and each allocation ≤ the cost's remaining (shared S3
  `netAllocationCents`, locking), lock the document (5), then insert one `approved_expense / apply` settlement and its
  allocations (`advance_settlement_id`), and the `settlement_approved` event. No ledger entry.
- **Settlement removal (§7.4):** with `treasury.reverse` and a reason: lock its allocations' cost sources (2), the
  advance (3); insert an `approved_expense / reverse` settlement (`reverses_settlement_id`) and one reverse allocation
  per active allocation (`reverses_allocation_id`). Not possible once the advance document is reversed (it is then
  already settled to 0 by §2.2d).
- **Advance return (§7.6, §7.7, direct only):** with `treasury.reverse`, wallet of the project, amount ≤ outstanding:
  lock the wallet (0), the advance (3), insert the `advance_return` document (party → wallet, `posting_path direct`)
  and its credit, and one `cash_return / apply` settlement (`financial_document_id` = the document) atomically; status
  posted_unreconciled. No approval step (cash physically received).
- **Reversal (S2 `reverse()`, §2.2b, §2.2d):** `advance` and `advance_return` become reversible. Advance: lock the
  advance (3), refuse unless settlement net = 0 (no active approved_expense and no active cash_return), then the S2
  reversal. Advance return: lock the advance (3), then the S2 reversal plus one `cash_return / reverse` settlement in
  the same transaction. Reversal of an advance whose return is active is refused with the reason shown.
- **Ageing:** per posted, non-reversed advance with outstanding > 0: party, amount, settled, returned, outstanding,
  days since `posted_at`, bucket `<30`, `30–60`, `60–90`, `>90` (lower bound inclusive), open request flag.
- **Lock order (§11):** issue 0 → 5 (+ advance insert); settle 2 → 3 → 5; remove settlement 2 → 3; return 0 → 3 → 4
  → 5; reverse advance / return 0 → 3 → 4 → 5. Class 0 is S2's wallet lock, before v17's 1→6.

### 2. Who (no new permission)

Advance create / edit / submit: `treasury.create_expense` / `submit_expense`; approve / reject:
`approve_expense` (+ `self_approve_expense` for own request); settlement request / withdraw: `submit_expense`; settlement
approve / reject: `approve_expense` (+ self rule); return, settlement removal, reversal: `treasury.reverse`; view /
ageing: `treasury.view`.

### 3. API and web

API (`api.zena.treasury.advances.*`): list + ageing, create / update / submit / approve / reject / copy draft, detail,
settlement request create / withdraw / approve / reject, settlement removal, return. Reversal uses the existing S2
endpoint. Web: "Tạm ứng" card on the project Treasury page (ageing list, "Đề nghị tạm ứng" form) and an advance page
(document, approval log, settlements, open request with approve / reject, request form, return form, remove
settlement, reverse).

### 4. Files (allowlist)

New `TreasuryAdvanceService`; `TreasuryExpenseService` (share allocation validation / cost locking / approval log
helpers — visibility only, behaviour unchanged); `TreasuryPostingService` (reversible types + the two §2.2 branches);
`TreasuryAdvance` / `TreasuryAdvanceSettlement` models (relations only); `TreasuryPolicy` and `AuthServiceProvider`
(gates mapped to existing permissions); new `Api\Treasury\TreasuryAdvanceController`, `routes/api_zena.php`; new
`Web\Treasury\TreasuryAdvancePageController`, `routes/web.php`, the treasury views; the hidden concurrency command and
the real-MySQL Treasury concurrency test; tests; governed plan; this packet and 03-release.

**Not changed:** schema, permissions / seeders, S3 expense behaviour, S4 routes, ContractExpense / material receipts,
reports (S6).

### 5. Tests (TDD)

Advance flow (draft → submit → approve posts debit + advance row; reject; copy; self-approval; wallet-holder rule;
insufficient balance); settlement request validation (sum, project, outstanding, one open), withdraw / reject /
approve; approval caps re-checked (outstanding and §6.3 shared with S3 expenses); settlement removal compensates 1:1
and frees the cost; return credit + settlement, cap; reversal of return (coupled reverse settlement) and of advance
(refused while settlement net ≠ 0, allowed at 0, outstanding 0 after); ageing buckets at 29/30/60/90 days;
reconciliation of advance and return documents; permissions; API + web; **real MySQL**: two settlement approvals racing
past the outstanding, and a settlement approval racing an S3 expense past a cost's incurred amount — the class-3 /
class-2 lock removal mutations must go red.

## Verification required at Gate 3

All Treasury tests green locally and exact-head CI (incl. the MySQL concurrency job); mutation runs red; PHPStan,
php-cs-fixer, SSOT lint clean.

## Decision Needed

Owner chọn: `APPROVE GAP-096 Gate 2 Option A` (khuyến nghị) / Option B / Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Chưa duyệt Gate 3, merge hay phát hành; không đổi lược đồ hay quyền; chưa duyệt S6.
