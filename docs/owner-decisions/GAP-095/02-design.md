---
work_id: GAP-095
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-27-gap-095-treasury-s4b2-contract-payment-tracing-readiness.md
  plan: docs/superpowers/plans/2026-10-27-gap-095-treasury-s4b2-contract-payment-tracing.md
  branch: docs/GAP-095-treasury-s4b2-contract-payment-tracing
  pr: https://github.com/kha997/zenamanagephp/pull/374
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-27T10:50:16+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-27, verbatim: 'APPROVE GAP-095 Gate 2 Option' (option name missing), completed via AskUserQuestion with the Owner's selection, verbatim: 'Option A (Recommended)'. Reviewed design head: 83f8f3bb2263f42cd56d26f0c232bd0c166065c6. Approves Option A and its exact allowlist (Case A routes for paid contract payments of the project in TreasuryRouteService with the class-1 contract_payments lock and allocation cap, S4b-1 legs / reversal / cancel / completion branched for routes without a document, reminder list, two-way confirmable duplicate warning between funding and paid contract payments, summary line, API and web additions, real-MySQL race, docs); no schema change, no change to ContractPayment, contract pages or reports, no new permission; not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-27T10:21:04+07:00"
  updated_at: "2026-10-27T10:50:16+07:00"
generated_by: agent
---

# GAP-095 — Treasury S4b-2 contract-payment traceability: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-27 against reviewed design head
`83f8f3bb2263f42cd56d26f0c232bd0c166065c6` (typed "APPROVE GAP-095 Gate 2 Option", completed by selecting "Option A (Recommended)").
This authorizes only the bounded implementation defined by this packet; it does not authorize Gate 3, merge, release,
or deployment.

## Owner Summary

Thiết kế theo quy tắc đã duyệt (kiến trúc B2-T, GAP-037 v17 §4.1 Case A, §4.2a, §4.3, §11) và câu trả lời của Owner.
**Không đổi cơ sở dữ liệu** (bảng tuyến đã có cột gắn đợt thanh toán). Cách dùng:

- Trên trang ngân quỹ dự án có **danh sách các đợt thanh toán hợp đồng "đã thanh toán"** của dự án: số tiền, đã truy
  vết bao nhiêu, đã về ví đích bao nhiêu. Đợt nào chưa truy vết đủ được đánh dấu (danh sách nhắc).
- Chủ DN / kế toán bấm **"Truy vết"** ở một đợt: chọn ví đích, số tiền (mặc định phần còn lại) → tạo tuyến. Ghi
  chặng, đảo chặng, huỷ tuyến **giống hệt S4b-1** (cùng trang tuyến, cùng quyền).
- Tuyến **tự hoàn tất** khi ví đích nhận đủ và ví trung gian về 0. Không có chứng từ ngân quỹ nào được tạo: đợt thanh
  toán hợp đồng vẫn là nơi duy nhất ghi "chủ đầu tư đã trả".

**Năm điểm Owner cần biết khi duyệt:**

1. **Chặn khai trùng hai chiều bằng cảnh báo (như cảnh báo trùng của S2):** khai "tiền nhận" (thẳng hoặc qua tuyến)
   có cùng số tiền và cùng ngày với một đợt thanh toán hợp đồng đã thanh toán của dự án → hệ thống cảnh báo "khoản này
   có thể là đợt thanh toán hợp đồng, hãy dùng Truy vết"; người dùng vẫn có thể bấm "Vẫn ghi". Ngược lại, truy vết một
   đợt mà dự án đã có "tiền nhận" cùng số tiền, cùng ngày → cũng cảnh báo.
2. **Tổng truy vết không vượt số tiền của đợt** (kiểm tra khi tạo tuyến, có khoá). Tuyến đã huỷ không tính.
3. **Chỉ chặng của tuyến mới cần đợt còn "đã thanh toán"** (v17 §4.2a). Phía hợp đồng **không bị chặn**: nếu ai đó
   sửa đợt về "chưa thanh toán" hoặc giảm số tiền sau khi đã truy vết, ngân quỹ không sửa gì, chỉ hiện cảnh báo trong
   danh sách nhắc ("đợt không còn đã thanh toán" / "đã truy vết vượt số tiền") và không cho ghi chặng mới.
4. **Đối soát:** bút toán chặng đối soát theo ví như S4a/S4b-1 (không có chứng từ nào để chuyển trạng thái).
5. **Tổng hiển thị:** thêm dòng "Tiền thanh toán hợp đồng đã về ví" (tổng các tuyến truy vết đã hoàn tất) ở phần số
   dư; "Nhà đầu tư đã nộp" (tiền nhận ngân quỹ) giữ nguyên. Báo cáo dòng tiền và trang hợp đồng không đổi.

Đề xuất **Phương án A**.

## Options

| Option | Content | Verdict |
|---|---|---|
| **A. Two-way confirmable warning** | As below | **Recommended** — catches the likely double count, never blocks a real second payment of the same amount on the same day |
| B. Hard block | Funding with the same amount and date as a traced paid contract payment is refused | A genuine separate payment of the same amount the same day cannot be recorded |
| C. No automatic check | Reminder list only | Double counting is only visible by reading both lists |

## Design: Option A (exact allowlist)

### 1. Rules

- **Eligible payments:** `contract_payments` of a contract with `project_id` = the project, same tenant, `status =
  paid` and `paid_at` set, at route creation.
- **Create (Case A):** under a lock of the `contract_payments` row (class 1): `amount > 0`, `SUM(total_allocated_amount)`
  of its non-cancelled routes + amount ≤ payment amount; the route gets `linked_contract_payment_id`,
  `expected_destination_wallet_id` (a wallet of the project), `status planned`; both immutable. No financial document.
- **Legs / leg reversal / cancel:** the S4b-1 code paths, branched for a route without a document: every leg (and only
  legs; a reversal is a correction) re-checks under the class-1 lock that the payment is still `paid` with `paid_at`
  (§4.2a); completion uses `expected_destination_wallet_id` (§4.3: destination +amount, all others 0); no document
  status to move; cancel only while not completed and no leg in force. Money "from outside" allowed (the client's
  payment entering the first wallet), bounded by the route amount as in S4b-1.
- **Lock order:** create 1 → 6 (insert); leg 0 (from-wallet) → 1 (payment) → shared 4 → 6; leg reversal 0 → 1 → 4 → 6;
  cancel 1 → 6. Class 0 is S2's wallet lock, defined before v17's 1→6.
- **Reconciliation:** leg entries of a Case A route reconcile per wallet (already allowed since S4b-1; they map to no
  document, so no status moves).
- **Duplicate warnings (Option A):** S2 `declareFunding` and S4b-1 funding routes warn (409 / "Vẫn ghi") when a paid
  contract payment of the project has the same amount and `paid_at` date as the funding's transaction date; creating
  a Case A route warns when a posted or in-transit funding / owner contribution of the project has the same amount and
  transaction date as the payment's `paid_at`.
- **Reminder list:** per paid payment of the project: amount, traced (non-cancelled routes), arrived (completed
  routes), remaining; flags "chưa truy vết đủ", "đợt không còn đã thanh toán" (routes exist but status ≠ paid),
  "đã truy vết vượt số tiền" (payment amount lowered later).

### 2. Who

Create / cancel / reverse leg: `treasury.manage_routes` (owner, accountant). Legs: as S4b-1. View: `treasury.view`.
No new permission.

### 3. API and web

API: `GET projects/{project}/treasury/contract-payments` (reminder list), `POST
projects/{project}/treasury/contract-payments/{payment}/routes` (create); legs, reversal, cancel and route detail reuse
the S4b-1 endpoints. Web: "Thanh toán hợp đồng" card on the project Treasury page with the list, flags and a
"Truy vết" form; the S4b-1 route page shows a Case A route (contract, payment name, expected destination). Summary
line "Tiền thanh toán hợp đồng đã về ví".

### 4. Files (allowlist)

`TreasuryRouteService` (Case A create, branches in legs / reversal / cancel / completion / detail, reminder list),
`TreasuryPostingService` (funding duplicate warning), `TreasuryBalanceService` (summary line),
`TreasuryPaymentRoute` model (relation to `ContractPayment`), `Api\Treasury\TreasuryRouteController` and routes,
`Web\Treasury\TreasuryRoutePageController` / `TreasuryPageController` and the treasury views, the hidden concurrency
command and the real-MySQL Treasury concurrency test, tests, governed plan, this packet and 03-release.

**Not changed:** schema, `ContractPayment`, its controller and policy, contract pages, cashflow and KPI reports,
permissions.

### 5. Tests (TDD)

Only paid payments of the project are eligible (other project / tenant / unpaid refused); split across routes up to
the amount, over-allocation refused, cancelled routes free their share; legs complete a Case A route to the expected
destination; a leg refused once the payment is no longer paid; leg reversal and cancel; no financial document is
created and funding totals are unchanged; reminder list figures and flags; duplicate warnings both ways (and "Vẫn
ghi"); reconciliation of Case A leg entries; permissions; API + web; **real MySQL**: two routes racing past the payment
amount — the class-1 lock removal mutation must go red.

## Owner amendment 1

After the Gate-3 approval at digest `01caa5df…`, Codex (P2, PR #374 review on `c0452e89`) reported that after a
duplicate warning the "Truy vết" form reloaded with the full remaining amount and the first wallet, so confirming with
"Vẫn ghi" could silently trace a different amount into a different wallet. The fix changes the tree, so the Owner was
asked via AskUserQuestion and chose, verbatim: "Sửa trong PR này (Recommended)". Change: the form carries the payment
id; on a retry for that payment it reopens with the entered amount and destination; new web test
`test_a_retry_after_a_duplicate_warning_keeps_the_entered_amount_and_wallet` (red before the fix). The earlier Gate-3
approval is superseded; Gate 3 is re-presented.

## Verification required at Gate 3

All Treasury tests green locally and exact-head CI (incl. the MySQL concurrency job); mutation run red; PHPStan,
php-cs-fixer, SSOT lint clean.

## Decision Needed

Owner chọn: `APPROVE GAP-095 Gate 2 Option A` (khuyến nghị) / Option B / Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy; S5, S6.
