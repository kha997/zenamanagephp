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
  recorded_at: "2026-08-16T19:32:27+07:00"
  owner_response_reference: "Owner Gate 2 Schema Proposal Revision 10 decision -- REQUEST CHANGES, recorded in-session on 2026-08-16 against reviewed PR #263 head 72a2e29c604bb802ef0d26036b3d74a805f670ac: 'GAP-037 -- Gate 2 Schema Proposal Revision 10 -- Owner Decision: REQUEST CHANGES. Toi, Owner, yeu cau chinh sua schema proposal tai PR #263, reviewed head 72a2e29c604bb802ef0d26036b3d74a805f670ac. Toi xac nhan hai correction cua Revision 10 da xu ly dung yeu cau truoc: financial-document route completion da endpoint-aware; reversal via_route eligibility da duoc xac dinh tu chinh endpoint shape cua reversal document. Architecture A3 + A4-a + A.5 / B2 + B2-T / C / D van approved, frozen va khong duoc mo lai. Revision 11 chi xu ly hai diem: 1. Give ContractPayment-linked routes an explicit completion endpoint. Case A khong duoc dua vao mot terminal wallet by construction khong ton tai trong schema. Bo sung mot binding expected/terminal destination wallet cho ContractPayment-linked route -- preferably expected_destination_wallet_id -- va yeu cau completed chi khi full active route allocation da toi chinh wallet do, intermediary wallets net zero. Giu same-tenant/same-project/wallet compatibility rules. 2. Do not reduce economic completion target because a route leg was reversed. Xoa total_allocated_amount - R khoi completion semantics. Leg reversal chi triet tieu mot movement; no khong giam route.total_allocated_amount, financial_document.amount hay canonical ContractPayment allocation. Voi financial-document route, completed van phai chung minh full total_allocated_amount tai endpoint dung; voi internal transfer, full -amount/+amount; voi ContractPayment route, full amount tai expected destination. Neu leg bi reverse do sai movement, route tiep tuc partial cho toi khi replacement movement hoan tat. Neu economic route bi huy, dung cancelled; neu financial document bi dao, dung reversal financial document theo schema hien huu. Giu nguyen toan bo v10 con lai: reversal eligibility theo own endpoint shape; amount immutability; 14-table architecture; 12 composite-FK targets; B2-T; signed immutable ledger; allocation/advance semantics; Tier-B/project integrity; reconciliation actor; MySQL/SQLite concurrency; zero existing-table changes; PR #245/#257 untouched; khong runtime/schema implementation; khong Gate 3. Record REQUEST CHANGES truoc vao 02-design-v10.md, freeze v10, tao self-contained 02-design-v11.md, rerun required CI va quay lai awaiting_owner. Khong duoc suy luan schema approval hoac Gate 3 authorization.'"
  reconciliation_required: false
supersedes: docs/owner-decisions/GAP-037/02-design-v9.md
superseded_by: docs/owner-decisions/GAP-037/02-design-v11.md
timestamps:
  created_at: "2026-08-16T18:59:40+07:00"
  updated_at: "2026-08-16T19:32:27+07:00"
generated_by: agent
---

# GAP-037 — Project Treasury: Gate 2 Revision 10 — Endpoint-Aware Completion + Reversal Eligibility (Self-Contained)

**Status:** Gate 1 approved. Gate 2 architecture decisions **approved** (`docs/owner-decisions/GAP-037/02-design.md`, frozen) — **A3 + A4-a + A.5 / B2 + B2-T / C / D**, not reopened. Fully self-contained. Still Gate 2 — a proposal, not implementation. No migration file, model, controller, service, route, UI, or test exists or is authorized by this packet.

**Database compatibility (unchanged verification):** MySQL for production/dev (`config/database.php`, `.env.example`), SQLite for the test suite (`.env.testing`, `phpunit.xml`).

---

## 0. What changed vs. v9, at a glance

| # | Owner's finding | Fix |
|---|---|---|
| 1 | §4.3's `completed` predicate used one generic "sum route custody = total_allocated_amount" check for every document type — this proves money settled *somewhere* in the route's wallets, not that it reached the *correct* endpoint | Predicate is now **endpoint-aware**: wallet-terminating inbound documents must prove the full amount reached `destination_wallet_id` specifically, with zero residual at every intermediary wallet; `internal_transfer` must prove the exact net route effect (`source_wallet = -amount`, `destination_wallet = +amount`, all intermediary wallets `= 0`) |
| 2 | §4.1's eligibility table let a `reversal` document inherit `via_route` eligibility mechanically from the document it reverses, rather than checking the reversal's own actual endpoint shape | Eligibility is now determined **structurally**, from whichever endpoint fields are populated on the document itself — applies uniformly to every `document_type` including `reversal`; a reversal is only `via_route`-eligible if its own resulting source/destination shape is wallet-representable, regardless of what it reverses |

---

## 1. `treasury_financial_parties` and `treasury_wallets` — unchanged from v9

**`treasury_financial_parties`**: `id`, `tenant_id`, `party_type`, `name`, `linked_account_id` (nullable), `linked_user_id` (nullable), timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_wallets`**: `id`, `tenant_id`, `project_id` (nullable), `wallet_type`, `name`, `custodian_party_id` (nullable, composite FK), timestamps. **Unique: `(tenant_id, id)`.**

---

## 2. `treasury_financial_documents`

`id`, `tenant_id`, `project_id`, `document_type` (funding|internal_transfer|expense|owner_contribution|advance|advance_return|reversal|adjustment), `status` (draft|submitted|approved|rejected|posted_unreconciled|posted_reconciled|reversed), `posting_path` (nullable enum: `direct`|`via_route`, set exactly once), `amount`, `source_wallet_id` (nullable, composite FK), `destination_wallet_id` (nullable, composite FK), `counterparty_id` (nullable, composite FK), `description`, `created_by`, `approved_by` (nullable), `posted_at` (nullable), `reversed_document_id` (nullable self-ref), `replacement_document_id` (nullable self-ref), timestamps. **Unique: `(tenant_id, id)`.**

A `reversal` document is a first-class row in this same table (`document_type = reversal`, `reversed_document_id` set) — it carries its **own** `source_wallet_id`/`destination_wallet_id`/`counterparty_id` describing its own actual money movement (which runs opposite to the document it reverses), not a copy of the original document's fields. This is what makes fix #2 (§4.1) possible: eligibility can be read directly off the reversal row's own populated endpoint fields.

### 2.1 Posting-path freeze + amount immutability + eligibility gate — unchanged from v9
A route may only attach (§4) while `posting_path IS NULL` and `status` is unposted, and only for a `document_type`/endpoint-shape combination that is `via_route`-eligible per §4.1's structural rule. Attaching sets `posting_path = via_route` and simultaneously freezes `amount` as immutable — both writes occur in the same transaction, under the same lock (§11 item 5). If no route is ever attached and the document posts directly, `posting_path` locks to `direct` at that moment; `amount` is fixed at whichever of these two transitions occurs first (no document posts more than once).

Once `posting_path` is non-null (either value), `treasury_financial_documents.amount` has no update path — immutable, full stop.

---

## 3. The typed-nullable-FK pattern — unchanged principle

Every former polymorphic reference is N nullable typed FK columns + a `CHECK` requiring exactly one non-null: `treasury_payment_routes` (§4), `treasury_ledger_entries` (§5), `treasury_cost_settlement_allocations` (§7), `treasury_fund_chain_members` (§8).

---

## 4. `treasury_payment_routes` and `treasury_payment_route_legs`

**`treasury_payment_routes`**: `id`, `tenant_id`, `project_id`, `total_allocated_amount`, `status` (planned|partial|completed|cancelled), `linked_financial_document_id` (nullable, composite FK), `linked_contract_payment_id` (nullable, single-column FK → `contract_payments(id)`), timestamps. `CHECK ((linked_financial_document_id IS NULL) != (linked_contract_payment_id IS NULL))`. Unique index directly on `linked_financial_document_id`. **Unique: `(tenant_id, id)`.**

### 4.1 Conservation, immutability, and eligibility

**Case A — `linked_contract_payment_id` (unchanged):** `SUM(total_allocated_amount) <= ContractPayment.amount` across a `ContractPayment`'s routes. Lock the `contract_payments` row (§11, item 1). `total_allocated_amount` is immutable post-creation for these routes, same as Case B.

**Case B — `linked_financial_document_id` (unchanged from v9):**
```
route.total_allocated_amount = linked_financial_document.amount
```
Both sides frozen together, atomically, the moment this equality is established (route attachment) — no update path for either afterward. Checked and locked in the same transaction as §2.1's `posting_path` freeze (§11, item 5).

**Fix #2 — `via_route` eligibility, now structural rather than type-inherited (revised this round):**

v9 classified eligibility per `document_type`, with `reversal` special-cased to "mirror the reversed document." The Owner's finding: this is wrong because a `reversal` document's own actual movement can have a *different* endpoint shape than the document it reverses — e.g. reversing an `advance` (originally a wallet→`counterparty_id` cash-out, always `direct` per the table below) can, depending on how the reversal is executed, still end with the money returning to a Treasury wallet (wallet-representable) or continuing to move externally (not wallet-representable). Inheriting the original's eligibility either wrongly forbids a legitimately representable reversal or wrongly permits one that isn't.

**The corrected rule reads the reversal's own populated endpoint fields directly, exactly the same way it reads any other document's fields — no `document_type`-based special case for `reversal` at all:**

`via_route` is eligible for a document (any `document_type`, including `reversal`) if and only if its own populated fields describe a shape the current leg model (`to_wallet_id` required, `from_wallet_id` nullable for external origin, no external-destination field) can represent:

| Document's own populated endpoint fields | `via_route` eligible? | Why |
|---|---|---|
| `destination_wallet_id` set, `counterparty_id` NULL, `source_wallet_id` NULL | **Yes** | Wallet-terminating inbound shape — representable as a single-leg-minimum chain ending at `to_wallet_id = destination_wallet_id` |
| `source_wallet_id` set AND `destination_wallet_id` set, `counterparty_id` NULL | **Yes** | `internal_transfer`-shaped — wallet-to-wallet, fully representable; completion predicate is §4.3's `internal_transfer` case |
| `counterparty_id` set (regardless of whether `source_wallet_id` is also set) | **No — `direct` only** | The endpoint is an external `financial_party`, not a wallet — no leg field can represent this destination today |
| `source_wallet_id` set, `destination_wallet_id` NULL, `counterparty_id` NULL (decrease-type `adjustment` shape) | **No — `direct` only** | Money leaves with no trackable wallet endpoint |

**Applied to every `document_type` in §2, including `reversal`:** `funding`, `owner_contribution`, `advance_return`, and increase-type `adjustment` populate only `destination_wallet_id` → eligible. `internal_transfer` populates both → eligible. `expense`, `advance`, and decrease-type `adjustment` populate `counterparty_id` or leave `destination_wallet_id` unset → `direct`-only. A `reversal` document is classified by **its own** populated fields under this same table — never by looking up what `document_type` its `reversed_document_id` points to. This is a strictly more precise restatement of v9's table, not a loosening: every non-reversal classification is unchanged; only the `reversal` special case is removed and replaced with "apply the same structural rule directly."

Enforced at the same moment as §2.1's attachment check, under the same lock (§11, item 5): an attachment attempt for a document whose own populated fields don't match an eligible row is rejected outright.

**`treasury_payment_route_legs`**: `id`, `tenant_id`, `payment_route_id` (composite FK), `sequence_no`, `from_wallet_id` (nullable, composite FK), `to_wallet_id` (composite FK), `amount`, `status` (in_transit|settled|reversed), `occurred_at`, timestamps. **Unique: `(tenant_id, id)`.**

### 4.2 Route-leg custody — unchanged from v7/v8/v9
Lock the parent route (§11, item 6). Wallet-backed leg: validate against §5.3's balance formula. External-entry leg (`from_wallet_id IS NULL`): bounded by remaining `total_allocated_amount`. Both persist leg + ledger entries atomically under the lock.

### 4.3 Fix #1 — endpoint-aware route-completion predicate (revised this round)

v9's predicate checked only that the route's *total* non-reversed custody (summed across every wallet it currently occupies) equaled `total_allocated_amount`. The Owner's finding: this proves the money settled *somewhere* among the route's touched wallets, not that it reached the *correct* endpoint — a route could show `total custody = total_allocated_amount` while the balance actually sits at an intermediary hop rather than the document's real destination.

**Corrected predicate, branching on the linked document's own endpoint shape (per §4.1's eligibility table — only wallet-representable shapes ever reach this point):**

Define, for any wallet `w` and this route's legs: `net_custody(w) = SUM(credit) - SUM(debit)` over `treasury_ledger_entries` rows whose `source_payment_route_leg_id` belongs to a leg of this route and whose `wallet_id = w` (the same balance formula as §5.3, scoped to this route). Let `R` = the amount of `total_allocated_amount` returned via reversed legs (so the route's still-active allocation is `total_allocated_amount - R`).

- **Precondition, both cases:** every leg belonging to the route has `status IN ('settled', 'reversed')` — none remain `in_transit`.
- **Wallet-terminating inbound shape** (`linked_financial_document.destination_wallet_id` set, `source_wallet_id` NULL — see §4.1's first eligible row): `completed` requires `net_custody(destination_wallet_id) = total_allocated_amount - R`, **and** `net_custody(w) = 0` for every other wallet `w` the route's legs have touched (no residual left at any intermediary hop).
- **`internal_transfer` shape** (`linked_financial_document.source_wallet_id` and `destination_wallet_id` both set — §4.1's second eligible row): `completed` requires the exact net route effect: `net_custody(source_wallet_id) = -(total_allocated_amount - R)`, `net_custody(destination_wallet_id) = +(total_allocated_amount - R)`, and `net_custody(w) = 0` for every other (intermediary) wallet the route's legs have touched.

Both branches are checked under the same parent-route lock as §4.2 (§11, item 6). `status` cannot be set to `completed` by simple assignment while the applicable branch's conditions fail — the transition is gated by this predicate at write time, not left to caller discretion. A route with `status = partial` cannot skip to `completed` without satisfying its branch's conditions first.

**`Case A` routes** (`linked_contract_payment_id`, no `treasury_financial_documents` row to read an endpoint shape from) keep v9's original generic predicate — total non-reversed custody across the route's wallets equals `total_allocated_amount - R`, with the leg-status precondition — since there is no document-level endpoint field to branch on for this case; the route's terminal wallet is simply wherever the `ContractPayment`-funded route was configured to land, and only one wallet-terminating shape is possible for Case A routes by construction (§8/§4's B2-T conservation model, unchanged from prior rounds).

---

## 5. `treasury_ledger_entries` — unchanged from v9

`id`, `tenant_id`, `source_financial_document_id` (nullable, composite FK), `source_payment_route_leg_id` (nullable, composite FK), `wallet_id` (composite FK), `direction` (`debit`\|`credit`), `amount`, `entry_type`, `posted_at`, `reversal_of_entry_id` (nullable self-ref, composite), `created_at`. `CHECK` (exactly one source set). Idempotency via generated `original_posting_key` (excludes reversals). `wallet_balance = SUM(credit) - SUM(debit)`. Reversal: same wallet/source/amount, opposite direction, `UNIQUE(reversal_of_entry_id)`, no reverse-of-reverse.

Index: `(source_financial_document_id)`, `(source_payment_route_leg_id)`, `(wallet_id, posted_at)`, `UNIQUE(reversal_of_entry_id)`, `UNIQUE(original_posting_key)`. **Unique: `(tenant_id, id)`.**

---

## 6. Settlement conservation — unchanged from v7/v8/v9

**6.1** `net_allocation(cost_source) = SUM(apply) - SUM(reverse)`. **6.2** Direct-expense: `net_allocation(financial_document) = financial_document.amount`. **6.3** `0 <= net_allocation(cost_source) <= canonical_incurred_amount(cost_source)`. **6.5** `0 <= advance.amount - SUM(apply) + SUM(reverse) <= advance.amount`. **6.6** Violating writes rejected. **6.7** Material prepayment via `treasury_advances`, never a nonexistent-cost-record allocation.

---

## 7. `treasury_cost_settlement_allocations`, `treasury_advances`, `treasury_advance_settlements` — unchanged from v7/v8/v9

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

**Distinct from §4.1's fix #2:** this section governs the internal apply/reverse *event log* mechanics (unchanged). §4.1's fix concerns a different question — whether a `document_type = reversal` **financial document** (a real Treasury document row, not an event-log entry) may itself choose `posting_path = via_route`. The two are unrelated systems that happen to share the word "reversal."

---

## 10. `treasury_expense_approvals` — unchanged

`id`, `tenant_id`, `financial_document_id` (composite FK), `event`, `from_status`, `to_status`, `actor_id`, `note` (nullable), `context` (nullable), `created_at`.

---

## 11. Concurrency — unchanged list, items 5 and 6 now also cover this round's revised checks

| # | Check | Row/subject locked |
|---|---|---|
| 1 | `ContractPayment` route-allocation conservation (§4.1 Case A) | `contract_payments` row |
| 2 | Cost over-settlement cap (§6.3) | `contract_expenses`/`material_receipt_lines` row |
| 3 | Advance outstanding cap + settlement completeness (§7.4) | `treasury_advances` row |
| 4 | Active reconciliation uniqueness (§12) | `treasury_ledger_entries` row |
| 5 | Financial-document posting-source selection (§2.1), §4.1 Case B equality + immutability freeze, and **§4.1's structural eligibility gate (fix #2, including reversals)** | `treasury_financial_documents` row |
| 6 | Route-leg custody (§4.2) and **the endpoint-aware route-completion predicate (§4.3, fix #1)** | Parent `treasury_payment_routes` row |

MySQL: `SELECT ... FOR UPDATE`. SQLite (test suite): `BEGIN IMMEDIATE`.

---

## 12. `treasury_reconciliations` and `treasury_reconciliation_entries` — unchanged from v9 (references §4.3's revised predicate)

**`treasury_reconciliations`**: `id`, `tenant_id`, `wallet_id` (composite FK), `reconciliation_type`, `external_reference`, `reconciled_at`, `reconciled_by`, timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_reconciliation_entries`**: `id`, `tenant_id`, `reconciliation_id` (composite FK), `ledger_entry_id` (composite FK), `direction` (`apply`\|`reverse`), `reverses_reconciliation_entry_id` (nullable self-ref, composite), `actor_id`, `created_at`. `UNIQUE(reverses_reconciliation_entry_id)`. **Unique: `(tenant_id, id)`.**

Whole-entry reconciliation; covers every ledger entry regardless of source; `reconciliation.wallet_id = ledger_entry.wallet_id`.

### 12.1 `posted_unreconciled → posted_reconciled` — unchanged from v9, now backed by the endpoint-aware predicate

- **`posting_path = direct`:** unchanged — every ledger entry with `source_financial_document_id = <this document>` has a currently-active `apply` reconciliation-entry row.
- **`posting_path = via_route`:** requires **both** of the following:
  1. **The linked route's `status = completed`**, now per §4.3's **endpoint-aware** predicate — the economic movement is verified to have reached the document's actual endpoint, not merely settled somewhere among the route's wallets.
  2. Every ledger entry with `source_payment_route_leg_id` belonging to any leg of the linked route has a currently-active `apply` reconciliation-entry row.

Index: `(reconciliation_id)`, `(ledger_entry_id)`, `(actor_id)`.

---

## 13. Composite-FK-target index requirement — unchanged (12 tables)

1. `treasury_financial_parties` 2. `treasury_wallets` 3. `treasury_financial_documents` 4. `treasury_payment_routes` 5. `treasury_payment_route_legs` 6. `treasury_ledger_entries` 7. `treasury_fund_chains` 8. `treasury_advances` 9. `treasury_advance_settlements` 10. `treasury_cost_settlement_allocations` (self-FK) 11. `treasury_reconciliations` 12. `treasury_reconciliation_entries` (self-FK).

---

## 14. Tier B — existing-table FK tenant/project rules — unchanged from v9

| Reference | Existence | Tenant/project match |
|---|---|---|
| `treasury_payment_routes.linked_contract_payment_id` | DB-enforced | Same `tenant_id`; same project via the `ContractPayment`'s `Contract.project_id` = route's own `project_id` |
| `treasury_cost_settlement_allocations.cost_source_contract_expense_id` | DB-enforced | Same `tenant_id`; same project via the expense's owning `Contract.project_id`, which must equal the allocation's own Treasury-side parent's project — that parent is the `financial_document`'s `project_id` when `financial_document_id` is set, or (via `advance_settlement_id → treasury_advances.project_id`) when `advance_settlement_id` is set instead |
| `treasury_cost_settlement_allocations.cost_source_material_receipt_line_id` | DB-enforced | Same rule as above, via the receipt's owning project |

---

## 15. Treasury-internal same-project integrity — unchanged from v9

| # | Reference | Binding rule |
|---|---|---|
| 1 | `treasury_payment_routes.linked_financial_document_id` → document's `project_id` | Must be equal |
| 2 | `treasury_advances.originating_financial_document_id` → document's `project_id` | Must be equal |
| 3 | `treasury_fund_chain_members.member_financial_document_id` / `member_payment_route_id` → parent chain's `project_id` | Must be equal |
| 4 | `treasury_financial_documents.reversed_document_id` / `replacement_document_id` (self-ref) | Must be equal |
| 5 | `treasury_financial_documents.source_wallet_id` / `destination_wallet_id`, `treasury_payment_route_legs.from_wallet_id` / `to_wallet_id` → wallet's `project_id` | Must be equal, **except** company/shared wallets (`project_id IS NULL`), compatible with any project |
| 6 | `treasury_ledger_entries.wallet_id` vs. its source's project | Must be equal, same company-wallet exception |
| 7 | `treasury_advance_settlements.financial_document_id` → document's `project_id`, when `settlement_type = cash_return` | Must equal the settlement's own project, via its parent `treasury_advances.project_id` |

**Enforcement:** the same `TreasuryReferentialIntegrityService`-equivalent named throughout prior rounds, validated at write time, under the same lock discipline as §11 wherever the write participates in a named aggregate check.

---

## 16. Exact table inventory and migration order — unchanged (14 tables)

1. `treasury_financial_parties` 2. `treasury_wallets` 3. `treasury_financial_documents` 4. `treasury_payment_routes` 5. `treasury_payment_route_legs` 6. `treasury_ledger_entries` 7. `treasury_fund_chains` 8. `treasury_advances` 9. `treasury_advance_settlements` 10. `treasury_cost_settlement_allocations` 11. `treasury_expense_approvals` 12. `treasury_reconciliations` 13. `treasury_fund_chain_members` 14. `treasury_reconciliation_entries`. **No migration file exists yet.**

---

## 17. Verification against every held-constant item

- A3/A4-a/A.5/B2/B2-T/C/D: unchanged, not reopened.
- Amount immutability (v9 fix #1), 14-table count, 12 composite-FK targets, two fund-chain indexes, ledger reversal's own vocabulary, MySQL/SQLite wording, external-entry route-leg conservation, signed ledger, advance/cost allocation conservation, reconciliation actor, Tier-B rules, Treasury-internal project-integrity table (§15, all 7 rows including the v9-added `advance_settlement ↔ financial_document` join): all unchanged from Revision 9.
- Fix #1's endpoint-aware predicate verified against §4.2's existing leg-custody data — it reads the same per-wallet ledger-entry data already scoped by `source_payment_route_leg_id`, introducing no new column, only a per-wallet (rather than route-total) aggregation and a branch on the document's own endpoint shape.
- Fix #1 verified not to regress Case A (`ContractPayment` routes): explicitly kept on the prior generic predicate since there is no `treasury_financial_documents` row to read an endpoint shape from in that case, and B2-T's conservation model gives Case A routes a single well-defined terminal wallet by construction.
- Fix #2 verified exhaustive: every row of v9's old type-keyed eligibility table maps to exactly one row of the new field-keyed table with an identical Yes/No outcome for every non-reversal `document_type`; only the `reversal` special case changed, from "inherit from original" to "read own fields" — and this is strictly more precise, not a loosening (a reversal that happens to end up with the same shape as its original still gets the same answer as before; the difference only shows up when the shapes genuinely differ, exactly the case the Owner flagged).
- No external-destination leg representation designed this round, per Owner's explicit instruction.

---

## 18. Trạng thái và bước tiếp theo
- Nếu Owner Approve: chuẩn bị Gate 3 cho GAP-037 — vẫn chỉ là quyết định merge tài liệu.
- Nếu Owner Request changes: sẽ tạo `02-design-v11.md` (supersedes bản này).
- Nếu Owner Decline: dừng GAP-037 ở schema-proposal này.

## 19. Loại trừ phạm vi
Kế thừa nguyên vẹn từ mọi round trước: không migration file thật; không model/controller/service/route/UI/test thật; không seed/backfill; không implementation plan coi schema này là đã duyệt cho Gate 3; không Gate 3 tự suy luận; không mark PR ready; không merge PR #263; không sửa/merge/đóng PR #245 hoặc #257; không GAP-036; không Today Workspace; không sửa canonical SSOT stale metadata; không production/deployment; không thiết kế external-destination leg representation.

## Decision Needed
**Resolved 2026-08-16T19:32:27+07:00 — Owner Decision: REQUEST CHANGES.** Both corrections in Revision 10 (endpoint-aware completion, reversal eligibility from own endpoint shape) were confirmed handled correctly; architecture A3+A4-a+A.5/B2+B2-T/C/D remains approved, frozen, not reopened. Two further points required: (1) give `ContractPayment`-linked (Case A) routes an explicit binding completion endpoint (`expected_destination_wallet_id`) instead of relying on an unmodeled "terminal wallet by construction," with the same same-tenant/same-project/wallet-compatibility rules as every other wallet reference; (2) remove `total_allocated_amount - R` from every completion predicate — a reversed leg cancels a movement, it does not shrink the economic target; completion must always be checked against the full, unreduced `total_allocated_amount` (or financial-document `amount`, or expected `ContractPayment` allocation) reaching the correct endpoint, with a route staying `partial` until a replacement movement actually completes it. This packet (`02-design-v10.md`) is now **frozen** — no further edits. `docs/owner-decisions/GAP-037/02-design-v11.md` (self-contained) follows in the next commit.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt migration file thật hay chi tiết implementation. Owner cũng không được yêu cầu duyệt lại architecture set A3/A4-a/A.5/B2/B2-T/C/D — đã approved, không mở lại. Owner cũng không được yêu cầu duyệt overpayment/prepayment semantics, hay thiết kế external-destination leg representation (nêu là future extension point, chưa thiết kế ở đây).
