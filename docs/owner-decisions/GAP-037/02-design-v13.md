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
  recorded_at: "2026-08-16T22:09:19+07:00"
  owner_response_reference: "Owner Gate 2 Schema Proposal Revision 13 decision -- REQUEST CHANGES, recorded in-session on 2026-08-16 against reviewed PR #263 head 202351e2e2c46366bf6cdc0608f0923f371dd7ed: 'GAP-037 -- Gate 2 Schema Proposal Revision 13 -- Owner Decision: REQUEST CHANGES. Toi, Owner, yeu cau chinh sua schema proposal tai PR #263, reviewed head 202351e2e2c46366bf6cdc0608f0923f371dd7ed. Toi xac nhan Revision 13 da xu ly dat toan bo 4 yeu cau cua Revision 12: exact swapped reversal endpoints; directional source_party_id/destination_party_id; advance-cash type/amount/party/uniqueness binding; Case A status=paid AND paid_at IS NOT NULL. Architecture A3 + A4-a + A.5 / B2 + B2-T / C / D van approved, frozen, khong mo lai. Revision 14 chi can closure 3 diem: 1. Reversal completion timing: direct reversal marks original reversed at direct post completion; via-route reversal marks original reversed only when its route reaches full-target completed, never at first leg. 2. Dependent-state reversal coupling: when a document-level reversal economically completes, atomically reverse all dependent settlement facts of the original -- cost allocations for expense payments, advance outstanding/opening effects for advances, and cash-return settlement effects for advance returns. 3. Atomic advance-cash lifecycle: originating advance posting + advance-row creation must be atomic; advance-return posting + cash-return/apply settlement creation must be atomic; cash-return/reverse must be coupled to the corresponding economic reversal document. Approved-expense settlement remains a non-second-cash movement. Giu nguyen toan bo phan v13 da dat: 14-table architecture; 12 composite-FK targets; source/destination party provenance; exact endpoint reversal shape; explicit Case A endpoint; full-target completion; posting matrix; leg reversal atomicity; B2-T; many-to-many cost allocations; approval/reconciliation lifecycle; Tier-B/project isolation; MySQL/SQLite concurrency; A4-a; D no ReportPageController::cashflow() edit; zero existing-table/data migration; #245/#257 untouched. Record REQUEST CHANGES truoc vao 02-design-v13.md, freeze v13, tao self-contained 02-design-v14.md, rerun required CI va quay lai awaiting_owner. Khong suy luan schema approval hoac Gate 3 authorization.'"
  reconciliation_required: false
supersedes: docs/owner-decisions/GAP-037/02-design-v12.md
superseded_by: null
timestamps:
  created_at: "2026-08-16T20:59:30+07:00"
  updated_at: "2026-08-16T22:09:19+07:00"
generated_by: agent
---

# GAP-037 — Project Treasury: Gate 2 Revision 13 — Exact Reversal, Directional Party Provenance, Advance-Cash Conservation, Case A `paid_at` Gate (Self-Contained)

**Status:** Gate 1 approved. Gate 2 architecture decisions **approved** (`docs/owner-decisions/GAP-037/02-design.md`, frozen) — **A3 + A4-a + A.5 / B2 + B2-T / C / D**, not reopened. Fully self-contained. Still Gate 2 — a proposal, not implementation. No migration file, model, controller, service, route, UI, or test exists or is authorized by this packet.

**Database compatibility (unchanged verification):** MySQL for production/dev (`config/database.php`, `.env.example`), SQLite for the test suite (`.env.testing`, `phpunit.xml`).

**Repo fact verified for this round:** `ContractPayment.paid_at` is a real, nullable `@property \Carbon\Carbon|null` column (`app/Models/ContractPayment.php`, cast `'date'`), distinct from `status`. Fix #4 binds to both `status = 'paid'` **and** `paid_at IS NOT NULL` together, neither alone.

---

## 0. What changed vs. v12, at a glance

| # | Owner's finding | Fix |
|---|---|---|
| 1 | Document-level reversal (§2.2) only checked amount equality and at-most-once — not endpoint equal-and-opposite, and the original's `status → 'reversed'` was never bound to the reversal's own economic posting | Reversal must now match the original's endpoints exactly, swapped; `status = 'reversed'` on the original is set atomically with the reversal document's own posting transition, never at reversal creation, never manually |
| 2 | `counterparty_id` was one ambiguous field with no stated direction, and `funding`/`owner_contribution`/`advance_return` never recorded their source party at all | `counterparty_id` replaced by `source_party_id` / `destination_party_id` — every document type now records the actual external party on whichever side it belongs, and reversals swap both wallet and party endpoints together |
| 3 | `treasury_advances`/`treasury_advance_settlements` linkage checked existence but not type/amount/party matching, uniqueness, or that bookkeeping tracks real ledger movement | Originating advance document must type/amount/party-match and be unique per `treasury_advances` row; `advance_return` must have exactly one linked `cash_return`/`apply` settlement with matching amount and party; settlement rows are bound to require a real, posted, matching ledger movement — no pure-bookkeeping drift |
| 4 | §4.2a's Case A gate checked `ContractPayment.status = 'paid'` alone | Extended to require `status = 'paid' AND paid_at IS NOT NULL` together |

---

## 1. `treasury_financial_parties` and `treasury_wallets` — unchanged from v12

**`treasury_financial_parties`**: `id`, `tenant_id`, `party_type`, `name`, `linked_account_id` (nullable), `linked_user_id` (nullable), timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_wallets`**: `id`, `tenant_id`, `project_id` (nullable), `wallet_type`, `name`, `custodian_party_id` (nullable, composite FK), timestamps. **Unique: `(tenant_id, id)`.**

---

## 2. `treasury_financial_documents`

`id`, `tenant_id`, `project_id`, `document_type` (funding|internal_transfer|expense|owner_contribution|advance|advance_return|reversal|adjustment), `status` (draft|submitted|approved|rejected|posted_unreconciled|posted_reconciled|reversed), `posting_path` (nullable enum: `direct`|`via_route`, set exactly once), `amount`, `source_wallet_id` (nullable, composite FK), `destination_wallet_id` (nullable, composite FK), **`source_party_id` (nullable, composite FK — replaces the old ambiguous `counterparty_id`, fix #2)**, **`destination_party_id` (nullable, composite FK — new, fix #2)**, `description`, `created_by`, `approved_by` (nullable), `posted_at` (nullable), `reversed_document_id` (nullable self-ref, composite), `replacement_document_id` (nullable self-ref, composite), timestamps. **Unique: `(tenant_id, id)`.** `UNIQUE(reversed_document_id)` (from v12, unchanged).

**New `CHECK`s (fix #2):** `NOT (source_wallet_id IS NOT NULL AND source_party_id IS NOT NULL)` — a document's source is a wallet or a party, never both. `NOT (destination_wallet_id IS NOT NULL AND destination_party_id IS NOT NULL)` — same for destination. Both fields may be `NULL` on a given side (e.g. an increase-`adjustment` genuinely has no source at all — it is an internal correction, not a real movement from anywhere).

**`counterparty_id` is removed entirely** — every prior round's reference to it is superseded by the `source_party_id`/`destination_party_id` pair below.

### 2.1 Posting-path freeze + amount immutability + eligibility gate — unchanged mechanics from v9-v12
A route may only attach (§4) while `posting_path IS NULL` and `status` is unposted, and only for an endpoint shape that is `via_route`-eligible per §4.1's (now party-field-aware) structural rule. Attaching sets `posting_path = via_route` and simultaneously freezes `amount` as immutable. If no route is ever attached, `posting_path` locks to `direct` at posting. Once `posting_path` is non-null, `amount` has no update path — immutable, full stop.

### 2.2 Document-level reversal — exact (amount **and** endpoints) and atomically applied (fix #1, revised this round)

v12 established at-most-once (`UNIQUE(reversed_document_id)`) and amount equality, but left two gaps: nothing checked that the reversal's *endpoints* were the true opposite of the original's, and the original's `status` never actually moved to `'reversed'` — that enum value existed in §2 since the first round but was never bound to any write path.

**Binding rules, in two stages — creation-time structural checks, and post-time atomic status transition:**

**At reversal creation** (when `reversed_document_id` is set — immutable once set, same as every other set-once field):

1. **At-most-once:** `UNIQUE(reversed_document_id)` (§2, unchanged from v12).
2. **Exact amount:** `reversal.amount = reversed_document.amount` (unchanged from v12).
3. **Exact, opposite endpoints (new this round):** `reversal.source_wallet_id = reversed_document.destination_wallet_id`, `reversal.destination_wallet_id = reversed_document.source_wallet_id`, `reversal.source_party_id = reversed_document.destination_party_id`, `reversal.destination_party_id = reversed_document.source_party_id`. Every endpoint field is swapped, wallet-for-wallet and party-for-party — there is no partial reversal that changes the amount without mirroring the movement, and no reversal that mirrors the movement with a different amount. (A reversal of a decrease-`adjustment`, which has only `source_wallet_id` set and no destination at all, is itself only `destination_wallet_id` set and no source — consistent with the swap rule applied to an originally one-sided document.)
4. **Postable-original precondition:** `status IN ('posted_unreconciled', 'posted_reconciled')` on the original at creation time (unchanged from v12).
5. **Project match:** unchanged from §15 row 4.

**At reversal posting** (new this round — the atomic status transition v12 never specified):

The original document's `status` is set to `'reversed'` **in the same transaction** as the moment the reversal document's own economic posting actually occurs — i.e. the same write that sets the reversal's `posting_path` (§2.1) and creates its first `direct`-posting ledger entry or its first `via_route` leg. This is never a separate write, never triggered by the reversal merely being *created* (which only establishes the `reversed_document_id` link, per the creation-time checks above), and never settable by any other path. Locking: both the original and the reversal document rows must be locked under §11 item 5's discipline; to avoid a new deadlock ordering, the lower `id` of the two rows is always locked first (consistent, total ordering, independent of which one happens to be "original" vs. "reversal" in a given transaction).

**Why staged this way:** a reversal document can legitimately exist in `draft`/`submitted`/`approved` for review before its own economic movement actually happens — nothing about undoing an economic fact should occur before the undoing itself is real. Binding `reversed → status` to reversal *creation* (as an implicit reading of v12's silence might have suggested) would have let an uncommitted, unposted "reversal" retroactively flag the original as already undone, which is not true until money actually moves.

### 2.3 External-party direction convention + advance/cash-return posting preconditions (fix #2, fix #3)

**Direction convention (fix #2):** exactly two document-endpoint shapes involve `treasury_financial_parties`:

- **Party-as-source** — `source_party_id` set, `destination_wallet_id` set: `funding`, `owner_contribution`, `advance_return`. The party is where the money came from; the wallet is where it landed. `source_party_id` is now **required** for these three types (not merely permitted) — this is the substance of fix #2's "must retain source party" requirement.
- **Party-as-destination** — `source_wallet_id` set, `destination_party_id` set: `expense`, `advance`. The wallet is where the money left from; the party is who received it. `destination_party_id` is **required** for these two types.
- **Neither** — `internal_transfer` (both wallets, no party) and `adjustment` (one wallet, no party) never populate a party field.
- **`reversal`** — always the exact swap of whichever of the above shapes its `reversed_document_id` target had (§2.2, fix #1) — a reversal of a party-as-destination document becomes party-as-source, and vice versa, with the *same* party on both sides of the pair.

**Posting preconditions (fix #3, detailed fully in §7.5 — restated here for locality with the field definitions they gate):** an `advance` document's `destination_party_id` becomes, once posted, the binding party for its `treasury_advances.financial_party_id` (§7.5a). An `advance_return` document's `source_party_id` must equal that same advance's `financial_party_id` before it may post (§7.5b) — the party that received the advance is the only party permitted to be recorded as returning it.

---

## 3. The typed-nullable-FK pattern — unchanged principle

Every former polymorphic reference is N nullable typed FK columns + a `CHECK` requiring exactly one non-null: `treasury_payment_routes` (§4), `treasury_ledger_entries` (§5), `treasury_cost_settlement_allocations` (§7), `treasury_fund_chain_members` (§8). §2's new source/destination-wallet/party pair (fix #2) follows the same discipline at the endpoint level, one level up from this section's FK-shape pattern.

---

## 4. `treasury_payment_routes` and `treasury_payment_route_legs`

**`treasury_payment_routes`**: `id`, `tenant_id`, `project_id`, `total_allocated_amount`, `status` (planned|partial|completed|cancelled), `linked_financial_document_id` (nullable, composite FK), `linked_contract_payment_id` (nullable, single-column FK → `contract_payments(id)`), `expected_destination_wallet_id` (nullable, composite FK), timestamps. `CHECK ((linked_financial_document_id IS NULL) != (linked_contract_payment_id IS NULL))`. `CHECK ((linked_contract_payment_id IS NOT NULL) = (expected_destination_wallet_id IS NOT NULL))`. Unique index directly on `linked_financial_document_id`. **Unique: `(tenant_id, id)`.**

### 4.1 Conservation, immutability, and eligibility

**Case A — `linked_contract_payment_id`:** `SUM(total_allocated_amount) <= ContractPayment.amount` across a `ContractPayment`'s routes (§11, item 1). `total_allocated_amount` and `expected_destination_wallet_id` are both immutable post-creation, same tenant/project/wallet-compatibility rule as §15 row 5.

**Case B — `linked_financial_document_id`:** `route.total_allocated_amount = linked_financial_document.amount`, both frozen together atomically at attachment (§11, item 5).

**`via_route` eligibility — structural, updated for fix #2's new party fields (Yes/No outcomes unchanged from v10-v12, only the field names updated):**

| Document's own populated endpoint fields | `via_route` eligible? |
|---|---|
| `destination_wallet_id` set, `destination_party_id` NULL, `source_wallet_id` NULL (`source_party_id` may be set or NULL) | **Yes** |
| `source_wallet_id` set AND `destination_wallet_id` set (both party fields NULL) | **Yes** |
| `destination_party_id` set | **No — `direct` only** |
| `source_wallet_id` set, `destination_wallet_id` NULL, `destination_party_id` NULL | **No — `direct` only** |

This is a strict rename, not a behavior change: every document that was eligible under v12's `counterparty_id`-based table is eligible under this one (a document with `destination_party_id` set is exactly the set of documents that used to have `counterparty_id` set — §2's new `CHECK`s make the two fields mutually exclusive with their wallet counterparts in the same way `counterparty_id` was), and vice versa for ineligible.

**`treasury_payment_route_legs`**: `id`, `tenant_id`, `payment_route_id` (composite FK), `sequence_no`, `from_wallet_id` (nullable, composite FK), `to_wallet_id` (composite FK), `amount`, `status` (in_transit|settled|reversed), `occurred_at`, timestamps. **Unique: `(tenant_id, id)`.**

### 4.2 Route-leg custody — unchanged mechanics from v7-v12
Lock the parent route (§11, item 6). Wallet-backed leg: validate against §5a's balance formula. External-entry leg (`from_wallet_id IS NULL`): bounded by remaining `total_allocated_amount`. Both persist leg + ledger entries atomically under the lock.

### 4.2a Case A ledger-posting precondition — `status = 'paid'` AND `paid_at IS NOT NULL` (fix #4, revised this round)

v12 gated Case A leg settlement on `ContractPayment.status = 'paid'` alone. The Owner's finding: `status` and `paid_at` are two separate, independently-writable columns on the real `ContractPayment` model (verified above) — nothing in the existing model guarantees they are set together, so a row could in principle have `status = 'paid'` with `paid_at` still `NULL` (or vice versa, depending on how the existing `ContractPayment` write paths behave, which this packet does not modify or assume beyond what the model's own columns declare).

**Binding rule (extended):** for a Case A route, no leg belonging to that route may transition into `status = settled` (no ledger entries may post for it) unless, at the moment of that write, **both** `ContractPayment.status = 'paid'` **and** `ContractPayment.paid_at IS NOT NULL` hold. Either condition failing blocks the transition — this is a conjunction, not an either-or fallback.

**Locking:** unchanged from v12 — reads the same `contract_payments` row lock already acquired for §11 item 1's Case A conservation check, same fixed lock order (`contract_payments` before `treasury_payment_routes`).

### 4.2b Atomic leg-reversal ↔ ledger-reversal coupling — unchanged from v12

A leg's `status → reversed` transition and the creation of reversal ledger entries for every one of that leg's original entries are one atomic write under the parent-route lock (§11 item 6) — never independent. (Distinct from §2.2's *document*-level reversal, which is a separate mechanism at a different layer — see §5's restated disambiguation.)

### 4.3 Route-completion predicate — endpoint-aware, full-target — unchanged from v11/v12

Define `net_custody(w) = SUM(credit) - SUM(debit)` over this route's ledger entries at wallet `w`.

- **Precondition, all cases:** every leg `status IN ('settled', 'reversed')`.
- **Wallet-terminating inbound (Case B):** `net_custody(destination_wallet_id) = total_allocated_amount` (full, unreduced), intermediaries `= 0`.
- **`internal_transfer` (Case B):** `net_custody(source_wallet_id) = -total_allocated_amount`, `net_custody(destination_wallet_id) = +total_allocated_amount`, intermediaries `= 0`.
- **Case A:** `net_custody(expected_destination_wallet_id) = total_allocated_amount`, intermediaries `= 0`.

A route with a reversed leg stays `partial` until a full-target replacement completes it; `cancelled` if abandoned; represented via a `reversal` financial document (§2.2) if the whole document-level movement is undone.

---

## 5. `treasury_ledger_entries` — unchanged schema from v9-v12

`id`, `tenant_id`, `source_financial_document_id` (nullable, composite FK), `source_payment_route_leg_id` (nullable, composite FK), `wallet_id` (composite FK), `direction` (`debit`\|`credit`), `amount`, `entry_type`, `posted_at`, `reversal_of_entry_id` (nullable self-ref, composite), `created_at`. `CHECK` (exactly one source set). Idempotency via generated `original_posting_key`. `wallet_balance = SUM(credit) - SUM(debit)`. Reversal: same wallet/source/amount, opposite direction, `UNIQUE(reversal_of_entry_id)`, no reverse-of-reverse.

Index: `(source_financial_document_id)`, `(source_payment_route_leg_id)`, `(wallet_id, posted_at)`, `UNIQUE(reversal_of_entry_id)`, `UNIQUE(original_posting_key)`. **Unique: `(tenant_id, id)`.**

**Three distinct reversal mechanisms in this schema, now that §2.2 is fully specified — restated to keep them from ever being conflated:** (a) `treasury_ledger_entries.reversal_of_entry_id` — a single erroneous *ledger entry* correction. (b) `treasury_payment_route_legs.status = 'reversed'` coupled atomically to its own entries' reversal (§4.2b) — a *leg*-level correction, scoped to one route. (c) `treasury_financial_documents.document_type = 'reversal'` (§2.2) — a *document*-level, economically real "undo" of a whole completed original, expressed as an entirely separate document with its own fresh postings, whose creation only links the two documents and whose eventual **posting** is what atomically flips the original to `status = 'reversed'`. None of the three ever substitutes for another.

### 5a. Exact document/route-leg → ledger posting conservation matrix — updated field names (fix #2), rules unchanged from v12

**`posting_path = direct`** (entries carry `source_financial_document_id = <this document>`):

| `document_type` (direct-eligible shape) | Ledger entries created at posting |
|---|---|
| `funding`, `owner_contribution` | One `credit` entry at `destination_wallet_id`, `amount = document.amount`. `source_party_id` (fix #2, now mandatory for these types, §2.3) is metadata identifying who funded it — no ledger entry exists for the party side, since `treasury_financial_parties` custody is not itself tracked by `treasury_ledger_entries` |
| `advance_return` | One `credit` entry at `destination_wallet_id`, `amount = document.amount` (requires §2.3's mandatory, exact settlement linkage) |
| `internal_transfer` | One `debit` entry at `source_wallet_id` **and** one `credit` entry at `destination_wallet_id`, both `amount = document.amount`, atomic |
| `expense`, `advance` | One `debit` entry at `source_wallet_id`, `amount = document.amount`. `destination_party_id` (fix #2, mandatory) identifies the receiving party — again no ledger entry for the party side |
| `adjustment` (increase) | One `credit` entry at `destination_wallet_id`, `amount = document.amount` |
| `adjustment` (decrease) | One `debit` entry at `source_wallet_id`, `amount = document.amount` |
| `reversal` | Entries per its own shape, which per §2.2 is always the exact swap of the reversed document's shape |

**`posting_path = via_route`** (entries carry `source_payment_route_leg_id = <leg>`) — unchanged from v12: wallet-to-wallet hop posts a `debit`/`credit` pair at `leg.amount`; external-entry leg (`from_wallet_id IS NULL`) posts a lone `credit` at `to_wallet_id`.

Conservation identity unchanged from v12: every entry created by a single posting action is a self-balancing pair or a lone external-entry credit; no entry's amount ever differs from its document's/leg's own `amount`.

---

## 6. Settlement conservation — unchanged from v7-v12

**6.1** `net_allocation(cost_source) = SUM(apply) - SUM(reverse)`. **6.2** Direct-expense: `net_allocation(financial_document) = financial_document.amount`. **6.3** `0 <= net_allocation(cost_source) <= canonical_incurred_amount(cost_source)`. **6.5** `0 <= advance.amount - SUM(apply) + SUM(reverse) <= advance.amount` — **this formula is unchanged, but §7.5 (new this round) now binds every term in it to a real, posted ledger movement, closing the drift gap fix #3 identifies.** **6.6** Violating writes rejected. **6.7** Material prepayment via `treasury_advances`, never a nonexistent-cost-record allocation.

---

## 7. `treasury_cost_settlement_allocations`, `treasury_advances`, `treasury_advance_settlements`

**`treasury_cost_settlement_allocations`**: unchanged from v7-v12 — `id`, `tenant_id`, `financial_document_id` (nullable, composite FK), `advance_settlement_id` (nullable, composite FK), `cost_source_contract_expense_id` (nullable, single-column FK), `cost_source_material_receipt_line_id` (nullable, single-column FK), `direction` (`apply`\|`reverse`), `allocated_amount`, `reverses_allocation_id` (nullable self-ref, composite), `created_at`. Two `CHECK`s. `UNIQUE(reverses_allocation_id)`. **Unique: `(tenant_id, id)`.**

**`treasury_advances`**: `id`, `tenant_id`, `project_id`, `financial_party_id` (composite FK), `originating_financial_document_id` (composite FK), `amount`, timestamps. **Unique: `(tenant_id, id)`.** **New this round (fix #3): `UNIQUE(originating_financial_document_id)`** — at most one `treasury_advances` row may book against any given originating document, preventing the same `advance` document from being double-counted as two separate outstanding advances.

**`treasury_advance_settlements`**: `id`, `tenant_id`, `advance_id` (composite FK), `settlement_type` (`approved_expense`\|`cash_return`), `direction` (`apply`\|`reverse`), `amount`, `financial_document_id` (nullable, composite FK), `reverses_settlement_id` (nullable self-ref, composite), `created_at`. `UNIQUE(reverses_settlement_id)`. **New this round (fix #3): `UNIQUE(financial_document_id)`** (nullable-safe — only enforced across the non-null values, i.e. across `cash_return` rows that actually link a return document) — at most one settlement row may claim any given `advance_return` document. **Unique: `(tenant_id, id)`.**

### 7.4 Advance-settlement completeness — unchanged from v7-v12
`apply`: own allocations sum to its amount. `reverse`: atomically creates compensating allocations for every still-active original, complete 1:1 coverage, own completeness check.

### 7.5 Advance↔cash conservation — no type/amount/party drift, no bookkeeping-vs-ledger drift (fix #3, new this round)

v12's §2.3 established that an `advance_return` document must have *some* linked `cash_return`/`apply` settlement row before it can post. The Owner's finding: existence alone is not conservation — the linked row could still have the wrong amount, the wrong party, or exist purely as a bookkeeping entry disconnected from what the ledger actually recorded. This section closes all three gaps, for both ends of the advance lifecycle.

**7.5a — Originating advance: type, amount, party match, and uniqueness.** A `treasury_advances` row may only be created when, at that moment:
1. `originating_financial_document.document_type = 'advance'` — type match (a `treasury_advances` row can never originate from any other document type).
2. `treasury_advances.amount = originating_financial_document.amount` — amount match.
3. `treasury_advances.financial_party_id = originating_financial_document.destination_party_id` — party match, using fix #2's new field (an `advance` document's destination party, per §2.3, is exactly the party the advance was given to).
4. `originating_financial_document.status IN ('posted_unreconciled', 'posted_reconciled')` — the advance can only be booked once real money has actually moved (its own `debit` entry at `source_wallet_id`, per §5a, already exists by this point); a `treasury_advances` row is never created against a still-`draft` document.
5. `UNIQUE(originating_financial_document_id)` — at most one `treasury_advances` row per originating document (schema-level, above).

**7.5b — `advance_return` settlement: exactly-one, amount, and party match.** An `advance_return` document may only reach `status = posted_unreconciled` (§2.3 restates the existence precondition; this section adds the matching content) when, for its linked `treasury_advance_settlements` row (`financial_document_id = this document's id`, `settlement_type = cash_return`, `direction = apply`):
1. `UNIQUE(financial_document_id)` guarantees **exactly one** such row exists (schema-level, above) — never zero (§2.3's existence rule), never more than one.
2. `settlement.amount = advance_return.amount` — amount match.
3. `advance_return.source_party_id = treasury_advances.financial_party_id` (reached via `settlement.advance_id → treasury_advances.financial_party_id`) — party match: the party returning the advance must be the same party the advance was originally given to.

**7.5c — No drift between outstanding bookkeeping and real wallet cash movement.** §6.5's formula (`0 <= advance.amount - SUM(apply) + SUM(reverse) <= advance.amount`) is arithmetic over `treasury_advance_settlements` rows — it says nothing, on its own, about whether those rows correspond to real money. This round binds it to reality: every `apply`-direction `cash_return` settlement counted in that formula's `SUM(apply)` must have its `financial_document_id` pointing to an `advance_return` document that has actually posted (§7.5b's precondition, restated as a standing invariant, not just a one-time creation check — an `advance_return` document's `status` can move forward but its settlement linkage, once matched at creation, is never re-pointed, so this holds for the document's whole lifetime). Symmetrically, the originating advance's own outflow (`treasury_advances.amount`) is only ever counted once §7.5a's posted-status precondition held at creation. Together, these two facts mean §6.5's `outstanding` figure is never a number that exists only in `treasury_advance_settlements` rows disconnected from `treasury_ledger_entries` — every unit counted on either side of the formula corresponds to a real, posted, exact-amount ledger movement, by construction, not by a separate reconciliation step.

---

## 8. `treasury_fund_chains` and `treasury_fund_chain_members` — unchanged

**`treasury_fund_chains`**: `id`, `tenant_id`, `project_id`, `chain_reference`, `description`, timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_fund_chain_members`**: `id`, `tenant_id`, `fund_chain_id` (composite FK), `member_financial_document_id` (nullable, composite FK), `member_payment_route_id` (nullable, composite FK), timestamps. `CHECK` (exactly one member set). Two separate unique indexes.

---

## 9. Reversal invariants — unchanged, cross-referenced by §5's restated three-mechanism disambiguation

`treasury_ledger_entries`: own debit/credit vocabulary. The event-log tables: `apply`/`reverse`, same-subject, exact-amount, at-most-once, no reverse-of-reverse. §4.2b's leg-reversal coupling and §2.2's document-level reversal are the other two mechanisms — see §5's disambiguation.

---

## 10. `treasury_expense_approvals` — unchanged from v12

`id`, `tenant_id`, `financial_document_id` (composite FK), `event`, `from_status`, `to_status`, `actor_id`, `note` (nullable), `context` (nullable), `created_at`.

### 10.1 Expense approval gate on posting — unchanged from v12
An `expense` document may only reach `posted_unreconciled` if the most recent `treasury_expense_approvals` row for it has `to_status = 'approved'`.

---

## 11. Concurrency — unchanged list from v12, items 1, 3, and 5 extended by this round's checks

| # | Check | Row/subject locked |
|---|---|---|
| 1 | `ContractPayment` route-allocation conservation, `expected_destination_wallet_id` immutability/compatibility, **and the Case A ledger-posting precondition now requiring both `status = 'paid'` and `paid_at IS NOT NULL` (§4.2a, fix #4)** | `contract_payments` row |
| 2 | Cost over-settlement cap (§6.3) | `contract_expenses`/`material_receipt_lines` row |
| 3 | Advance outstanding cap + settlement completeness (§7.4), `advance_return` mandatory settlement linkage, **and, new this round, §7.5's type/amount/party-match and no-drift checks (fix #3)** | `treasury_advances` row |
| 4 | Active reconciliation uniqueness (§12), inverse-reconciliation status regression | `treasury_ledger_entries` row (extended to parent document/route on regression) |
| 5 | Financial-document posting-source selection, Case B equality + immutability freeze, structural eligibility gate, §2.2's exact/at-most-once/**opposite-endpoint (fix #1)** reversal precondition **and its atomic status-flip on the reversal's own posting (fix #1)**, §2.3's direction/linkage rules (fix #2), expense approval gate | `treasury_financial_documents` row — **for §2.2's atomic status flip, both the original and reversal rows, locked in a fixed order (lower `id` first) to avoid deadlock** |
| 6 | Route-leg custody, full-target endpoint-aware completion, atomic leg-reversal/ledger-reversal coupling | Parent `treasury_payment_routes` row |

MySQL: `SELECT ... FOR UPDATE`. SQLite (test suite): `BEGIN IMMEDIATE`.

---

## 12. `treasury_reconciliations` and `treasury_reconciliation_entries` — unchanged from v12

**`treasury_reconciliations`**: `id`, `tenant_id`, `wallet_id` (composite FK), `reconciliation_type`, `external_reference`, `reconciled_at`, `reconciled_by`, timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_reconciliation_entries`**: `id`, `tenant_id`, `reconciliation_id` (composite FK), `ledger_entry_id` (composite FK), `direction` (`apply`\|`reverse`), `reverses_reconciliation_entry_id` (nullable self-ref, composite), `actor_id`, `created_at`. `UNIQUE(reverses_reconciliation_entry_id)`. **Unique: `(tenant_id, id)`.**

### 12.1 `posted_unreconciled → posted_reconciled` — unchanged from v9-v12
`direct`: every ledger entry for the document has an active `apply` reconciliation-entry row. `via_route`: linked route `status = completed` (§4.3) **and** every leg-sourced ledger entry has an active `apply` reconciliation-entry row.

### 12.2 Inverse reconciliation lifecycle — unchanged from v12
A `direction = reverse` reconciliation-entry that breaks §12.1's completeness condition regresses the document's `status` back to `posted_unreconciled`, atomically, in the same transaction.

---

## 13. Composite-FK-target index requirement — unchanged (12 tables)

1. `treasury_financial_parties` 2. `treasury_wallets` 3. `treasury_financial_documents` 4. `treasury_payment_routes` 5. `treasury_payment_route_legs` 6. `treasury_ledger_entries` 7. `treasury_fund_chains` 8. `treasury_advances` 9. `treasury_advance_settlements` 10. `treasury_cost_settlement_allocations` (self-FK) 11. `treasury_reconciliations` 12. `treasury_reconciliation_entries` (self-FK). `source_party_id`/`destination_party_id` (fix #2) are composite FKs into `treasury_financial_parties`, table 1 in this list — no new table.

---

## 14. Tier B — existing-table FK tenant/project rules — unchanged from v9-v12

| Reference | Existence | Tenant/project match |
|---|---|---|
| `treasury_payment_routes.linked_contract_payment_id` | DB-enforced | Same `tenant_id`; same project via `Contract.project_id` = route's own `project_id` |
| `treasury_cost_settlement_allocations.cost_source_contract_expense_id` | DB-enforced | Same `tenant_id`; same project via the expense's owning `Contract.project_id`, matching the allocation's own Treasury-side parent project |
| `treasury_cost_settlement_allocations.cost_source_material_receipt_line_id` | DB-enforced | Same rule as above |

---

## 15. Treasury-internal same-project integrity — unchanged 8 rows from v11/v12

`treasury_financial_parties` carries no `project_id` (§1, unchanged) — `source_party_id`/`destination_party_id` therefore have no project-match rule to add here, only the existing tenant match already DB-enforced by their composite FK shape (`(tenant_id, id)`, same as every other composite FK in this schema). The 8 rows below are otherwise unchanged from v11/v12:

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

---

## 16. Exact table inventory and migration order — unchanged (14 tables)

1. `treasury_financial_parties` 2. `treasury_wallets` 3. `treasury_financial_documents` 4. `treasury_payment_routes` 5. `treasury_payment_route_legs` 6. `treasury_ledger_entries` 7. `treasury_fund_chains` 8. `treasury_advances` 9. `treasury_advance_settlements` 10. `treasury_cost_settlement_allocations` 11. `treasury_expense_approvals` 12. `treasury_reconciliations` 13. `treasury_fund_chain_members` 14. `treasury_reconciliation_entries`. **No migration file exists yet.** This round replaces one column (`counterparty_id`) with two (`source_party_id`, `destination_party_id`) on table 3, and adds two new unique indexes (tables 8 and 9) — zero new tables, 14-table count unchanged.

---

## 17. Verification against every held-constant item

- A3/A4-a/A.5/B2/B2-T/C/D: unchanged, not reopened. **A4-a exclusion held.** **D — no `ReportPageController::cashflow()` edit**: none of this round's 4 fixes touch that controller.
- **B2-T no-second-`ContractPayment`-fact**: fix #4 still only *reads* `ContractPayment.status`/`paid_at` — no new fact is asserted about a `ContractPayment` beyond its own two existing columns.
- **Many-to-many cost allocation**: `treasury_cost_settlement_allocations` (§7) unchanged, untouched by this round's fixes.
- 14-table count, 12 composite-FK targets, explicit Case A endpoint, full-target endpoint completion, posting matrix (field-renamed, behavior-preserved), leg-reversal atomicity, approval/reconciliation lifecycle, Tier-B rules, reconciliation actor, MySQL/SQLite locking, zero existing-table/data migration: all unchanged from Revision 12.
- Fix #1 verified additive to §2.2's existing checks (unique + amount, both retained) — endpoint-equality is a new creation-time check, and the atomic status flip is a new post-time write-path rule; neither removes or weakens anything v12 established.
- Fix #2 verified as a strict, behavior-preserving rename at the eligibility/posting-matrix layer (§4.1, §5a) — every Yes/No outcome and every posting-matrix row maps 1:1 to its v12 `counterparty_id`-based equivalent; the only *new* behavior is that `source_party_id` is now populated (and required, §2.3) for `funding`/`owner_contribution`/`advance_return`, which v12 never recorded at all.
- Fix #3 verified additive-only: two new unique indexes, five new creation-time/standing checks in §7.5, zero new tables or columns beyond what already existed in `treasury_advances`/`treasury_advance_settlements`.
- Fix #4 verified as a strict conjunction extension of v12's single-condition gate — strictly more restrictive, never less.

---

## 18. Trạng thái và bước tiếp theo
- Nếu Owner Approve: chuẩn bị Gate 3 cho GAP-037 — vẫn chỉ là quyết định merge tài liệu.
- Nếu Owner Request changes: sẽ tạo `02-design-v14.md` (supersedes bản này).
- Nếu Owner Decline: dừng GAP-037 ở schema-proposal này.

## 19. Loại trừ phạm vi
Kế thừa nguyên vẹn từ mọi round trước: không migration file thật; không model/controller/service/route/UI/test thật; không seed/backfill; không implementation plan coi schema này là đã duyệt cho Gate 3; không Gate 3 tự suy luận; không mark PR ready; không merge PR #263; không sửa/merge/đóng PR #245 hoặc #257; không GAP-036; không Today Workspace; không sửa canonical SSOT stale metadata; không production/deployment; không thiết kế external-destination leg representation; không sửa `ReportPageController::cashflow()`.

## Decision Needed
**Resolved 2026-08-16T22:09:19+07:00 — Owner Decision: REQUEST CHANGES.** All 4 of Revision 12's points were confirmed achieved — exact swapped reversal endpoints, directional `source_party_id`/`destination_party_id`, advance↔cash type/amount/party/uniqueness binding, Case A `status='paid' AND paid_at IS NOT NULL`. Architecture A3+A4-a+A.5/B2+B2-T/C/D remains approved, frozen, not reopened. Revision 14 must close 3 remaining points: (1) reversal completion timing — a direct reversal marks the original `reversed` at direct-post completion; a `via_route` reversal marks it `reversed` only when its own route reaches full-target `completed`, never at the first leg; (2) dependent-state reversal coupling — when a document-level reversal economically completes, atomically reverse all dependent settlement facts of the original (cost allocations for expense payments, advance outstanding/opening effects for advances, cash-return settlement effects for advance returns); (3) atomic advance↔cash lifecycle — advance posting + advance-row creation atomic; advance-return posting + cash-return/apply settlement creation atomic; cash-return/reverse coupled to the corresponding economic reversal document; `approved_expense` settlement remains a non-second-cash movement. This packet (`02-design-v13.md`) is now **frozen** — no further edits. `docs/owner-decisions/GAP-037/02-design-v14.md` (self-contained) follows in the next commit.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt migration file thật hay chi tiết implementation. Owner cũng không được yêu cầu duyệt lại architecture set A3/A4-a/A.5/B2/B2-T/C/D — đã approved, không mở lại. Owner cũng không được yêu cầu duyệt overpayment/prepayment semantics, hay thiết kế external-destination leg representation (nêu là future extension point, chưa thiết kế ở đây).
