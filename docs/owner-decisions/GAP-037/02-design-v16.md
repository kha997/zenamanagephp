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
  recorded_at: "2026-08-16T23:04:06+07:00"
  owner_response_reference: "Owner Gate 2 Schema Proposal Revision 16 decision -- REQUEST CHANGES, recorded in-session on 2026-08-16 against reviewed PR #263 head dbd1496e5593bbdb7128a6abf498ac4542eeba53: 'GAP-037 -- Gate 2 Schema Proposal Revision 16 -- Owner Decision: REQUEST CHANGES. Reviewed head: dbd1496e5593bbdb7128a6abf498ac4542eeba53. Revision 16 successfully closes all five binding corrections and three same-revision corrections required from Revision 15. Architecture A3 + A4-a + A.5 / B2 + B2-T / C / D remains approved and frozen. Before schema approval, Revision 17 must close only the remaining via_route-original -> document-reversal concurrency boundary: Correct reversal-creation lock ordering: the packet global order is 1->2->3->4->5->6, therefore reversal creation against a via_route original must acquire the original financial-document lock (5) before the original route lock (6). Remove every current 6->5 ordering/statement. Re-check original full-target state at reversal completion: when the reversed original has posting_path=via_route, its original route must still be status=completed at the reversal own economic-completion transaction. If leg reversal/replacement has made the original route partial after reversal creation, reversal completion must reject until the original route is full-target completed again. Lock all class-6 route rows deterministically by id if both original and reversal routes are involved. Add adversarial scenarios for: completed original -> create reversal -> original leg reversed -> reversal completion rejected; original route restored by replacement leg -> reversal completion succeeds; concurrent original-leg reversal vs reversal creation; concurrent original-leg reversal vs reversal completion; both original and reversal are via-route, proving deterministic class-6 ordering. Then run one final full normative-rule <-> scenario <-> concurrency/idempotency audit. Do not return simply because these two edits are made; return only if no further material counterexample is found. No Gate 3, implementation, merge, or modification of PR #245/#257 is inferred.'"
  reconciliation_required: false
supersedes: docs/owner-decisions/GAP-037/02-design-v15.md
superseded_by: docs/owner-decisions/GAP-037/02-design-v17.md
timestamps:
  created_at: "2026-08-16T22:50:00+07:00"
  updated_at: "2026-08-16T23:04:06+07:00"
generated_by: agent
---

# GAP-037 — Project Treasury: Gate 2 Revision 16 — Settlement-Net Correction, Reversal-Path Disambiguation, Immutable-Posting Closure (Fully Self-Contained)

**Status:** Gate 1 approved. Gate 2 architecture decisions **approved** (`docs/owner-decisions/GAP-037/02-design.md`, frozen) — **A3 + A4-a + A.5 / B2 + B2-T / C / D**, not reopened. Still Gate 2 — a proposal, not implementation. No migration file, model, controller, service, route, UI, or test exists or is authorized by this packet. **This revision restates every table, field, constraint, index, state transition, conservation rule, posting-matrix row, reversal rule, Tier-B rule, project/tenant rule, reconciliation rule, and lock rule from scratch. Nothing in this packet depends on "unchanged from vN" for a binding invariant — every rule below is the complete, current, binding text.**

**Database compatibility:** MySQL for production/dev (`config/database.php`, `.env.example`), SQLite for the test suite (`.env.testing`, `phpunit.xml`).

**Repo fact previously verified, restated:** `ContractPayment.paid_at` is a real, nullable `@property \Carbon\Carbon|null` column (`app/Models/ContractPayment.php`, cast `'date'`), distinct from `status`.

**Owner decision recorded 2026-08-16T23:04:06+07:00 — REQUEST CHANGES**, against reviewed head `dbd1496e5593bbdb7128a6abf498ac4542eeba53`. All 5 binding + 3 same-revision corrections from Revision 15 confirmed closed; one narrow remaining gap: §2.2 check #6's stated lock order (route before document) contradicts the packet's own global order, and reversal completion never re-checks that a `via_route` original's route is still `completed` at completion time (it can regress to `partial` via an intervening leg reversal after reversal creation). Architecture A3+A4-a+A.5/B2+B2-T/C/D remains approved, frozen, not reopened. Verbatim text in `decision_provenance.owner_response_reference`. **This packet (`02-design-v16.md`) is now frozen — no further content edits.** `docs/owner-decisions/GAP-037/02-design-v17.md`, self-contained, follows in the next commit.

---

## 0. What changed vs. v15, at a glance

| # | Finding | Fix |
|---|---|---|
| 1 | §2.2d's settlement-net formula subtracted `SUM(reverse)` from `SUM(apply, currently active)` — but "currently active" already excludes applies that have been matched by a `reverse` row, so the extra subtraction double-counted every cancellation and could report a spuriously negative or wrong net | Rewritten to the same two-term net formula §6.5 already uses: `SUM(amount, direction=apply, all rows) - SUM(amount, direction=reverse, all rows)`, over **all** rows (not filtered to "active"), across both `settlement_type`s |
| 2 | `approved_expense` (manual, standalone, §7.4, no ledger effect) and `cash_return` (exclusively document-reversal-coupled, §7.7) reversal were summed together in §2.2d without ever stating they are reached by two structurally different, non-interchangeable mechanisms — v15's own audit scenario (18.10) wrongly modeled a `cash_return` reversal as a bare standalone settlement action | New §7.4a states the distinction as a binding rule, not just narrative; §18's audit scenarios that "reverse the settlements" before a full advance reversal are corrected to route `cash_return` unwinding through reversing the originating `advance_return` **document** (triggering §2.2b's coupling), never through a standalone settlement-level write |
| 3 | Individual freeze/immutability rules existed per-table but no single explicit statement tied them to Architecture C's "immutable posting" requirement, and no explicit closed status-transition graph existed — leaving `reversed`'s terminal status under-specified as a *general* rule rather than a scattered set of per-section carve-outs | New §2.1a: one explicit, general, schema-wide immutable-posting rule (every posted economic row, in every table, is append-only — correction is exclusively additive via a new compensating row) plus the complete closed status-transition graph, with `reversed` and `rejected` stated as the only two terminal states, unconditionally, from which no write path ever transitions out |
| 4 | A `via_route` original's `status` can reach `posted_unreconciled` progressively, leg-by-leg (§5a), before its route reaches full-target `completed` — but §2.2's creation-time reversal precondition only checked `status IN (posted_unreconciled, posted_reconciled)`, which a partially-posted `via_route` document already satisfies, letting a reversal be created against an economic fact that has not actually fully happened yet | New §2.2 check #6: when `reversed_document.posting_path = 'via_route'`, its linked route must additionally have `status = 'completed'` (§4.3) at reversal-creation time — no such extra check exists for `direct` originals, which are always fully-posted-or-not-yet-posted, never partial |
| 5 | §12.2's inverse-reconciliation regression rule applied unconditionally to any document, including one whose `status` had already reached the terminal `reversed` state via §2.2a — letting a reconciliation-layer event on an old, pre-reversal ledger entry silently un-terminal a `reversed` document back to `posted_unreconciled`, corrupting an immutable economic fact | §12.2 now excludes documents whose current `status = 'reversed'` from the regression effect — this is also the concrete instance §2.1a's general terminal-state rule (fix #3) predicts and closes |
| — | Case-B scenario 18.12 corrected: Case B's `UNIQUE(linked_financial_document_id)` means a financial document has **at most one** route, ever — v15's narrative wrongly offered "a second route" as an alternative path to reach 40 then 100; only multiple legs on the single Case-B route can do this, corrected below |
| — | PR #263's body "Current gate status" section, which still pointed at Revision 14 as current even after Revision 15 was posted, is corrected to point at Revision 16 in the same commit as this file |
| — | Positive-magnitude constraints added: `CHECK (amount > 0)`-equivalent (or the appropriately named column) on all 7 economic amount columns across this schema — `treasury_financial_documents.amount`, `treasury_payment_routes.total_allocated_amount`, `treasury_payment_route_legs.amount`, `treasury_ledger_entries.amount`, `treasury_cost_settlement_allocations.allocated_amount`, `treasury_advances.amount`, `treasury_advance_settlements.amount` — stated inline in each table's definition and summarized in new §2.1b |

Everything else in this document is either byte-for-byte restated from v13-v15 (marked "held constant" where useful for traceability) or the fixes above.

---

## 1. `treasury_financial_parties` and `treasury_wallets`

**`treasury_financial_parties`**: `id`, `tenant_id`, `party_type`, `name`, `linked_account_id` (nullable), `linked_user_id` (nullable), timestamps. **Unique: `(tenant_id, id)`.** No `project_id` — parties are tenant-scoped, not project-scoped.

**`treasury_wallets`**: `id`, `tenant_id`, `project_id` (nullable — company/shared wallets have `project_id = NULL`), `wallet_type`, `name`, `custodian_party_id` (nullable, composite FK → `treasury_financial_parties(tenant_id, id)`), timestamps. **Unique: `(tenant_id, id)`.**

---

## 2. `treasury_financial_documents`

Columns: `id`, `tenant_id`, `project_id`, `document_type` (`funding`|`internal_transfer`|`expense`|`owner_contribution`|`advance`|`advance_return`|`reversal`|`adjustment`), `status` (`draft`|`submitted`|`approved`|`rejected`|`posted_unreconciled`|`posted_reconciled`|`reversed`), `posting_path` (nullable enum: `direct`|`via_route`, set exactly once), `amount`, `source_wallet_id` (nullable, composite FK), `destination_wallet_id` (nullable, composite FK), `source_party_id` (nullable, composite FK → `treasury_financial_parties`), `destination_party_id` (nullable, composite FK → `treasury_financial_parties`), `description`, `created_by`, `approved_by` (nullable), `posted_at` (nullable), `reversed_document_id` (nullable self-ref, composite), `replacement_document_id` (nullable self-ref, composite), timestamps.

**Indexes/constraints:** Unique `(tenant_id, id)`. `UNIQUE(reversed_document_id)`. `CHECK NOT (source_wallet_id IS NOT NULL AND source_party_id IS NOT NULL)`. `CHECK NOT (destination_wallet_id IS NOT NULL AND destination_party_id IS NOT NULL)`. Both source fields, and both destination fields, may be simultaneously `NULL` (e.g. an increase-`adjustment` has no source at all). **`CHECK (amount > 0)`** (fix, this round — §2.1b).

There is no `counterparty_id` column — every party reference is directional (`source_party_id`/`destination_party_id`).

### 2.1 Posting-path freeze, amount immutability, eligibility gate

A route (§4) may only attach while `posting_path IS NULL` and `status` is unposted, and only for an endpoint shape that is `via_route`-eligible per §4.1's field-keyed table. Attaching sets `posting_path = via_route` and simultaneously freezes `amount` as immutable. If no route is ever attached, `posting_path` locks to `direct` at posting. **Once `posting_path` is non-null, `amount` has no update path — immutable, full stop.**

### 2.1a Immutable posting and the closed status-transition graph (new, this round — restores Architecture C)

**General rule, schema-wide:** every row in every economic/event-log table in this schema — `treasury_ledger_entries`, `treasury_payment_route_legs` (once `status = 'settled'`), `treasury_cost_settlement_allocations`, `treasury_advance_settlements`, `treasury_advances`, `treasury_reconciliation_entries` — is **immutable once created**: no `UPDATE` path exists, ever, for any of its economic fields (`amount`/`allocated_amount`, `direction`, wallet references, party references, `financial_document_id`/`advance_settlement_id`/`cost_source_*_id` linkage). The **only** way to correct a posted economic fact anywhere in this schema is additive — a new, separate compensating row referencing the original through that table's own established self-referencing pattern (`reversal_of_entry_id`, `reverses_allocation_id`, `reverses_settlement_id`, `reverses_reconciliation_entry_id`) or, at the document level, a new `reversal` document (§2.2) or corrective replacement (§2.2c). This is the direct, general restatement of Architecture C's binding "immutable posting... must be preserved" requirement — every per-table freeze rule elsewhere in this document (§2.1's `amount` freeze, §4.1's `total_allocated_amount`/`expected_destination_wallet_id` freeze) is a specific instance of this one general rule, not a separate, narrower guarantee.

**`treasury_financial_documents.status` — the complete, closed transition graph** (no transition exists in this schema outside the ones listed below; any other transition is invalid by omission, not merely undocumented):

- `draft → submitted → approved → posted_unreconciled` (posting occurs; `rejected` may be reached from `submitted` or `approved` instead of `posted_unreconciled`).
- `posted_unreconciled ⇄ posted_reconciled` — forward via §12.1's completeness condition, inverse via §12.2's regression — **bidirectional, but only while `status ≠ reversed`** (§12.2's exclusion, fix this round, below).
- `(posted_unreconciled | posted_reconciled) → reversed` — via §2.2a's completion moment. One-way.
- **`reversed` is terminal, unconditionally.** No write path in this schema ever transitions a document's `status` away from `reversed`, for any reason — not reconciliation regression (§12.2, corrected this round), not any other event this schema defines. Once `status = 'reversed'`, it never changes again.
- **`rejected` is terminal**, symmetrically — a `rejected` document never posts, is never reversed (nothing to reverse — it never became an economic fact), and never transitions further.

`reversed` and `rejected` are the schema's only two terminal states.

### 2.1b Positive-magnitude constraint (new, this round)

Every economic amount column in this schema carries a binding `CHECK (<column> > 0)` — zero and negative amounts are never valid for a posted or postable economic fact anywhere in this design. This applies to all seven amount-bearing columns: `treasury_financial_documents.amount` (§2, above), `treasury_payment_routes.total_allocated_amount` (§4), `treasury_payment_route_legs.amount` (§4), `treasury_ledger_entries.amount` (§5), `treasury_cost_settlement_allocations.allocated_amount` (§7), `treasury_advances.amount` (§7), `treasury_advance_settlements.amount` (§7). Each is also restated inline at its own table definition, below, for self-containment.

### 2.2 Document-level reversal — creation-time structural checks

When `reversed_document_id` is set (immutable once set, same convention as every other set-once field), the following are checked **at creation**:

1. **At-most-once:** `UNIQUE(reversed_document_id)` — a document may be the target of at most one reversal, ever.
2. **No reversal-of-reversal:** `reversed_document.document_type <> 'reversal'`. A `reversal` document can never itself become the target of another `reversal`. This is absolute — it does not matter whether the first reversal has posted, is still `draft`, or was itself created in error; the only path to correcting an erroneous reversal is §2.2c's `replacement_document_id`, never a second reversal layer.
3. **Exact amount:** `reversal.amount = reversed_document.amount`.
4. **Exact, opposite endpoints:** `reversal.source_wallet_id = reversed_document.destination_wallet_id`, `reversal.destination_wallet_id = reversed_document.source_wallet_id`, `reversal.source_party_id = reversed_document.destination_party_id`, `reversal.destination_party_id = reversed_document.source_party_id`. Every endpoint field is swapped, wallet-for-wallet and party-for-party. (A reversal of a decrease-`adjustment`, which has only `source_wallet_id` set, is itself only `destination_wallet_id` set — consistent with the swap rule applied to a one-sided document.)
5. **Postable-original precondition:** `status IN ('posted_unreconciled', 'posted_reconciled')` on the original at creation time.
6. **Full-target completion for `via_route` originals (fix, this round):** if `reversed_document.posting_path = 'via_route'`, its linked `treasury_payment_routes` row must additionally have `status = 'completed'` (§4.3's full-target predicate) at the moment of reversal creation. Check #5 alone is insufficient for `via_route` originals, since §5a's per-leg progressive posting lets `status` reach `posted_unreconciled` while the route is still only `planned`/`partial` — i.e. before the document's own economic movement has actually, fully happened. A reversal may never be created against a `via_route` original whose route has not yet reached full-target `completed`. **No equivalent extra check exists for `posting_path = direct` originals** — a `direct` posting is a single atomic action (§2.1a), so it is always either fully posted or not posted at all; check #5 alone is already sufficient for that branch. **Locking:** this check reads the original's route under the route lock (§11 item 6), acquired before the `treasury_financial_documents` insert (§11 item 5) that creates the reversal row — consistent with §11's ascending order — so a concurrent leg-level event on that same route cannot race this check.
7. **Project match:** unchanged rule, §15 row 4 — `reversed_document_id`/`replacement_document_id` targets must share `project_id` with the reversal.

### 2.2a Document-level reversal — completion timing (branched by `posting_path`)

The original document's `status → 'reversed'` transition is **never** set at reversal *creation* (creation only establishes the `reversed_document_id` link per §2.2 above). It is set exactly once, atomically, at the reversal's own **economic completion** — defined as exactly one of two moments, branched on the reversal document's own `posting_path` (set exactly once, §2.1, and never changes — so exactly one branch ever applies to a given reversal):

- **`posting_path = direct`:** the original's `status` is set to `'reversed'` in the same transaction as the reversal document's own direct ledger entries being created (§5a). A `direct` posting is a single atomic action, so "posting completion" and "first entry created" are the same fact.
- **`posting_path = via_route`:** the original's `status` is set to `'reversed'` **only** when the reversal's own linked `treasury_payment_routes` row reaches `status = 'completed'` per §4.3's full-target, endpoint-aware predicate — never at route attachment, never at the first leg settling, never while the route is `planned`/`partial`. This write is atomic with the route's own `completed` transition (§11 item 6).

There is no third moment, and no reversal document skips this. **Locking:** both the original and reversal document rows (§11 item 5) are locked in a fixed, deterministic order — lower `id` first, independent of which one is conceptually "original" vs. "reversal" — to avoid a new deadlock ordering. When the `via_route` branch also needs the route lock (§11 item 6) and, per §2.2b, an advance/cost/advance-settlement lock (§11 items 2/3), the full fixed global order (§11: 1→2→3→4→5→6) is followed — see §11 for the complete, corrected statement.

**Why staged this way:** a reversal document can legitimately exist in `draft`/`submitted`/`approved` for review before its own economic movement actually happens — nothing about undoing an economic fact should occur before the undoing itself is real.

### 2.2b Dependent-state reversal coupling

When the original's `status → 'reversed'` fires (§2.2a's completion moment, whichever branch applies), the following **dependent** facts — settlement-layer state the original document's own posting had already created — are reversed **in the same atomic write**, scoped by the original's `document_type`:

- **`document_type = 'expense'`:** for every `treasury_cost_settlement_allocations` row with `financial_document_id = <original>` that is currently active (`direction = apply`, no currently-active compensating `reverse` row per §7's `UNIQUE(reverses_allocation_id)`), a matching row is created with `direction = reverse`, `reverses_allocation_id` → that `apply` row's id, `allocated_amount` = that row's `allocated_amount`, and **`financial_document_id = reversal_document.id`** (v15's fix, restated — previously unstated in v14; bound for the same auditability reason as the `advance_return` bullet below).
- **`document_type = 'advance'`:** the `treasury_advances` row whose `originating_financial_document_id = <original>` is **closed to further `apply`-direction settlements** from this moment forward — no new `treasury_advance_settlements` row with `direction = apply` may reference this `treasury_advances` row once its originating document is `reversed`. This does **not** cascade to automatically reverse settlements that were already `apply`-active before the reversal — those are separate facts needing their own explicit reversal (§2.2d makes this a hard precondition, not just a scope note: see below). Additionally, per §6.5's outstanding override: `outstanding(advance) = 0` from this moment, definitionally, regardless of the settlement-sum formula's result.
- **`document_type = 'advance_return'`:** the exactly-one linked `treasury_advance_settlements` row (`settlement_type = cash_return`, `direction = apply`, `financial_document_id = <original>`, unique per §7.5b) gets a compensating row created with `direction = reverse`, `reverses_settlement_id` → that `apply` row's id, `amount` = that row's `amount`, and **`financial_document_id = reversal_document.id`** (v15's fix, restated). These three fields — `financial_document_id`, `reverses_settlement_id`, `amount` — are the complete, binding persisted shape of every `cash_return`/`reverse` row created by this coupling; no such row is ever created any other way (§7.7).
- **Every other `document_type`** (`funding`, `internal_transfer`, `owner_contribution`, `adjustment`): no dependent settlement-layer state exists for these types, so reversal completion is exactly §2.2a's status flip alone.

All of the above, when they apply, happen in the **exact same transaction** as §2.2a's status flip — never a follow-up write, never eventually-consistent.

### 2.2c `replacement_document_id` — corrective replacement (v15's semantics, restated)

`replacement_document_id` (nullable self-ref, composite FK into this same table) exists in the column list but its semantics were never previously bound. Defined now:

- **May only be set on a document whose own `document_type = 'reversal'`** — mirroring §2.2's no-reversal-of-reversal rule: only a `reversal` document is ever barred from being reversed again, and only a `reversal` document is ever the kind of mistake `replacement_document_id` exists to correct.
- **Pure audit-trail link, zero automated economic effect.** Setting it does not post anything, does not flip any status, does not create any ledger entry, settlement row, or allocation row. It is informational: "this erroneous reversal is superseded, in substance, by that other document."
- **The corrective document is a fresh, independently valid document of whatever `document_type` the correction actually requires** (a new `advance`, a new `expense`, a new `funding`, etc., possibly itself later a target of its own single reversal under the normal rules). It must satisfy every one of its own type's creation/posting rules from scratch — no dependent-state replay, no cascading from the erroneous reversal it corrects. This is the deliberately narrow design choice the Owner's finding calls for: replay of dependent state is out of scope; a new corrective business document is the whole mechanism.
- **Immutable once set**, same convention as every other set-once field in this table.
- **Project match:** unchanged rule, §15 row 4.

### 2.2d Advance full-reversal precondition — settlement net must be zero (corrected, this round)

A `reversal` document whose `reversed_document.document_type = 'advance'` may only reach its own economic completion (§2.2a's completion moment) if, **at that exact moment, checked under lock**, the target's `treasury_advances` row (found via `originating_financial_document_id = reversed_document.id`) has an **active settlement net of exactly zero**:

**`SUM(amount WHERE direction = apply) - SUM(amount WHERE direction = reverse) = 0`**

summed over **all** `treasury_advance_settlements` rows for this advance (not filtered to any "currently active" subset), across **both** `settlement_type`s (`approved_expense` and `cash_return`) together — the identical two-term net formula §6.5 already uses for `outstanding`, restated here as a precondition rather than a computed value.

**Correction, this round:** v15's formula wrongly filtered the `apply` term to "currently active" applies (those with no compensating `reverse` yet) and *then* separately subtracted the full `SUM(reverse)` — but an `apply` row excluded from "currently active" is, by definition, one that already has a matching `reverse` of the same amount (§7.4's 1:1 completeness discipline), so subtracting `SUM(reverse)` a second time double-counted every cancellation and could report a net anywhere from wrong-but-still-zero (by coincidence) to a nonsensical negative value once any reverse existed at all. The corrected formula sums both directions over **every** row, unfiltered, letting `apply` and `reverse` cancel arithmetically exactly once each — this is the same style of formula §6.5 already used correctly, and §2.2d now matches it exactly rather than inventing a second, inconsistent one.

**§7.4a (new, cross-referenced here) matters for how this net can ever reach zero:** the two `settlement_type`s reach a matching `reverse` through structurally different mechanisms — an `approved_expense` `reverse` is created by a standalone manual action (§7.4, §7.4a), while a `cash_return` `reverse` is created **only** as the automatic consequence of reversing the `advance_return` **document** that created the matching `apply` (§2.2b, §7.7) — there is no standalone write path for a `cash_return` reverse. Both, once created by whichever mechanism applies, contribute identically to this section's net-zero sum; the precondition itself does not care which mechanism produced a given `reverse` row, only that the sum is zero.

If this net is non-zero at the completion-time check, the write is rejected — the reversal document may still exist (`draft`/`submitted`/`approved`, or even posted-but-not-yet-completed for a `via_route` reversal whose route has not yet reached full-target `completed`), but it cannot complete until every `approved_expense`/`cash_return` settlement previously applied against the advance has itself been explicitly reversed first, through whichever of the two mechanisms above actually applies to that settlement's type — never auto-cascaded by this rule.

This check is **re-evaluated at completion, not only at creation** — a settlement applied after the reversal was created but before it completed still blocks completion, closing the race the Owner's finding points at. **Locking:** the `treasury_advances` row (§11 item 3) is locked **before** the `treasury_financial_documents` row(s) (§11 item 5), consistent with §11's corrected fixed order (see §11).

Once this precondition holds and the reversal completes, §2.2b's `advance` bullet and §6.5's fix override both apply: no further `apply`s are permitted, and `outstanding` reads as `0` from that moment on.

### 2.3 External-party direction convention

Exactly two document-endpoint shapes involve `treasury_financial_parties`:

- **Party-as-source** — `source_party_id` set, `destination_wallet_id` set: `funding`, `owner_contribution`, `advance_return`. `source_party_id` is **required** for these three types.
- **Party-as-destination** — `source_wallet_id` set, `destination_party_id` set: `expense`, `advance`. `destination_party_id` is **required** for these two types.
- **Neither:** `internal_transfer` (both wallets, no party) and `adjustment` (one wallet, no party).
- **`reversal`:** always the exact swap of whichever shape its `reversed_document_id` target had (§2.2) — a reversal of a party-as-destination document becomes party-as-source, and vice versa, with the *same* party on both sides of the pair.

An `advance` document's `destination_party_id` becomes, once posted, the binding party for its `treasury_advances.financial_party_id` (§7.5a). An `advance_return` document's `source_party_id` must equal that same advance's `financial_party_id` before it may post (§7.5b).

---

## 3. The typed-nullable-FK pattern

Every former polymorphic reference is N nullable typed FK columns + a `CHECK` requiring exactly one non-null: `treasury_payment_routes` (§4), `treasury_ledger_entries` (§5), `treasury_cost_settlement_allocations` (§7), `treasury_fund_chain_members` (§8). §2's source/destination-wallet/party pair follows the same discipline at the endpoint level.

---

## 4. `treasury_payment_routes` and `treasury_payment_route_legs`

**`treasury_payment_routes`**: `id`, `tenant_id`, `project_id`, `total_allocated_amount`, `status` (`planned`|`partial`|`completed`|`cancelled`), `linked_financial_document_id` (nullable, composite FK), `linked_contract_payment_id` (nullable, single-column FK → `contract_payments(id)`), `expected_destination_wallet_id` (nullable, composite FK), timestamps. `CHECK ((linked_financial_document_id IS NULL) != (linked_contract_payment_id IS NULL))`. `CHECK ((linked_contract_payment_id IS NOT NULL) = (expected_destination_wallet_id IS NOT NULL))`. `CHECK (total_allocated_amount > 0)` (fix, this round — §2.1b). Unique index directly on `linked_financial_document_id`. **Unique: `(tenant_id, id)`.**

**`treasury_payment_route_legs`**: `id`, `tenant_id`, `payment_route_id` (composite FK), `sequence_no`, `from_wallet_id` (nullable, composite FK), `to_wallet_id` (composite FK), `amount`, `status` (`in_transit`|`settled`|`reversed`), `occurred_at`, timestamps. `CHECK (amount > 0)` (fix, this round — §2.1b). **Unique: `(tenant_id, id)`.**

### 4.1 Conservation, immutability, and eligibility

**Case A — `linked_contract_payment_id`:** `SUM(total_allocated_amount) <= ContractPayment.amount` across a `ContractPayment`'s routes (§11 item 1). `total_allocated_amount` and `expected_destination_wallet_id` are both immutable post-creation, same tenant/project/wallet-compatibility rule as §15 row 5.

**Case B — `linked_financial_document_id`:** `route.total_allocated_amount = linked_financial_document.amount`, both frozen together atomically at attachment (§11 item 5).

**`via_route` eligibility (structural, field-keyed):**

| Document's own populated endpoint fields | `via_route` eligible? |
|---|---|
| `destination_wallet_id` set, `destination_party_id` NULL, `source_wallet_id` NULL (`source_party_id` may be set or NULL) | **Yes** |
| `source_wallet_id` set AND `destination_wallet_id` set (both party fields NULL) | **Yes** |
| `destination_party_id` set | **No — `direct` only** |
| `source_wallet_id` set, `destination_wallet_id` NULL, `destination_party_id` NULL | **No — `direct` only** |

A `via_route`-eligible `reversal` document is classified by this same table using its own (swapped) fields. Concretely: `advance` always has `destination_party_id` set → always **`direct` only**, never `via_route` — this is why §2.2d's precondition and §7.5's atomicity never need a `via_route` branch for the `advance` document itself. `advance_return` always has `destination_wallet_id` set with no party on that side → always **`via_route`-eligible**, which is exactly why §7.6's `posting_path` branch (originally v15's fix) is needed. `expense` always has `destination_party_id` set → always `direct` only.

### 4.2 Route-leg custody

Lock the parent route (§11 item 6). Wallet-backed leg: validate against §5a's balance formula. External-entry leg (`from_wallet_id IS NULL`): bounded by remaining `total_allocated_amount`. Both persist leg + ledger entries atomically under the lock.

### 4.2a Case A ledger-posting precondition

For a Case A route, no leg belonging to that route may transition into `status = settled` (no ledger entries may post for it) unless, at the moment of that write, **both** `ContractPayment.status = 'paid'` **and** `ContractPayment.paid_at IS NOT NULL` hold — a conjunction, not an either-or fallback. **Locking:** the `contract_payments` row (§11 item 1), same fixed lock order (`contract_payments` before `treasury_payment_routes`).

### 4.2b Atomic leg-reversal ↔ ledger-reversal coupling

A leg's `status → reversed` transition and the creation of reversal ledger entries for every one of that leg's original entries are one atomic write under the parent-route lock (§11 item 6) — never independent. (Distinct from §2.2's *document*-level reversal — see §5's disambiguation.)

### 4.3 Route-completion predicate — endpoint-aware, full-target

Define `net_custody(w) = SUM(credit) - SUM(debit)` over this route's ledger entries at wallet `w`.

- **Precondition, all cases:** every leg `status IN ('settled', 'reversed')`.
- **Wallet-terminating inbound (Case B):** `net_custody(destination_wallet_id) = total_allocated_amount` (full, unreduced), intermediaries `= 0`.
- **`internal_transfer` (Case B):** `net_custody(source_wallet_id) = -total_allocated_amount`, `net_custody(destination_wallet_id) = +total_allocated_amount`, intermediaries `= 0`.
- **Case A:** `net_custody(expected_destination_wallet_id) = total_allocated_amount`, intermediaries `= 0`.

A route with a reversed leg stays `partial` until a full-target replacement completes it; `cancelled` if abandoned; represented via a `reversal` financial document (§2.2) if the whole document-level movement is undone. **This is the exact predicate §2.2a's `via_route` reversal-completion branch, §7.6's `via_route` `advance_return`-settlement-completion branch, and §2.2 check #6's reversal-creation precondition (this round's addition) all depend on** — the same route-completion definition is the trigger all three use; no new predicate exists.

---

## 5. `treasury_ledger_entries`

`id`, `tenant_id`, `source_financial_document_id` (nullable, composite FK), `source_payment_route_leg_id` (nullable, composite FK), `wallet_id` (composite FK), `direction` (`debit`|`credit`), `amount`, `entry_type`, `posted_at`, `reversal_of_entry_id` (nullable self-ref, composite), `created_at`. `CHECK` (exactly one source set). `CHECK (amount > 0)` (fix, this round — §2.1b). Idempotency via generated `original_posting_key` (unique — a retried write that would re-create an already-posted entry is rejected by this constraint, not silently duplicated). `wallet_balance = SUM(credit) - SUM(debit)`. Reversal: same wallet/source/amount, opposite direction, `UNIQUE(reversal_of_entry_id)`, no reverse-of-reverse.

Index: `(source_financial_document_id)`, `(source_payment_route_leg_id)`, `(wallet_id, posted_at)`, `UNIQUE(reversal_of_entry_id)`, `UNIQUE(original_posting_key)`. **Unique: `(tenant_id, id)`.**

**Four distinct reversal mechanisms exist in this schema — never conflated:**
(a) `treasury_ledger_entries.reversal_of_entry_id` — single erroneous *ledger entry* correction.
(b) `treasury_payment_route_legs.status = 'reversed'` coupled atomically to its own entries (§4.2b) — *leg*-level, scoped to one route.
(c) `treasury_financial_documents.document_type = 'reversal'` (§2.2/§2.2a) — *document*-level, economically real undo, expressed as a separate document whose creation only links the two documents and whose eventual completion atomically flips the original to `status = 'reversed'`.
(d) §2.2b's dependent-state coupling — not a reversal mechanism of its own, but the automatic *triggering* of the `apply`/`reverse` event-log pattern in §7 as a **consequence** of (c) completing. It never creates a *new kind* of reversal row — only causes existing patterns to fire automatically instead of by separate manual request.

### 5a. Posting conservation matrix

**`posting_path = direct`** (entries carry `source_financial_document_id = <this document>`):

| `document_type` (direct-eligible shape) | Ledger entries created at posting |
|---|---|
| `funding`, `owner_contribution` | One `credit` entry at `destination_wallet_id`, `amount = document.amount`. `source_party_id` is metadata; no ledger entry exists for the party side |
| `advance_return` (`direct` branch only) | One `credit` entry at `destination_wallet_id`, `amount = document.amount` |
| `internal_transfer` | One `debit` entry at `source_wallet_id` **and** one `credit` entry at `destination_wallet_id`, both `amount = document.amount`, atomic |
| `expense`, `advance` | One `debit` entry at `source_wallet_id`, `amount = document.amount`. `destination_party_id` identifies the receiving party; no ledger entry for the party side |
| `adjustment` (increase) | One `credit` entry at `destination_wallet_id`, `amount = document.amount` |
| `adjustment` (decrease) | One `debit` entry at `source_wallet_id`, `amount = document.amount` |
| `reversal` | Entries per its own shape, which per §2.2 is always the exact swap of the reversed document's shape |

**`posting_path = via_route`** (entries carry `source_payment_route_leg_id = <leg>`): wallet-to-wallet hop posts a `debit`/`credit` pair at `leg.amount`; external-entry leg (`from_wallet_id IS NULL`) posts a lone `credit` at `to_wallet_id`. This applies per-leg, progressively, as legs settle — **this ledger-posting progression is independent of, and unaffected by, §7.6's `advance_return` settlement-row timing fix**: a `via_route` `advance_return`'s ledger entries post leg-by-leg as usual; only the `cash_return`/`apply` **settlement row** (a separate fact, in a separate table) waits for full route completion.

Conservation identity: every entry created by a single posting action is a self-balancing pair or a lone external-entry credit; no entry's amount ever differs from its document's/leg's own `amount`.

---

## 6. Settlement conservation

**6.1** `net_allocation(cost_source) = SUM(apply) - SUM(reverse)`.
**6.2** Direct-expense: `net_allocation(financial_document) = financial_document.amount`.
**6.3** `0 <= net_allocation(cost_source) <= canonical_incurred_amount(cost_source)`.
**6.5** `outstanding(advance)`:
- **If `advance.originatingFinancialDocument.status = 'reversed'`: `outstanding(advance) = 0`, unconditionally** (unchanged from v15 — the advance is economically inactive; the cash that funded it no longer exists, so no positive outstanding balance may ever be reported for it, regardless of what the raw settlement-sum arithmetic below would otherwise produce).
- **Otherwise:** `outstanding(advance) = advance.amount - SUM(apply) + SUM(reverse)`, bounded `0 <= outstanding(advance) <= advance.amount`, summed across both `settlement_type`s together.
**6.6** Any write that would violate `6.3` or `6.5`'s bound is rejected.
**6.7** Material prepayment is represented via `treasury_advances`, never a nonexistent-cost-record allocation.

---

## 7. `treasury_cost_settlement_allocations`, `treasury_advances`, `treasury_advance_settlements`

**`treasury_cost_settlement_allocations`**: `id`, `tenant_id`, `financial_document_id` (nullable, composite FK), `advance_settlement_id` (nullable, composite FK), `cost_source_contract_expense_id` (nullable, single-column FK), `cost_source_material_receipt_line_id` (nullable, single-column FK), `direction` (`apply`|`reverse`), `allocated_amount`, `reverses_allocation_id` (nullable self-ref, composite), `created_at`. `CHECK` (exactly one of `financial_document_id`/`advance_settlement_id` set — the two-way split between a direct-expense allocation and an advance-settlement-sourced allocation). `CHECK` (exactly one of `cost_source_contract_expense_id`/`cost_source_material_receipt_line_id` set). `CHECK (allocated_amount > 0)` (fix, this round — §2.1b). `UNIQUE(reverses_allocation_id)`. **Unique: `(tenant_id, id)`.**

**`treasury_advances`**: `id`, `tenant_id`, `project_id`, `financial_party_id` (composite FK), `originating_financial_document_id` (composite FK), `amount`, timestamps. `CHECK (amount > 0)` (fix, this round — §2.1b). **Unique: `(tenant_id, id)`.** `UNIQUE(originating_financial_document_id)` — at most one `treasury_advances` row per originating document.

**`treasury_advance_settlements`**: `id`, `tenant_id`, `advance_id` (composite FK), `settlement_type` (`approved_expense`|`cash_return`), `direction` (`apply`|`reverse`), `amount`, `financial_document_id` (nullable, composite FK), `reverses_settlement_id` (nullable self-ref, composite), `created_at`. `CHECK (amount > 0)` (fix, this round — §2.1b). `UNIQUE(reverses_settlement_id)`. `UNIQUE(financial_document_id)` (nullable-safe — enforced only across non-null values) — at most one settlement row may claim any given document (an `advance_return`'s `apply` row, or a reversal's `reverse` row per §2.2b/§7.7 — each is a distinct document id, so no conflict between the two). **Unique: `(tenant_id, id)`.**

### 7.4 Advance-settlement completeness

`apply`: own allocations sum to its amount. `reverse`: atomically creates compensating allocations for every still-active original, complete 1:1 coverage, own completeness check.

### 7.4a Two non-interchangeable reversal mechanisms for `treasury_advance_settlements` (new, this round — disambiguates §2.2d)

The two `settlement_type`s are reversed through **structurally different mechanisms that must never be conflated**, even though both ultimately produce a `direction = reverse` row counted identically by §2.2d's and §6.5's net formulas:

- **`approved_expense` — manual, standalone, no document.** A `reverse` row is created directly, by an independent business action (e.g. an operator undoing a cost application), through this section's ordinary `apply`/`reverse` completeness mechanism alone. It has no `financial_document_id` (§7's `treasury_cost_settlement_allocations` linkage for this settlement type is keyed by `advance_settlement_id`, never `financial_document_id` — §7.7), it is never triggered by, coupled to, or dependent on any `treasury_financial_documents`-level reversal completing, and it never has a paired `treasury_ledger_entries` row to undo (§7.7 — `approved_expense` never created one in the first place).
- **`cash_return` — exclusively document-reversal-coupled, never manual.** A `reverse` row for this settlement type may **only** be created as the automatic consequence of reversing the `advance_return` **document** that created the matching `apply` row (§2.2b's `advance_return` bullet, §7.7). There is no standalone write path — no business action can directly create a `cash_return`/`reverse` row without going through §2.2's full document-reversal creation-and-completion sequence against that specific `advance_return` document. Attempting to "manually reverse a cash return" without reversing its originating document is not a supported operation in this schema.

**Why this distinction matters for §2.2d:** to bring an advance's active settlement net to zero before its full reversal can complete, an `approved_expense` application is unwound by a direct, standalone reverse action, while a `cash_return` application can *only* be unwound by reversing the `advance_return` document that created it — a heavier, document-level operation with its own creation/completion sequence (§2.2, §2.2a) and its own dependent-state consequences (§2.2b). §18's audit scenarios (18.9-18.10) are corrected this round to reflect this — see below.

### 7.5 Atomic advance posting

The `advance` document's own posting transition (its `debit` ledger entry creation, per §5a — the moment it first satisfies `status = posted_unreconciled`) and the creation of its `treasury_advances` row are **one atomic write, in the same transaction**. There is no code path that posts an `advance` document without also creating its `treasury_advances` row in that same write, and none that creates a `treasury_advances` row referencing a document that is not, in that same transaction, becoming posted. `advance` is always `posting_path = direct` (§4.1 — `destination_party_id` is always set for this type), so no `via_route` branch exists for this section.

**Checked as part of this one atomic write:**
1. `originating_financial_document.document_type = 'advance'` — type match.
2. `treasury_advances.amount = originating_financial_document.amount` — amount match.
3. `treasury_advances.financial_party_id = originating_financial_document.destination_party_id` — party match.
4. `UNIQUE(originating_financial_document_id)` (schema-level, above).

**Locking (v15's correction, restated):** the `treasury_advances` row (§11 item 3) is locked **before** the `treasury_financial_documents` row (§11 item 5) — item 3 precedes item 5 in the fixed global order (§11).

### 7.6 Atomic advance-return posting — branched by `posting_path` (v15's fix, restated)

The `advance_return` document's own settlement-row creation is bound to its `posting_path` (§4.1 — `advance_return` is always party-as-source, always `via_route`-eligible, so both branches are real and must both be specified):

- **`posting_path = direct`:** the document's own `credit` ledger entry creation (§5a) and the creation of its `treasury_advance_settlements` row (`settlement_type = cash_return`, `direction = apply`, `financial_document_id = <this document>`) are **one atomic write, in the same transaction** — unchanged from the direct-only rule prior rounds stated.
- **`posting_path = via_route`:** the `treasury_advance_settlements` row (same shape as above) is created **only** when the document's own linked `treasury_payment_routes` row reaches `status = 'completed'` per §4.3's full-target predicate — never at route attachment, never at a partial/first leg. The document's own ledger entries still post progressively per leg as usual (§5a) — only the settlement-row creation (and therefore its effect on the advance's `outstanding` figure, §6.5) waits for full completion. This is the same conceptual branch as §2.2a's reversal-completion timing fix, applied here to the *original* `advance_return` document rather than to a `reversal` document — the Owner's finding is that this same defect existed in both places and both needed the identical fix.

**Checked as part of the atomic write, either branch:**
1. `UNIQUE(financial_document_id)` guarantees exactly one such row (schema-level, above).
2. `settlement.amount = advance_return.amount` — amount match.
3. `advance_return.source_party_id = treasury_advances.financial_party_id` (via `settlement.advance_id`) — party match.

**Locking:** `treasury_advances` row (§11 item 3) **before** `treasury_financial_documents` row (§11 item 5) — same corrected relative order as §7.5. For the `via_route` branch, the route lock (§11 item 6) is also held for the completion write, acquired last, after items 3 and 5, per §11's fixed ascending order.

### 7.7 `cash_return`/`reverse` settlements — created only by reversal coupling

A `treasury_advance_settlements` row with `settlement_type = cash_return` and `direction = reverse` may be created **only** as part of §2.2b's dependent-state coupling — the automatic consequence of an `advance_return` document's own `reversal` document economically completing (§2.2a). There is no independent, manually-triggered write path that creates a `cash_return`/`reverse` row outside of that coupling. **Its persisted shape is exactly three bound fields (v15's fix, restated — closes an ambiguity that existed through v14): `financial_document_id = reversal_document.id`, `reverses_settlement_id = original cash_return/apply settlement.id`, `amount = original settlement.amount`** — every unit of this row is traceable to the reversal document that created it and the exact settlement fact it undoes.

`approved_expense` settlements (either direction) never have a paired `treasury_ledger_entries` row of their own — they represent applying an already-existing advance balance against a cost, not a fresh cash movement. If an expense settled via `approved_expense` is itself the target of a document-level reversal, §2.2b's `expense`-branch coupling (reversing its `treasury_cost_settlement_allocations` rows, if any exist with `financial_document_id` set) is what fires; the `approved_expense` settlement itself, keyed by `advance_settlement_id` rather than `financial_document_id` in `treasury_cost_settlement_allocations`, is untouched by this coupling — consistent with it never having created a cash movement to undo.

### 7.8 Full advance-reversal precondition — cross-reference

See §2.2d for the binding rule that a `reversal` targeting an `advance`-type original may only complete when the advance's active settlement net is zero, and §6.5 for the resulting `outstanding = 0` override once it does.

---

## 8. `treasury_fund_chains` and `treasury_fund_chain_members`

**`treasury_fund_chains`**: `id`, `tenant_id`, `project_id`, `chain_reference`, `description`, timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_fund_chain_members`**: `id`, `tenant_id`, `fund_chain_id` (composite FK), `member_financial_document_id` (nullable, composite FK), `member_payment_route_id` (nullable, composite FK), timestamps. `CHECK` (exactly one member set). Two separate unique indexes (one per member-type column, nullable-safe).

---

## 9. Reversal invariants — cross-reference

`treasury_ledger_entries`: own debit/credit vocabulary (§5). The event-log tables (§7): `apply`/`reverse`, same-subject, exact-amount, at-most-once, no reverse-of-reverse. §4.2b's leg-reversal coupling and §2.2/§2.2a's document-level reversal are the other two mechanisms — see §5's four-mechanism disambiguation. §2.2's no-reversal-of-reversal `CHECK` (v15's addition, restated) is the document-level analogue of `treasury_ledger_entries`' own `UNIQUE(reversal_of_entry_id)` "no reverse-of-reverse" rule and `treasury_cost_settlement_allocations`'/`treasury_advance_settlements`'/`treasury_reconciliation_entries`' own `UNIQUE(reverses_*_id)` rules — every layer in this schema independently enforces "a reversal is a leaf, never itself reversed."

---

## 10. `treasury_expense_approvals`

`id`, `tenant_id`, `financial_document_id` (composite FK), `event`, `from_status`, `to_status`, `actor_id`, `note` (nullable), `context` (nullable), `created_at`.

### 10.1 Expense approval gate on posting
An `expense` document may only reach `posted_unreconciled` if the most recent `treasury_expense_approvals` row for it has `to_status = 'approved'`.

---

## 11. Concurrency — single global fixed lock order, corrected and made fully deterministic

MySQL: `SELECT ... FOR UPDATE`. SQLite (test suite): `BEGIN IMMEDIATE`.

**There is exactly one global acquisition order: 1 → 2 → 3 → 4 → 5 → 6.** Every transaction that needs more than one of the six classes below acquires them in strictly ascending class order — never the reverse, never interleaved by which entity is conceptually "primary" to the write. This is the fix for the contradiction the Owner's finding identified: prior rounds stated the global order as 1→2→3→4→5→6 but then, in §7.5/§7.6, separately stated the document row (item 5) locks before the advance row (item 3) for advance posting — a direct violation. **That is corrected here: item 3 always locks before item 5, with no exception, in every write in this schema.**

| # | Check | Row/subject locked | Deterministic intra-class order (when multiple rows of this class are locked in one transaction) |
|---|---|---|---|
| 1 | `ContractPayment` route-allocation conservation, `expected_destination_wallet_id` immutability/compatibility, Case A `status='paid' AND paid_at IS NOT NULL` gate | `contract_payments` row | Single row per transaction in every write this schema defines; no multi-row case exists |
| 2 | Cost over-settlement cap (§6.3) | `contract_expenses`/`material_receipt_lines` row | When an `expense` document's cost allocations, or their §2.2b reversal coupling, touch more than one cost-source row: order by `(source_table, id)` ascending, `contract_expenses` before `material_receipt_lines` |
| 3 | Advance outstanding cap + settlement completeness (§7.4), `advance_return` linkage/amount/party match, §7.5/§7.6's atomic advance/advance-return posting + row-creation (both branches), §2.2b's advance-closure-to-new-applies and advance/advance_return dependent-reversal writes, §2.2d's full-reversal settlement-net-zero precondition | `treasury_advances` row | Single row per transaction in every write this schema defines (one advance per operation); no multi-row case exists |
| 4 | Active reconciliation uniqueness, inverse-reconciliation status regression | `treasury_ledger_entries` row | When multiple entries are locked (e.g. every entry for a document/leg on regression): order by `id` ascending |
| 5 | Financial-document posting-source selection, Case B equality + immutability freeze, structural eligibility gate, §2.2's creation-time reversal checks including the no-reversal-of-reversal rule and the `via_route`-original full-target-completion precondition (check #6), §2.2a's branched atomic status-flip, §2.2b's dependent-state coupling, §2.2d's advance-reversal precondition, direction/linkage rules, expense approval gate | `treasury_financial_documents` row | When two document rows are locked together (an original + its reversal): lower `id` first, independent of which is "original" vs. "reversal" |
| 6 | Route-leg custody, full-target endpoint-aware completion, atomic leg-reversal/ledger-reversal coupling, the `via_route` reversal-completion trigger (§2.2a), the `via_route` `advance_return`-settlement-completion trigger (§7.6), the `via_route`-original full-target-completion read for reversal creation (§2.2 check #6) | Parent `treasury_payment_routes` row | Single row per transaction in every write this schema defines (a reversal's own route is always a distinct row from the original's, and the original's route, if any, is never re-locked by a later reversal-completion write since it already reached `completed` earlier); if a future extension ever locks two route rows together, lower `id` first |

**Worked orderings, restated for the transactions this round's fixes touch, to make the corrected order unambiguous:**
- **Advance posting (§7.5):** 3 → 5.
- **Advance-return posting, `direct` (§7.6):** 3 → 5.
- **Advance-return posting, `via_route` completion (§7.6):** 3 → 5 → 6.
- **Reversal creation against a `via_route` original (§2.2 check #6):** 6 (read the original's route completion state) → 5 (insert the reversal row).
- **Reversal completion, `direct`, original `document_type = advance`:** 3 (§2.2d's precondition check + §2.2b's closure) → 5 (lower id first, original+reversal).
- **Reversal completion, `direct`, original `document_type = expense` with cost allocations spanning both cost-source tables:** 2 (both rows, `(source_table, id)` order) → 5.
- **Reversal completion, `via_route`, original `document_type = advance_return`:** 3 (§2.2b's `cash_return`/`reverse` coupling touches the advance) → 5 (lower id first) → 6 (the reversal's own route).
- **Route-leg settlement under Case A (§4.2a):** 1 → 6.

No transaction in this schema ever acquires a higher-numbered class before a lower-numbered one.

---

## 12. `treasury_reconciliations` and `treasury_reconciliation_entries`

**`treasury_reconciliations`**: `id`, `tenant_id`, `wallet_id` (composite FK), `reconciliation_type`, `external_reference`, `reconciled_at`, `reconciled_by`, timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_reconciliation_entries`**: `id`, `tenant_id`, `reconciliation_id` (composite FK), `ledger_entry_id` (composite FK), `direction` (`apply`|`reverse`), `reverses_reconciliation_entry_id` (nullable self-ref, composite), `actor_id`, `created_at`. `UNIQUE(reverses_reconciliation_entry_id)`. **Unique: `(tenant_id, id)`.**

### 12.1 `posted_unreconciled → posted_reconciled`
`direct`: every ledger entry for the document has an active `apply` reconciliation-entry row. `via_route`: linked route `status = completed` (§4.3) **and** every leg-sourced ledger entry has an active `apply` reconciliation-entry row.

### 12.2 Inverse reconciliation lifecycle
A `direction = reverse` reconciliation-entry that breaks §12.1's completeness condition regresses the document's `status` back to `posted_unreconciled`, atomically, in the same transaction — **unless the document's current `status = 'reversed'` (fix, this round)**, in which case the regression side-effect is suppressed entirely: the `treasury_reconciliation_entries` row still records normally (reconciliation bookkeeping for the underlying ledger entry is unaffected), but the document's own `status` does not move, ever, once it has reached `'reversed'`. This is the concrete instance of §2.1a's general rule that `reversed` is a terminal state with no write path out of it, for any reason, including a reconciliation-layer event on a ledger entry that was posted before the document was reversed. Without this exclusion, an old, pre-reversal ledger entry's reconciliation being reversed could silently un-terminal an already-`reversed` document back to `posted_unreconciled` — corrupting an economic fact that §2.2a already made permanent.

---

## 13. Composite-FK-target index requirement (12 tables)

1. `treasury_financial_parties` 2. `treasury_wallets` 3. `treasury_financial_documents` 4. `treasury_payment_routes` 5. `treasury_payment_route_legs` 6. `treasury_ledger_entries` 7. `treasury_fund_chains` 8. `treasury_advances` 9. `treasury_advance_settlements` 10. `treasury_cost_settlement_allocations` (self-FK) 11. `treasury_reconciliations` 12. `treasury_reconciliation_entries` (self-FK). `source_party_id`/`destination_party_id` are composite FKs into `treasury_financial_parties`, table 1 — no new table.

---

## 14. Tier B — existing-table FK tenant/project rules

| Reference | Existence | Tenant/project match |
|---|---|---|
| `treasury_payment_routes.linked_contract_payment_id` | DB-enforced | Same `tenant_id`; same project via `Contract.project_id` = route's own `project_id` |
| `treasury_cost_settlement_allocations.cost_source_contract_expense_id` | DB-enforced | Same `tenant_id`; same project via the expense's owning `Contract.project_id`, matching the allocation's own Treasury-side parent project |
| `treasury_cost_settlement_allocations.cost_source_material_receipt_line_id` | DB-enforced | Same rule as above |

---

## 15. Treasury-internal same-project integrity (8 rows)

`treasury_financial_parties` carries no `project_id` — `source_party_id`/`destination_party_id` have no project-match rule to add, only the tenant match already DB-enforced by their composite FK shape.

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

## 16. Exact table inventory and migration order (14 tables)

1. `treasury_financial_parties` 2. `treasury_wallets` 3. `treasury_financial_documents` 4. `treasury_payment_routes` 5. `treasury_payment_route_legs` 6. `treasury_ledger_entries` 7. `treasury_fund_chains` 8. `treasury_advances` 9. `treasury_advance_settlements` 10. `treasury_cost_settlement_allocations` 11. `treasury_expense_approvals` 12. `treasury_reconciliations` 13. `treasury_fund_chain_members` 14. `treasury_reconciliation_entries`. **No migration file exists yet.** This round adds **zero new tables, zero new columns, zero new indexes** — every correction (§0) is a write-path/lock-order/completion-timing/precondition/formula rule over columns and indexes that already existed as of v15. The one schema-level addition this round is **7 new `CHECK (> 0)` constraints** (§2.1b) on existing amount columns across existing tables — not a new column, index, or table.

---

## 17. Verification against every held-constant item

- A3/A4-a/A.5/B2/B2-T/C/D: unchanged, not reopened. A4-a exclusion held. D — no `ReportPageController::cashflow()` edit: none of this round's fixes touch that controller. **C — immutable posting**: was under-specified in v15 (per-table freeze rules with no general statement); now explicit and general (§2.1a) — this round's correction *restores* a held-constant item, it does not relax or narrow it.
- B2-T no-second-`ContractPayment`-fact: unaffected — this round adds no new read or write against `ContractPayment` beyond the already-existing §4.2a gate, restated verbatim.
- 14-table count, 12 composite-FK targets, source/destination party provenance, exact endpoint reversal shape, explicit Case A endpoint, full-target completion predicate, posting matrix, leg-reversal atomicity, many-to-many cost allocation, approval/reconciliation lifecycle, Tier-B/project isolation, MySQL/SQLite locking, zero existing-table/data migration: all restated, all unchanged in substance from Revision 15.
- Correction #1 (§2.2d math) verified as arithmetic-only: no precondition became looser or stricter in *kind* — a net that was truly zero under the correct formula was always zero under any correct reading of "everything's been reversed"; only the (buggy) v15 formula could produce a wrong non-zero/zero classification. The corrected formula is now provably identical in form to §6.5's own formula, eliminating the possibility of the two ever disagreeing again.
- Correction #2 (§7.4a) verified as clarifying, not behavior-changing: §7.7 already stated `cash_return`/`reverse` rows are document-coupled-only; §7.4a makes the *contrast* with `approved_expense`'s manual path explicit and corrects the audit narrative (18.9-18.10) that had glossed over it.
- Correction #3 (§2.1a) verified as additive-only: a general statement subsuming every specific freeze rule already present (§2.1, §4.1), plus the first-ever explicit closed status-transition graph — no transition that was previously reachable becomes newly reachable, and no transition that was previously reachable becomes newly blocked, except via correction #5's exclusion (which is itself a closing, not an opening).
- Correction #4 (§2.2 check #6) verified as strictly additive: a new creation-time rejection case for `via_route` originals whose route has not reached `completed` — every reversal that was valid to create against a `direct` original, or against a `via_route` original whose route was already `completed`, remains exactly as valid as before.
- Correction #5 (§12.2 exclusion) verified as strictly additive: the only behavior removed is the one specific case (regression while `status='reversed'`) that correction #3's general terminal-state rule already says must never happen — every other regression case (18.15's) is byte-for-byte unchanged.
- Case-B scenario correction (§18.12): narrative-only — no rule text needed to change, since §4's `UNIQUE(linked_financial_document_id)` and §4.1 Case B's binding invariant already prohibited a second route; only the audit's prose was wrong, now corrected to match the rules that were always there.
- Positive-magnitude constraints (§2.1b): verified additive-only across all 7 amount columns — no valid posted row in any prior round's scenario ever had a zero or negative amount, so no previously-valid write becomes invalid.
- No external-destination leg representation designed this round (unchanged out-of-scope item).

---

## 18. Closure audit

Simulated against the fixed design above. Each scenario states: **canonical fact**, **ledger effect**, **cost effect**, **advance outstanding effect**, **route/custody effect**, **reversal effect**, **final reconstructible state**.

**18.1 Funding, direct.** Owner contributes 100 to Wallet W. Canonical fact: `owner_contribution` document, `source_party_id = Owner`, `destination_wallet_id = W`, posts `direct`. Ledger: one `credit` W +100. Cost: n/a. Advance: n/a. Route: n/a. Reversal: n/a. Final state: `net_custody(W) = 100`, document `posted_unreconciled`.

**18.2 Funding, via A→C→Y.** `ContractPayment` P (amount 100, `status=paid`, `paid_at` set) routes through custody wallet C to final wallet Y. Canonical fact: one Case A route, `total_allocated_amount = 100`, `expected_destination_wallet_id = Y`, two legs (A→C, C→Y). Ledger: leg 1 posts `debit A / credit C` 100 (blocked until §4.2a's conjunction holds); leg 2 posts `debit C / credit Y` 100. Cost/Advance: n/a. Route: both legs `settled`; §4.3 evaluates `net_custody(Y) = 100`, `net_custody(C) = 0` (100 in, 100 out) → `completed`. Reversal: n/a. Final state: allocation across P's routes = 100 <= P.amount = 100 (Case A conservation, §4.1); C→Y is not a second 100 — it is the same allocated 100 moving one hop further, matching §B2-T.

**18.3 Partial routes, 40/100.** Same P=100, one route with `total_allocated_amount = 40`. Legs settle 40 to Y. §4.3: `net_custody(Y) = 40 ≠ total_allocated_amount(40)`? — here `total_allocated_amount` for *this* route is 40, so `net_custody(Y)=40=40` → this route reaches `completed` at 40 (it was only ever asked to move 40). A second route for the remaining 60 is a separate row; combined `SUM(total_allocated_amount) = 40+60=100 <= 100` (§4.1 Case A, checked across all of P's routes). Final state: two Case A routes, each independently completable, jointly conserving against P.amount.

**18.4 Internal transfer and reversal.** `internal_transfer` X 50, W1→W2, `direct`. Ledger: `debit W1 50 / credit W2 50`. Reversal created: swapped endpoints (`source=W2`, `destination=W1`), amount 50, passes §2.2's no-reversal-of-reversal check (`X.document_type = internal_transfer ≠ reversal` → allowed). Completes `direct` → same-transaction: X.status→`reversed` (§2.2a), reversal's own entries post `debit W2 50 / credit W1 50`. §2.2b: `internal_transfer` has no dependent state → no additional coupled write. Final state: `net_custody(W1)=0, net_custody(W2)=0` net of the pair; X is `reversed`; the reversal document itself is now permanently un-reversable (§2.2).

**18.5 Expense settling multiple costs, and reversal.** `expense` document E=90, `destination_party_id`=Supplier, `direct`. Cost allocations: `apply` 50 → `contract_expenses` row CE1, `apply` 40 → `material_receipt_lines` row ML1, both `financial_document_id=E`. §6.2: `net_allocation(E) = 90 = E.amount` ✓. §6.3 checked per cost-source under item 2's lock, order `(contract_expenses, CE1.id)` before `(material_receipt_lines, ML1.id)`. Reversal of E created (swap: `source_party_id`=Supplier, `source_wallet_id`=NULL→ wait: E was party-as-destination, `source_wallet_id` set, `destination_party_id`=Supplier; reversal swaps to `source_party_id`=Supplier, `destination_wallet_id`=E's `source_wallet_id`), completes `direct`. Same transaction: E.status→`reversed`; §2.2b `expense` branch fires under item 2's lock (both CE1/ML1 rows, ordered) — two new `reverse` rows created, `reverses_allocation_id`→ CE1-apply/ML1-apply respectively, `financial_document_id = reversal.id` (§2.2b). Final state: `net_allocation(CE1)=0`, `net_allocation(ML1)=0`, both restored to zero outstanding cost-settlement; E `reversed`; every allocation row traceable to the reversal that undid it.

**18.6 One cost paid in several installments.** Cost source CE2 (canonical incurred 200) settled by three `expense` documents, 80+70+50=200, each its own `apply` row, `financial_document_id` = its own document. §6.3: running `net_allocation(CE2)` after each: 80, 150, 200 — never exceeds 200. No reversal in this scenario. Final state: `net_allocation(CE2)=200=canonical_incurred_amount(CE2)`, fully settled, three independently-reversable `apply` rows (each traceable to its own installment document), none of them coupled to each other.

**18.7 Advance 100, no settlements, then full reversal.** `advance` A=100, `direct` (always direct, §4.1), posts atomically with `treasury_advances` row creation (§7.5, lock order 3→5). `outstanding(A) = 100 - 0 + 0 = 100` (§6.5, no settlements). Reversal of the `advance` document created (passes §2.2's no-reversal-of-reversal check). At completion: §2.2d checks active settlement net = `0 - 0 = 0` (nothing was ever applied) → **precondition holds, reversal is allowed to complete.** Same transaction: original.status→`reversed`; §2.2b `advance` branch: closes to further applies; §6.5's override: `outstanding(A) = 0` from this moment (override, not `100-0+0=100`). Final state: advance economically inactive, `outstanding=0`, no phantom positive balance despite zero settlements ever having existed — this is exactly the case v15's Owner finding (blocker 2) targeted, and it resolves correctly under this round's corrected §2.2d formula too (`0-0=0`, unaffected by the correction since there was nothing to double-subtract here).

**18.8 Advance 100 + `approved_expense` 30, attempted full reversal.** After A=100 posts, an `approved_expense` settlement `apply` 30 is recorded against it (cost allocation created against some cost source, `advance_settlement_id` set). `outstanding(A) = 100-30+0=70`. Reversal of A created; at completion, §2.2d evaluates active net = `30-0=30 ≠ 0` → **completion rejected.** The reversal document remains in existence but cannot complete (`direct` branch: its own ledger entries are never created, so the status flip never fires — the write that would post it is simply refused). Final state: A still active, `outstanding(A)=70`, unchanged; no partial/inconsistent reversal state is ever persisted, since §2.2a's flip and the reversal's own posting are the same atomic write — refusing one refuses both.

**18.9 Advance 100 + `cash_return` 20, attempted full reversal.** `cash_return` `apply` 20 created via an `advance_return` document R2=20 (`source_party_id=P`, `direct`, itself posted per §7.6). `outstanding(A)=100-20+0=80`. Reversal of A attempted: §2.2d net (corrected formula, §2.2d) = `SUM(apply)-SUM(reverse) = 20-0=20≠0` → **completion rejected**, same mechanism as 18.8, but note the settlement type: per §7.4a, this `cash_return` `apply` can only ever be cancelled by reversing **document R2** — there is no standalone settlement-level action available for it, unlike 18.8's `approved_expense`.

**18.10 Reverse those settlements, then full advance reversal.** Continuing 18.8+18.9 — the two settlement types are unwound through their two distinct mechanisms (§7.4a), never interchangeably: (a) the `approved_expense` 30 is reversed **manually, standalone** — a new `reverse` row, `reverses_settlement_id`→ the apply row (§7.4); `net_allocation` on its cost source also unwinds via the paired `treasury_cost_settlement_allocations reverse` row. (b) the `cash_return` 20 is **not** reversed directly — instead, a `reversal` document targeting **R2** (the `advance_return` document, not the advance) is created and completes `direct`: R2.status→`reversed`; §2.2b's `advance_return` branch fires under the item-3 lock, creating the compensating `cash_return`/`reverse` row automatically (`reverses_settlement_id`→R2's apply row, `amount=20`, `financial_document_id`=R2's reversal document's id, per §2.2b). Only after both (a) and (b) land does active net over `treasury_advance_settlements` for A reach `(30-30)+(20-20)=0`. Reversal of A (created earlier or fresh) now completes: §2.2d holds → completes; `outstanding(A)=0` (§6.5 override). Final state: identical terminal state to 18.7, reached via a different, fully auditable path — every one of the four settlement rows (2 apply + 2 reverse), both cost-source allocations, and R2's own now-`reversed` document remain in the ledger, non-destructively; A's own reversal never had to (and could not have) directly touched R2's settlement row.

**18.11 `advance_return` direct.** Advance A=100 (party P) active, `outstanding=100`. `advance_return` R=100, `source_party_id=P`, `destination_wallet_id=W`, `direct`. Posts: §7.6 direct branch — same-transaction `credit W 100` + `treasury_advance_settlements` row (`cash_return`/`apply`, `financial_document_id=R`, `amount=100`, party-matched against A). `outstanding(A)=100-100+0=0`. Final state: A fully returned, R `posted_unreconciled`, one clean apply row.

**18.12 `advance_return` via_route, 40/100 then 100/100 (corrected, this round — Case B).** R=100 (party P, `destination_wallet_id` populated → `via_route`-eligible per §4.1) attaches **one** route under **Case B** (`linked_financial_document_id=R`) — Case B's own binding invariant (§4.1) is `route.total_allocated_amount = linked_financial_document.amount = 100`, frozen atomically at attachment, and the schema's `UNIQUE` index directly on `linked_financial_document_id` (§4) means R can never have a second route: **there is no "second route" option for a Case B document, ever** — v15's narrative offering one was itself an inconsistency, corrected here. The single route's total target is 100 from the moment of attachment; it is reached via **multiple legs on that one route**. Leg 1 settles 40 to the destination wallet (leg `settled`, `net_custody(destination)=40 ≠ total_allocated_amount=100` → route stays `partial`). Per §7.6's `via_route` branch: **no `cash_return`/`apply` row exists yet** — `outstanding(A)` still reads `100` (unaffected by the partial leg, since the settlement fact that would change it has not been created). This is the scenario the Owner's finding (v15's blocker 4) specifically flags: without the fix, an earlier design might have created the settlement row at the first leg, prematurely showing `outstanding=60`. Leg 2 settles the remaining 60 on the **same** route, bringing `net_custody(destination)=100=total_allocated_amount` → §4.3 evaluates the route to `completed`. At that moment, §7.6's `via_route` branch fires: `cash_return`/`apply` row created, `financial_document_id=R`, `amount=100`. `outstanding(A)=100-100+0=0`. Final state: one route, two legs, settlement fact and its economic effect on `outstanding` both land at the same moment as that single route's real full-target completion — never earlier, and never via a second route that Case B structurally cannot have.

**18.13 Reversal of `advance_return`.** Continuing 18.11 (R=100, direct, already settled `cash_return`/`apply`, `outstanding(A)=0`). Reversal of R created (swap: `source_wallet_id=W`, `destination_party_id=P`), passes §2.2's no-reversal-of-reversal check (`R.document_type=advance_return≠reversal`). Completes `direct`: same transaction — R.status→`reversed`; §2.2b `advance_return` branch fires (item 3 lock, before item 5 per §11): the `cash_return`/`apply` row gets a compensating `reverse` row, `reverses_settlement_id`→apply row's id, `amount=100`, **`financial_document_id = reversal_document.id`** (§2.2b). `outstanding(A)` recomputes: `100 - (100-100) + 0 = 100` — since active `apply` net is now `100-100=0` for the cash_return leg (reverse cancels the apply), `outstanding` reverts to the full `100`, correctly reflecting that the return itself was undone and the advance is once again fully outstanding (unless A's own origin has separately been reversed, in which case §6.5's override still forces `0` regardless — the two rules never conflict since they key off different documents' status). Final state: R `reversed`, its settlement fact durably reversed with full auditability (§2.2b/§7.7's three bound fields), A's outstanding correctly restored.

**18.14 Attempted reversal-of-reversal.** Take 18.4's completed reversal document (call it X'). Attempt to create a new reversal targeting X' (`reversed_document_id = X'.id`). §2.2 check #2 (unchanged from v15): `X'.document_type = 'reversal'` → **creation is rejected outright** — no row is ever persisted, no `reversed_document_id` link is ever set. The only path to correcting X' (if X' itself were wrong) is §2.2c: set `X'.replacement_document_id` to a fresh, independently-valid corrective document (e.g., a new `internal_transfer` moving the funds back to where X' incorrectly left them) — that corrective document undergoes its own full creation/posting checks from scratch, with no dependent-state replay from X'. Final state: no reversal ever targets a `reversal` document; the schema-wide "a reversal is a leaf" invariant (§9) holds without exception.

**18.15 Reconciliation and reconciliation reversal, not yet document-reversed.** A posted `direct` `funding` document's single `credit` entry gets a `treasury_reconciliation_entries` row (`direction=apply`) under a `treasury_reconciliations` batch for its wallet. §12.1: document → `posted_reconciled`. A `direction=reverse` reconciliation-entry is later created (`reverses_reconciliation_entry_id`→ the apply row) — §12.2: since the document's status is `posted_reconciled` (not `reversed`), the regression fires normally: document → `posted_unreconciled`, atomically. This is fully orthogonal to every fix in this round (document-level reversal, §2.2, is a different mechanism from reconciliation, §12, per §5's four-mechanism disambiguation) — no interaction, no shared lock beyond item 4 (`treasury_ledger_entries`).

**18.15a Reconciliation reversal on an already economically-reversed document (new, this round — proves the §12.2 exclusion).** Take 18.4's `internal_transfer` X, already reconciled (`posted_reconciled`) *before* it was later reversed (18.4: X → `reversed` via its own `reversal` document completing). X's original `credit`/`debit` entries still each carry an active `apply` reconciliation-entry row from before the reversal. Someone now creates a `direction=reverse` reconciliation-entry against one of those old entries (`reverses_reconciliation_entry_id`→ the apply row) — perhaps a delayed bank-statement correction. §12.2 (corrected, this round): the `treasury_reconciliation_entries` row is still created normally (the reconciliation bookkeeping fact is recorded), **but** since `X.status = 'reversed'` at that moment, the regression side-effect is suppressed — X's `status` does **not** move back to `posted_unreconciled`. Final state: the reconciliation-entry table gained a row (an honest record of the correction event), but `X.status` remains permanently `reversed`, consistent with §2.1a's closed transition graph — the terminal fact §2.2a established is never disturbed by a later event in an unrelated subsystem (reconciliation).

**18.15b Reversal creation rejected against a partial `via_route` original (new, this round — proves §2.2 check #6).** `funding` document F=100, `via_route`-eligible (`destination_wallet_id` set), attaches a Case B route, `total_allocated_amount=100`. Leg 1 settles 40; `F.status` has already progressed to `posted_unreconciled` per §5a's progressive per-leg posting, while the route itself is still `status='partial'` (`net_custody(destination)=40≠100`). Someone attempts to create a `reversal` document targeting F (`reversed_document_id=F.id`). §2.2 check #5 alone would pass (`F.status IN (posted_unreconciled, posted_reconciled)` — it is `posted_unreconciled`). **§2.2 check #6 (this round's fix) additionally evaluates:** `F.posting_path='via_route'` → its route must have `status='completed'`; it is `'partial'` → **creation is rejected**. No reversal row is ever persisted against F until its route reaches full-target `completed` (leg 2 settles the remaining 60). Final state: no reversal ever exists against an economically-incomplete `via_route` original; once the route completes, the identical reversal-creation request succeeds normally under checks #1-#7.

**18.16 Concurrent operations exercising every multi-row lock combination.** (a) Two transactions racing to post an `advance` and settle it via `approved_expense` in the same moment: the `treasury_advances` row lock (item 3) serializes them — the settlement's own `apply`-creation check (§7.4, under item 3) cannot proceed until the advance-posting transaction (also item 3, then item 5) commits or rolls back. (b) A transaction reversing an `advance` (item 3 → item 5) racing a transaction creating a fresh `approved_expense` apply against the same advance (item 3): whichever acquires the item-3 lock first wins; if the settlement commits first, §2.2d's re-check (under the same item-3 lock, on the reversal's later attempt) sees the new nonzero net and rejects completion — no race window, because §2.2d's check and the settlement's own write are both serialized through the identical row lock. (c) A `via_route` reversal of an `advance_return` (items 3→5→6) racing a new leg settling on that same route (item 6, plus item 1 if Case A): the route lock (item 6, acquired last by the reversal-completion write, after 3 and 5) means the leg-settlement transaction and the reversal-completion transaction cannot interleave — either the leg settles first (and is then part of what the reversal's §4.3 full-target check must account for) or the reversal's route-completion check runs first and the leg transaction waits. (d) A reversal-*creation* request against a `via_route` original (§2.2 check #6, item 6 read) racing a leg settling on that same route to reach full-target `completed` (item 6, write): the shared item-6 lock serializes them — either the leg-settlement transaction commits `completed` first (and the reversal-creation check then sees `completed` and passes) or the reversal-creation check runs first (sees `partial`, rejects) while the leg transaction waits its turn; no interleaving where the check reads a stale, half-updated route state is possible. No transaction in this schema ever needs items in any order other than ascending, so no deadlock cycle is constructible.

**18.17 Duplicate retries / idempotency.** A client retries a "post this `advance`" request after a timeout (write may or may not have committed). Retried write: `UNIQUE(originating_financial_document_id)` on `treasury_advances` (§7) rejects a second row for the same document if the first attempt succeeded; if the first attempt did not commit, the retry proceeds normally — no double-advance is ever created either way. A retried "reverse this document" request: `UNIQUE(reversed_document_id)` (§2.2) rejects a second reversal row for the same original. A retried reversal-*completion* write (e.g. a duplicate route-completion event re-firing §2.2a's `via_route` branch): the original's `status` is already `'reversed'`; a well-formed implementation checks `status <> 'reversed'` as part of the same locked write before flipping it, making the transition idempotent (and now doubly so, since §2.1a's closed transition graph makes `reversed` terminal by construction) — and even absent that check, §2.2b's coupling writes are themselves protected by `UNIQUE(reverses_allocation_id)` / `UNIQUE(reverses_settlement_id)`, so a duplicate coupling attempt fails on those constraints rather than double-reversing dependent state. Every mutation this schema defines that has an economic effect is guarded by at least one uniqueness constraint that makes a retried duplicate a no-op-or-reject, never a double-application.

**Audit conclusion:** no scenario above surfaces a further inconsistency inside the approved architecture (A3+A4-a+A.5/B2+B2-T/C/D). The fixes in §0, as specified, close every point in the Owner's Revision 15 REQUEST CHANGES decision and hold under the full scenario set, including the concurrency and idempotency cases.

---

## 19. Trạng thái và bước tiếp theo
- Nếu Owner Approve: chuẩn bị Gate 3 cho GAP-037 — vẫn chỉ là quyết định merge tài liệu.
- Nếu Owner Request changes: sẽ tạo `02-design-v17.md` (supersedes bản này).
- Nếu Owner Decline: dừng GAP-037 ở schema-proposal này.

## 20. Loại trừ phạm vi
Kế thừa nguyên vẹn từ mọi round trước: không migration file thật; không model/controller/service/route/UI/test thật; không seed/backfill; không implementation plan coi schema này là đã duyệt cho Gate 3; không Gate 3 tự suy luận; không mark PR ready; không merge PR #263; không sửa/merge/đóng PR #245 hoặc #257; không GAP-036; không Today Workspace; không sửa canonical SSOT stale metadata; không production/deployment; không thiết kế external-destination leg representation; không sửa `ReportPageController::cashflow()`.

## Decision Needed
**Resolved 2026-08-16T23:04:06+07:00 — Owner Decision: REQUEST CHANGES.** All 5 binding corrections and 3 same-revision corrections from Revision 15 confirmed closed. One remaining, narrowly-scoped concurrency boundary found: reversal-creation's own lock ordering for a `via_route` original's route (stated as item 6 before item 5 — backwards relative to the packet's own global order) plus a missing completion-time re-check that an original's route hasn't regressed from `completed` back to `partial` (via intervening leg reversal/replacement) between reversal creation and reversal completion. Architecture A3+A4-a+A.5/B2+B2-T/C/D remains approved, frozen, not reopened. Verbatim text in `decision_provenance.owner_response_reference`. **This packet (`02-design-v16.md`) is now frozen — no further content edits.** `docs/owner-decisions/GAP-037/02-design-v17.md` (self-contained) follows in the next commit.

**Agent recommendation recorded at the time this packet was submitted (superseded by the Owner's finding above — kept verbatim for the record, not restated as current):** every one of the 5 binding corrections and 3 same-revision corrections from the Owner's Revision 15 REQUEST CHANGES decision was closed in this revision (§0), and a fresh full normative-rule ↔ scenario ↔ concurrency/idempotency audit (§18, including two new scenarios — 18.15a, 18.15b — proving the two new guards) found no further material inconsistency the agent itself detected. This packet was, in the agent's assessment at the time, ready for Owner approval; the Owner's independent review found one further narrow gap.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt migration file thật hay chi tiết implementation. Owner cũng không được yêu cầu duyệt lại architecture set A3/A4-a/A.5/B2/B2-T/C/D — đã approved, không mở lại. Owner cũng không được yêu cầu duyệt overpayment/prepayment semantics, hay thiết kế external-destination leg representation (nêu là future extension point, chưa thiết kế ở đây).
