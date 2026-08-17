---
work_id: GAP-037
gate: 2
gate_status: superseded
owner_decision:
  value: none
  authority: human_owner
decision_requested: null
references:
  spec: docs/superpowers/specs/2026-08-16-gap037-project-treasury-architecture-decisions.md
  plan: null
  branch: docs/GAP-037-project-treasury-gate1-prep
  pr: https://github.com/kha997/zenamanagephp/pull/263
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-16T22:10:40+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: docs/owner-decisions/GAP-037/02-design-v13.md
superseded_by: docs/owner-decisions/GAP-037/02-design-v15.md
timestamps:
  created_at: "2026-08-16T22:10:40+07:00"
  updated_at: "2026-08-18T06:21:51+07:00"
generated_by: agent
---

# GAP-037 — Project Treasury: Gate 2 Revision 14 — Reversal Completion Timing, Dependent-State Coupling, Atomic Advance-Cash Lifecycle (Self-Contained)

**Status:** Gate 1 approved. Gate 2 architecture decisions **approved** (`docs/owner-decisions/GAP-037/02-design.md`, frozen) — **A3 + A4-a + A.5 / B2 + B2-T / C / D**, not reopened. Fully self-contained. Still Gate 2 — a proposal, not implementation. No migration file, model, controller, service, route, UI, or test exists or is authorized by this packet.

**Database compatibility (unchanged verification):** MySQL for production/dev (`config/database.php`, `.env.example`), SQLite for the test suite (`.env.testing`, `phpunit.xml`).

**Superseded without an Owner round-trip:** per an explicit Owner instruction to close this schema to a single recommend-ready-for-approval revision without using the Owner as an interim QA pass, this packet was independently re-audited (not by Owner decision) and found not fully self-contained plus six further closure gaps; `docs/owner-decisions/GAP-037/02-design-v15.md` (self-contained, closure-audited) supersedes it. This packet (`02-design-v14.md`) is now frozen — no further edits.

---

## 0. What changed vs. v13, at a glance

| # | Owner's finding | Fix |
|---|---|---|
| 1 | §2.2's "reversal posting" trigger for the original's `status → 'reversed'` used "first `via_route` leg," not full route completion | Branched: `direct` reversal still fires at direct-post completion (unchanged, since that is a single atomic action); `via_route` reversal now fires only when its own route reaches full-target `completed` (§4.3), never at the first leg |
| 2 | Nothing coupled a completed document-level reversal to the *dependent* settlement facts the original had already created — cost allocations, advance outstanding, cash-return settlements | New §2.2b: when the original's `status → 'reversed'` fires (per fix #1's timing), dependent facts are atomically reversed in the same write: `expense` → its active cost-settlement allocations; `advance` → the advance closes to further applies; `advance_return` → its cash-return settlement gets a compensating reverse |
| 3 | Advance/cash-return posting and their corresponding `treasury_advances`/`treasury_advance_settlements` row creation were checked as preconditions but never bound to be *the same write* | §7.5/§7.6: advance posting + `treasury_advances` row creation is one atomic write; advance-return posting + `cash_return`/`apply` settlement creation is one atomic write; a `cash_return`/`reverse` settlement row may only ever be created by fix #2's automatic reversal coupling — no independent path creates one |

---

## 1. `treasury_financial_parties` and `treasury_wallets` — unchanged from v13

**`treasury_financial_parties`**: `id`, `tenant_id`, `party_type`, `name`, `linked_account_id` (nullable), `linked_user_id` (nullable), timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_wallets`**: `id`, `tenant_id`, `project_id` (nullable), `wallet_type`, `name`, `custodian_party_id` (nullable, composite FK), timestamps. **Unique: `(tenant_id, id)`.**

---

## 2. `treasury_financial_documents`

`id`, `tenant_id`, `project_id`, `document_type` (funding|internal_transfer|expense|owner_contribution|advance|advance_return|reversal|adjustment), `status` (draft|submitted|approved|rejected|posted_unreconciled|posted_reconciled|reversed), `posting_path` (nullable enum: `direct`|`via_route`, set exactly once), `amount`, `source_wallet_id` (nullable, composite FK), `destination_wallet_id` (nullable, composite FK), `source_party_id` (nullable, composite FK), `destination_party_id` (nullable, composite FK), `description`, `created_by`, `approved_by` (nullable), `posted_at` (nullable), `reversed_document_id` (nullable self-ref, composite), `replacement_document_id` (nullable self-ref, composite), timestamps. **Unique: `(tenant_id, id)`.** `UNIQUE(reversed_document_id)`. Same two mutual-exclusion `CHECK`s as v13 (`source_wallet_id`/`source_party_id`, `destination_wallet_id`/`destination_party_id`).

### 2.1 Posting-path freeze + amount immutability + eligibility gate — unchanged from v9-v13
Once `posting_path` is non-null, `amount` has no update path — immutable, full stop.

### 2.2 Document-level reversal — exact endpoints, and completion-timing branch (fix #1, revised this round)

v13 established: at-most-once, exact amount, exact opposite endpoints, postable-original precondition, and project match — all **unchanged, held constant** — as creation-time checks. v13's *post-time* rule (the original's `status → 'reversed'`) fired at "the same write that... creates its first `direct`-posting ledger entry or its first `via_route` leg." The Owner's finding: for `via_route`, "first leg" is not completion — a multi-leg route's first leg settling is not the same fact as the whole reversal having actually happened.

**Revised binding rule, branched on the reversal document's own `posting_path`:**

- **`posting_path = direct`:** unchanged from v13 — the original's `status` is set to `'reversed'` in the same transaction as the reversal document's own direct ledger entries being created (§5a). This branch needed no change: a `direct` posting is a single atomic action, so "posting completion" and "first entry created" were always the same fact for this branch — only the `via_route` branch was wrong.
- **`posting_path = via_route`:** the original's `status` is set to `'reversed'` **only** when the reversal's own linked `treasury_payment_routes` row reaches `status = 'completed'` per §4.3's full-target, endpoint-aware predicate — never at route attachment, never at the first leg settling, never while the route is `planned`/`partial`. This write is atomic with the route's own `completed` transition (§11 item 6), extended to also touch both financial-document rows (§11 item 5's fixed lower-`id`-first ordering, unchanged from v13) in the same transaction.

**Locking, both branches:** the fixed lock order already established across §11's items (1 → 2 → 3 → 4 → 5 → 6) is followed for whichever locks a given reversal-completion write touches — this round introduces no new relative ordering, only extends which items 5/6 checks bundle together atomically.

### 2.2a Reversal completion — a single defined moment, restated

To keep fix #1's branch unambiguous: "the reversal's own economic posting actually posts" (v13's phrase, now precisely defined) means exactly one of two moments, and only one applies to any given reversal document, since `posting_path` is set exactly once (§2.1) and never changes: the `direct`-posting transaction, or the `via_route` route's `completed` transition. There is no third moment, and no reversal document skips this — every `via_route`-eligible document (§4.1) that a reversal targets is itself, if it also chooses `via_route`, subject to the same completion predicate as any other route.

### 2.2b Dependent-state reversal coupling (fix #2, new this round)

When the original's `status → 'reversed'` fires (§2.2's completion moment, whichever branch applies), the following **dependent** facts — settlement-layer state the original document's own posting had already created — are reversed **in the same atomic write**, scoped by the original's `document_type`:

- **`document_type = 'expense'`:** for every `treasury_cost_settlement_allocations` row with `financial_document_id = <original>` that is currently active (`direction = apply`, no currently-active compensating `reverse` row per §7's `UNIQUE(reverses_allocation_id)`), a matching `direction = reverse` row is created (`reverses_allocation_id` → that `apply` row, same `allocated_amount`), exactly mirroring §7.4's existing reverse-completeness shape — the only difference is this reversal is triggered automatically by the document-level reversal completing, not by a separately-requested manual reverse.
- **`document_type = 'advance'`:** the `treasury_advances` row whose `originating_financial_document_id = <original>` is **closed to further `apply`-direction settlements** from this moment forward — no new `treasury_advance_settlements` row with `direction = apply` may reference this `treasury_advances` row once its originating document is `reversed` (the cash that funded the advance no longer exists). This is a standing write-time check (consulting the originating document's `status`), not a one-time creation-time check. **Scope boundary, stated explicitly:** this does *not* cascade to automatically reverse settlements that were already `apply`-active against the advance before the reversal — an already-consumed advance balance (e.g. an `approved_expense` settlement made before the reversal) is a separate fact that would need its own explicit reversal if ever undone; this closure only prevents *new* draws against a now-reversed advance, it does not retroactively unwind prior draws.
- **`document_type = 'advance_return'`:** the exactly-one linked `treasury_advance_settlements` row (`settlement_type = cash_return`, `direction = apply`, `financial_document_id = <original>`, guaranteed unique per v13 §7.5b) gets a compensating `direction = reverse` row created (`reverses_settlement_id` → that `apply` row, same `amount`) — undoing the cash-return's effect on the advance's outstanding balance, via the same §7.4 mechanism used for any other settlement reversal.
- **Every other `document_type`** (`funding`, `internal_transfer`, `owner_contribution`, `adjustment`): no dependent settlement-layer state exists for these types (§6/§7 never key off them), so reversal completion is exactly §2.2's status flip alone — no additional coupled write.

**All of the above, when they apply, happen in the exact same transaction as §2.2's status flip** — never a follow-up write, never eventually-consistent. A reversal is either fully applied (status flip + every applicable dependent reversal) or not applied at all.

---

## 3. The typed-nullable-FK pattern — unchanged principle

Every former polymorphic reference is N nullable typed FK columns + a `CHECK` requiring exactly one non-null: `treasury_payment_routes` (§4), `treasury_ledger_entries` (§5), `treasury_cost_settlement_allocations` (§7), `treasury_fund_chain_members` (§8).

---

## 4. `treasury_payment_routes` and `treasury_payment_route_legs`

**`treasury_payment_routes`**: `id`, `tenant_id`, `project_id`, `total_allocated_amount`, `status` (planned|partial|completed|cancelled), `linked_financial_document_id` (nullable, composite FK), `linked_contract_payment_id` (nullable, single-column FK), `expected_destination_wallet_id` (nullable, composite FK), timestamps. Same `CHECK`s as v11-v13. Unique index directly on `linked_financial_document_id`. **Unique: `(tenant_id, id)`.**

### 4.1 Conservation, immutability, and eligibility — unchanged from v13

**Case A:** `SUM(total_allocated_amount) <= ContractPayment.amount`. **Case B:** `route.total_allocated_amount = linked_financial_document.amount`, both frozen atomically at attachment.

**`via_route` eligibility (unchanged field-keyed table from v13):**

| Document's own populated endpoint fields | `via_route` eligible? |
|---|---|
| `destination_wallet_id` set, `destination_party_id` NULL, `source_wallet_id` NULL | **Yes** |
| `source_wallet_id` set AND `destination_wallet_id` set, both party fields NULL | **Yes** |
| `destination_party_id` set | **No — `direct` only** |
| `source_wallet_id` set, `destination_wallet_id` NULL, `destination_party_id` NULL | **No — `direct` only** |

A `via_route`-eligible `reversal` document — including one whose completion this round newly defines precisely (§2.2) — is classified by this same table using its own (swapped) fields, unchanged.

**`treasury_payment_route_legs`**: unchanged column set from v9-v13.

### 4.2 Route-leg custody — unchanged mechanics from v7-v13

### 4.2a Case A ledger-posting precondition — unchanged from v13
`ContractPayment.status = 'paid' AND paid_at IS NOT NULL`, checked under the `contract_payments` lock (§11 item 1).

### 4.2b Atomic leg-reversal ↔ ledger-reversal coupling — unchanged from v12/v13

### 4.3 Route-completion predicate — endpoint-aware, full-target — unchanged from v11-v13

Define `net_custody(w) = SUM(credit) - SUM(debit)` over this route's ledger entries at wallet `w`. Wallet-terminating inbound / `internal_transfer` / Case A branches unchanged from v13. **This is the exact predicate fix #1's `via_route` reversal-completion branch (§2.2) now depends on** — no new predicate was invented for reversals; the already-existing route-completion definition is simply the trigger fix #1 uses.

---

## 5. `treasury_ledger_entries` — unchanged schema from v9-v13

`id`, `tenant_id`, `source_financial_document_id` (nullable, composite FK), `source_payment_route_leg_id` (nullable, composite FK), `wallet_id` (composite FK), `direction` (`debit`\|`credit`), `amount`, `entry_type`, `posted_at`, `reversal_of_entry_id` (nullable self-ref, composite), `created_at`. Same `CHECK`, idempotency, and reversal shape as every prior round.

**Four distinct reversal mechanisms now exist in this schema — the third and fourth are new/clarified this round, restated to prevent conflation:** (a) `treasury_ledger_entries.reversal_of_entry_id` — single erroneous ledger-entry correction. (b) `treasury_payment_route_legs.status = 'reversed'` coupled atomically to its own entries (§4.2b) — leg-level, scoped to one route. (c) `treasury_financial_documents.document_type = 'reversal'` (§2.2) — document-level, economically real undo, whose *completion timing* fix #1 now precisely defines. (d) **§2.2b's dependent-state coupling (new this round)** — not a reversal mechanism of its own, but the automatic *triggering* of mechanisms (b) [already covered elsewhere] and the `apply`/`reverse` event-log pattern in §7 (cost allocations, advance settlements) as a **consequence** of (c) completing. (d) never creates a *new kind* of reversal row — it only causes existing patterns (§7's `reverses_*_id` shape) to fire automatically instead of by a separate manual request.

### 5a. Posting conservation matrix — unchanged from v13

---

## 6. Settlement conservation — unchanged from v7-v13

**6.1-6.7** unchanged. §2.2b's dependent-state coupling produces exactly the `apply`/`reverse` row shapes these formulas already account for — no new arithmetic, only a new automatic trigger for writes these formulas were always compatible with.

---

## 7. `treasury_cost_settlement_allocations`, `treasury_advances`, `treasury_advance_settlements`

**`treasury_cost_settlement_allocations`**: unchanged from v7-v13.

**`treasury_advances`**: unchanged column set from v13, `UNIQUE(originating_financial_document_id)` unchanged.

**`treasury_advance_settlements`**: unchanged column set from v13, `UNIQUE(financial_document_id)` unchanged.

### 7.4 Advance-settlement completeness — unchanged from v7-v13

### 7.5 Atomic advance posting (fix #3, revised this round — was "creation precondition," now "same write")

v13's §7.5a required `originating_financial_document.status IN ('posted_unreconciled', 'posted_reconciled')` **before** a `treasury_advances` row could be created — phrased as a precondition on a (potentially separate, later) write. The Owner's finding: a precondition alone permits a window where the document has posted but the `treasury_advances` row does not yet exist (or, worse, never gets created at all, silently losing the advance-tracking fact).

**Binding rule (strengthened):** the `advance` document's own posting transition (its `debit` ledger entry creation, per §5a — the moment it first satisfies `status = posted_unreconciled`) and the creation of its `treasury_advances` row are **one atomic write, in the same transaction**. There is no code path that posts an `advance` document without also creating its `treasury_advances` row in that same write, and none that creates a `treasury_advances` row referencing a document that is not, in that same transaction, becoming posted. Type/amount/party match (§7.5a's v13 checks — `document_type = 'advance'`, amount equality, `financial_party_id = originating_financial_document.destination_party_id`) and `UNIQUE(originating_financial_document_id)` are unchanged, now simply checked as part of this one atomic write instead of a separable precondition. Lock: the `treasury_financial_documents` row (§11 item 5) and the `treasury_advances` row (§11 item 3), in that relative order (item 3 comes after item 5 in the fixed order — restated here since this is the first round where both are acquired together for a single write).

### 7.6 Atomic advance-return posting (fix #3, revised this round)

Same strengthening as §7.5, applied to the other end of the lifecycle. v13's §7.5b required a matching `cash_return`/`apply` settlement to exist before an `advance_return` document could post.

**Binding rule (strengthened):** the `advance_return` document's own posting transition (its `credit` ledger entry creation) and the creation of its `treasury_advance_settlements` row (`settlement_type = cash_return`, `direction = apply`, `financial_document_id = <this document>`) are **one atomic write, in the same transaction**. Amount match and party match (§7.5b's v13 checks) are checked as part of this same write. Lock: `treasury_financial_documents` row (§11 item 5), `treasury_advances` row (§11 item 3, since the settlement affects that advance's outstanding balance), same relative order as §7.5.

### 7.7 `cash_return`/`reverse` settlements are created only by reversal coupling (fix #3, new this round)

A `treasury_advance_settlements` row with `settlement_type = cash_return` and `direction = reverse` may be created **only** as part of §2.2b's dependent-state coupling — i.e., only as the automatic consequence of an `advance_return` document's own `reversal` document economically completing (§2.2's timing). There is no independent, manually-triggered write path that creates a `cash_return`/`reverse` row outside of that coupling. This closes the gap fix #3 identifies: without this rule, a `cash_return`/`reverse` row could in principle be created disconnected from any real document-level reversal ever having happened, silently corrupting §6.5's outstanding formula with a fact the ledger never actually recorded.

**`approved_expense` settlements remain unaffected — restated as a non-second-cash-movement invariant (fix #3's closing clause):** `approved_expense`-type settlements (either direction) never have a paired `treasury_ledger_entries` row of their own — they represent applying an already-existing advance balance against a cost, not a fresh cash movement (unchanged since the round that introduced §6/§7). Neither §2.2b's coupling nor §7.5/§7.6/this section's atomicity rules create, require, or imply a ledger entry for an `approved_expense` settlement or its reverse — if an expense that was settled via `approved_expense` is itself the target of a document-level reversal, §2.2b's `expense`-branch coupling (reversing its `treasury_cost_settlement_allocations` rows, if any exist with `financial_document_id` set) is what fires; the `approved_expense` settlement itself, being keyed by `advance_settlement_id` rather than `financial_document_id` in `treasury_cost_settlement_allocations` (§7's existing two-way `CHECK`), is untouched by this coupling — consistent with it never having created a cash movement to undo in the first place.

---

## 8. `treasury_fund_chains` and `treasury_fund_chain_members` — unchanged

## 9. Reversal invariants — unchanged, cross-referenced by §5's now-four-mechanism disambiguation

## 10. `treasury_expense_approvals` — unchanged from v12/v13, including §10.1's approval gate

---

## 11. Concurrency — unchanged list from v13, items 3 and 5 extended by this round's atomic-write checks

| # | Check | Row/subject locked |
|---|---|---|
| 1 | `ContractPayment` route-allocation conservation, `expected_destination_wallet_id` immutability/compatibility, Case A `status='paid' AND paid_at IS NOT NULL` gate | `contract_payments` row |
| 2 | Cost over-settlement cap (§6.3) | `contract_expenses`/`material_receipt_lines` row |
| 3 | Advance outstanding cap + settlement completeness, `advance_return` linkage/amount/party match, **and, new this round, §7.5/§7.6's atomic advance/advance-return posting + row-creation, plus §2.2b's advance-closure-to-new-applies and advance/advance_return dependent-reversal writes** | `treasury_advances` row |
| 4 | Active reconciliation uniqueness, inverse-reconciliation status regression | `treasury_ledger_entries` row |
| 5 | Financial-document posting-source selection, Case B equality + immutability freeze, structural eligibility gate, §2.2's exact/at-most-once/opposite-endpoint reversal precondition **and its now-branched atomic status-flip (fix #1)**, **§2.2b's dependent-state coupling (fix #2)**, direction/linkage rules, expense approval gate | `treasury_financial_documents` row — fixed lower-`id`-first ordering for the original/reversal pair, unchanged from v13 |
| 6 | Route-leg custody, full-target endpoint-aware completion, atomic leg-reversal/ledger-reversal coupling, **and, new this round, the `via_route` reversal-completion trigger (§2.2, fix #1) sharing this same lock** | Parent `treasury_payment_routes` row |

MySQL: `SELECT ... FOR UPDATE`. SQLite (test suite): `BEGIN IMMEDIATE`. **Fixed total lock order across every item above is 1 → 2 → 3 → 4 → 5 → 6, unchanged from prior rounds** — this round's more complex atomic writes (§2.2b touching both item 5 and, depending on `document_type`, item 3 or item 2; §7.5/§7.6 touching items 5 and 3 together) simply acquire more of the existing fixed-order locks in a single transaction than any prior round's writes did, never a new relative order.

---

## 12. `treasury_reconciliations` and `treasury_reconciliation_entries` — unchanged from v9-v13

## 13. Composite-FK-target index requirement — unchanged (12 tables)

## 14. Tier B — existing-table FK tenant/project rules — unchanged from v9-v13

## 15. Treasury-internal same-project integrity — unchanged 8 rows from v11-v13

## 16. Exact table inventory and migration order — unchanged (14 tables). This round adds zero new columns and zero new indexes — every fix is a write-path/lock/trigger-timing rule over columns and indexes that already existed as of v13.

---

## 17. Verification against every held-constant item

- A3/A4-a/A.5/B2/B2-T/C/D: unchanged, not reopened. A4-a exclusion held. D — no `ReportPageController::cashflow()` edit: none of this round's 3 fixes touch that controller.
- B2-T no-second-`ContractPayment`-fact: unaffected — this round adds no new read or write against `ContractPayment`.
- 14-table count, 12 composite-FK targets, source/destination party provenance, exact endpoint reversal shape (creation-time checks, unchanged), explicit Case A endpoint, full-target completion predicate (used, not altered, by fix #1), posting matrix, leg-reversal atomicity, many-to-many cost allocation, approval/reconciliation lifecycle, Tier-B/project isolation, MySQL/SQLite locking, zero existing-table/data migration: all unchanged from Revision 13.
- Fix #1 verified to change only the `via_route` branch's trigger moment — the `direct` branch, and every creation-time check in §2.2, are byte-for-byte unchanged from v13.
- Fix #2 verified scoped to exactly the three `document_type`s (`expense`, `advance`, `advance_return`) that have dependent settlement-layer state in this schema at all; every other type is explicitly stated to receive no additional write, and the explicit non-cascading scope boundary for `advance` (blocks new applies, does not retroactively unwind prior ones) is stated to prevent over-reading this fix as a deeper cascade than the Owner asked for.
- Fix #3 verified additive-only at the write-path/lock level — zero new columns, zero new indexes; §7.7's "only created by reversal coupling" rule is a closure of an open write path, not a new one.
- No external-destination leg representation designed this round (unchanged out-of-scope item).

---

## 18. Trạng thái và bước tiếp theo
- Nếu Owner Approve: chuẩn bị Gate 3 cho GAP-037 — vẫn chỉ là quyết định merge tài liệu.
- Nếu Owner Request changes: sẽ tạo `02-design-v15.md` (supersedes bản này).
- Nếu Owner Decline: dừng GAP-037 ở schema-proposal này.

## 19. Loại trừ phạm vi
Kế thừa nguyên vẹn từ mọi round trước: không migration file thật; không model/controller/service/route/UI/test thật; không seed/backfill; không implementation plan coi schema này là đã duyệt cho Gate 3; không Gate 3 tự suy luận; không mark PR ready; không merge PR #263; không sửa/merge/đóng PR #245 hoặc #257; không GAP-036; không Today Workspace; không sửa canonical SSOT stale metadata; không production/deployment; không thiết kế external-destination leg representation; không sửa `ReportPageController::cashflow()`.

## Decision Needed
Owner chọn một: Approve corrected schema proposal to proceed toward Gate 3 preparation / Request further changes / Decline.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt migration file thật hay chi tiết implementation. Owner cũng không được yêu cầu duyệt lại architecture set A3/A4-a/A.5/B2/B2-T/C/D — đã approved, không mở lại. Owner cũng không được yêu cầu duyệt overpayment/prepayment semantics, hay thiết kế external-destination leg representation (nêu là future extension point, chưa thiết kế ở đây).
