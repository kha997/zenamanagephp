---
work_id: GAP-064
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-07-gap-064-treasury-s2-ledger-readiness.md
  plan: null
  branch: docs/GAP-064-treasury-s2-ledger
  pr: https://github.com/kha997/zenamanagephp/pull/337
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-07T08:17:02+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-07: 'APPROVE GAP-064 Gate 2 Option A'. Reviewed design head: f348a3cd3cc2228747ef4553170d46b671450d96. Approves Option A and its exact allowlist, including both flagged points (negative-balance guard on transfers and decrease adjustments with a new wallet lock class 0 placed before the approved 1-6 order; reversals exempt from the guard); not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-07T07:58:48+07:00"
  updated_at: "2026-10-07T08:17:02+07:00"
generated_by: agent
---

# GAP-064 — Treasury S2 ledger engine: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-07 against reviewed design head
`f348a3cd3cc2228747ef4553170d46b671450d96`. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Thiết kế S2 theo đúng thiết kế đã duyệt (v17) và 3 câu trả lời của Owner. **Hai
điểm Owner cần quyết khi duyệt:**

1. **Chặn số dư âm (đề xuất: có).** Chuyển ví và điều chỉnh giảm bị từ chối nếu
   ví nguồn không đủ tiền — tránh nhập sai kiểu "chuyển 500 triệu từ ví còn 50
   triệu". Để chặn đúng khi hai người thao tác cùng lúc, ví nguồn được khoá trước
   mọi khoá khác (bổ sung một bậc khoá **đứng trước** toàn bộ thứ tự khoá đã
   duyệt, không đổi thứ tự cũ).
2. **Đảo bút toán không bị chặn bởi số dư.** Đảo một khoản nhận tiền mà tiền đó
   đã được chuyển đi sẽ làm ví âm — vẫn cho phép (sửa sai không được bị khoá),
   số dư âm hiển thị màu đỏ để xử lý tiếp.

Đề xuất **Phương án A**.

## Options

| Option | Content | Verdict |
|---|---|---|
| **A. Block negative balance on transfers/decrease adjustments; reversals exempt** | As below | **Recommended** |
| B. Warn only, never block | Simpler; no extra lock | Allows obviously wrong entries |
| C. Block everything incl. reversals | Strictest | A wrong funding whose cash already moved could never be corrected without first reversing later transfers |

## Design: Option A (exact allowlist)

### 1. Migration (additive, Owner answer 1)

`treasury_financial_documents`: add `transaction_date` (date, nullable in DB;
required by the application for every document S2 creates) and `reference`
(string 100, nullable); index `(project_id, transaction_date)`. No other schema
change. `down()` drops both columns and the index.

### 2. Posting service — `App\Services\Treasury\TreasuryPostingService`

All writes in one DB transaction each; every rule below restates v17.

| Action | Document | Ledger (§5a direct) | Permission |
|---|---|---|---|
| Declare funding | `funding`, party → project wallet | 1 credit at destination | `declare_funding` |
| Owner contribution | `owner_contribution`, party (type `owner`) → wallet | 1 credit | `declare_funding` |
| Internal transfer | wallet → different wallet, same project | debit source + credit destination | `create_transfer`; from any wallet only with `manage_wallets` (X), otherwise only from a wallet whose custodian party is linked to the user (Owner answer 2) |
| Adjustment | one wallet; increase = destination, decrease = source; `description` (reason) required | 1 credit / 1 debit | `adjust` (X, accountant) |
| Reversal | `reversal` of a posted funding / owner_contribution / transfer / adjustment | own shape = exact swap (§2.2) | `reverse` (X, accountant) |

- **Immediate posting** (PR #245 §5.2.1, §8.3): the document passes the closed
  graph `draft → submitted → approved → posted_unreconciled` inside the same
  transaction; `approved_by` = actor, `posting_path = direct`, `posted_at` =
  now. Never any other transition.
- **Ledger entries** (§5): `entry_type` ∈ funding, owner_contribution,
  transfer_out, transfer_in, adjustment_increase, adjustment_decrease,
  reversal; `original_posting_key = <document_id>:<wallet_id>:<direction>`
  (unique → idempotent); entries never updated or deleted.
- **Reversal rules** (§2.2, §2.2a, §2.2c): at most once; no
  reversal-of-reversal; same amount and project; endpoints swapped; original
  must be `posted_*`; reversal posts directly and the original flips to
  `reversed` in the same transaction; both document rows locked lower-`id`
  first (§11 class 5). Optional **replacement link**: a separate action sets
  `replacement_document_id` on a reversal (once) to a document of the same
  project — audit link only, no economic effect.
- **Same-project integrity** (§15 rows 4–6): every wallet and linked document
  must belong to the document's project; amounts `> 0`, VND, 2 decimals.
- **Negative-balance guard** (Option A): transfers and decrease adjustments
  lock the source wallet row (`SELECT … FOR UPDATE`; SQLite tests use the
  transaction) **before** any v17 class-1…6 lock — a new class 0, so the
  approved order 1→6 is unchanged — then require
  `SUM(credit) − SUM(debit) ≥ amount`. Reversals are exempt.
- **Duplicate warning** (Owner answer 1, PR #245 §5.2.6): declaring funding /
  owner contribution with the same project, type, amount, transaction date,
  source, destination and reference as an existing non-reversed posted
  document returns a warning; the user must confirm to post anyway.
- **Audit trail** (PR #245 §5.8.6): one `audit_logs` row per action (entity
  `treasury_financial_document`, project, tenant, actor, before/after status,
  amount, reason).

### 3. Balances — read side

`TreasuryBalanceService`: wallet balance = `SUM(credit) − SUM(debit)`;
project held funds = sum over project wallets; investor funding total = posted,
non-reversed `funding` amounts; owner contribution total separately (PR #245
§5.2.7); internal transfers change neither total.

### 4. API (`/api/zena/projects/{project}/treasury/…`)

`GET documents` (register, filters type/status/date), `GET documents/{id}`,
`POST funding`, `POST transfers`, `POST adjustments`,
`POST documents/{id}/reverse`, `POST documents/{id}/replacement`,
`GET balances`. Rejections: 403 permission/membership, 404 other tenant/project,
422 validation and rule violations (insufficient balance, already reversed,
reversal-of-reversal, wrong project), 409 duplicate warning unless
`confirm_duplicate=true`.

### 5. Web (project Treasury page)

Balance card (each wallet, project total, investor funding, owner contribution;
negatives in red); forms "Khai báo tiền nhận", "Chuyển ví", "Điều chỉnh" shown
only to permitted users (transfer source list filtered by Owner answer 2);
transaction register with status and an "Đảo" action (reason + date) for
`reverse` holders; duplicate warning with a confirm checkbox.

### 6. Files (allowlist)

New migration; `TreasuryPostingService`, `TreasuryBalanceService`;
`TreasuryPolicy` (new abilities: declare, transfer, adjust, reverse);
`AuthServiceProvider` registration; API controller
`Api\Treasury\TreasuryDocumentController`; `Web\Treasury\TreasuryPageController`
(new actions); views under `resources/views/treasury/`; routes; model
`TreasuryFinancialDocument` (fillable/casts for the two new columns,
`HasFactory`) and `TreasuryLedgerEntry` (`HasFactory` if needed); factories;
tests; governed plan file.

### 7. Tests (TDD)

Spec scenarios A (funding → +wallet, posted_unreconciled), C (transfer: totals
unchanged, two balanced entries), F (wrong amount → reverse + replacement →
correct balances, all three traceable); immediate-posting graph; idempotency
(duplicate posting key rejected); reversal rules (twice, of reversal, of
unposted, other project); negative-balance guard incl. concurrent attempts on
MySQL CI; Z transfer restriction; adjustment reason; permission matrix;
duplicate warning; audit rows; API + web.

## Verification required at Gate 3

All tests green locally and in exact-head CI (incl. Treasury MySQL job); pages
checked in a local run.

## Risks

New lock class 0 (wallet) — additive to §11; reversal may leave a negative
balance (intended, visible). No reconciliation yet (S4): documents stay
`posted_unreconciled`.

## Out of scope

Expenses/approvals, routes, reconciliation, advances, reports, company
wallets, deployment.
