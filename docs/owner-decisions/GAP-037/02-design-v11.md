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
  recorded_at: "2026-08-16T20:18:07+07:00"
  owner_response_reference: "Owner Gate 2 Schema Proposal Revision 11 decision -- REQUEST CHANGES, recorded in-session on 2026-08-16 against reviewed PR #263 head b7a58f781b8d61c88935770136398346ca4bb24b: 'GAP-037 -- Gate 2 Schema Proposal Revision 11 -- Owner Decision: REQUEST CHANGES. Toi, Owner, yeu cau chinh sua schema proposal tai PR #263, reviewed head b7a58f781b8d61c88935770136398346ca4bb24b. Toi xac nhan hai correction cua Revision 11 da dat: Case A co explicit expected_destination_wallet_id; route completion su dung full unreduced target. Architecture A3 + A4-a + A.5 / B2 + B2-T / C / D van approved, frozen, khong mo lai. Revision 12 la consolidated closure round va phai bo sung sau binding invariants: Case A custody chi duoc post sau canonical ContractPayment thuc su paid; exact document/route-leg-to-ledger posting conservation matrix; atomic leg-reversal/ledger-reversal coupling; exact/at-most-once document-level reversal; unambiguous external-party direction plus advance/cash-return semantic binding; expense approval gate va inverse reconciliation lifecycle. Giu nguyen cac phan v11 da dat: 14-table architecture; 12 composite-FK targets; Case A explicit endpoint; full-target endpoint-aware completion; reversal eligibility from own endpoint shape; amount immutability; B2-T no second ContractPayment fact; many-to-many cost allocation; advance-settlement no-second-cash-out rule; Tier-B/project integrity; reconciliation actor; MySQL/SQLite concurrency; A4-a exclusion; D no ReportPageController::cashflow() edit; zero existing-table/data migration; #245/#257 untouched. Record REQUEST CHANGES truoc vao 02-design-v11.md, freeze v11, sau do tao self-contained 02-design-v12.md, rerun required CI va quay lai awaiting_owner. Khong suy luan schema approval hoac Gate 3 authorization.'"
  reconciliation_required: false
supersedes: docs/owner-decisions/GAP-037/02-design-v10.md
superseded_by: docs/owner-decisions/GAP-037/02-design-v12.md
timestamps:
  created_at: "2026-08-16T19:33:40+07:00"
  updated_at: "2026-08-16T20:18:07+07:00"
generated_by: agent
---

# GAP-037 — Project Treasury: Gate 2 Revision 11 — Explicit Case A Completion Endpoint + No Reversal-Reduced Target (Self-Contained)

**Status:** Gate 1 approved. Gate 2 architecture decisions **approved** (`docs/owner-decisions/GAP-037/02-design.md`, frozen) — **A3 + A4-a + A.5 / B2 + B2-T / C / D**, not reopened. Fully self-contained. Still Gate 2 — a proposal, not implementation. No migration file, model, controller, service, route, UI, or test exists or is authorized by this packet.

**Database compatibility (unchanged verification):** MySQL for production/dev (`config/database.php`, `.env.example`), SQLite for the test suite (`.env.testing`, `phpunit.xml`).

---

## 0. What changed vs. v10, at a glance

| # | Owner's finding | Fix |
|---|---|---|
| 1 | §4.3's Case A (`ContractPayment`-linked) branch fell back to "total custody across the route's wallets," justified by a "terminal wallet by construction" claim that has no actual column backing it | New column `treasury_payment_routes.expected_destination_wallet_id` (composite FK, required exactly for Case A routes) gives Case A an explicit, binding completion endpoint — same treatment as Case B's `destination_wallet_id` |
| 2 | §4.3's predicate subtracted reversed-leg amount (`total_allocated_amount - R`) from the completion target, effectively letting a reversal shrink what "done" means | `R` is removed entirely from every completion predicate. A reversed leg cancels a movement; it never reduces `total_allocated_amount`, `financial_document.amount`, or the canonical `ContractPayment` allocation. Every completion check now requires the **full, unreduced** target amount to be present at the correct endpoint. A route with a reversed leg stays `partial` until a replacement movement actually completes it, `cancelled` if the economic route itself is abandoned, or represented via a `reversal` financial document if the whole document-level movement is reversed |

---

## 1. `treasury_financial_parties` and `treasury_wallets` — unchanged from v10

**`treasury_financial_parties`**: `id`, `tenant_id`, `party_type`, `name`, `linked_account_id` (nullable), `linked_user_id` (nullable), timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_wallets`**: `id`, `tenant_id`, `project_id` (nullable), `wallet_type`, `name`, `custodian_party_id` (nullable, composite FK), timestamps. **Unique: `(tenant_id, id)`.**

---

## 2. `treasury_financial_documents`

`id`, `tenant_id`, `project_id`, `document_type` (funding|internal_transfer|expense|owner_contribution|advance|advance_return|reversal|adjustment), `status` (draft|submitted|approved|rejected|posted_unreconciled|posted_reconciled|reversed), `posting_path` (nullable enum: `direct`|`via_route`, set exactly once), `amount`, `source_wallet_id` (nullable, composite FK), `destination_wallet_id` (nullable, composite FK), `counterparty_id` (nullable, composite FK), `description`, `created_by`, `approved_by` (nullable), `posted_at` (nullable), `reversed_document_id` (nullable self-ref), `replacement_document_id` (nullable self-ref), timestamps. **Unique: `(tenant_id, id)`.**

A `reversal` document is a first-class row in this same table (`document_type = reversal`, `reversed_document_id` set) — it carries its **own** `source_wallet_id`/`destination_wallet_id`/`counterparty_id` describing its own actual money movement, not a copy of the original document's fields. Eligibility (§4.1) and completion (§4.3) both read these fields directly off the reversal row itself.

### 2.1 Posting-path freeze + amount immutability + eligibility gate — unchanged from v9/v10
A route may only attach (§4) while `posting_path IS NULL` and `status` is unposted, and only for a document/endpoint-shape combination that is `via_route`-eligible per §4.1's structural rule. Attaching sets `posting_path = via_route` and simultaneously freezes `amount` as immutable — both writes occur in the same transaction, under the same lock (§11 item 5). If no route is ever attached and the document posts directly, `posting_path` locks to `direct` at that moment; `amount` is fixed at whichever of these two transitions occurs first.

Once `posting_path` is non-null (either value), `treasury_financial_documents.amount` has no update path — immutable, full stop. **Fix #2 reinforces this from the other direction: nothing that happens to a route afterward (including a leg reversal) is ever permitted to change what `amount` a completed route must have delivered.**

---

## 3. The typed-nullable-FK pattern — unchanged principle

Every former polymorphic reference is N nullable typed FK columns + a `CHECK` requiring exactly one non-null: `treasury_payment_routes` (§4), `treasury_ledger_entries` (§5), `treasury_cost_settlement_allocations` (§7), `treasury_fund_chain_members` (§8).

---

## 4. `treasury_payment_routes` and `treasury_payment_route_legs`

**`treasury_payment_routes`**: `id`, `tenant_id`, `project_id`, `total_allocated_amount`, `status` (planned|partial|completed|cancelled), `linked_financial_document_id` (nullable, composite FK), `linked_contract_payment_id` (nullable, single-column FK → `contract_payments(id)`), **`expected_destination_wallet_id` (nullable, composite FK — new this round, fix #1)**, timestamps. `CHECK ((linked_financial_document_id IS NULL) != (linked_contract_payment_id IS NULL))`. **New `CHECK`: `(linked_contract_payment_id IS NOT NULL) = (expected_destination_wallet_id IS NOT NULL)`** — required exactly when the route is Case A, forbidden (must stay `NULL`) when the route is Case B, since a Case B route's endpoint is already fully and unambiguously determined by its linked document's own `source_wallet_id`/`destination_wallet_id` (§4.1) — a second, independently-settable endpoint field on the route itself would risk drifting out of sync with the document it's supposed to describe, with no invariant tying the two together. Unique index directly on `linked_financial_document_id`. **Unique: `(tenant_id, id)`.**

### 4.1 Conservation, immutability, and eligibility

**Case A — `linked_contract_payment_id`:** `SUM(total_allocated_amount) <= ContractPayment.amount` across a `ContractPayment`'s routes. Lock the `contract_payments` row (§11, item 1). `total_allocated_amount` is immutable post-creation. **New this round (fix #1):** `expected_destination_wallet_id` is set once, at route creation, alongside `total_allocated_amount`, and is likewise immutable thereafter — it is the binding, schema-backed completion endpoint for this route (§4.3), replacing v10's unmodeled "terminal wallet by construction" claim. Tenant/project/wallet-compatibility rule: same as every other wallet reference in §15 — `expected_destination_wallet_id`'s `project_id` must equal the route's own `project_id`, except company/shared wallets (`project_id IS NULL`), which are compatible with any project.

**Case B — `linked_financial_document_id` (unchanged from v9/v10):**
```
route.total_allocated_amount = linked_financial_document.amount
```
Both sides frozen together, atomically, the moment this equality is established (route attachment) — no update path for either afterward. Checked and locked in the same transaction as §2.1's `posting_path` freeze (§11, item 5).

**`via_route` eligibility — structural, unchanged from v10 (Revision 10's fix #2, held constant this round):**

`via_route` is eligible for a document (any `document_type`, including `reversal`) if and only if its own populated fields describe a shape the current leg model can represent:

| Document's own populated endpoint fields | `via_route` eligible? | Why |
|---|---|---|
| `destination_wallet_id` set, `counterparty_id` NULL, `source_wallet_id` NULL | **Yes** | Wallet-terminating inbound shape |
| `source_wallet_id` set AND `destination_wallet_id` set, `counterparty_id` NULL | **Yes** | `internal_transfer`-shaped |
| `counterparty_id` set (regardless of whether `source_wallet_id` is also set) | **No — `direct` only** | External `financial_party` endpoint, not representable by a wallet-terminating leg |
| `source_wallet_id` set, `destination_wallet_id` NULL, `counterparty_id` NULL (decrease-type `adjustment` shape) | **No — `direct` only** | No trackable wallet endpoint |

Applied uniformly to every `document_type` including `reversal`, classified by the reversal row's **own** populated fields, never by looking up the reversed document's type. Case A routes have no `treasury_financial_documents` row of their own to classify this way — their eligibility is simply "any `ContractPayment` route may use `via_route`," gated instead by fix #1's explicit `expected_destination_wallet_id` requirement.

Enforced at the same moment as §2.1's attachment check, under the same lock (§11, item 5).

**`treasury_payment_route_legs`**: `id`, `tenant_id`, `payment_route_id` (composite FK), `sequence_no`, `from_wallet_id` (nullable, composite FK), `to_wallet_id` (composite FK), `amount`, `status` (in_transit|settled|reversed), `occurred_at`, timestamps. **Unique: `(tenant_id, id)`.**

### 4.2 Route-leg custody — unchanged from v7/v8/v9/v10
Lock the parent route (§11, item 6). Wallet-backed leg: validate against §5.3's balance formula. External-entry leg (`from_wallet_id IS NULL`): bounded by remaining `total_allocated_amount`. Both persist leg + ledger entries atomically under the lock.

### 4.3 Route-completion predicate — endpoint-aware (v10) AND full-target (fix #2, revised this round)

Define, for any wallet `w` and this route's legs: `net_custody(w) = SUM(credit) - SUM(debit)` over `treasury_ledger_entries` rows whose `source_payment_route_leg_id` belongs to a leg of this route and whose `wallet_id = w` (the same balance formula as §5.3, scoped to this route).

**Fix #2 — the completion target is never reduced by a reversal.** v10 defined `R` as "the amount of `total_allocated_amount` returned via reversed legs" and subtracted it from the target every branch checked against. This is removed entirely: reversing a leg cancels that one movement — it has no effect on `total_allocated_amount`, on `linked_financial_document.amount`, or on the canonical `ContractPayment` allocation this route represents. Every branch below checks against the **full, original, unreduced** target. If a leg is reversed because it moved money incorrectly, the route's `net_custody` at the intended endpoint necessarily falls short of the full target, and the route correctly remains `status = partial` until a replacement leg brings the endpoint's `net_custody` up to the full amount. This is the only mechanism for "finishing" a route that had a reversed leg — there is no separate reduced-target success path.

- **Precondition, all cases:** every leg belonging to the route has `status IN ('settled', 'reversed')` — none remain `in_transit`.
- **Wallet-terminating inbound shape** (Case B, `linked_financial_document.destination_wallet_id` set, `source_wallet_id` NULL): `completed` requires `net_custody(destination_wallet_id) = total_allocated_amount` (full amount, no `R`), **and** `net_custody(w) = 0` for every other wallet the route's legs have touched.
- **`internal_transfer` shape** (Case B, both `source_wallet_id` and `destination_wallet_id` set): `completed` requires `net_custody(source_wallet_id) = -total_allocated_amount`, `net_custody(destination_wallet_id) = +total_allocated_amount` (full amount, no `R`), and `net_custody(w) = 0` for every other (intermediary) wallet touched.
- **Case A (`ContractPayment`-linked, fix #1's new explicit endpoint):** `completed` requires `net_custody(expected_destination_wallet_id) = total_allocated_amount` (full amount, no `R`), and `net_custody(w) = 0` for every other wallet the route's legs have touched. This replaces v10's fallback generic predicate — Case A now has exactly the same shape of check as the two Case B branches, just keyed off the route's own `expected_destination_wallet_id` column instead of a linked document's field.

All three branches are checked under the same parent-route lock as §4.2 (§11, item 6). `status` cannot be set to `completed` by simple assignment while the applicable branch's conditions fail.

**Disposition when a route cannot reach its full target:** if the underlying economic route itself is abandoned (not merely one wrong leg awaiting a correcting replacement), the route's terminal state is `cancelled`, not a reduced-target `completed`. If the whole document-level movement this route represents is being undone, that is expressed at the document layer via a `reversal` financial document (§2, §9) against the original — the route itself is never retroactively "completed" for less than its full recorded target.

---

## 5. `treasury_ledger_entries` — unchanged from v9/v10

`id`, `tenant_id`, `source_financial_document_id` (nullable, composite FK), `source_payment_route_leg_id` (nullable, composite FK), `wallet_id` (composite FK), `direction` (`debit`\|`credit`), `amount`, `entry_type`, `posted_at`, `reversal_of_entry_id` (nullable self-ref, composite), `created_at`. `CHECK` (exactly one source set). Idempotency via generated `original_posting_key` (excludes reversals). `wallet_balance = SUM(credit) - SUM(debit)`. Reversal: same wallet/source/amount, opposite direction, `UNIQUE(reversal_of_entry_id)`, no reverse-of-reverse.

Index: `(source_financial_document_id)`, `(source_payment_route_leg_id)`, `(wallet_id, posted_at)`, `UNIQUE(reversal_of_entry_id)`, `UNIQUE(original_posting_key)`. **Unique: `(tenant_id, id)`.**

---

## 6. Settlement conservation — unchanged from v7/v8/v9/v10

**6.1** `net_allocation(cost_source) = SUM(apply) - SUM(reverse)`. **6.2** Direct-expense: `net_allocation(financial_document) = financial_document.amount`. **6.3** `0 <= net_allocation(cost_source) <= canonical_incurred_amount(cost_source)`. **6.5** `0 <= advance.amount - SUM(apply) + SUM(reverse) <= advance.amount`. **6.6** Violating writes rejected. **6.7** Material prepayment via `treasury_advances`, never a nonexistent-cost-record allocation.

---

## 7. `treasury_cost_settlement_allocations`, `treasury_advances`, `treasury_advance_settlements` — unchanged from v7/v8/v9/v10

**`treasury_cost_settlement_allocations`**: `id`, `tenant_id`, `financial_document_id` (nullable, composite FK), `advance_settlement_id` (nullable, composite FK), `cost_source_contract_expense_id` (nullable, single-column FK), `cost_source_material_receipt_line_id` (nullable, single-column FK), `direction` (`apply`\|`reverse`), `allocated_amount`, `reverses_allocation_id` (nullable self-ref, composite), `created_at`. Two `CHECK`s. `UNIQUE(reverses_allocation_id)`. **Unique: `(tenant_id, id)`.**

**`treasury_advances`**: `id`, `tenant_id`, `project_id`, `financial_party_id` (composite FK), `originating_financial_document_id` (composite FK), `amount`, timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_advance_settlements`**: `id`, `tenant_id`, `advance_id` (composite FK), `settlement_type` (`approved_expense`\|`cash_return`), `direction` (`apply`\|`reverse`), `amount`, `financial_document_id` (nullable, composite FK), `reverses_settlement_id` (nullable self-ref, composite), `created_at`. `UNIQUE(reverses_settlement_id)`. **Unique: `(tenant_id, id)`.**

### 7.4 Advance-settlement completeness (unchanged from v7)
`apply`: own allocations sum to its amount. `reverse`: atomically creates compensating allocations for every still-active original, complete 1:1 coverage, own completeness check.

---

## 8. `treasury_fund_chains` and `treasury_fund_chain_members` — unchanged

**`treasury_fund_chains`**: `id`, `tenant_id`, `project_id`, `chain_reference`, `description`, timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_fund_chain_members`**: `id`, `tenant_id`, `fund_chain_id` (composite FK), `member_financial_document_id` (nullable, composite FK), `member_payment_route_id` (nullable, composite FK), timestamps. `CHECK` (exactly one member set). Two separate unique indexes.

---

## 9. Reversal invariants — unchanged

`treasury_ledger_entries`: own debit/credit vocabulary. The three event-log tables: `apply`/`reverse`, same-subject, exact-amount, at-most-once, no reverse-of-reverse.

Distinct from §4's route/leg reversal semantics: this section governs the internal apply/reverse *event log* mechanics (unchanged). §4.3 governs whether and how a route with a **reversed leg** can still reach `completed` (fix #2: only via a full-target replacement movement, never via a reduced target).

---

## 10. `treasury_expense_approvals` — unchanged

`id`, `tenant_id`, `financial_document_id` (composite FK), `event`, `from_status`, `to_status`, `actor_id`, `note` (nullable), `context` (nullable), `created_at`.

---

## 11. Concurrency — unchanged list, items 1 and 6 now also cover this round's revised checks

| # | Check | Row/subject locked |
|---|---|---|
| 1 | `ContractPayment` route-allocation conservation (§4.1 Case A) **and, new this round, the route's `expected_destination_wallet_id` immutability/compatibility check (fix #1)** | `contract_payments` row |
| 2 | Cost over-settlement cap (§6.3) | `contract_expenses`/`material_receipt_lines` row |
| 3 | Advance outstanding cap + settlement completeness (§7.4) | `treasury_advances` row |
| 4 | Active reconciliation uniqueness (§12) | `treasury_ledger_entries` row |
| 5 | Financial-document posting-source selection (§2.1), §4.1 Case B equality + immutability freeze, and §4.1's structural eligibility gate | `treasury_financial_documents` row |
| 6 | Route-leg custody (§4.2) and **the full-target, endpoint-aware route-completion predicate across all three branches, including the new Case A branch (§4.3, fix #1 + fix #2)** | Parent `treasury_payment_routes` row |

MySQL: `SELECT ... FOR UPDATE`. SQLite (test suite): `BEGIN IMMEDIATE`.

---

## 12. `treasury_reconciliations` and `treasury_reconciliation_entries` — unchanged from v9/v10 (references §4.3's revised predicate)

**`treasury_reconciliations`**: `id`, `tenant_id`, `wallet_id` (composite FK), `reconciliation_type`, `external_reference`, `reconciled_at`, `reconciled_by`, timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_reconciliation_entries`**: `id`, `tenant_id`, `reconciliation_id` (composite FK), `ledger_entry_id` (composite FK), `direction` (`apply`\|`reverse`), `reverses_reconciliation_entry_id` (nullable self-ref, composite), `actor_id`, `created_at`. `UNIQUE(reverses_reconciliation_entry_id)`. **Unique: `(tenant_id, id)`.**

Whole-entry reconciliation; covers every ledger entry regardless of source; `reconciliation.wallet_id = ledger_entry.wallet_id`.

### 12.1 `posted_unreconciled → posted_reconciled` — unchanged from v9/v10, now backed by the full-target predicate

- **`posting_path = direct`:** unchanged — every ledger entry with `source_financial_document_id = <this document>` has a currently-active `apply` reconciliation-entry row.
- **`posting_path = via_route`:** requires **both** of the following:
  1. **The linked route's `status = completed`**, per §4.3's endpoint-aware, full-target predicate — the economic movement is verified to have reached the document's actual endpoint in its complete, unreduced amount.
  2. Every ledger entry with `source_payment_route_leg_id` belonging to any leg of the linked route has a currently-active `apply` reconciliation-entry row.

Index: `(reconciliation_id)`, `(ledger_entry_id)`, `(actor_id)`.

---

## 13. Composite-FK-target index requirement — unchanged (12 tables)

1. `treasury_financial_parties` 2. `treasury_wallets` 3. `treasury_financial_documents` 4. `treasury_payment_routes` 5. `treasury_payment_route_legs` 6. `treasury_ledger_entries` 7. `treasury_fund_chains` 8. `treasury_advances` 9. `treasury_advance_settlements` 10. `treasury_cost_settlement_allocations` (self-FK) 11. `treasury_reconciliations` 12. `treasury_reconciliation_entries` (self-FK).

`treasury_payment_routes.expected_destination_wallet_id` (new column, fix #1) is a composite FK into `treasury_wallets`, which is already table 2 in this list — no new table needed in this index requirement, only a new column on an existing composite-FK-target-referencing table.

---

## 14. Tier B — existing-table FK tenant/project rules — unchanged from v9/v10

| Reference | Existence | Tenant/project match |
|---|---|---|
| `treasury_payment_routes.linked_contract_payment_id` | DB-enforced | Same `tenant_id`; same project via the `ContractPayment`'s `Contract.project_id` = route's own `project_id` |
| `treasury_cost_settlement_allocations.cost_source_contract_expense_id` | DB-enforced | Same `tenant_id`; same project via the expense's owning `Contract.project_id`, which must equal the allocation's own Treasury-side parent's project — that parent is the `financial_document`'s `project_id` when `financial_document_id` is set, or (via `advance_settlement_id → treasury_advances.project_id`) when `advance_settlement_id` is set instead |
| `treasury_cost_settlement_allocations.cost_source_material_receipt_line_id` | DB-enforced | Same rule as above, via the receipt's owning project |

---

## 15. Treasury-internal same-project integrity — v9/v10's 7 rows, plus fix #1's new row 8

| # | Reference | Binding rule |
|---|---|---|
| 1 | `treasury_payment_routes.linked_financial_document_id` → document's `project_id` | Must be equal |
| 2 | `treasury_advances.originating_financial_document_id` → document's `project_id` | Must be equal |
| 3 | `treasury_fund_chain_members.member_financial_document_id` / `member_payment_route_id` → parent chain's `project_id` | Must be equal |
| 4 | `treasury_financial_documents.reversed_document_id` / `replacement_document_id` (self-ref) | Must be equal |
| 5 | `treasury_financial_documents.source_wallet_id` / `destination_wallet_id`, `treasury_payment_route_legs.from_wallet_id` / `to_wallet_id` → wallet's `project_id` | Must be equal, **except** company/shared wallets (`project_id IS NULL`), compatible with any project |
| 6 | `treasury_ledger_entries.wallet_id` vs. its source's project | Must be equal, same company-wallet exception |
| 7 | `treasury_advance_settlements.financial_document_id` → document's `project_id`, when `settlement_type = cash_return` | Must equal the settlement's own project, via its parent `treasury_advances.project_id` |
| 8 | **`treasury_payment_routes.expected_destination_wallet_id` → wallet's `project_id`, when set (Case A routes only)** | **New this round (fix #1)** — must equal the route's own `project_id`, same company-wallet exception as row 5 |

**Enforcement:** the same `TreasuryReferentialIntegrityService`-equivalent named throughout prior rounds, validated at write time, under the same lock discipline as §11 wherever the write participates in a named aggregate check.

---

## 16. Exact table inventory and migration order — unchanged (14 tables)

1. `treasury_financial_parties` 2. `treasury_wallets` 3. `treasury_financial_documents` 4. `treasury_payment_routes` 5. `treasury_payment_route_legs` 6. `treasury_ledger_entries` 7. `treasury_fund_chains` 8. `treasury_advances` 9. `treasury_advance_settlements` 10. `treasury_cost_settlement_allocations` 11. `treasury_expense_approvals` 12. `treasury_reconciliations` 13. `treasury_fund_chain_members` 14. `treasury_reconciliation_entries`. **No migration file exists yet.** `expected_destination_wallet_id` is a new column on table 4 (`treasury_payment_routes`), not a new table — the 14-table count and dependency order are unchanged.

---

## 17. Verification against every held-constant item

- A3/A4-a/A.5/B2+B2-T/C/D: unchanged, not reopened.
- Reversal `via_route` eligibility from own endpoint shape (v10), amount immutability (v9), 14-table count, 12 composite-FK targets, two fund-chain indexes, ledger reversal's own vocabulary, MySQL/SQLite wording, external-entry route-leg conservation, signed ledger, advance/cost allocation conservation, reconciliation actor, Tier-B rules, Treasury-internal project-integrity table rows 1-7: all unchanged from Revision 10.
- Fix #1 verified additive-only: one new nullable column + one new `CHECK` on an existing table (`treasury_payment_routes`), one new row in the existing §15 integrity table, no new table, no change to the 14/12 counts.
- Fix #1 verified not to touch Case B: the new `CHECK` explicitly forbids `expected_destination_wallet_id` from being set on Case B routes, so Case B's existing endpoint logic (read from the linked document's own fields, §4.1) is completely unaffected.
- Fix #2 verified applied identically across all three §4.3 branches (wallet-terminating inbound, `internal_transfer`, and the new Case A branch) — no branch retains a reversal-reduced target.
- Fix #2 verified not to weaken any existing conservation invariant: §4.1 Case A's `SUM(total_allocated_amount) <= ContractPayment.amount` and Case B's `route.total_allocated_amount = linked_financial_document.amount` were never expressed in terms of `R` in any prior round — only the §4.3 *completion* predicate used `R`, and only that predicate changes here.
- No external-destination leg representation designed this round (out of scope, unchanged from v9/v10).

---

## 18. Trạng thái và bước tiếp theo
- Nếu Owner Approve: chuẩn bị Gate 3 cho GAP-037 — vẫn chỉ là quyết định merge tài liệu.
- Nếu Owner Request changes: sẽ tạo `02-design-v12.md` (supersedes bản này).
- Nếu Owner Decline: dừng GAP-037 ở schema-proposal này.

## 19. Loại trừ phạm vi
Kế thừa nguyên vẹn từ mọi round trước: không migration file thật; không model/controller/service/route/UI/test thật; không seed/backfill; không implementation plan coi schema này là đã duyệt cho Gate 3; không Gate 3 tự suy luận; không mark PR ready; không merge PR #263; không sửa/merge/đóng PR #245 hoặc #257; không GAP-036; không Today Workspace; không sửa canonical SSOT stale metadata; không production/deployment; không thiết kế external-destination leg representation.

## Decision Needed
**Resolved 2026-08-16T20:18:07+07:00 — Owner Decision: REQUEST CHANGES.** Both corrections in Revision 11 (explicit Case A `expected_destination_wallet_id`, full unreduced completion target) were confirmed achieved; architecture A3+A4-a+A.5/B2+B2-T/C/D remains approved, frozen, not reopened. Revision 12 is a **consolidated closure round** requiring 6 new binding invariants: (1) Case A custody may only post after the canonical `ContractPayment` is actually paid; (2) an exact document/route-leg → ledger posting conservation matrix; (3) atomic leg-reversal ↔ ledger-reversal coupling; (4) exact, at-most-once document-level reversal; (5) unambiguous external-party direction plus advance/cash-return semantic binding; (6) an expense approval gate and inverse reconciliation lifecycle. This packet (`02-design-v11.md`) is now **frozen** — no further edits. `docs/owner-decisions/GAP-037/02-design-v12.md` (self-contained) follows in the next commit.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt migration file thật hay chi tiết implementation. Owner cũng không được yêu cầu duyệt lại architecture set A3/A4-a/A.5/B2/B2-T/C/D — đã approved, không mở lại. Owner cũng không được yêu cầu duyệt overpayment/prepayment semantics, hay thiết kế external-destination leg representation (nêu là future extension point, chưa thiết kế ở đây).
