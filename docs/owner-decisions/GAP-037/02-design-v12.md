---
work_id: GAP-037
gate: 2
gate_status: changes_requested
owner_decision:
  value: changes_requested
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
  recorded_at: "2026-08-16T20:58:07+07:00"
  owner_response_reference: "Owner Gate 2 Schema Proposal Revision 12 decision -- REQUEST CHANGES, recorded in-session on 2026-08-16 against reviewed PR #263 head 19c60f680635a0243790c6540e54f98b67afe781: 'GAP-037 -- Gate 2 Schema Proposal Revision 12 -- Owner Decision: REQUEST CHANGES. Toi, Owner, yeu cau chinh sua schema proposal tai PR #263, reviewed head 19c60f680635a0243790c6540e54f98b67afe781. Toi xac nhan Revision 12 da xu ly dat cac phan: Case A paid-status gate; exact posting conservation matrix; atomic leg-ledger reversal; expense approval gate; inverse reconciliation lifecycle; va phan at-most-once/amount-equality cua document reversal. Architecture A3 + A4-a + A.5 / B2 + B2-T / C / D van approved, frozen, khong mo lai. Revision 13 chi can closure cac diem con lai: Exact document-level economic reversal: reversal phai la equal-and-opposite cua original ve amount va endpoints; original chi chuyen reversed atomically khi reversal economic posting thuc su post. Directional external-party provenance: thay single ambiguous counterparty_id bang source_party_id/destination_party_id hoac equivalent unambiguous representation; funding/owner contribution/advance return phai giu duoc nguon party; expense/advance phai giu destination party; reversal swap dung endpoints. Advance-cash conservation: originating advance document phai type/amount/party-match va unique; advance_return phai exactly-one link toi cash_return/apply, same amount va same advance party; khong cho advance outstanding va wallet cash movement drift. Case A leg settlement gate phai yeu cau canonical payment status=paid va paid_at IS NOT NULL. Giu nguyen toan bo phan v12 da dat: 14-table architecture; 12 composite-FK targets; explicit Case A endpoint; full-target endpoint completion; posting matrix; leg-reversal atomicity; B2-T no-second-payment-fact; cost allocation many-to-many; approval/reconciliation lifecycle; Tier-B/project isolation; MySQL/SQLite concurrency; A4-a; D no company-cashflow edit; zero existing-table/data migration; #245/#257 untouched. Record REQUEST CHANGES truoc vao 02-design-v12.md, freeze v12, sau do tao self-contained 02-design-v13.md, rerun required CI va quay lai awaiting_owner. Khong suy luan schema approval hoac Gate 3 authorization.'"
  reconciliation_required: false
supersedes: docs/owner-decisions/GAP-037/02-design-v11.md
superseded_by: null
timestamps:
  created_at: "2026-08-16T20:19:30+07:00"
  updated_at: "2026-08-16T20:58:07+07:00"
generated_by: agent
---

# GAP-037 — Project Treasury: Gate 2 Revision 12 — Consolidated Closure: 6 Binding Invariants (Self-Contained)

**Status:** Gate 1 approved. Gate 2 architecture decisions **approved** (`docs/owner-decisions/GAP-037/02-design.md`, frozen) — **A3 + A4-a + A.5 / B2 + B2-T / C / D**, not reopened. Fully self-contained. Still Gate 2 — a proposal, not implementation. No migration file, model, controller, service, route, UI, or test exists or is authorized by this packet.

**Database compatibility (unchanged verification):** MySQL for production/dev (`config/database.php`, `.env.example`), SQLite for the test suite (`.env.testing`, `phpunit.xml`).

**Repo fact verified for this round:** `ContractPayment.status` is a real column with exactly three values — `planned`, `paid`, `overdue` (`app/Models/ContractPayment.php`, `STATUS_PLANNED`/`STATUS_PAID`/`STATUS_OVERDUE` constants, `VALID_STATUSES`). Invariant #1 below binds to `status = 'paid'` specifically, not to any invented or assumed status name.

---

## 0. What changed vs. v11, at a glance

This is a **consolidated closure round** — 6 new binding invariants, no change to any invariant v11 already established.

| # | Invariant | Where |
|---|---|---|
| 1 | Case A route custody may only post to the ledger after `ContractPayment.status = 'paid'` | §4.2a (new) |
| 2 | Exact document/route-leg → ledger posting conservation matrix, every `document_type` × `posting_path` combination | §5a (new) |
| 3 | Atomic leg-reversal ↔ ledger-reversal coupling | §4.2b (new) |
| 4 | Document-level reversal is exact (amount equality) and at-most-once (`UNIQUE(reversed_document_id)`) | §2.2 (new) |
| 5 | Unambiguous external-party direction convention + mandatory `advance_return` ↔ `cash_return` settlement linkage | §2.3 (new) |
| 6 | Expense approval gate on posting + inverse reconciliation lifecycle (reconciliation reversal regresses document status) | §10.1, §12.2 (new) |

---

## 1. `treasury_financial_parties` and `treasury_wallets` — unchanged from v11

**`treasury_financial_parties`**: `id`, `tenant_id`, `party_type`, `name`, `linked_account_id` (nullable), `linked_user_id` (nullable), timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_wallets`**: `id`, `tenant_id`, `project_id` (nullable), `wallet_type`, `name`, `custodian_party_id` (nullable, composite FK), timestamps. **Unique: `(tenant_id, id)`.**

---

## 2. `treasury_financial_documents`

`id`, `tenant_id`, `project_id`, `document_type` (funding|internal_transfer|expense|owner_contribution|advance|advance_return|reversal|adjustment), `status` (draft|submitted|approved|rejected|posted_unreconciled|posted_reconciled|reversed), `posting_path` (nullable enum: `direct`|`via_route`, set exactly once), `amount`, `source_wallet_id` (nullable, composite FK), `destination_wallet_id` (nullable, composite FK), `counterparty_id` (nullable, composite FK), `description`, `created_by`, `approved_by` (nullable), `posted_at` (nullable), `reversed_document_id` (nullable self-ref, composite), `replacement_document_id` (nullable self-ref, composite), timestamps. **Unique: `(tenant_id, id)`.** **New this round (fix #4): `UNIQUE(reversed_document_id)`** — at most one `reversal` document may target any given original document.

A `reversal` document is a first-class row in this same table (`document_type = reversal`, `reversed_document_id` set) — it carries its **own** `source_wallet_id`/`destination_wallet_id`/`counterparty_id` describing its own actual money movement, not a copy of the original document's fields. Eligibility (§4.1) and completion (§4.3) both read these fields directly off the reversal row itself.

### 2.1 Posting-path freeze + amount immutability + eligibility gate — unchanged from v9/v10/v11
A route may only attach (§4) while `posting_path IS NULL` and `status` is unposted, and only for a document/endpoint-shape combination that is `via_route`-eligible per §4.1's structural rule. Attaching sets `posting_path = via_route` and simultaneously freezes `amount` as immutable — both writes occur in the same transaction, under the same lock (§11 item 5). If no route is ever attached and the document posts directly, `posting_path` locks to `direct` at that moment; `amount` is fixed at whichever of these two transitions occurs first. Once `posting_path` is non-null, `amount` has no update path — immutable, full stop.

### 2.2 Document-level reversal — exact and at-most-once (fix #4, new this round)

v11 and earlier rounds established that a `reversal` document is a real row with its own endpoint fields (§2, §4.1), but never stated a binding uniqueness or amount-equality rule for it — this left two gaps: nothing prevented two separate `reversal` documents from targeting the same original, and nothing tied a reversal's `amount` to the original's `amount`.

**Binding rules, checked at the moment a `reversal` document's `reversed_document_id` is set (creation time — `reversed_document_id` is set once and is itself immutable thereafter, consistent with every other "set-once" field in this schema):**

1. **At-most-once:** `UNIQUE(reversed_document_id)` (§2, schema-level) — a second `reversal` document cannot ever target the same `reversed_document_id`. This is the same `UNIQUE(reverses_*_id)` pattern already used for `treasury_cost_settlement_allocations.reverses_allocation_id`, `treasury_advance_settlements.reverses_settlement_id`, and `treasury_reconciliation_entries.reverses_reconciliation_entry_id` (§7, §12) — applied here for consistency to the one remaining "reverses" relationship in the schema that didn't yet have it.
2. **Exact amount:** `reversal.amount = reversed_document.amount` — checked at creation, under the same lock as §11 item 5 (the `reversed_document`'s own row, since it is being referenced and must not be concurrently modified — though its `amount` is already immutable once posted per §2.1, so this is a read-and-compare, not a contested write). A partial-amount "reversal" is not representable by this table — that is a `document_type = adjustment` (decrease), a distinct concept, never a `reversal`.
3. **Postable-original precondition:** `reversed_document_id` may only reference a document whose `status IN ('posted_unreconciled', 'posted_reconciled')` at the moment the reversal is created — there is no economic movement to undo for a `draft`/`submitted`/`approved`/`rejected` document, since nothing posted yet. (A rejected document simply never posts; no reversal is needed or permitted.)
4. **Project match:** unchanged from §15 row 4 — `reversed_document_id`'s project must equal the reversal's own `project_id`.

### 2.3 External-party direction convention + advance/cash-return semantic binding (fix #5, new this round)

v11 left the direction of `counterparty_id`-bearing documents implicit (inferable from `document_type` but never stated as a binding rule), and never required that an `advance_return` document actually be backed by a real settlement row. Both are made explicit and binding this round.

**5a — Unambiguous external-party direction.** This schema has exactly one direction for any document that populates `counterparty_id`: **wallet → party (outflow)**. `source_wallet_id` is the paying wallet (debited); `counterparty_id` is the receiving external party. This applies to `expense` and `advance` — the only two `document_type` values that ever populate `counterparty_id` (§4.1's eligibility table). **There is no `counterparty_id`-bearing inflow direction in this schema** — money entering Treasury custody from an external party is always represented as `funding`/`owner_contribution`/`advance_return` (wallet-only fields: `destination_wallet_id` set, `counterparty_id` left `NULL`), with the originating party identified indirectly through the domain relationship (e.g. `advance_return`'s party is known via `treasury_advances.financial_party_id`, per 5b below — not via a second, redundant `counterparty_id` value on the return document itself). Binding constraint: a document with `counterparty_id` set must have `source_wallet_id` set and `destination_wallet_id` NULL — the inverse combination (`counterparty_id` set with `destination_wallet_id` set, implying an inflow-from-party) is rejected at write time as not a representable shape in this model.

**5b — Mandatory `advance_return` ↔ `cash_return` settlement linkage.** A `treasury_financial_documents` row with `document_type = advance_return` may only reach `status = posted_unreconciled` (i.e., may only post) if there exists a `treasury_advance_settlements` row with `financial_document_id` equal to this document's `id`, `settlement_type = cash_return`, and `direction = apply` (§7's `treasury_advance_settlements` table, `settlement_type`/`direction` columns already exist — this is a new binding *precondition* on the document's posting transition, not a new column). Checked under the same lock as §11 item 5 (the document row) jointly with §11 item 3 (the parent `treasury_advances` row the settlement belongs to, locked in the same order as every other advance-settlement write in §7.4). Without this precondition, a `advance_return` document could post — moving real ledger custody into a wallet — with no corresponding record of which advance it actually returned, silently breaking §6.5's advance-outstanding conservation. There is no equivalent mandatory-linkage rule for `expense`/`advance` documents beyond what §6/§7 already require for cost/advance settlement, since those flows were already fully specified in prior rounds; this round closes the one remaining unlinked case, `advance_return`.

---

## 3. The typed-nullable-FK pattern — unchanged principle

Every former polymorphic reference is N nullable typed FK columns + a `CHECK` requiring exactly one non-null: `treasury_payment_routes` (§4), `treasury_ledger_entries` (§5), `treasury_cost_settlement_allocations` (§7), `treasury_fund_chain_members` (§8).

---

## 4. `treasury_payment_routes` and `treasury_payment_route_legs`

**`treasury_payment_routes`**: `id`, `tenant_id`, `project_id`, `total_allocated_amount`, `status` (planned|partial|completed|cancelled), `linked_financial_document_id` (nullable, composite FK), `linked_contract_payment_id` (nullable, single-column FK → `contract_payments(id)`), `expected_destination_wallet_id` (nullable, composite FK), timestamps. `CHECK ((linked_financial_document_id IS NULL) != (linked_contract_payment_id IS NULL))`. `CHECK ((linked_contract_payment_id IS NOT NULL) = (expected_destination_wallet_id IS NOT NULL))`. Unique index directly on `linked_financial_document_id`. **Unique: `(tenant_id, id)`.**

### 4.1 Conservation, immutability, and eligibility — unchanged from v11

**Case A — `linked_contract_payment_id`:** `SUM(total_allocated_amount) <= ContractPayment.amount` across a `ContractPayment`'s routes. Lock the `contract_payments` row (§11, item 1). `total_allocated_amount` and `expected_destination_wallet_id` are both immutable post-creation. Tenant/project/wallet-compatibility rule for `expected_destination_wallet_id`: same as §15 row 5 — must equal the route's own `project_id`, except company/shared wallets.

**Case B — `linked_financial_document_id`:** `route.total_allocated_amount = linked_financial_document.amount`, both frozen together atomically at attachment, same lock as §2.1 (§11, item 5).

**`via_route` eligibility — structural, unchanged from v10/v11:**

| Document's own populated endpoint fields | `via_route` eligible? |
|---|---|
| `destination_wallet_id` set, `counterparty_id` NULL, `source_wallet_id` NULL | **Yes** |
| `source_wallet_id` set AND `destination_wallet_id` set, `counterparty_id` NULL | **Yes** |
| `counterparty_id` set | **No — `direct` only** |
| `source_wallet_id` set, `destination_wallet_id` NULL, `counterparty_id` NULL | **No — `direct` only** |

Applied uniformly to every `document_type` including `reversal`, classified by the document's own populated fields. Case A routes have no `treasury_financial_documents` row of their own to classify this way; eligibility is simply "any `ContractPayment` route may use `via_route`," gated instead by the explicit `expected_destination_wallet_id` requirement (§4).

**`treasury_payment_route_legs`**: `id`, `tenant_id`, `payment_route_id` (composite FK), `sequence_no`, `from_wallet_id` (nullable, composite FK), `to_wallet_id` (composite FK), `amount`, `status` (in_transit|settled|reversed), `occurred_at`, timestamps. **Unique: `(tenant_id, id)`.**

### 4.2 Route-leg custody — unchanged mechanics from v7-v11, extended by fix #1 (§4.2a) and fix #3 (§4.2b) this round
Lock the parent route (§11, item 6). Wallet-backed leg: validate against §5a's balance formula. External-entry leg (`from_wallet_id IS NULL`): bounded by remaining `total_allocated_amount`. Both persist leg + ledger entries atomically under the lock.

### 4.2a Case A ledger-posting precondition — `ContractPayment` must actually be paid (fix #1, new this round)

Prior rounds allowed a Case A route's legs to persist ledger entries (§4.2) as soon as route/leg-level checks passed, with no requirement that the `ContractPayment` this route represents had actually been paid in reality. The Owner's finding: a route's custody entries record real money movement, and for Case A that money movement is, by construction, *the same event* as the `ContractPayment` being paid — so it is incoherent for Treasury's ledger to show custody moving before the canonical record (`ContractPayment.status`) agrees the payment happened.

**Binding rule:** for a Case A route (`linked_contract_payment_id` set), no leg belonging to that route may transition into `status = settled` (i.e., no ledger entries with `source_payment_route_leg_id` referencing that leg may be created) unless, at the moment of that write, `ContractPayment.status = 'paid'` (the real, existing column and value, verified above — not `planned` or `overdue`).

**Locking:** this check reads the `contract_payments` row already locked by §11 item 1 for the Case A conservation check — the same lock is reused (acquired once, both checks performed under it), in the same fixed lock order already established (`contract_payments` before `treasury_payment_routes`, §11 items 1 then 6) to avoid introducing a new deadlock ordering. A leg may still be *created* with `status = in_transit` before the payment is marked `paid` (planning ahead is not restricted), but it cannot advance to `settled` — and therefore no ledger entry for it can post — until the precondition holds.

### 4.2b Atomic leg-reversal ↔ ledger-reversal coupling (fix #3, new this round)

Prior rounds specified leg `status → reversed` (§4, leg lifecycle) and ledger-entry reversal (§5's `reversal_of_entry_id` mechanism) as separate facts without ever binding them together — leaving open the possibility of a leg marked `reversed` whose ledger entries were never actually reversed (or vice versa), a direct custody/ledger inconsistency.

**Binding rule:** a `treasury_payment_route_leg.status` transition to `reversed` and the creation of reversal `treasury_ledger_entries` rows (via `reversal_of_entry_id`, §5) for every one of that leg's original entries are **one atomic write** — both happen in the same transaction, under the same parent-route lock (§11 item 6), or neither happens. Concretely:

1. Every `treasury_ledger_entries` row with `source_payment_route_leg_id = <this leg>` that does not already have an active reversal gets exactly one reversal entry created (same wallet, same source leg reference, same amount, opposite direction — §5's existing reversal shape), in the same transaction.
2. The leg's `status` field is set to `reversed` in that same transaction.
3. Neither step is ever performed without the other — there is no write path that reverses a leg's ledger entries without flipping its `status`, and none that flips `status` to `reversed` without reversing its entries.

This is a **1:1 coupling constraint** on the write path, not a new column — `treasury_payment_route_legs` and `treasury_ledger_entries` are unchanged from v11; only the transactional discipline binding their writes together is new.

### 4.3 Route-completion predicate — endpoint-aware, full-target — unchanged from v11

Define, for any wallet `w` and this route's legs: `net_custody(w) = SUM(credit) - SUM(debit)` over `treasury_ledger_entries` rows whose `source_payment_route_leg_id` belongs to a leg of this route and whose `wallet_id = w`.

- **Precondition, all cases:** every leg belonging to the route has `status IN ('settled', 'reversed')` — none remain `in_transit`.
- **Wallet-terminating inbound shape** (Case B): `completed` requires `net_custody(destination_wallet_id) = total_allocated_amount` (full amount, never reduced by a reversal), and `net_custody(w) = 0` for every other touched wallet.
- **`internal_transfer` shape** (Case B): `completed` requires `net_custody(source_wallet_id) = -total_allocated_amount`, `net_custody(destination_wallet_id) = +total_allocated_amount`, intermediaries `= 0`.
- **Case A** (§4.2a's paid precondition already gated whether any custody could post at all): `completed` requires `net_custody(expected_destination_wallet_id) = total_allocated_amount`, intermediaries `= 0`.

All three branches checked under the parent-route lock (§11, item 6). A route with a reversed leg (§4.2b) stays `partial` until a full-target replacement movement completes it; `cancelled` if abandoned; represented via a `reversal` financial document (§2.2) if the whole document-level movement is undone.

---

## 5. `treasury_ledger_entries` — unchanged schema from v9-v11

`id`, `tenant_id`, `source_financial_document_id` (nullable, composite FK), `source_payment_route_leg_id` (nullable, composite FK), `wallet_id` (composite FK), `direction` (`debit`\|`credit`), `amount`, `entry_type`, `posted_at`, `reversal_of_entry_id` (nullable self-ref, composite), `created_at`. `CHECK` (exactly one source set). Idempotency via generated `original_posting_key` (excludes reversals). `wallet_balance = SUM(credit) - SUM(debit)`. Reversal: same wallet/source/amount, opposite direction, `UNIQUE(reversal_of_entry_id)`, no reverse-of-reverse.

Index: `(source_financial_document_id)`, `(source_payment_route_leg_id)`, `(wallet_id, posted_at)`, `UNIQUE(reversal_of_entry_id)`, `UNIQUE(original_posting_key)`. **Unique: `(tenant_id, id)`.**

**Two distinct reversal mechanisms in this schema — restated here to prevent the vocabulary conflation an earlier round already corrected once:** (a) `treasury_ledger_entries.reversal_of_entry_id` reverses a single erroneous *ledger entry* (a posting-mistake correction, tied to the same source document/leg it always was); (b) a `treasury_financial_documents` row with `document_type = reversal` (§2.2) is a *document-level* construct representing an entirely separate economic document that undoes a whole completed original — it posts its **own fresh** ledger entries (`source_financial_document_id` = the reversal document's own `id`, per the `direct`-posting matrix in §5a) or its own fresh route legs (if `via_route`-eligible per §4.1), never by setting `reversal_of_entry_id` on the original document's entries. The two mechanisms never overlap on the same row.

### 5a. Exact document/route-leg → ledger posting conservation matrix (fix #2, new this round)

Prior rounds described posting behavior narratively, scattered across §2/§4/§5, without ever stating — for every `document_type` × `posting_path` combination — exactly which ledger entries get created. This closes that gap with one exhaustive table.

**`posting_path = direct`** (entries carry `source_financial_document_id = <this document>`, `source_payment_route_leg_id = NULL`):

| `document_type` (direct-eligible shape) | Ledger entries created at posting |
|---|---|
| `funding`, `owner_contribution` | One `credit` entry at `destination_wallet_id`, `amount = document.amount` |
| `advance_return` | One `credit` entry at `destination_wallet_id`, `amount = document.amount` (requires §2.3's mandatory settlement linkage) |
| `internal_transfer` | One `debit` entry at `source_wallet_id` **and** one `credit` entry at `destination_wallet_id`, both `amount = document.amount`, posted atomically together |
| `expense`, `advance` | One `debit` entry at `source_wallet_id`, `amount = document.amount` (per §2.3, `counterparty_id` documents are wallet→party only; no ledger entry exists for the party side, since `treasury_financial_parties` custody is not itself tracked by `treasury_ledger_entries`) |
| `adjustment` (increase, `destination_wallet_id` set) | One `credit` entry at `destination_wallet_id`, `amount = document.amount` |
| `adjustment` (decrease, `source_wallet_id` set) | One `debit` entry at `source_wallet_id`, `amount = document.amount` |
| `reversal` | Entries per **its own** populated shape — a `reversal` row is classified by this same table using its own `source_wallet_id`/`destination_wallet_id`/`counterparty_id`, exactly as any other document (§2.2, §4.1) |

**`posting_path = via_route`** (entries carry `source_payment_route_leg_id = <leg>`, `source_financial_document_id = NULL` — only shapes eligible per §4.1's table ever reach this path):

| Leg shape | Ledger entries created at leg settlement |
|---|---|
| `from_wallet_id` set (wallet-to-wallet hop) | One `debit` entry at `from_wallet_id` **and** one `credit` entry at `to_wallet_id`, both `amount = leg.amount`, posted atomically together |
| `from_wallet_id` NULL (external-entry leg, §4.2) | One `credit` entry at `to_wallet_id` only, `amount = leg.amount` — no offsetting debit, since the origin is untracked external custody |

**Conservation identity, both paths:** every entry created by a single posting action (a `direct` document post, or a single leg's settlement) is either a self-balancing debit/credit pair at the same amount, or a lone `credit` for an external-entry leg (which is exactly why §4.3's endpoint predicate — not a simple "sum to zero" — is what proves completion for routes with external-entry legs: a lone credit never nets to zero on its own, by design, since it represents money genuinely entering tracked custody from outside). No row in either table produces an entry whose amount differs from its document's/leg's own `amount` — this is the exactness this section closes: every ledger entry's amount is traceable, exactly, to exactly one document `amount` or leg `amount`, with no partial or scaled postings anywhere in the schema.

---

## 6. Settlement conservation — unchanged from v7-v11

**6.1** `net_allocation(cost_source) = SUM(apply) - SUM(reverse)`. **6.2** Direct-expense: `net_allocation(financial_document) = financial_document.amount`. **6.3** `0 <= net_allocation(cost_source) <= canonical_incurred_amount(cost_source)`. **6.5** `0 <= advance.amount - SUM(apply) + SUM(reverse) <= advance.amount`. **6.6** Violating writes rejected. **6.7** Material prepayment via `treasury_advances`, never a nonexistent-cost-record allocation.

---

## 7. `treasury_cost_settlement_allocations`, `treasury_advances`, `treasury_advance_settlements` — unchanged from v7-v11

**`treasury_cost_settlement_allocations`**: `id`, `tenant_id`, `financial_document_id` (nullable, composite FK), `advance_settlement_id` (nullable, composite FK), `cost_source_contract_expense_id` (nullable, single-column FK), `cost_source_material_receipt_line_id` (nullable, single-column FK), `direction` (`apply`\|`reverse`), `allocated_amount`, `reverses_allocation_id` (nullable self-ref, composite), `created_at`. Two `CHECK`s. `UNIQUE(reverses_allocation_id)`. **Unique: `(tenant_id, id)`.**

**`treasury_advances`**: `id`, `tenant_id`, `project_id`, `financial_party_id` (composite FK), `originating_financial_document_id` (composite FK), `amount`, timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_advance_settlements`**: `id`, `tenant_id`, `advance_id` (composite FK), `settlement_type` (`approved_expense`\|`cash_return`), `direction` (`apply`\|`reverse`), `amount`, `financial_document_id` (nullable, composite FK), `reverses_settlement_id` (nullable self-ref, composite), `created_at`. `UNIQUE(reverses_settlement_id)`. **Unique: `(tenant_id, id)`.** §2.3's mandatory-linkage rule (fix #5) is enforced as a *posting precondition* on `treasury_financial_documents`, not a new column here — this table's shape is unchanged.

### 7.4 Advance-settlement completeness (unchanged from v7)
`apply`: own allocations sum to its amount. `reverse`: atomically creates compensating allocations for every still-active original, complete 1:1 coverage, own completeness check.

---

## 8. `treasury_fund_chains` and `treasury_fund_chain_members` — unchanged

**`treasury_fund_chains`**: `id`, `tenant_id`, `project_id`, `chain_reference`, `description`, timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_fund_chain_members`**: `id`, `tenant_id`, `fund_chain_id` (composite FK), `member_financial_document_id` (nullable, composite FK), `member_payment_route_id` (nullable, composite FK), timestamps. `CHECK` (exactly one member set). Two separate unique indexes.

---

## 9. Reversal invariants — unchanged, cross-referenced by fix #3

`treasury_ledger_entries`: own debit/credit vocabulary. The three event-log tables (`treasury_cost_settlement_allocations`, `treasury_advance_settlements`, `treasury_reconciliation_entries`): `apply`/`reverse`, same-subject, exact-amount, at-most-once, no reverse-of-reverse. §4.2b's leg-reversal coupling (new this round) is a *fourth*, distinct reversal relationship — between a leg's own `status` field and its ledger entries — not an event-log row; it is a write-atomicity rule, not a new table shape.

---

## 10. `treasury_expense_approvals`

`id`, `tenant_id`, `financial_document_id` (composite FK), `event`, `from_status`, `to_status`, `actor_id`, `note` (nullable), `context` (nullable), `created_at`.

### 10.1 Expense approval gate on posting (fix #6a, new this round)

Prior rounds defined this table's shape (an approval event log) but never bound it to `expense` document posting as an enforced gate — an `expense` document could, per the schema alone, reach `posted_unreconciled` with zero approval history.

**Binding rule:** a `treasury_financial_documents` row with `document_type = expense` may only transition to `status = posted_unreconciled` if the **most recent** `treasury_expense_approvals` row for that `financial_document_id` (ordered by `created_at`, ties broken by `id`) has `to_status = 'approved'`. If no approval row exists yet, or the most recent row's `to_status` is anything else (`rejected`, or an intermediate step), the posting transition is rejected. Checked under the same lock as §11 item 5 (the document row).

This does not require every `document_type` to have an approval gate — only `expense`, matching the table's own name and the existing `Document`/`DocumentVersion`-style approval-event pattern this table was modeled on (confirmed in an earlier round: `DocumentApprovalEvent` itself was hard-coupled and not reusable, which is why `treasury_expense_approvals` exists as its own table — this round finally wires that table into the write path it was created for).

---

## 11. Concurrency — unchanged list from v11, items 1, 5, and 6 extended by this round's new checks

| # | Check | Row/subject locked |
|---|---|---|
| 1 | `ContractPayment` route-allocation conservation (§4.1 Case A), `expected_destination_wallet_id` immutability/compatibility, **and, new this round, the Case A ledger-posting precondition (§4.2a, fix #1)** | `contract_payments` row |
| 2 | Cost over-settlement cap (§6.3) | `contract_expenses`/`material_receipt_lines` row |
| 3 | Advance outstanding cap + settlement completeness (§7.4), **and, new this round, the `advance_return` mandatory-settlement-linkage precondition (§2.3, fix #5)** | `treasury_advances` row |
| 4 | Active reconciliation uniqueness (§12), **and, new this round, the inverse-reconciliation status regression (§12.2, fix #6b)** | `treasury_ledger_entries` row (extended to also touch the parent document/route on regression) |
| 5 | Financial-document posting-source selection (§2.1), §4.1 Case B equality + immutability freeze, §4.1's structural eligibility gate, **§2.2's exact/at-most-once reversal precondition (fix #4)**, §2.3's direction/linkage rules (fix #5), and **§10.1's expense approval gate (fix #6a)** | `treasury_financial_documents` row |
| 6 | Route-leg custody (§4.2), the full-target endpoint-aware completion predicate (§4.3), **and, new this round, the atomic leg-reversal/ledger-reversal coupling (§4.2b, fix #3)** | Parent `treasury_payment_routes` row |

MySQL: `SELECT ... FOR UPDATE`. SQLite (test suite): `BEGIN IMMEDIATE`. Lock ordering for checks spanning two rows (e.g. item 1's Case A checks touching both `contract_payments` and, indirectly, the route) follows the fixed order already established in prior rounds — `contract_payments` before `treasury_payment_routes` — to avoid introducing new deadlock potential.

---

## 12. `treasury_reconciliations` and `treasury_reconciliation_entries`

**`treasury_reconciliations`**: `id`, `tenant_id`, `wallet_id` (composite FK), `reconciliation_type`, `external_reference`, `reconciled_at`, `reconciled_by`, timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_reconciliation_entries`**: `id`, `tenant_id`, `reconciliation_id` (composite FK), `ledger_entry_id` (composite FK), `direction` (`apply`\|`reverse`), `reverses_reconciliation_entry_id` (nullable self-ref, composite), `actor_id`, `created_at`. `UNIQUE(reverses_reconciliation_entry_id)`. **Unique: `(tenant_id, id)`.**

Whole-entry reconciliation; covers every ledger entry regardless of source; `reconciliation.wallet_id = ledger_entry.wallet_id`.

### 12.1 `posted_unreconciled → posted_reconciled` — unchanged from v9-v11

- **`posting_path = direct`:** every ledger entry with `source_financial_document_id = <this document>` has a currently-active `apply` reconciliation-entry row.
- **`posting_path = via_route`:** requires **both** the linked route's `status = completed` (§4.3) **and** every ledger entry with `source_payment_route_leg_id` belonging to any leg of the linked route having a currently-active `apply` reconciliation-entry row.

### 12.2 Inverse reconciliation lifecycle — `posted_reconciled → posted_unreconciled` (fix #6b, new this round)

§12.1 only ever defined the forward transition. The Owner's finding: reconciliation is not always monotonic in practice — a `treasury_reconciliation_entries` row with `direction = reverse` can be created against a previously-active `apply` entry (e.g. correcting a bank-statement matching error), and when that happens, a document that was `posted_reconciled` on the strength of that now-reversed reconciliation is no longer actually fully reconciled — the schema must say what happens to its `status`.

**Binding rule:** whenever a `direction = reverse` row is created in `treasury_reconciliation_entries` against a `ledger_entry_id` that belongs (directly via `source_financial_document_id`, or indirectly via `source_payment_route_leg_id → payment_route_id → linked_financial_document_id`) to a `treasury_financial_documents` row currently at `status = posted_reconciled`, the document's §12.1 completeness condition is **re-evaluated in the same transaction**: if it no longer holds (this ledger entry no longer has a currently-active `apply` reconciliation-entry row), the document's `status` is set back to `posted_unreconciled`, atomically with the reconciliation-entry reversal write. If the document's §12.1 condition still holds despite this one entry's reconciliation being reversed — which cannot actually happen under §12.1's "every entry" requirement, since reversing any one covered entry's reconciliation necessarily breaks the "every entry has an active `apply` row" condition — this branch is stated for completeness but is structurally unreachable given §12.1's own definition; the regression is unconditional whenever it fires.

This regression never touches `posting_path`, `amount`, or any other immutable field (§2.1) — only `status` moves, and only backward along the same forward path §12.1 already defines, never past `posted_unreconciled` (a `posted_reconciled` document never regresses all the way to `draft` or any pre-posting state; the underlying economic posting already happened and is immutable).

Checked under the same lock as §11 item 4 (`treasury_ledger_entries` row), extended in this one case to also touch the parent document (or, for `via_route`, the parent route and its linked document) so the status write is atomic with the reconciliation-entry write.

Index: `(reconciliation_id)`, `(ledger_entry_id)`, `(actor_id)`.

---

## 13. Composite-FK-target index requirement — unchanged (12 tables)

1. `treasury_financial_parties` 2. `treasury_wallets` 3. `treasury_financial_documents` 4. `treasury_payment_routes` 5. `treasury_payment_route_legs` 6. `treasury_ledger_entries` 7. `treasury_fund_chains` 8. `treasury_advances` 9. `treasury_advance_settlements` 10. `treasury_cost_settlement_allocations` (self-FK) 11. `treasury_reconciliations` 12. `treasury_reconciliation_entries` (self-FK).

---

## 14. Tier B — existing-table FK tenant/project rules — unchanged from v9-v11

| Reference | Existence | Tenant/project match |
|---|---|---|
| `treasury_payment_routes.linked_contract_payment_id` | DB-enforced | Same `tenant_id`; same project via the `ContractPayment`'s `Contract.project_id` = route's own `project_id` |
| `treasury_cost_settlement_allocations.cost_source_contract_expense_id` | DB-enforced | Same `tenant_id`; same project via the expense's owning `Contract.project_id`, matching the allocation's own Treasury-side parent project |
| `treasury_cost_settlement_allocations.cost_source_material_receipt_line_id` | DB-enforced | Same rule as above, via the receipt's owning project |

---

## 15. Treasury-internal same-project integrity — unchanged 8 rows from v11

| # | Reference | Binding rule |
|---|---|---|
| 1 | `treasury_payment_routes.linked_financial_document_id` → document's `project_id` | Must be equal |
| 2 | `treasury_advances.originating_financial_document_id` → document's `project_id` | Must be equal |
| 3 | `treasury_fund_chain_members.member_financial_document_id` / `member_payment_route_id` → parent chain's `project_id` | Must be equal |
| 4 | `treasury_financial_documents.reversed_document_id` / `replacement_document_id` (self-ref) | Must be equal |
| 5 | `treasury_financial_documents.source_wallet_id` / `destination_wallet_id`, `treasury_payment_route_legs.from_wallet_id` / `to_wallet_id` → wallet's `project_id` | Must be equal, except company/shared wallets |
| 6 | `treasury_ledger_entries.wallet_id` vs. its source's project | Must be equal, same company-wallet exception |
| 7 | `treasury_advance_settlements.financial_document_id` → document's `project_id`, when `settlement_type = cash_return` | Must equal the settlement's own project |
| 8 | `treasury_payment_routes.expected_destination_wallet_id` → wallet's `project_id`, when set | Must equal the route's own `project_id`, same company-wallet exception |

**Enforcement:** the same `TreasuryReferentialIntegrityService`-equivalent named throughout prior rounds, validated at write time, under the same lock discipline as §11.

---

## 16. Exact table inventory and migration order — unchanged (14 tables)

1. `treasury_financial_parties` 2. `treasury_wallets` 3. `treasury_financial_documents` 4. `treasury_payment_routes` 5. `treasury_payment_route_legs` 6. `treasury_ledger_entries` 7. `treasury_fund_chains` 8. `treasury_advances` 9. `treasury_advance_settlements` 10. `treasury_cost_settlement_allocations` 11. `treasury_expense_approvals` 12. `treasury_reconciliations` 13. `treasury_fund_chain_members` 14. `treasury_reconciliation_entries`. **No migration file exists yet.** This round adds one new index (`UNIQUE(reversed_document_id)` on table 3) and zero new tables — the 14-table count and dependency order are unchanged.

---

## 17. Verification against every held-constant item

- A3/A4-a/A.5/B2/B2-T/C/D: unchanged, not reopened. **A4-a exclusion held**: no invariant this round touches whatever A4-a excluded (unchanged from every prior round). **D — no `ReportPageController::cashflow()` edit**: nothing in this round proposes or requires any change to that controller; all 6 invariants are internal to the 14 new Treasury tables and their own write paths.
- **B2-T no second `ContractPayment` fact**: §4.2a's new precondition reads `ContractPayment.status` (an existing column, read-only) — it does not add, duplicate, or shadow any `ContractPayment` fact; B2-T's conservation model (`SUM(route allocations) <= ContractPayment.amount`) is unchanged and still the sole source of truth for allocation capacity.
- **Many-to-many cost allocation**: unchanged — `treasury_cost_settlement_allocations` (§7) still permits many allocations against one cost source and many cost sources' worth of allocations against one document, exactly as established in prior rounds; nothing in this round's 6 invariants touches that table's cardinality.
- **Advance-settlement no-second-cash-out rule**: unchanged — §7.4's completeness check (own allocations sum to the settlement's amount) still prevents a second, unaccounted cash-out against the same advance; §2.3's new mandatory-linkage rule (fix #5) is additive on top of this, not a replacement.
- 14-table count, 12 composite-FK targets, reversal `via_route` eligibility from own endpoint shape, amount immutability, full-target endpoint-aware completion, Tier-B rules, reconciliation actor, MySQL/SQLite locking, zero existing-table/data migration: all unchanged from Revision 11.
- Fix #1 verified read-only against `ContractPayment` — no write, no new column on that model, just a status check under the lock §11 item 1 already acquires.
- Fix #2 verified exhaustive against every `document_type` in §2 and every leg shape in §4 — every row of §5a's two tables maps to a real, already-established posting fact from a prior round; no new posting behavior was invented, only made explicit and exact.
- Fix #3 verified additive-only — no new column on `treasury_payment_route_legs` or `treasury_ledger_entries`; a transactional-atomicity rule binding two already-existing writes.
- Fix #4 verified additive-only — one new unique index plus a creation-time equality/precondition check; no new column.
- Fix #5 verified consistent with §4.1's existing eligibility table (the `counterparty_id` set ⇒ `direct`-only row is unaffected) and with §6.5's advance-outstanding conservation (which fix #5's mandatory linkage now protects rather than potentially bypasses).
- Fix #6a verified to use the existing `treasury_expense_approvals` table exactly as designed in the round that created it, finally wiring it into the write path.
- Fix #6b verified to only ever move `status` backward along §12.1's own forward path, never touching `posting_path`/`amount`/any immutable field.

---

## 18. Trạng thái và bước tiếp theo
- Nếu Owner Approve: chuẩn bị Gate 3 cho GAP-037 — vẫn chỉ là quyết định merge tài liệu.
- Nếu Owner Request changes: sẽ tạo `02-design-v13.md` (supersedes bản này).
- Nếu Owner Decline: dừng GAP-037 ở schema-proposal này.

## 19. Loại trừ phạm vi
Kế thừa nguyên vẹn từ mọi round trước: không migration file thật; không model/controller/service/route/UI/test thật; không seed/backfill; không implementation plan coi schema này là đã duyệt cho Gate 3; không Gate 3 tự suy luận; không mark PR ready; không merge PR #263; không sửa/merge/đóng PR #245 hoặc #257; không GAP-036; không Today Workspace; không sửa canonical SSOT stale metadata; không production/deployment; không thiết kế external-destination leg representation; không sửa `ReportPageController::cashflow()`.

## Decision Needed
**Resolved 2026-08-16T20:58:07+07:00 — Owner Decision: REQUEST CHANGES.** All 6 of Revision 12's invariants were confirmed achieved — Case A paid-status gate, exact posting conservation matrix, atomic leg↔ledger reversal, expense approval gate, inverse reconciliation lifecycle, and document reversal's at-most-once/amount-equality portion. Architecture A3+A4-a+A.5/B2+B2-T/C/D remains approved, frozen, not reopened. Revision 13 must close 4 remaining points: (1) exact document-level economic reversal — equal-and-opposite endpoints, not just amount, and the original transitions to `reversed` atomically only when the reversal's own economic posting actually posts; (2) directional external-party provenance — replace the single ambiguous `counterparty_id` with `source_party_id`/`destination_party_id`, so `funding`/`owner_contribution`/`advance_return` retain their source party and `expense`/`advance` retain their destination party, with reversals swapping endpoints exactly; (3) advance↔cash conservation — the originating advance document must type/amount/party-match and be unique, `advance_return` must have exactly-one linked `cash_return`/`apply` settlement with matching amount and party, and advance-outstanding bookkeeping must never drift from actual wallet cash movement; (4) the Case A leg-settlement gate must also require `ContractPayment.paid_at IS NOT NULL`, not `status = 'paid'` alone. This packet (`02-design-v12.md`) is now **frozen** — no further edits. `docs/owner-decisions/GAP-037/02-design-v13.md` (self-contained) follows in the next commit.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt migration file thật hay chi tiết implementation. Owner cũng không được yêu cầu duyệt lại architecture set A3/A4-a/A.5/B2/B2-T/C/D — đã approved, không mở lại. Owner cũng không được yêu cầu duyệt overpayment/prepayment semantics, hay thiết kế external-destination leg representation (nêu là future extension point, chưa thiết kế ở đây).
