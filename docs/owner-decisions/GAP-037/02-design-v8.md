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
  recorded_at: "2026-08-16T18:46:10+07:00"
  owner_response_reference: "Owner Gate 2 Schema Proposal Revision 8 decision — REQUEST CHANGES, recorded in-session on 2026-08-16 against reviewed PR #263 head 43897988d486adf0e07b48edcbe33c131e139bbb: 'GAP-037 — Gate 2 Schema Proposal Revision 8 — Owner Decision: REQUEST CHANGES. Tôi, Owner, yêu cầu chỉnh sửa schema proposal tại PR #263, reviewed head 43897988d486adf0e07b48edcbe33c131e139bbb. Tôi xác nhận hai correction của Revision 8 đã xử lý đúng yêu cầu trước: Treasury-internal same-project rules đã được bổ sung cho các nhóm đã nêu; financial-document-linked route đã có exact amount equality và reconciliation branching theo posting_path. Architecture A3 + A4-a + A.5 / B2 + B2-T / C / D vẫn approved, frozen và không được mở lại. Revision 9 chỉ cần xử lý ba closure items: 1. Preserve route/document amount equality throughout lifecycle. Sau khi một document chọn posting_path=via_route, financial_document.amount và linked route total_allocated_amount không được drift. Prefer immutable after route attachment; nếu cho update thì equality phải được revalidated atomically under the same locks. 2. Require economic route completion before posted_reconciled. Với via_route, việc tất cả ledger entries hiện có đã reconciled chưa đủ. Document chỉ được posted_reconciled khi linked route đã hoàn thành toàn bộ economic movement của document và toàn bộ applicable route-ledger entries có active reconciliation. Nếu dùng route.status=completed, phải định nghĩa binding completion predicate; status không được set tùy ý trong khi route còn partial. 3. Close route eligibility and remaining derived-project joins. Với schema hiện tại (from_wallet_id nullable, to_wallet_id required), via_route chỉ được dùng cho document types mà endpoint model biểu diễn được. Không cho expense/advance cash-out chọn route nếu chưa có external-destination representation. Đồng thời bind same-project cho advance_settlement ↔ financial_document và cho cost_settlement_allocation giữa Treasury-side source và external cost source. Giữ nguyên toàn bộ v8 còn lại: 14 tables; 12 composite-FK targets; B2-T; route external-entry conservation; signed immutable ledger; reversal semantics; advance/cost allocation conservation; reconciliation actor; MySQL/SQLite locking; Tier-B rules; zero existing-table changes; #245/#257 untouched; không migration/model/controller/service/route/UI/tests; không Gate 3. Record REQUEST CHANGES trước vào 02-design-v8.md, freeze v8, tạo self-contained 02-design-v9.md, chạy lại required CI và quay lại awaiting_owner. Không được suy luận schema approval hoặc Gate 3 authorization.'"
  reconciliation_required: false
supersedes: docs/owner-decisions/GAP-037/02-design-v7.md
superseded_by: null
timestamps:
  created_at: "2026-08-16T18:17:11+07:00"
  updated_at: "2026-08-16T18:46:10+07:00"
generated_by: agent
---

# GAP-037 — Project Treasury: Gate 2 Revision 8 — Project-Integrity + Document-Linked-Route Fixes (Self-Contained)

**Status:** Gate 1 approved. Gate 2 architecture decisions **approved** (`docs/owner-decisions/GAP-037/02-design.md`, frozen) — **A3 + A4-a + A.5 / B2 + B2-T / C / D**, not reopened. Fully self-contained. Still Gate 2 — a proposal, not implementation. No migration file, model, controller, service, route, UI, or test exists or is authorized by this packet.

**Database compatibility (unchanged verification):** MySQL for production/dev (`config/database.php`, `.env.example`), SQLite for the test suite (`.env.testing`, `phpunit.xml`).

---

## 0. What changed vs. v7, at a glance

| # | Owner's finding | Fix |
|---|---|---|
| 1 | Composite `(tenant_id, id)` FKs prove tenant match only, never project match, for **internal** Treasury-to-Treasury references | New §15: binding same-project rule for 6 named reference groups, with an explicit company/shared-wallet (`project_id IS NULL`) exception |
| 2 | A financial-document-linked route wasn't required to carry the document's *entire* amount, and reconciliation never branched on `posting_path` | §4.1 gets a new binding equality (`route.total_allocated_amount = linked_financial_document.amount`, exact, not `≤`); §12's `posted_reconciled` transition rewritten to branch explicitly on `posting_path` |

---

## 1. `treasury_financial_parties` and `treasury_wallets` — unchanged from v7

**`treasury_financial_parties`**: `id`, `tenant_id`, `party_type`, `name`, `linked_account_id` (nullable), `linked_user_id` (nullable), timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_wallets`**: `id`, `tenant_id`, `project_id` (nullable — company/shared wallets), `wallet_type`, `name`, `custodian_party_id` (nullable, composite FK), timestamps. **Unique: `(tenant_id, id)`.**

---

## 2. `treasury_financial_documents` — unchanged shape from v7

`id`, `tenant_id`, `project_id`, `document_type` (funding|internal_transfer|expense|owner_contribution|advance|advance_return|reversal|adjustment), `status` (draft|submitted|approved|rejected|posted_unreconciled|posted_reconciled|reversed), `posting_path` (nullable enum: `direct`|`via_route`, set exactly once), `amount`, `source_wallet_id` (nullable, composite FK), `destination_wallet_id` (nullable, composite FK), `counterparty_id` (nullable, composite FK), `description`, `created_by`, `approved_by` (nullable), `posted_at` (nullable), `reversed_document_id` (nullable self-ref), `replacement_document_id` (nullable self-ref), timestamps. **Unique: `(tenant_id, id)`.**

Posting-path freeze (unchanged): route may only attach while `posting_path IS NULL` and unposted; attaching locks `posting_path = via_route`; reaching posting with no route locks `posting_path = direct`; immutable once set.

---

## 3. The typed-nullable-FK pattern — unchanged principle

Every former polymorphic reference is N nullable typed FK columns + a `CHECK` requiring exactly one non-null: `treasury_payment_routes` (§4), `treasury_ledger_entries` (§5), `treasury_cost_settlement_allocations` (§7), `treasury_fund_chain_members` (§8).

---

## 4. `treasury_payment_routes` and `treasury_payment_route_legs`

**`treasury_payment_routes`**: `id`, `tenant_id`, `project_id`, `total_allocated_amount`, `status` (planned|partial|completed|cancelled), `linked_financial_document_id` (nullable, composite FK), `linked_contract_payment_id` (nullable, single-column FK → `contract_payments(id)`), timestamps. `CHECK ((linked_financial_document_id IS NULL) != (linked_contract_payment_id IS NULL))`. Unique index directly on `linked_financial_document_id`. **Unique: `(tenant_id, id)`.**

### 4.1 Conservation — two cases, now both stated as binding equalities/bounds

**Case A — `linked_contract_payment_id` set (unchanged from Round 2):**
```
SUM(total_allocated_amount WHERE linked_contract_payment_id = <ContractPayment.id>) <= ContractPayment.amount
```
Multiple routes may share one `ContractPayment`, each carrying a partial allocation — bound, not equality. Lock the `contract_payments` row (§11, item 1).

**Case B — `linked_financial_document_id` set (new binding rule this round, fix #2):**
```
route.total_allocated_amount = linked_financial_document.amount
```
**Exact equality, not a bound.** Justification, restated from the Owner's own reasoning: a `treasury_financial_documents` row may have **at most one** associated route (§2's uniqueness), and when one exists, `posting_path = via_route` means the route's legs are the **sole** ledger-posting mechanism for that document (§2's posting-path freeze, §5.1's bridge) — no other posting path exists for it. A route that carried only *part* of the document's amount would leave the remainder economically unaccounted for, with no second posting path available to cover it. Therefore the route must represent the document's **entire** amount, exactly, not merely be bounded by it.

**Concurrency for Case B:** validated in the **same transaction and under the same lock** as the posting-path freeze itself (§11, item 5 — the `treasury_financial_documents` row) — this equality is checked at the exact moment a route is being attached to a document (the same moment `posting_path` is set to `via_route`), not as a separate, later check.

**`treasury_payment_route_legs`**: `id`, `tenant_id`, `payment_route_id` (composite FK), `sequence_no`, `from_wallet_id` (nullable, composite FK), `to_wallet_id` (composite FK), `amount`, `status` (in_transit|settled|reversed), `occurred_at`, timestamps. **Unique: `(tenant_id, id)`.**

### 4.2 Route-leg custody — wallet-backed and external-entry cases, unchanged from v7
Lock the parent route (§11, item 6). Wallet-backed leg (`from_wallet_id` set): validate against §5.3's balance formula. External-entry leg (`from_wallet_id IS NULL`): bounded by remaining `total_allocated_amount` instead of a wallet balance. Both persist leg + ledger entries atomically under the lock.

---

## 5. `treasury_ledger_entries` — unchanged from v7

`id`, `tenant_id`, `source_financial_document_id` (nullable, composite FK), `source_payment_route_leg_id` (nullable, composite FK), `wallet_id` (composite FK), `direction` (enum: `debit`\|`credit`), `amount`, `entry_type`, `posted_at`, `reversal_of_entry_id` (nullable self-ref, composite), `created_at`. `CHECK ((source_financial_document_id IS NULL) != (source_payment_route_leg_id IS NULL))`.

### 5.1 Ledger-source bridge, idempotency, balance formula, reversal (all unchanged)
Route leg posts directly (no wrapping document, per B2-T); Treasury-native movements post via `source_financial_document_id`. Idempotency via the generated-column `original_posting_key` (excludes reversals). `wallet_balance = SUM(credit) - SUM(debit)`. Reversal: same wallet/source/amount, opposite direction, `UNIQUE(reversal_of_entry_id)`, no reverse-of-reverse.

Index: `(source_financial_document_id)`, `(source_payment_route_leg_id)`, `(wallet_id, posted_at)`, `UNIQUE(reversal_of_entry_id)`, `UNIQUE(original_posting_key)`. **Unique: `(tenant_id, id)`.**

---

## 6. Settlement conservation — unchanged from v7

**6.1** `net_allocation(cost_source) = SUM(apply) - SUM(reverse)`, scoped to `cost_source`. **6.2** Direct-expense: `net_allocation(financial_document) = financial_document.amount`. **6.3** Per-cost-source: `0 <= net_allocation(cost_source) <= canonical_incurred_amount(cost_source)`. **6.5** Outstanding advance: `0 <= advance.amount - SUM(apply) + SUM(reverse) <= advance.amount`. **6.6** Any violating write rejected outright. **6.7** Material prepayment modeled via `treasury_advances`, never as an allocation against a nonexistent cost record.

---

## 7. `treasury_cost_settlement_allocations`, `treasury_advances`, `treasury_advance_settlements` — unchanged from v7

**`treasury_cost_settlement_allocations`**: `id`, `tenant_id`, `financial_document_id` (nullable, composite FK), `advance_settlement_id` (nullable, composite FK), `cost_source_contract_expense_id` (nullable, single-column FK), `cost_source_material_receipt_line_id` (nullable, single-column FK), `direction` (`apply`\|`reverse`), `allocated_amount`, `reverses_allocation_id` (nullable self-ref, composite), `created_at`. Two `CHECK`s. `UNIQUE(reverses_allocation_id)`. **Unique: `(tenant_id, id)`.**

**`treasury_advances`**: `id`, `tenant_id`, `project_id`, `financial_party_id` (composite FK), `originating_financial_document_id` (composite FK), `amount`, timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_advance_settlements`**: `id`, `tenant_id`, `advance_id` (composite FK), `settlement_type` (`approved_expense`\|`cash_return`), `direction` (`apply`\|`reverse`), `amount`, `financial_document_id` (nullable, composite FK), `reverses_settlement_id` (nullable self-ref, composite), `created_at`. `UNIQUE(reverses_settlement_id)`. **Unique: `(tenant_id, id)`.**

### 7.4 Advance-settlement completeness (unchanged from v7)
`apply` settlement: its own allocations (`direction='apply'`, `advance_settlement_id = this settlement`) sum to its amount. `reverse` settlement: atomically creates compensating allocations (`direction='reverse'`, `reverses_allocation_id` → each still-active original, `advance_settlement_id = this reverse settlement`) summing to its own amount, with complete 1:1 coverage of the original's still-active allocations.

---

## 8. `treasury_fund_chains` and `treasury_fund_chain_members` — unchanged from v7

**`treasury_fund_chains`**: `id`, `tenant_id`, `project_id`, `chain_reference`, `description`, timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_fund_chain_members`**: `id`, `tenant_id`, `fund_chain_id` (composite FK), `member_financial_document_id` (nullable, composite FK), `member_payment_route_id` (nullable, composite FK), timestamps. `CHECK` (exactly one member set). Two separate unique indexes: `(fund_chain_id, member_financial_document_id)`, `(fund_chain_id, member_payment_route_id)`.

---

## 9. Reversal invariants — unchanged from v7

`treasury_ledger_entries`: own debit/credit vocabulary (§5.1). The three event-log tables (`treasury_cost_settlement_allocations`, `treasury_advance_settlements`, `treasury_reconciliation_entries`, §12): `apply`/`reverse`, same-subject, exact-amount, at-most-once via `UNIQUE` on `reverses_*_id`, no reverse-of-reverse.

---

## 10. `treasury_expense_approvals` — unchanged

`id`, `tenant_id`, `financial_document_id` (composite FK), `event`, `from_status`, `to_status`, `actor_id`, `note` (nullable), `context` (nullable), `created_at`. No `(tenant_id, id)` unique needed.

---

## 11. Concurrency — unchanged from v7, item 5 now also covers §4.1 Case B

| # | Check | Row/subject locked |
|---|---|---|
| 1 | `ContractPayment` route-allocation conservation (§4.1 Case A) | `contract_payments` row |
| 2 | Cost over-settlement cap (§6.3) | `contract_expenses`/`material_receipt_lines` row |
| 3 | Advance outstanding cap + settlement completeness (§7.4) | `treasury_advances` row |
| 4 | Active reconciliation uniqueness (§12) | `treasury_ledger_entries` row |
| 5 | Financial-document posting-source selection (§2) **and** the §4.1 Case B equality (route ↔ document amount, checked at the same moment) | `treasury_financial_documents` row |
| 6 | Route-leg custody, both wallet-backed and external-entry (§4.2) | Parent `treasury_payment_routes` row |

MySQL: `SELECT ... FOR UPDATE`. SQLite (test suite): `BEGIN IMMEDIATE`.

---

## 12. `treasury_reconciliations` and `treasury_reconciliation_entries` — fix #2 (deterministic transition by posting path)

**`treasury_reconciliations`**: `id`, `tenant_id`, `wallet_id` (composite FK), `reconciliation_type`, `external_reference`, `reconciled_at`, `reconciled_by`, timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_reconciliation_entries`**: `id`, `tenant_id`, `reconciliation_id` (composite FK), `ledger_entry_id` (composite FK), `direction` (`apply`\|`reverse`), `reverses_reconciliation_entry_id` (nullable self-ref, composite), `actor_id` (FK → `users`), `created_at`. `UNIQUE(reverses_reconciliation_entry_id)`. **Unique: `(tenant_id, id)`.**

Whole-entry reconciliation (unchanged); covers every ledger entry regardless of source (unchanged); `reconciliation.wallet_id = ledger_entry.wallet_id` (unchanged).

### 12.1 Fix #2 — deterministic `posted_unreconciled → posted_reconciled`, branched by `posting_path`

v7's rule only covered documents with `posting_path = direct`, and separately noted route-leg-sourced entries were reconcilable but didn't drive any document's status — correct as far as it went, but incomplete once a `linked_financial_document_id`-routed document exists: that document's *entire* economic outcome now lives in the route's legs (§4.1 Case B), yet nothing tied the document's own status to those entries being reconciled. Corrected, branching explicitly on `posting_path`:

- **`posting_path = direct`:** transitions to `posted_reconciled` when every ledger entry with `source_financial_document_id = <this document>` has a currently-active `apply` reconciliation-entry row. *(Unchanged from Round 4/6.)*
- **`posting_path = via_route`:** transitions to `posted_reconciled` when every ledger entry with `source_payment_route_leg_id` belonging to **any leg of the document's linked route** (found via `treasury_payment_routes.linked_financial_document_id = <this document>`) has a currently-active `apply` reconciliation-entry row. This is well-defined and exhaustive precisely because §4.1 Case B guarantees the route represents the document's *entire* amount — there is no leftover economic movement outside the route's legs for this document.
- **`ContractPayment`-linked routes** (§4.1 Case A) are unaffected by this rule — they have no `linked_financial_document_id`, so no `treasury_financial_documents` row's status depends on their legs' reconciliation state; those legs' reconciliation is tracked purely at the `treasury_reconciliation_entries`/`treasury_ledger_entries` level, exactly as in Round 4–7, with no document-level transition to drive.

Index: `(reconciliation_id)`, `(ledger_entry_id)`, `(actor_id)`.

---

## 13. Composite-FK-target index requirement — unchanged from v6/v7 (12 tables)

1. `treasury_financial_parties` 2. `treasury_wallets` 3. `treasury_financial_documents` 4. `treasury_payment_routes` 5. `treasury_payment_route_legs` 6. `treasury_ledger_entries` 7. `treasury_fund_chains` 8. `treasury_advances` 9. `treasury_advance_settlements` 10. `treasury_cost_settlement_allocations` (self-FK) 11. `treasury_reconciliations` 12. `treasury_reconciliation_entries` (self-FK).

---

## 14. Tier B — existing-table FK tenant/project rules (unchanged from v7)

| Reference | Existence | Tenant/project match |
|---|---|---|
| `treasury_payment_routes.linked_contract_payment_id` | DB-enforced (single-column FK) | Same `tenant_id`; same project via the `ContractPayment`'s `Contract.project_id` = route's `project_id` |
| `treasury_cost_settlement_allocations.cost_source_contract_expense_id` | DB-enforced | Same `tenant_id`; same project via the expense's owning `Contract.project_id` |
| `treasury_cost_settlement_allocations.cost_source_material_receipt_line_id` | DB-enforced | Same `tenant_id`; same project via the receipt's owning project |

---

## 15. Fix #1 — Treasury-internal same-project integrity (new this round)

**The gap, precisely:** a composite `(tenant_id, id)` foreign key (§13) guarantees the referenced row exists **and** belongs to the same tenant — it does **not** guarantee the referenced row belongs to the same *project*, since `project_id` is not part of the FK's own column set on either side. Every internal Treasury-to-Treasury reference that has project semantics therefore needs an explicit, binding, application-layer same-project rule, exactly as Tier B (§14) already requires for the three existing-table cases. This section is that rule, restated for the internal case, covering every reference the Owner named:

| # | Reference | Binding rule |
|---|---|---|
| 1 | `treasury_payment_routes.linked_financial_document_id` → `treasury_financial_documents.project_id` | Must be equal — the route's own `project_id` must match the linked document's `project_id` exactly |
| 2 | `treasury_advances.originating_financial_document_id` → `treasury_financial_documents.project_id` | Must be equal — the advance's `project_id` must match its originating document's `project_id` |
| 3 | `treasury_fund_chain_members.member_financial_document_id` / `member_payment_route_id` → parent `treasury_fund_chains.project_id` | Must be equal — a chain may only contain members from the same project as the chain itself |
| 4 | `treasury_financial_documents.reversed_document_id` / `replacement_document_id` (self-ref) | Must be equal — a document may only reverse or replace another document in the same project |
| 5 | `treasury_financial_documents.source_wallet_id` / `destination_wallet_id`, and `treasury_payment_route_legs.from_wallet_id` / `to_wallet_id` → `treasury_wallets.project_id` | Must be equal, **with one explicit exception**: a company/shared wallet (`treasury_wallets.project_id IS NULL`) is compatible with a reference from **any** project, since such a wallet is not project-scoped by definition (matches the existing `wallet_type: company_bank`/`company_cash` semantics from §1). A `project_wallet`-typed wallet (non-null `project_id`) must match exactly |
| 6 | `treasury_ledger_entries.wallet_id` compatibility with its source's project (`source_financial_document_id`'s `project_id`, or — for route-leg-sourced entries — the leg's parent route's `project_id`) | Must be equal, subject to the same company-wallet exception as row 5 (a ledger entry crediting/debiting a company wallet is compatible regardless of the source's project) |

**Enforcement:** the same `TreasuryReferentialIntegrityService`-equivalent named throughout prior rounds validates every row above at write time, under the same lock discipline as §11 wherever the write also participates in a named aggregate check (rows 1 and 2 above occur under §11 items 5 and 3 respectively, since they're checked at the same moment as those aggregate operations; rows 3–6 occur under whichever lock the enclosing write already requires, or independently if none applies).

---

## 16. Exact table inventory and migration order — unchanged (14 tables)

1. `treasury_financial_parties` 2. `treasury_wallets` 3. `treasury_financial_documents` 4. `treasury_payment_routes` 5. `treasury_payment_route_legs` 6. `treasury_ledger_entries` 7. `treasury_fund_chains` 8. `treasury_advances` 9. `treasury_advance_settlements` 10. `treasury_cost_settlement_allocations` 11. `treasury_expense_approvals` 12. `treasury_reconciliations` 13. `treasury_fund_chain_members` 14. `treasury_reconciliation_entries`. **No migration file exists yet.**

---

## 17. Verification against every held-constant item

- A3/A4-a/A.5/B2/B2-T/C/D: unchanged, not reopened.
- 14-table count, 12 composite-FK targets, two fund-chain indexes, ledger reversal's own vocabulary, MySQL/SQLite wording, Tier-B existing-table rules: all unchanged from Round 7.
- §4.1 Case A (`ContractPayment`, bound `<=`) and Case B (`linked_financial_document_id`, equality `=`) verified to be two genuinely different, correctly-justified rules — not accidentally conflated.
- §12.1's branch-by-`posting_path` rule verified exhaustive: every posted document has exactly one of the two `posting_path` values (never both, never neither, per §2's freeze), so the two branches cover every case with no gap.

---

## 18. Trạng thái và bước tiếp theo
- Nếu Owner Approve: chuẩn bị Gate 3 cho GAP-037 — vẫn chỉ là quyết định merge tài liệu.
- Nếu Owner Request changes: sẽ tạo `02-design-v9.md` (supersedes bản này).
- Nếu Owner Decline: dừng GAP-037 ở schema-proposal này.

## 19. Loại trừ phạm vi
Kế thừa nguyên vẹn từ mọi round trước: không migration file thật; không model/controller/service/route/UI/test thật; không seed/backfill; không implementation plan coi schema này là đã duyệt cho Gate 3; không Gate 3 tự suy luận; không mark PR ready; không merge PR #263; không sửa/merge/đóng PR #245 hoặc #257; không GAP-036; không Today Workspace; không sửa canonical SSOT stale metadata; không production/deployment.

## Decision Needed
**Owner đã chọn: Request changes**, tại PR #263 head `43897988d486adf0e07b48edcbe33c131e139bbb` (2026-08-16) — xác nhận 2 correction của Revision 8 đạt yêu cầu; 3 closure items còn lại: (1) preserve route/document amount equality throughout lifecycle (immutable after attachment); (2) require binding route-completion predicate before posted_reconciled for via_route, not just entry-level reconciliation; (3) close route eligibility (expense/advance not eligible for via_route until external-destination representation exists) and remaining derived-project joins (advance_settlement↔financial_document, cost_settlement_allocation's Treasury-side project source). Architecture A3+A4-a+A.5/B2+B2-T/C/D confirmed unchanged. Chi tiết nguyên văn tại `decision_provenance.owner_response_reference`. **This packet (`02-design-v8.md`) is now frozen — no further content edits.** `docs/owner-decisions/GAP-037/02-design-v9.md`, self-contained, addressing these 3 points, follows in the next commit.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt migration file thật hay chi tiết implementation. Owner cũng không được yêu cầu duyệt lại architecture set A3/A4-a/A.5/B2/B2-T/C/D — đã approved, không mở lại. Owner cũng không được yêu cầu duyệt overpayment/prepayment semantics.
