# GAP-038 Option B — v17 CHECK conformance implementation mapping

**Correction (2026-08-18, Owner REQUEST CHANGES on candidate `f4cefe59`):**
the first candidate used SQLite `BEFORE INSERT`/`BEFORE UPDATE` triggers as
a DB-enforced substitute for SQLite `CHECK` clauses. Though genuinely
DB-engine-enforced and semantically equivalent, this was a design
deviation from the literal approved Option B ("the same approved CHECK
expressions embedded as actual CHECK clauses in the initial CREATE TABLE")
and was not authorized as a silent substitution. Corrected below.

Mechanism: `App\Support\Treasury\TreasuryCheckConstraint::createTableWithChecks(
$table, $definition, $checks)`, called in place of `Schema::create()` in
each affected migration's `up()`. `$checks` is `[constraintName =>
booleanExpression]`; the exact same expression string is used verbatim on
both drivers (no `NEW.`-qualification needed — a `CHECK` clause in a
`CREATE TABLE` statement, unlike a trigger's `WHEN` clause, references bare
column names on both engines).

- **MySQL:** `$definition` runs through `Schema::create()` unchanged, then
  `ALTER TABLE ... ADD CONSTRAINT ... CHECK (...)` is run for each check
  (available since MySQL 8.0.16; this repo's CI/production target is
  `mysql:8.0`).
- **SQLite** (cannot `ALTER TABLE ADD CONSTRAINT CHECK` after creation, but
  a `CHECK` clause CAN be part of the table's *initial* `CREATE TABLE` —
  every affected table here is newly created, never altered): `$definition`
  is run through a real `Blueprint` to obtain Laravel's own compiled
  `create table (...)` SQL string via `Blueprint::toSql()` — byte-identical
  to what `Schema::create()` would have executed, so columns/FKs/PK/indexes
  are Laravel's own untouched compiler output, never hand-duplicated — and
  only that one string is text-spliced to insert
  `, CONSTRAINT "name" CHECK (expr)` clauses immediately before its closing
  parenthesis, before executing every statement in the same order
  `Blueprint::build()` itself uses.

Verified via direct `sqlite_master` introspection (not merely by observing
rejected writes) that every table's `CREATE TABLE` SQL text contains a real
`CONSTRAINT "..." CHECK (...)` clause and that zero triggers exist on any
Treasury table. `EnforcesRowInvariants` (Eloquent `saving` event) remains
in every model unchanged, as defense-in-depth per Option B.

| # | v17 CHECK (verbatim) | Table.column(s) | Current app-layer guard | MySQL native | SQLite native | Raw-SQL-rejection test |
|---|---|---|---|---|---|---|
| 1 | `CHECK (amount > 0)` | `treasury_financial_documents.amount` | `EnforcesRowInvariants::$positiveAmountColumns` | `ALTER TABLE ... CHECK (amount > 0)` | trigger `WHEN NOT (NEW.amount > 0)` | insert `amount = 0` via `DB::table()->insert()` → expect DB exception |
| 2 | `CHECK NOT (source_wallet_id IS NOT NULL AND source_party_id IS NOT NULL)` | `treasury_financial_documents.{source_wallet_id,source_party_id}` | `$mutuallyExclusivePairs` | `CHECK (NOT (source_wallet_id IS NOT NULL AND source_party_id IS NOT NULL))` | trigger `WHEN NOT (NOT (NEW.source_wallet_id IS NOT NULL AND NEW.source_party_id IS NOT NULL))` | insert both non-null via raw insert → rejected |
| 3 | `CHECK NOT (destination_wallet_id IS NOT NULL AND destination_party_id IS NOT NULL)` | `treasury_financial_documents.{destination_wallet_id,destination_party_id}` | `$mutuallyExclusivePairs` | same pattern | same pattern | insert both non-null via raw insert → rejected |
| 4 | `CHECK (total_allocated_amount > 0)` | `treasury_payment_routes.total_allocated_amount` | `$positiveAmountColumns` | `CHECK (total_allocated_amount > 0)` | trigger `WHEN NOT (NEW.total_allocated_amount > 0)` | insert `0` via raw insert → rejected |
| 5 | `CHECK ((linked_financial_document_id IS NULL) != (linked_contract_payment_id IS NULL))` | `treasury_payment_routes.{linked_financial_document_id,linked_contract_payment_id}` | `$exactlyOneOfGroups` | `CHECK ((linked_financial_document_id IS NULL) != (linked_contract_payment_id IS NULL))` | trigger `WHEN NOT ((NEW.linked_financial_document_id IS NULL) != (NEW.linked_contract_payment_id IS NULL))` | insert both null / both set via raw insert → rejected |
| 6 | `CHECK ((linked_contract_payment_id IS NOT NULL) = (expected_destination_wallet_id IS NOT NULL))` | `treasury_payment_routes.{linked_contract_payment_id,expected_destination_wallet_id}` | `$coNullablePairs` | `CHECK ((linked_contract_payment_id IS NOT NULL) = (expected_destination_wallet_id IS NOT NULL))` | trigger `WHEN NOT ((NEW.linked_contract_payment_id IS NOT NULL) = (NEW.expected_destination_wallet_id IS NOT NULL))` | insert one set, other null via raw insert → rejected |
| 7 | `CHECK (amount > 0)` | `treasury_payment_route_legs.amount` | `$positiveAmountColumns` | `CHECK (amount > 0)` | trigger `WHEN NOT (NEW.amount > 0)` | insert `0`/negative via raw insert → rejected |
| 8 | `CHECK (amount > 0)` | `treasury_ledger_entries.amount` | `$positiveAmountColumns` | `CHECK (amount > 0)` | trigger `WHEN NOT (NEW.amount > 0)` | insert `0` via raw insert → rejected |
| 9 | `CHECK` exactly one source | `treasury_ledger_entries.{source_financial_document_id,source_payment_route_leg_id}` | `$exactlyOneOfGroups` | `CHECK ((source_financial_document_id IS NULL) != (source_payment_route_leg_id IS NULL))` | trigger, same pattern | insert both/neither via raw insert → rejected |
| 10 | `CHECK (allocated_amount > 0)` | `treasury_cost_settlement_allocations.allocated_amount` | `$positiveAmountColumns` | `CHECK (allocated_amount > 0)` | trigger `WHEN NOT (NEW.allocated_amount > 0)` | insert `0` via raw insert → rejected |
| 11 | `CHECK` exactly one of financial_document/advance_settlement | `treasury_cost_settlement_allocations.{financial_document_id,advance_settlement_id}` | `$exactlyOneOfGroups` | `CHECK ((financial_document_id IS NULL) != (advance_settlement_id IS NULL))` | trigger, same pattern | insert both/neither → rejected |
| 12 | `CHECK` exactly one of cost_source_contract_expense/cost_source_material_receipt_line | `treasury_cost_settlement_allocations.{cost_source_contract_expense_id,cost_source_material_receipt_line_id}` | `$exactlyOneOfGroups` | `CHECK ((cost_source_contract_expense_id IS NULL) != (cost_source_material_receipt_line_id IS NULL))` | trigger, same pattern | insert both/neither → rejected |
| 13 | `CHECK (amount > 0)` | `treasury_advances.amount` | `$positiveAmountColumns` | `CHECK (amount > 0)` | trigger `WHEN NOT (NEW.amount > 0)` | insert `0` via raw insert → rejected |
| 14 | `CHECK (amount > 0)` | `treasury_advance_settlements.amount` | `$positiveAmountColumns` | `CHECK (amount > 0)` | trigger `WHEN NOT (NEW.amount > 0)` | insert `0` via raw insert → rejected |
| 15 | `CHECK` exactly one member | `treasury_fund_chain_members.{member_financial_document_id,member_payment_route_id}` | `$exactlyOneOfGroups` | `CHECK ((member_financial_document_id IS NULL) != (member_payment_route_id IS NULL))` | trigger, same pattern | insert both/neither → rejected |

15 individual CHECK clauses across 8 tables (matches the 8-row summary table
in `GAP-038/02-design.md`, which grouped per-table). Every clause's boolean
predicate is copied verbatim from `02-design-v17.md`'s own syntax (already
MySQL-8/SQLite-compatible: `IS NULL`, `!=`, `=`, `NOT`, `AND` all evaluate
identically on both engines) — no invariant is reworded or weakened.

Tables with zero CHECK requirement in v17 (`treasury_financial_parties`,
`treasury_wallets`, `treasury_fund_chains`, `treasury_expense_approvals`,
`treasury_reconciliations`, `treasury_reconciliation_entries`) are
unaffected — v17 does not require one, so none is added.
