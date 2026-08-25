---
work_id: GAP-038
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/owner-decisions/GAP-037/02-design-v17.md
  plan: null
  branch: docs/GAP-038-project-treasury-implementation-gate1-prep
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-18T09:45:00+07:00"
  owner_response_reference: "Owner Gate 2 decision -- APPROVE OPTION B, recorded in-session on 2026-08-18 in direct reply to this packet's three-option presentation (Option A: accept application-layer-only deviation; Option B: require native DB CHECK constraints, engineering-recommended; Option C: request an alternative design), against reviewed main HEAD dbe662972755493f675970e30022083622a9f066 and preserved WIP HEAD cdf17b91dceb9b5bc7a578a5d4884144f926bf06 (re-verified unchanged immediately before recording this decision). Owner's verbatim reply: 'APPROVE Option B'. Binding selection: GAP-038's implementation MUST add native database CHECK constraints for all 8 rows/clauses listed in this packet's 'Affected approved constraints' table, via DB::statement() -- ALTER TABLE ... ADD CONSTRAINT ... CHECK (...) on MySQL after Schema::create(), and CHECK clauses written directly into the initial CREATE TABLE statement on SQLite (all 14 tables are newly created, never altered, so this is available on both drivers). The application-layer EnforcesRowInvariants trait MAY remain in place as defense-in-depth per Option B's own text, but the native DB constraint is the authoritative guarantee, not a substitute for it. Authorization scope: this decision authorizes GAP-038 implementation to proceed using Option B's design -- it does not authorize merge, deploy, or any production change (GAP-038's own future Gate 3, not opened here); it does not modify, merge, or authorize merging the preserved WIP branch cdf17b91 as-is (that branch currently implements Option A and does not yet conform to this decision -- any future implementation work must add the CHECK constraints before that branch, or its successor, may be considered Gate-2-conformant); it does not reopen or alter GAP-037 Gate 1, Gate 2 architecture, 02-design-v17.md, 03-release.md, or PR #263, all of which remain exactly as previously approved."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-18T09:10:00+07:00"
  updated_at: "2026-08-18T09:45:00+07:00"
generated_by: agent
---

## Decision Recorded

**Resolved 2026-08-18T09:45:00+07:00 — Owner Decision: APPROVE OPTION B.**
Native DB `CHECK` constraints are required for all 8 rows in the "Affected
approved constraints" table. `EnforcesRowInvariants` may remain as
defense-in-depth. See `decision_provenance.owner_response_reference` above
for the exact verbatim reply and full authorization boundary.

## Owner Summary

GAP-037's Owner-approved schema (`02-design-v17.md`) requires **native
database `CHECK` constraints** for a defined set of single-row invariants.
The preliminary implementation (local WIP branch, not authorized for merge)
currently enforces the equivalent rules only inside PHP, on Eloquent writes.
This packet asks the Owner to choose how GAP-038 must resolve that gap before
implementation is authorized to proceed. **This is a POST-GATE-3
IMPLEMENTATION CONFORMANCE / DESIGN DEVIATION decision, not a reopening of
GAP-037's frozen Gate 1, Gate 2 architecture, `02-design-v17.md`, or
`03-release.md` — none of those are touched, edited, or reinterpreted by this
packet.**

## What is frozen and not being reopened

- GAP-037 Gate 1 (business request): **unchanged**.
- GAP-037 Gate 2 architecture decisions (A3+A4-a+A.5/B2+B2-T/C/D):
  **unchanged**.
- `docs/owner-decisions/GAP-037/02-design-v17.md`: **unchanged, still the
  sole schema authority** this packet defers to. Every table name, column,
  index, lock order, and `CHECK` clause below is quoted from it verbatim,
  never restated with different content.
- `docs/owner-decisions/GAP-037/03-release.md`: **unchanged** — still records
  exactly what it always recorded: a docs/governance-only release, explicitly
  not an implementation authorization.
- PR #263 history: **unchanged**, not reopened, not amended.
- There is no "Gate 4." GAP-037's Gate 1→2→3 lifecycle is complete and
  terminal. This is GAP-038's **own Gate 2**, a new and separate lifecycle,
  as required by the canonical SSOT's "each implementation slice requires its
  own Work ID and Gate 1→2→3 lifecycle" rule (OWN-2026-009,
  §14 preamble).

## Exact reconciliation state at the time of this packet

- `main` HEAD: `dbe662972755493f675970e30022083622a9f066` (unchanged since the
  GAP-037 Gate 3 merge — zero commits landed on `main` since).
- WIP implementation branch: `worktree-gap037-treasury-migrations`, HEAD
  `cdf17b91dceb9b5bc7a578a5d4884144f926bf06`, tree
  `02da64cd2154b8308dfdd834e0fe35efe5e8b63d`. Confirmed still local-only
  (`git branch -r --contains` returns nothing), working tree clean before and
  after this preflight, **not modified by this packet**.
- Upstream schema authority: `docs/owner-decisions/GAP-037/02-design-v17.md`
  at `main` HEAD `dbe66297`, `gate_status: approved`, `owner_decision.value:
  approved`, `superseded_by: null` — the unique terminal schema packet.
- PR #245 (design evidence, non-normative): head `cd8b79d861f4c1bae5278b6c57f29cd14e505594`,
  OPEN, unchanged. PR #257 (untouched boundary): head
  `ded7cf9f558bd7960b5eff5836140b1e15255b9a`, OPEN, unchanged.

## Preflight evidence (this session, live-verified)

1. **Table-name conformance:** all 14 tables in v17 (`treasury_financial_parties`,
   `treasury_wallets`, `treasury_financial_documents`, `treasury_payment_routes`,
   `treasury_payment_route_legs`, `treasury_ledger_entries`, `treasury_fund_chains`,
   `treasury_advances`, `treasury_advance_settlements`,
   `treasury_cost_settlement_allocations`, `treasury_expense_approvals`,
   `treasury_reconciliations`, `treasury_fund_chain_members`,
   `treasury_reconciliation_entries`) are present in the WIP's migrations,
   name-for-name, 1:1, zero extras, zero omissions.
2. **Test baseline:** `tests/Unit/Migrations/Treasury/*` +
   `tests/Unit/Models/Treasury/*` — **40 tests, 93 assertions, all pass**,
   run live against the preserved WIP head (SQLite driver, the same driver
   this repository's local/CI test suite always uses per `phpunit.xml`).
3. **D-constraint boundary:** `ReportPageController::cashflow()` untouched
   since before GAP-037 began (last real change `c81ffb1c`, unrelated to
   Treasury) — the architecture's cost/cash separation boundary is intact.
4. **The CHECK-constraint gap (below) is the only conformance gap found.**
   Every other structural element of v17 — columns, composite FKs, unique
   indexes, ULID keys, `TenantScope` — is present and matches.

## Affected approved constraints (quoted from `02-design-v17.md`, unchanged)

| # | Table | `CHECK` clause (verbatim from v17) |
|---|-------|-------------------------------------|
| 1 | `treasury_financial_documents` | `CHECK (amount > 0)`; `CHECK NOT (source_wallet_id IS NOT NULL AND source_party_id IS NOT NULL)`; `CHECK NOT (destination_wallet_id IS NOT NULL AND destination_party_id IS NOT NULL)` |
| 2 | `treasury_payment_routes` | `CHECK (total_allocated_amount > 0)`; `CHECK ((linked_financial_document_id IS NULL) != (linked_contract_payment_id IS NULL))`; `CHECK ((linked_contract_payment_id IS NOT NULL) = (expected_destination_wallet_id IS NOT NULL))` |
| 3 | `treasury_payment_route_legs` | `CHECK (amount > 0)` |
| 4 | `treasury_ledger_entries` | `CHECK (amount > 0)`; `CHECK` (exactly one of `source_financial_document_id`/`source_payment_route_leg_id` set) |
| 5 | `treasury_cost_settlement_allocations` | `CHECK (allocated_amount > 0)`; `CHECK` (exactly one of `financial_document_id`/`advance_settlement_id` set); `CHECK` (exactly one of `cost_source_contract_expense_id`/`cost_source_material_receipt_line_id` set) |
| 6 | `treasury_advances` | `CHECK (amount > 0)` |
| 7 | `treasury_advance_settlements` | `CHECK (amount > 0)` |
| 8 | `treasury_fund_chain_members` | `CHECK` (exactly one of `member_financial_document_id`/`member_payment_route_id` set) |

All seven `amount`-bearing columns v17 §2.1b names are covered (row 1-3, 5-7
above). Uniqueness constraints (`UNIQUE(reversed_document_id)`,
`UNIQUE(reversal_of_entry_id)`, etc.) are a separate mechanism, already
implemented as real unique indexes in the WIP migrations, and are **not**
part of this finding — this packet is scoped to the `CHECK`-clause rows only.

## Implementation deviation (current WIP state)

`app/Models/Treasury/Concerns/EnforcesRowInvariants.php` (trait, hooked into
Eloquent's `saving` event) re-implements every row above in PHP:
`positiveAmountColumns`, `mutuallyExclusivePairs`, `exactlyOneOfGroups`, and
`coNullablePairs` per-model config arrays. I traced every model's config
against the table above line-by-line: **coverage is complete** — all 8 rows,
all sub-clauses. This is a **semantic-equivalence, architectural-nonconformance**
gap: every invariant v17 requires is enforced *when a write goes through
Eloquent*. It is not enforced by the database itself, and is therefore not
enforced against `DB::table(...)->insert(...)`, bulk inserts, raw
`DB::statement()`, artisan tinker, queued jobs using the query builder, or
any future write path that does not instantiate and save an Eloquent model.

## Independent technical verification (not taking the WIP's own claim on faith)

I re-verified the premise stated in the trait's own doc-comment
("Laravel 12's Schema Blueprint has no fluent `check()` builder, and SQLite
cannot `ALTER TABLE ADD CONSTRAINT CHECK`") directly against this
repository's vendor code and CI configuration, rather than accepting it as
given:

- **Blueprint/Grammar CHECK support:** grepped
  `vendor/laravel/framework/src/Illuminate/Database/Schema/Blueprint.php`
  and every file under `.../Schema/Grammars/*.php` for `CHECK` — **zero
  fluent CHECK-constraint generation exists anywhere in this framework
  version** (`^12.0`, resolved 12.63.0). The only `CHECK` hits are unrelated
  (`FOREIGN_KEY_CHECKS` pragma statements). **Confirmed true**, not a
  fabricated excuse.
- **This does not make native CHECK infeasible — only unavailable through
  the fluent builder.** `DB::statement()` (raw SQL) remains fully available
  inside any migration on both drivers.
- **MySQL (production/CI target, confirmed `mysql:8.0` in every relevant
  GitHub Actions workflow):** MySQL has supported `CHECK` constraints
  (enforced, not silently ignored) since 8.0.16 — the `mysql:8.0` Docker tag
  tracks current 8.0.x, well past that floor. `ALTER TABLE ... ADD
  CONSTRAINT ... CHECK (...)` after `Schema::create()` works with no
  ordering hazard (unlike the composite-FK-vs-unique-index ordering bug this
  same WIP already found and fixed — CHECK addition has no equivalent
  dependency).
- **SQLite (confirmed local/test driver — `phpunit.xml`:
  `DB_CONNECTION=sqlite`):** SQLite's `ALTER TABLE` genuinely cannot add a
  `CHECK` constraint to an existing table (real, verified limitation) — but
  every one of these 14 tables is **newly created**, never altered, so the
  constraint can be written directly into the table's original `CREATE
  TABLE` statement. SQLite has supported inline `CHECK` clauses in `CREATE
  TABLE` since its earliest versions — this is not a gap, it is a
  requirement to construct that one raw statement instead of using
  `Schema::create()`'s fluent output verbatim for the affected columns.
- **Conclusion: native `CHECK` constraints are technically implementable on
  both drivers this repository actually uses**, via a driver-conditional
  migration technique (raw `ALTER`/`CREATE` via `DB::statement()`), not an
  exotic or unsupported one. The SQLite/Blueprint limitations cited by the
  WIP are real, but they are implementation-technique obstacles, not design
  blockers — per instruction, they are not treated as sufficient reason to
  waive the approved invariant on their own.

## Risk / trade-off comparison

| Axis | Option A (app-layer only) | Option B (native DB CHECK) |
|---|---|---|
| Protection against raw SQL / non-Eloquent writers | **None** — bulk inserts, `DB::table()`, tinker, queued raw writes are all unprotected | **Full** — enforced by the database engine regardless of write path |
| Data integrity at the DB boundary | Depends entirely on every write path remembering to go through the model | Guaranteed at the storage layer itself; cannot be bypassed by a mistake in application code |
| MySQL production behavior | N/A (PHP-only) | Native, well-supported since 8.0.16; this repo already targets `mysql:8.0` in CI/production config |
| SQLite test behavior | N/A (PHP-only) | Requires the constraint to be present at initial `CREATE TABLE` (all 14 tables qualify — none are alterations of existing tables) |
| Migration complexity | None beyond what already exists | Moderate: driver-conditional raw SQL in ~13 of 14 migrations; shareable via one small helper to avoid per-file duplication |
| Rollback/reversibility | Trivial (trait is just PHP) | Reversible: `down()` drops the whole table in both cases (these are all brand-new tables), so no separate constraint-drop step is even needed |
| Maintainability | Single PHP file, easy to read, but can silently drift from the DB's real behavior if a write path bypasses it | Constraint lives with the table it governs; visible via `SHOW CREATE TABLE` (MySQL) / `sqlite_master` (SQLite); cannot silently drift from what the database actually enforces |
| Risk of code paths bypassing model events | **Real, not hypothetical** — Eloquent's own documented behavior: bulk `insert()`, `upsert()`, and query-builder writes do **not** fire model events, so `EnforcesRowInvariants` never runs for them even from application code, not only "external" raw SQL | Not applicable — the DB enforces regardless of which application code path performed the write |
| Layered design (DB CHECK + app validation together) | Not layered — a single point of enforcement | This is the standard defense-in-depth pattern: app-layer validation for fast, friendly errors; DB constraint as the last-resort, unbypassable guarantee. `EnforcesRowInvariants` can and should remain in place under Option B, not be removed |

**Independent engineering assessment** (not chosen merely because the WIP
already implements Option A): native DB `CHECK` constraints are technically
feasible on both drivers this repository actually targets, with bounded,
moderate migration complexity and no rollback complication (every affected
table is newly created). Given this is a **financial ledger domain**, where
a single bulk-insert or raw-SQL write bypassing an invariant is a real and
plausible failure mode (not a contrived one — bulk `insert()`/`upsert()`
bypassing Eloquent events is core, documented Laravel behavior, not an edge
case), the DB-boundary guarantee that Option B provides is the materially
stronger design on almost every axis evaluated above. Option A's only
advantage is that it requires no further migration engineering effort today.

## Options

### OPTION A — ACCEPT DEVIATION

Permit application-layer enforcement (`EnforcesRowInvariants`) as the
GAP-038 implementation's answer to v17's `CHECK` requirement, instead of
native DB constraints. If selected, GAP-038's implementation must explicitly
document, in its own plan and code comments:
- Eloquent is treated as the controlled write boundary for these 14 tables —
  no raw/bulk write path may ever be added without also enforcing these
  invariants manually at that call site.
- Direct/raw DB writes do **not** receive equivalent protection; this is an
  accepted, named gap, not an oversight.
- `02-design-v17.md` is **not** rewritten retroactively to say "application
  layer" instead of "CHECK" — it stays exactly as Owner-approved; this
  option is recorded as an explicit, Owner-authorized **implementation
  deviation** from the literal schema text, not a schema correction.

### OPTION B — REQUIRE STRICT V17 CONFORMANCE

Require native DB `CHECK` constraints, added via `DB::statement()`, in
production MySQL (`ALTER TABLE ... ADD CONSTRAINT ... CHECK (...)` after
`Schema::create()`) and preserve SQLite testability by constructing the
initial `CREATE TABLE` for these 14 tables with the `CHECK` clauses inline
(driver-conditional migration code). `EnforcesRowInvariants` may remain in
place as defense-in-depth — fast, friendly PHP-level errors before a write
ever reaches the database — but does not replace the DB constraint as the
authoritative guarantee.

### OPTION C — REQUEST ALTERNATIVE DESIGN

Neither A nor B is accepted yet. Engineering must propose a different
cross-DB strategy (e.g., a shared migration macro, a different SQLite
technique, or a phased MySQL-first/SQLite-deferred approach) before
implementation resumes.

## Engineering recommendation

**Option B**, based on the independent assessment above: the technical
premise that blocked native CHECK constraints in the WIP (no fluent
Blueprint support) is real, but does not extend to "infeasible" — raw SQL
closes the gap on both drivers this repository targets, with moderate,
one-time migration complexity and zero rollback complication. In a financial
ledger domain, the DB-enforced guarantee against non-Eloquent write paths is
worth that cost. This is a recommendation only; the choice belongs to the
Owner.

## Decision Needed

Owner chooses one: **Approve Option A** / **Approve Option B** / **Approve
Option C** / **Request changes** (a different option entirely) / **Decline**
(do not proceed with GAP-038 implementation at all).

## What the owner is NOT being asked to decide

Not being asked to reopen or change anything in `02-design-v17.md` or
`03-release.md` — both stay exactly as already approved. Not being asked to
approve a merge, deploy, or any production change — that is GAP-038's own
future Gate 3, not opened by this packet. Not being asked to approve class
names, migration file names, or other pure engineering detail beyond the
specific CHECK-vs-application-layer choice above.
