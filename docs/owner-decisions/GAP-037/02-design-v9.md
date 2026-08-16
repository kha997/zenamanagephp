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
  recorded_at: "2026-08-16T18:58:47+07:00"
  owner_response_reference: "Owner Gate 2 Schema Proposal Revision 9 decision -- REQUEST CHANGES, recorded in-session on 2026-08-16 against reviewed PR #263 head f8b3d5c03810c3d02b26c3e09ab6fcb7ae8fba8c: 'GAP-037 -- Gate 2 Schema Proposal Revision 9 -- Owner Decision: REQUEST CHANGES. Toi, Owner, yeu cau chinh sua schema proposal tai PR #263, reviewed head f8b3d5c03810c3d02b26c3e09ab6fcb7ae8fba8c. Toi xac nhan 3 closure items cua Revision 9 da xu ly dung huong va architecture A3 + A4-a + A.5 / B2 + B2-T / C / D van approved, frozen, khong mo lai. Revision 10 chi xu ly hai diem: 1. Make financial-document route completion endpoint-aware. Khong dung mot predicate chung sum route custody = total_allocated_amount cho moi document type. Voi wallet-terminating inbound documents, completed phai chung minh full amount da toi chinh destination_wallet_id, khong con residual o intermediary wallets. Voi internal_transfer, completed phai chung minh net route effect: source_wallet = -amount, destination_wallet = +amount, all intermediary wallets = 0, dong thoi khong con leg in_transit. posted_reconciled cua via_route tiep tuc yeu cau route completed theo predicate nay va toan bo applicable ledger entries reconciled. 2. Determine reversal route eligibility from the reversal's own endpoint shape. Khong inherit eligibility mot cach may moc tu original document. Neu reversal tao wallet-to-external movement ma current leg schema khong bieu dien duoc, reversal phai direct. Reversal chi duoc via_route khi chinh source/destination shape sau reversal nam trong kha nang bieu dien hien tai cua payment_route_legs. Khong thiet ke external-destination leg trong work item nay. Giu nguyen toan bo v9 con lai: amount immutability; 14 tables; 12 composite-FK targets; B2-T; signed ledger; reversal/allocation semantics; Tier-B/project integrity; advance settlement; reconciliation actor; MySQL/SQLite locking; zero existing-table changes; #245/#257 untouched; khong runtime/schema implementation; khong Gate 3. Record REQUEST CHANGES truoc vao 02-design-v9.md, freeze v9, tao self-contained 02-design-v10.md, rerun required CI va quay lai awaiting_owner. Khong duoc suy luan schema approval hoac Gate 3 authorization.'"
  reconciliation_required: false
supersedes: docs/owner-decisions/GAP-037/02-design-v8.md
superseded_by: null
timestamps:
  created_at: "2026-08-16T18:47:25+07:00"
  updated_at: "2026-08-16T18:58:47+07:00"
generated_by: agent
---

# GAP-037 — Project Treasury: Gate 2 Revision 9 — Closure Items (Self-Contained)

**Status:** Gate 1 approved. Gate 2 architecture decisions **approved** (`docs/owner-decisions/GAP-037/02-design.md`, frozen) — **A3 + A4-a + A.5 / B2 + B2-T / C / D**, not reopened. Fully self-contained. Still Gate 2 — a proposal, not implementation. No migration file, model, controller, service, route, UI, or test exists or is authorized by this packet.

**Database compatibility (unchanged verification):** MySQL for production/dev (`config/database.php`, `.env.example`), SQLite for the test suite (`.env.testing`, `phpunit.xml`).

---

## 0. What changed vs. v8, at a glance

| # | Owner's finding | Fix |
|---|---|---|
| 1 | `financial_document.amount` and `route.total_allocated_amount` could drift apart after route attachment | Both become **immutable** the moment a route attaches (same transaction/lock as the attachment itself, §11 item 5) — no update path for either exists from that point forward |
| 2 | `posted_reconciled` for `via_route` only checked entry-level reconciliation, not whether the route had actually finished moving the money | New binding completion predicate for `treasury_payment_routes.status → completed`; `posted_reconciled` for `via_route` now requires **both** route completion **and** full entry reconciliation |
| 3 | `via_route` had no eligibility restriction, but the leg model (`to_wallet_id` required, no external-party-destination equivalent) cannot represent an `expense`/`advance` cash-out's real endpoint; two Treasury-internal same-project joins were still missing | `via_route` restricted to document types whose endpoint the current leg model can represent (wallet-terminating types only — `expense`/`advance` are `direct`-only); added `advance_settlement ↔ financial_document` and clarified `cost_settlement_allocation`'s own Treasury-side project source in §15/§14 |

---

## 1. `treasury_financial_parties` and `treasury_wallets` — unchanged from v8

**`treasury_financial_parties`**: `id`, `tenant_id`, `party_type`, `name`, `linked_account_id` (nullable), `linked_user_id` (nullable), timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_wallets`**: `id`, `tenant_id`, `project_id` (nullable), `wallet_type`, `name`, `custodian_party_id` (nullable, composite FK), timestamps. **Unique: `(tenant_id, id)`.**

---

## 2. `treasury_financial_documents`

`id`, `tenant_id`, `project_id`, `document_type` (funding|internal_transfer|expense|owner_contribution|advance|advance_return|reversal|adjustment), `status` (draft|submitted|approved|rejected|posted_unreconciled|posted_reconciled|reversed), `posting_path` (nullable enum: `direct`|`via_route`, set exactly once), `amount`, `source_wallet_id` (nullable, composite FK), `destination_wallet_id` (nullable, composite FK), `counterparty_id` (nullable, composite FK), `description`, `created_by`, `approved_by` (nullable), `posted_at` (nullable), `reversed_document_id` (nullable self-ref), `replacement_document_id` (nullable self-ref), timestamps. **Unique: `(tenant_id, id)`.**

### 2.1 Posting-path freeze (unchanged) + fix #1 (amount immutability) + fix #3 (eligibility gate)
A route may only attach (§4) while `posting_path IS NULL` and `status` is unposted. **Fix #3 — eligibility gate, checked at the same moment:** attachment is only permitted for `document_type` values whose endpoint is a Treasury-tracked wallet — see §4's eligibility rule for the complete, binding list. Attaching sets `posting_path = via_route` **and, new this round, simultaneously freezes `amount` as immutable** (fix #1) — both writes occur in the same transaction, under the same lock (§11 item 5). If no route is ever attached and the document reaches posting (`status → posted_unreconciled`), `posting_path` locks to `direct` at that moment; `amount` was already effectively frozen the moment posting occurred in either path (no document posts more than once, and `direct`-path documents have no separate "attachment" moment, so their `amount` is fixed at the posting transition itself — restated explicitly here since v8 left the `direct` case's amount-freeze implicit).

**Fix #1, restated as the binding rule:** once `posting_path` is non-null (either value), `treasury_financial_documents.amount` has no update path — immutable, full stop. This is the preferred design per the Owner's own instruction (immutability over revalidate-on-update), and it is simpler: no "atomic revalidation" logic is needed anywhere, because the value that would need revalidating can never change in the first place.

---

## 3. The typed-nullable-FK pattern — unchanged principle

Every former polymorphic reference is N nullable typed FK columns + a `CHECK` requiring exactly one non-null: `treasury_payment_routes` (§4), `treasury_ledger_entries` (§5), `treasury_cost_settlement_allocations` (§7), `treasury_fund_chain_members` (§8).

---

## 4. `treasury_payment_routes` and `treasury_payment_route_legs`

**`treasury_payment_routes`**: `id`, `tenant_id`, `project_id`, `total_allocated_amount`, `status` (planned|partial|completed|cancelled), `linked_financial_document_id` (nullable, composite FK), `linked_contract_payment_id` (nullable, single-column FK → `contract_payments(id)`), timestamps. `CHECK ((linked_financial_document_id IS NULL) != (linked_contract_payment_id IS NULL))`. Unique index directly on `linked_financial_document_id`. **Unique: `(tenant_id, id)`.**

### 4.1 Conservation, immutability, and eligibility

**Case A — `linked_contract_payment_id` (unchanged):** `SUM(total_allocated_amount) <= ContractPayment.amount` across a `ContractPayment`'s routes. Lock the `contract_payments` row (§11, item 1). `total_allocated_amount` for a `ContractPayment`-linked route was already effectively fixed at creation in prior rounds; restated here as explicitly immutable post-creation, for consistency with Case B's new rule.

**Case B — `linked_financial_document_id` (equality unchanged from Round 8; fix #1 adds immutability):**
```
route.total_allocated_amount = linked_financial_document.amount
```
**Fix #1 — both sides frozen together, atomically:** the moment this equality is established (route attachment), **both** `route.total_allocated_amount` and `linked_financial_document.amount` become immutable — no update path exists for either. This is checked and locked in the *same* transaction as §2.1's `posting_path` freeze (§11, item 5) — there is no window in which the two values could be read as equal and then one of them changed before the lock is released.

**Fix #3 — `via_route` document-type eligibility (new this round):**

The current leg model has `to_wallet_id` **required** (non-nullable) with no equivalent for "money leaving to an external party" — only `from_wallet_id` has an external-origin equivalent (`NULL`, §4.2 Case B). This means a leg can represent money **entering** Treasury's tracked custody from outside, but **not** money **leaving** to an external party (a supplier, an employee) — which is exactly what `expense` and `advance` cash-outs need. Until the leg model is extended with an explicit external-destination representation (not designed in this revision — noted as a future extension point), **`via_route` is only eligible for `document_type` values whose economic endpoint is a Treasury-tracked wallet**:

| `document_type` | `via_route` eligible? | Why |
|---|---|---|
| `funding`, `internal_transfer`, `owner_contribution`, `advance_return` | **Yes** | Endpoint is `destination_wallet_id`, a real Treasury wallet — representable by a leg's `to_wallet_id` |
| `expense`, `advance` | **No — `direct` only** | Endpoint is `counterparty_id` (a `financial_party`), not a wallet — no leg field can represent this destination today |
| `adjustment` (increase case, `destination_wallet_id` set) | **Yes** | Same reasoning as the wallet-terminating group |
| `adjustment` (decrease case, `source_wallet_id` set only) | **No — `direct` only** | Same reasoning as `expense`/`advance` — money leaves with no trackable wallet endpoint |
| `reversal` | Mirrors the reversed document's own eligibility | A reversal of a `direct` document is `direct`; `expense`/`advance` reversals are therefore always `direct` too |

Enforced at the same moment as §2.1's attachment check, under the same lock (§11, item 5): an attachment attempt for an ineligible `document_type` is rejected outright.

**`treasury_payment_route_legs`**: `id`, `tenant_id`, `payment_route_id` (composite FK), `sequence_no`, `from_wallet_id` (nullable, composite FK), `to_wallet_id` (composite FK), `amount`, `status` (in_transit|settled|reversed), `occurred_at`, timestamps. **Unique: `(tenant_id, id)`.**

### 4.2 Route-leg custody — unchanged from v7/v8
Lock the parent route (§11, item 6). Wallet-backed leg: validate against §5.3's balance formula. External-entry leg (`from_wallet_id IS NULL`): bounded by remaining `total_allocated_amount`. Both persist leg + ledger entries atomically under the lock.

### 4.3 Fix #2 — binding route-completion predicate
`treasury_payment_routes.status` may transition to `completed` **only** when, at the moment of transition (checked under the same parent-route lock as §4.2, §11 item 6):

1. **Every leg belonging to the route has `status IN ('settled', 'reversed')`** — none remain `in_transit`.
2. **The route's total non-reversed custody, summed across every wallet it currently occupies (via §5.3's balance formula, scoped to this route's legs), equals `total_allocated_amount` net of any amount returned via a reversed leg.** In other words: every unit of the route's allocated amount has reached a settled, terminal position — nothing is missing, nothing is still mid-transit.

**Binding constraint:** `status` cannot be set to `completed` by simple assignment while either condition fails — the transition is gated by this predicate, checked at write time, not left to caller discretion. A route with `status = partial` (some legs settled, more expected) cannot skip to `completed` without satisfying both conditions above first.

---

## 5. `treasury_ledger_entries` — unchanged from v8

`id`, `tenant_id`, `source_financial_document_id` (nullable, composite FK), `source_payment_route_leg_id` (nullable, composite FK), `wallet_id` (composite FK), `direction` (`debit`\|`credit`), `amount`, `entry_type`, `posted_at`, `reversal_of_entry_id` (nullable self-ref, composite), `created_at`. `CHECK` (exactly one source set). Idempotency via generated `original_posting_key` (excludes reversals). `wallet_balance = SUM(credit) - SUM(debit)`. Reversal: same wallet/source/amount, opposite direction, `UNIQUE(reversal_of_entry_id)`, no reverse-of-reverse.

Index: `(source_financial_document_id)`, `(source_payment_route_leg_id)`, `(wallet_id, posted_at)`, `UNIQUE(reversal_of_entry_id)`, `UNIQUE(original_posting_key)`. **Unique: `(tenant_id, id)`.**

---

## 6. Settlement conservation — unchanged from v7/v8

**6.1** `net_allocation(cost_source) = SUM(apply) - SUM(reverse)`. **6.2** Direct-expense: `net_allocation(financial_document) = financial_document.amount`. **6.3** `0 <= net_allocation(cost_source) <= canonical_incurred_amount(cost_source)`. **6.5** `0 <= advance.amount - SUM(apply) + SUM(reverse) <= advance.amount`. **6.6** Violating writes rejected. **6.7** Material prepayment via `treasury_advances`, never a nonexistent-cost-record allocation.

---

## 7. `treasury_cost_settlement_allocations`, `treasury_advances`, `treasury_advance_settlements` — unchanged from v7/v8

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

---

## 10. `treasury_expense_approvals` — unchanged

`id`, `tenant_id`, `financial_document_id` (composite FK), `event`, `from_status`, `to_status`, `actor_id`, `note` (nullable), `context` (nullable), `created_at`.

---

## 11. Concurrency — unchanged list, items 5 and 6 now also cover this round's new checks

| # | Check | Row/subject locked |
|---|---|---|
| 1 | `ContractPayment` route-allocation conservation (§4.1 Case A) | `contract_payments` row |
| 2 | Cost over-settlement cap (§6.3) | `contract_expenses`/`material_receipt_lines` row |
| 3 | Advance outstanding cap + settlement completeness (§7.4) | `treasury_advances` row |
| 4 | Active reconciliation uniqueness (§12) | `treasury_ledger_entries` row |
| 5 | Financial-document posting-source selection (§2.1), §4.1 Case B equality **and its new immutability freeze (fix #1)**, and **§4.1's eligibility gate (fix #3)** | `treasury_financial_documents` row |
| 6 | Route-leg custody (§4.2) **and the new route-completion predicate (§4.3, fix #2)** | Parent `treasury_payment_routes` row |

MySQL: `SELECT ... FOR UPDATE`. SQLite (test suite): `BEGIN IMMEDIATE`.

---

## 12. `treasury_reconciliations` and `treasury_reconciliation_entries` — fix #2 (completion required)

**`treasury_reconciliations`**: `id`, `tenant_id`, `wallet_id` (composite FK), `reconciliation_type`, `external_reference`, `reconciled_at`, `reconciled_by`, timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_reconciliation_entries`**: `id`, `tenant_id`, `reconciliation_id` (composite FK), `ledger_entry_id` (composite FK), `direction` (`apply`\|`reverse`), `reverses_reconciliation_entry_id` (nullable self-ref, composite), `actor_id`, `created_at`. `UNIQUE(reverses_reconciliation_entry_id)`. **Unique: `(tenant_id, id)`.**

Whole-entry reconciliation; covers every ledger entry regardless of source; `reconciliation.wallet_id = ledger_entry.wallet_id`.

### 12.1 Fix #2 — `posted_unreconciled → posted_reconciled`, corrected for `via_route`

- **`posting_path = direct`:** unchanged — every ledger entry with `source_financial_document_id = <this document>` has a currently-active `apply` reconciliation-entry row.
- **`posting_path = via_route`:** now requires **both** of the following (previously only the second was checked, which the Owner correctly identified as insufficient):
  1. **The linked route's `status = completed`**, per §4.3's binding predicate — the economic movement is actually finished, not merely "whatever legs exist so far are reconciled."
  2. Every ledger entry with `source_payment_route_leg_id` belonging to any leg of the linked route has a currently-active `apply` reconciliation-entry row.

  Both conditions together mean: the document cannot reach `posted_reconciled` while the route is still `planned`/`partial` (money still moving) even if every leg posted *so far* happens to be reconciled — reconciliation of a partial, still-in-progress route proves nothing about the document's actual completion.

Index: `(reconciliation_id)`, `(ledger_entry_id)`, `(actor_id)`.

---

## 13. Composite-FK-target index requirement — unchanged (12 tables)

1. `treasury_financial_parties` 2. `treasury_wallets` 3. `treasury_financial_documents` 4. `treasury_payment_routes` 5. `treasury_payment_route_legs` 6. `treasury_ledger_entries` 7. `treasury_fund_chains` 8. `treasury_advances` 9. `treasury_advance_settlements` 10. `treasury_cost_settlement_allocations` (self-FK) 11. `treasury_reconciliations` 12. `treasury_reconciliation_entries` (self-FK).

---

## 14. Tier B — existing-table FK tenant/project rules, clarified (fix #3)

| Reference | Existence | Tenant/project match |
|---|---|---|
| `treasury_payment_routes.linked_contract_payment_id` | DB-enforced | Same `tenant_id`; same project via the `ContractPayment`'s `Contract.project_id` = route's own `project_id` |
| `treasury_cost_settlement_allocations.cost_source_contract_expense_id` | DB-enforced | Same `tenant_id`; same project via the expense's owning `Contract.project_id`, which must equal **the allocation's own Treasury-side parent's project** — clarified this round: that parent is the `financial_document`'s `project_id` when `financial_document_id` is set, or (via `advance_settlement_id → treasury_advances.project_id`) when `advance_settlement_id` is set instead |
| `treasury_cost_settlement_allocations.cost_source_material_receipt_line_id` | DB-enforced | Same rule as above, via the receipt's owning project |

**Fix #3, clarification:** v8's wording ("same project as the allocation") was ambiguous about *which* of the allocation's two possible parents (direct expense document vs. advance settlement) supplies the comparison project when they could, in principle, name different things. The rule above resolves this explicitly: whichever of `financial_document_id`/`advance_settlement_id` is actually set on the allocation row (exactly one, per §7's `CHECK`) is the Treasury-side project source to compare against.

---

## 15. Treasury-internal same-project integrity — restored from v8, fix #3 adds the missing `advance_settlement ↔ financial_document` join

| # | Reference | Binding rule |
|---|---|---|
| 1 | `treasury_payment_routes.linked_financial_document_id` → document's `project_id` | Must be equal |
| 2 | `treasury_advances.originating_financial_document_id` → document's `project_id` | Must be equal |
| 3 | `treasury_fund_chain_members.member_financial_document_id` / `member_payment_route_id` → parent chain's `project_id` | Must be equal |
| 4 | `treasury_financial_documents.reversed_document_id` / `replacement_document_id` (self-ref) | Must be equal |
| 5 | `treasury_financial_documents.source_wallet_id` / `destination_wallet_id`, `treasury_payment_route_legs.from_wallet_id` / `to_wallet_id` → wallet's `project_id` | Must be equal, **except** company/shared wallets (`project_id IS NULL`), compatible with any project |
| 6 | `treasury_ledger_entries.wallet_id` vs. its source's project | Must be equal, same company-wallet exception |
| 7 | **`treasury_advance_settlements.financial_document_id` → document's `project_id`, when `settlement_type = cash_return`** (`financial_document_id` is set only in this case, §7.3) | **New this round (fix #3)** — must equal the settlement's own project, via its parent `treasury_advances.project_id` |

**Enforcement:** the same `TreasuryReferentialIntegrityService`-equivalent named throughout prior rounds, validated at write time, under the same lock discipline as §11 wherever the write participates in a named aggregate check.

---

## 16. Exact table inventory and migration order — unchanged (14 tables)

1. `treasury_financial_parties` 2. `treasury_wallets` 3. `treasury_financial_documents` 4. `treasury_payment_routes` 5. `treasury_payment_route_legs` 6. `treasury_ledger_entries` 7. `treasury_fund_chains` 8. `treasury_advances` 9. `treasury_advance_settlements` 10. `treasury_cost_settlement_allocations` 11. `treasury_expense_approvals` 12. `treasury_reconciliations` 13. `treasury_fund_chain_members` 14. `treasury_reconciliation_entries`. **No migration file exists yet.**

---

## 17. Verification against every held-constant item

- A3/A4-a/A.5/B2/B2-T/C/D: unchanged, not reopened.
- 14-table count, 12 composite-FK targets, two fund-chain indexes, ledger reversal's own vocabulary, MySQL/SQLite wording, external-entry route-leg conservation, advance/cost allocation conservation, reconciliation actor: all unchanged from Round 8.
- Fix #1's immutability rule verified not to conflict with §4.1 Case A (`ContractPayment` routes) — Case A's `total_allocated_amount` was already fixed at creation in substance; this round only makes that explicit and consistent with Case B's now-stated rule.
- Fix #2's completion predicate verified against §4.2's existing leg-custody mechanics — it reads the same `status`/balance data already maintained by every leg write, introducing no new data requirement, only a new gating check.
- Fix #3's eligibility table verified exhaustive against every `document_type` in §2 — each of the 8 values is explicitly classified.

---

## 18. Trạng thái và bước tiếp theo
- Nếu Owner Approve: chuẩn bị Gate 3 cho GAP-037 — vẫn chỉ là quyết định merge tài liệu.
- Nếu Owner Request changes: sẽ tạo `02-design-v10.md` (supersedes bản này).
- Nếu Owner Decline: dừng GAP-037 ở schema-proposal này.

## 19. Loại trừ phạm vi
Kế thừa nguyên vẹn từ mọi round trước: không migration file thật; không model/controller/service/route/UI/test thật; không seed/backfill; không implementation plan coi schema này là đã duyệt cho Gate 3; không Gate 3 tự suy luận; không mark PR ready; không merge PR #263; không sửa/merge/đóng PR #245 hoặc #257; không GAP-036; không Today Workspace; không sửa canonical SSOT stale metadata; không production/deployment.

## Decision Needed
**Resolved 2026-08-16T18:58:47+07:00 — Owner Decision: REQUEST CHANGES.** The 3 closure items from Revision 8 were confirmed handled correctly; architecture A3+A4-a+A.5/B2+B2-T/C/D remains approved, frozen, not reopened. Two further points required: (1) make financial-document route completion endpoint-aware — no single generic "sum route custody = total_allocated_amount" predicate for every document type; wallet-terminating inbound documents must prove the full amount reached `destination_wallet_id` specifically with no residual at intermediary wallets; `internal_transfer` must prove the net route effect (`source_wallet = -amount`, `destination_wallet = +amount`, all intermediary wallets `= 0`), with no leg `in_transit`; (2) determine reversal route eligibility from the reversal's own endpoint shape, not inherited mechanically from the original document — a reversal producing a wallet-to-external movement the current leg schema cannot represent must be `direct`; `via_route` is only permitted when the reversal's own resulting source/destination shape is representable by the current `payment_route_legs` model. This packet (`02-design-v9.md`) is now **frozen** — no further edits. `docs/owner-decisions/GAP-037/02-design-v10.md` (self-contained) follows in the next commit.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt migration file thật hay chi tiết implementation. Owner cũng không được yêu cầu duyệt lại architecture set A3/A4-a/A.5/B2/B2-T/C/D — đã approved, không mở lại. Owner cũng không được yêu cầu duyệt overpayment/prepayment semantics, hay thiết kế external-destination leg representation (nêu là future extension point, chưa thiết kế ở đây).
