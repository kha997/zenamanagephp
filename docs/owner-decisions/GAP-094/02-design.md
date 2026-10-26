---
work_id: GAP-094
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-26-gap-094-treasury-s4b-routes-readiness.md
  plan: null
  branch: docs/GAP-094-treasury-s4b-routes
  pr: https://github.com/kha997/zenamanagephp/pull/373
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
  created_at: "2026-10-27T06:10:00+07:00"
  updated_at: "2026-10-27T06:10:00+07:00"
generated_by: agent
---

# GAP-094 — Treasury S4b-1 via-route payments: Gate 2 design

## OWNER GATE 2: AWAITING OWNER

## Owner Summary

Thiết kế S4b-1 theo thiết kế đã duyệt (GAP-037 v17 §4, §5a, §11, §12.1) và câu trả
lời của Owner. **Không đổi cơ sở dữ liệu** (bảng tuyến/chặng đã có). Cách dùng:

- Chủ DN hoặc kế toán **tạo tuyến**: chọn loại (tiền nhận / góp vốn / chuyển nội bộ),
  nguồn, ví đích, số tiền, ngày. Chứng từ được tạo cùng tuyến, trạng thái **"Đang
  trên đường"**.
- Mỗi lần tiền đi qua một chỗ, ghi một **chặng**: từ ví nào (hoặc "từ bên ngoài" với
  tiền nhận/góp vốn) đến ví nào, bao nhiêu. Số dư ví đổi ngay theo chặng.
- Khi ví đích đã nhận đủ và các ví trung gian về 0, tuyến **tự hoàn tất** và chứng
  từ chuyển "chưa đối soát" như chứng từ thường.

**Năm điểm Owner cần biết khi duyệt:**

1. **Tiền nhận / góp vốn chỉ cộng vào tổng "đã nhận" khi tuyến hoàn tất.** Trong lúc
   tiền đang đi, nó hiện ở số dư các ví trung gian, không hiện ở tổng tiền nhận.
2. **Kế toán tạo được tuyến tiền nhận / góp vốn**, theo câu trả lời của Owner (chủ DN
   và kế toán tạo tuyến). Ở S2 kế toán **không** được tự khai tiền nhận hay chuyển ví;
   tuyến là đường duy nhất kế toán ghi được tiền vào. Cần một quyền mới
   `treasury.manage_routes` (chủ DN, kế toán). PM/kỹ sư chỉ ghi chặng ra hoặc vào ví
   mình giữ, bằng quyền chuyển ví sẵn có.
3. **Ghi sai một chặng → đảo chặng** (bắt buộc lý do), không sửa. Ví nhận của chặng
   phải còn đủ tiền (đảo các chặng sau trước). Nếu tuyến đã hoàn tất, nó quay lại
   "đang trên đường" cho đến khi ghi chặng thay thế.
4. **Huỷ tuyến** chỉ được khi tuyến chưa hoàn tất và không còn chặng nào có hiệu lực
   (chưa ghi hoặc đã đảo hết); chứng từ chuyển "bị từ chối", không ảnh hưởng số dư.
5. **Đảo chứng từ đi tuyến**: chỉ khi tuyến đã hoàn tất; bút toán đảo ghi thẳng ở ví
   đích (và ví nguồn với chuyển nội bộ), như đảo chứng từ thường. **Đối soát**: bút
   toán của từng chặng đối soát theo ví như S4a; chứng từ "đã đối soát" khi tuyến hoàn
   tất và mọi bút toán chặng đều đã đối soát.

Đề xuất **Phương án A**.

## Options

| Option | Content | Verdict |
|---|---|---|
| **A. Post at completion, one-step legs, new `treasury.manage_routes`** | As below | **Recommended** — follows the Owner's answers; a route that never completes never counts as received money; it can be cancelled cleanly |
| B. Post the document at route creation (S2-style) | Document is `posted_unreconciled` from the start; funding totals count at once | v17 §2.2 #6 then blocks reversal until completion and the §2.1a graph has no way to cancel a posted document: an abandoned route leaves a posted document stuck forever |
| C. Two-step legs (send = `in_transit`, receive = `settled`) | Every hop is recorded twice | More clicks for the same balances; the transit wallet balance already shows money in someone's hands |

## Design: Option A (exact allowlist)

### 1. Rules (v17 restated, plus Option A choices)

- **Eligible documents (v17 §4.1):** `funding`, `owner_contribution` (party → project
  wallet) and `internal_transfer` (wallet → wallet). Expense, advance, adjustment and
  advance return stay `direct` in S4b-1.
- **Route creation:** one transaction creates the document (`posting_path = via_route`,
  `status = approved`, amount frozen, same validation as S2 incl. the funding duplicate
  warning and the owner-contribution party rule) and the route (`planned`,
  `total_allocated_amount = amount`, Case B link). The status walk
  draft → submitted → approved is recorded in the audit row, as S2 does.
- **Leg (one step, `settled`):** `to_wallet` is a wallet of the same project;
  `from_wallet` is a different wallet of the same project, or empty ("from outside")
  only on a funding/owner-contribution route. Posting (v17 §5a): wallet → wallet posts a
  debit/credit pair; from outside posts one credit. Guards: the from-wallet balance
  covers the amount (S2's negative-balance guard); the sum of "from outside" legs never
  exceeds the route amount; on a transfer route, the route's net out of the source
  wallet never exceeds the route amount; no leg on a `completed` or `cancelled` route;
  `occurred_at` not in the future.
- **Completion (v17 §4.3), checked after every leg and every leg reversal:** destination holds
  `+amount`, (transfer) source holds `−amount`, every other wallet touched by the route
  holds 0 (route-scoped `net_custody`). Then route → `completed` and document →
  `posted_unreconciled` (`posted_at` = now) in the same transaction; if every leg entry
  is already reconciled it moves on to `posted_reconciled` (§12.1).
- **Leg reversal (v17 §4.2b):** reason required; leg → `reversed` and one compensating
  entry per original entry (`reversal_of_entry_id`, same leg source) in one write; the
  leg's to-wallet must still cover the amount; a `completed` route goes back to
  `partial` (document stays posted, and a `posted_reconciled` document regresses to
  `posted_unreconciled` because §12.1 no longer holds).
- **Cancel:** reason required; only while the route is not `completed` and every leg is
  `reversed` (or there is none): route → `cancelled`, document → `rejected` (§2.1a).
- **Document reversal:** the existing "Đảo" accepts `via_route` originals whose route is
  `completed` (§2.2 #6), re-checked in the same transaction (§2.2e); the reversal is
  posted `direct` with its swapped shape (funding: one debit at the destination;
  transfer: debit destination, credit source). S2's guard exemption for reversals is
  unchanged.
- **Reconciliation (§12.1):** S4a stops rejecting leg-sourced entries; a `via_route`
  document becomes `posted_reconciled` when its route is `completed` and every leg entry
  (including reversed legs and their compensating entries) has an active `apply`; undo
  regresses it as for `direct` documents.

### 2. Who (Owner answers 2026-10-08)

| Action | Permission |
|---|---|
| Create route, cancel route, reverse a leg | **new** `treasury.manage_routes` + project access (owner X, accountant) |
| Record a leg | `treasury.manage_routes` (any leg), or `treasury.create_transfer` when the user holds the from-wallet or the to-wallet (custodian rule of S2 transfers) |
| View routes | `treasury.view` |

### 3. Lock order (v17 §11 with S2's class 0)

Leg: 0 (from-wallet) → 4 (shared balance read) → 5 (document) → 6 (route). Leg
reversal: 0 (to-wallet) → 4 (the leg's entries, by id) → 5 → 6. Reconcile/undo: 4 → 5
→ 6 (read). Document reversal: 5 (lower id first) → 6. Cancel: 5 → 6. No transaction
takes a lower class after a higher one.

### 4. API (`/api/zena/projects/{project}/treasury/…`)

`GET routes` (filter by status), `GET routes/{route}` (legs, net per wallet,
remaining), `POST routes`, `POST routes/{route}/legs`, `POST routes/{route}/cancel`,
`POST route-legs/{leg}/reverse`. Error mapping as S2 (403/404/422/409).

### 5. Web (operator)

Project Treasury page: a "Tuyến tiền" section (open routes first, status, amount,
remaining) with a "Tạo tuyến" form; new page
`/operator/treasury/projects/{project}/routes/{route}` with the leg list, per-wallet
net, the "Ghi chặng" form, "Đảo chặng" and "Huỷ tuyến" (reason fields; plain forms, no
JavaScript). Register shows "Đang trên đường" and the route link. Users without the
right permission see the pages read-only.

### 6. Files (allowlist)

New `App\Services\Treasury\TreasuryRouteService`; changes in `TreasuryPostingService`
(reversal of `via_route` originals), `TreasuryReconciliationService` (§12.1 `via_route`
branch), `TreasuryBalanceService` (read helpers); `TreasuryPaymentRoute` /
`TreasuryPaymentRouteLeg` / `TreasuryLedgerEntry` / `TreasuryFinancialDocument`
relations and constants as needed; `TreasuryPolicy` and `AuthServiceProvider`
(`treasury.manage-routes`, `treasury.record-route-leg`); `ZenaPermissionsSeeder` and
`TreasuryRolePermissionSeeder` (+ `treasury.manage_routes` for owner and accountant;
`TreasuryRolePermissionSeederTest` count 16 → 17); new API controller
`Api\Treasury\TreasuryRouteController`; web actions in `TreasuryPageController` (or a
sibling web controller); views; routes (`api_zena.php`, web treasury routes); a hidden
concurrency command + one more method in the existing real-MySQL Treasury concurrency
test/job; tests; skip-inventory/phpstan baselines only if a new test needs it; governed
plan; this packet and 03-release.

**Not changed:** schema, `ContractPayment` and contract pages (S4b-2), advances (S5),
reports (S6), other modules.

### 7. Tests (TDD)

Create route for each eligible type (ineligible types rejected); funding route
outside → transit → site completes and posts; transfer route completes with source
−amount; external legs over the route amount rejected; from-wallet balance guard;
legs on completed/cancelled routes rejected; wallets of another project/tenant
rejected; leg reversal (reason, coupling, completed → partial, to-wallet guard,
reconciled document regresses); cancel rules; funding totals count only after
completion; document reversal of a completed `via_route` original (and refused while
partial); reconciliation of leg entries and the §12.1 `via_route` branch; permissions
(accountant creates routes; PM records only legs on wallets they hold; viewer
read-only); API + web; **concurrency on real MySQL**: two processes draining one
transit wallet, and two "from outside" legs over the route amount — the lock-removal
mutation must go red.

## Verification required at Gate 3

All Treasury tests green locally and exact-head CI (incl. the MySQL concurrency job);
mutation run red; PHPStan, php-cs-fixer, SSOT lint clean.

## Decision Needed

Owner chọn: `APPROVE GAP-094 Gate 2 Option A` (khuyến nghị) / Option B / Option C /
Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy; S4b-2 (truy vết thanh toán hợp đồng), S5, S6.
