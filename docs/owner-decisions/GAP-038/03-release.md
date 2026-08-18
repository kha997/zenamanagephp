---
work_id: GAP-038
gate: 3
gate_status: approved
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: null
  plan: docs/superpowers/plans/2026-08-18-gap038-treasury-native-check-constraints.md
  branch: feature/GAP-038-treasury-native-check-constraints
  pr: https://github.com/kha997/zenamanagephp/pull/265
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-18T18:40:00+07:00"
  owner_response_reference: "Owner Gate 3 decision -- APPROVE, recorded in-session on 2026-08-18 in direct reply to the GAP-038 Gate 3 packet presented against reviewed PR #265 head be5a21eef1fae3c3562533f656358cdc5ef023ee and implementation_tree_digest 0ebffd51d72a809e101dd7467b66046e072c10c1359b1d55e2e0c28e23ee961e -- independently re-verified live immediately before recording this decision: PR #265 still Draft/mergeable at head be5a21ee (zero drift since the packet was presented), main HEAD unchanged, preserved WIP branch worktree-gap037-treasury-migrations unchanged at cdf17b91, and the digest recomputed against be5a21ee matched 0ebffd51... exactly. Owner's verbatim decision message: 'GAP-038 GATE 3 -- OWNER DECISION. Decision: APPROVE. I approve GAP-038 Gate 3 for the exact implementation represented by the current verified implementation tree.' The Owner's message explicitly named and accepted as verified basis: PR #265 head be5a21eef1fae3c3562533f656358cdc5ef023ee; technical implementation evidence subject b9f8578bbc6bb9d8c3171761f5d56d2b6c58c3ff (the difference to current head being the Gate 3 release packet only, no implementation drift); implementation_tree_digest 0ebffd51d72a809e101dd7467b66046e072c10c1359b1d55e2e0c28e23ee961e; the real-MySQL 8.0 evidence (24/24 native CHECK rejection/acceptance tests, 0 skipped; information_schema.CHECK_CONSTRAINTS 1/1 test, 15/15 constraint assertions; dedicated Treasury Native CHECK Constraints (real MySQL) CI job pass; Owner Governance evidence-freshness verification pass; required CI green at current head). The Owner explicitly acknowledged and disposed of the residual FOREIGN_KEY_CHECKS-disabled-for-MySQL-testing finding: it does NOT block this Gate 3 (pre-existing, testing-environment-only, unrelated to Option B, and the CHECK constraints themselves have independent real-MySQL + schema-catalog evidence), must NOT be fixed inside GAP-038, and must be recorded as a separate follow-up technical-debt item for later Owner prioritization with no implication it was resolved here. Authorization scope, per the Owner's own explicit statement: this approval authorizes merge of the approved GAP-038 implementation after the normal post-decision governance and exact-head CI gates pass. It does NOT authorize production deployment, production data migration, reopening GAP-037, modifying GAP-038 Gate 1/2, fixing the unrelated FOREIGN_KEY_CHECKS testing-infrastructure finding, or touching the preserved GAP-037 WIP branch. The Owner explicitly required: if any implementation-tree digest drift occurs after this approval, STOP -- this approval does not cover a changed implementation, and the work must return for re-verification/re-binding."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-18T17:25:00+07:00"
  updated_at: "2026-08-18T18:40:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-038 Gate 2 Option B (native database CHECK constraints for GAP-037 v17's 15 single-row invariants) is now implemented and independently verified against real MySQL 8.0 and real SQLite, not merely believed to work. Correction history on this branch: candidate f4cefe59 used SQLite triggers as a DB-enforced substitute for the approved CHECK-clause design -- Owner REQUEST CHANGES rejected this as a design deviation even though functionally equivalent; candidate 6fbdc2f9 replaced it with genuine inline SQLite CHECK clauses (verified via direct sqlite_master introspection: all 15 named CONSTRAINT...CHECK clauses physically present, zero triggers). A first MySQL CI evidence attempt was itself found to be vacuous (this repo's tests/bootstrap.php silently forces every test onto an isolated SQLite database unless ZENA_INVARIANTS_DB=mysql is set, which also silently breaks the named 'mysql' Eloquent connection by overwriting DB_DATABASE) -- corrected by adding a dedicated CI job (scripts/ci/treasury-check-constraints-mysql, modeled on this repo's own proven document-workflow-concurrency-mysql/rfi-escalation-concurrency-mysql pattern) that sets ZENA_INVARIANTS_DB=mysql and strictly fails if any CHECK-relevant test skips. That job's final run (PR #265 head b9f8578bbc6bb9d8c3171761f5d56d2b6c58c3ff) genuinely exercised real MySQL: 24/24 raw-SQL-bypassing-Eloquent tests passed (every one of the 15 CHECK invariants both rejects an invalid row and accepts a valid one), and a separate information_schema.CHECK_CONSTRAINTS introspection test independently confirmed all 15 named constraints physically exist in MySQL's own schema catalog (15 assertions, not inferred from write-rejection alone). One genuine, pre-existing, out-of-scope finding surfaced by this being the first-ever real-MySQL run of any Treasury test: database/migrations/2025_09_20_145756_disable_foreign_keys_for_testing.php (repo-wide, predates GAP-037/038, unrelated to CHECK constraints) issues SET FOREIGN_KEY_CHECKS=0 for the whole MySQL testing session, which made one unrelated pre-existing test (TreasuryWalletsSchemaTest::test_custodian_party_composite_foreign_key_is_enforced, expecting an FK violation) pass vacuously instead of throwing. This is reported here as a residual finding, not fixed (repo-wide testing-environment scope, not a Treasury/Option-B defect) -- see §8 below."
technical_evidence:
  subject_sha: "b9f8578bbc6bb9d8c3171761f5d56d2b6c58c3ff"
  implementation_tree_digest: "0ebffd51d72a809e101dd7467b66046e072c10c1359b1d55e2e0c28e23ee961e"
  verified_pr_head_sha: "b9f8578bbc6bb9d8c3171761f5d56d2b6c58c3ff"
  verified_at: "2026-08-18T17:25:00+07:00"
owner_decision_binding:
  implementation_tree_digest: "0ebffd51d72a809e101dd7467b66046e072c10c1359b1d55e2e0c28e23ee961e"
  decision_recorded_at: "2026-08-18T18:40:00+07:00"
---

## Decision Recorded

**Resolved 2026-08-18T18:40:00+07:00 — Owner Decision: APPROVE.** Bound to
PR #265 head `be5a21eef1fae3c3562533f656358cdc5ef023ee` and
`implementation_tree_digest`
`0ebffd51d72a809e101dd7467b66046e072c10c1359b1d55e2e0c28e23ee961e`, zero
drift re-verified immediately before recording. See
`decision_provenance.owner_response_reference` above for the exact
verbatim decision, verified basis, residual-finding disposition, and full
authorization boundary. **Separate follow-up recommended, not authorized
or begun here:** the `FOREIGN_KEY_CHECKS`-disabled-for-MySQL-testing
finding (§7/§8 below) should be logged as its own technical-debt /
test-infrastructure item for future Owner prioritization.

## Gói quyết định phát hành

**1. Vấn đề đã xảy ra là gì?**
GAP-037's Owner-approved Treasury schema (`02-design-v17.md`) requires
native database `CHECK` constraints for 15 single-row invariants across
8 tables (positive amounts, mutually-exclusive fields, exactly-one-of
groups, co-nullable pairs). GAP-038 Gate 2 already decided (Owner
APPROVE OPTION B) that these must be enforced by the database engine
itself, not only by application code -- this request is whether the
now-implemented, now-independently-verified result of that decision is
ready to merge.

**2. Người dùng nào bị ảnh hưởng?**
No end user yet -- this is still docs/schema/test code only (no
controller, route, or UI exists). The people affected are future
engineers/automation writing to these 8 Treasury tables: previously,
only Eloquent writes were protected (`EnforcesRowInvariants`); after
this merge, the database itself rejects invalid rows regardless of
write path.

**3. Bây giờ người dùng có thể làm gì?**
Nothing new is user-facing. What changes: a raw SQL insert, a bulk
`insert()`/`upsert()` (which bypass Eloquent's `saving` event by
design), or a queued job writing via the query builder can no longer
silently write a negative amount, both-null or both-set mutually
exclusive pair, etc. into any of these 8 tables -- the database
rejects it the same way Eloquent already did.

**4. Rủi ro nào đã được đóng lại?**
The exact gap this Gate 2 decision named: "a single bulk-insert or
raw-SQL write bypassing the approved invariant" in a financial-ledger
domain. That gap is now closed on both of this repo's two supported
database engines, independently verified (not merely asserted) on
each: SQLite via direct `sqlite_master` schema introspection, MySQL
via direct `information_schema.CHECK_CONSTRAINTS` introspection, both
also proven by attempting the actual violating raw write and observing
the database reject it.

**5. Đã kiểm thử những gì?**
64 pre-existing Treasury schema/model tests (unchanged, still passing)
plus 3 new test files added by this work: (a) 24 tests proving every
one of the 15 CHECK invariants rejects an invalid raw SQL write and
accepts a valid one, on SQLite locally and on real MySQL in CI; (b) 3
tests directly querying each database engine's own schema catalog to
confirm the CHECK constraints physically exist, not merely that writes
happen to be rejected; (c) the pre-existing full Treasury suite,
re-run against real MySQL for the first time. Required repository CI
green throughout, including a new dedicated
`Treasury Native CHECK Constraints (real MySQL)` job.

**6. Điều gì KHÔNG nằm trong phạm vi lần này?**
No migration, model, controller, service, route, UI, or permission
code beyond what GAP-037/038's schema-and-constraint scope already
covers. No merge to `main`, no deploy, no production data change --
this Gate 3 decision, if approved, authorizes merging this exact
implementation tree only. The preserved implementation WIP branch
(`worktree-gap037-treasury-migrations`, HEAD `cdf17b91`) is not part
of this PR and remains untouched, local-only.

**7. Vì sao các gap liên quan vẫn để riêng?**
The `SET FOREIGN_KEY_CHECKS=0`-for-MySQL-testing finding (§8) is a
repo-wide testing-environment decision that predates GAP-037/038 by
almost a year and affects every FK-enforcement test in this codebase,
not something specific to Treasury or to this Gate 2 Option B
decision. Fixing it here would be scope creep into an unrelated,
repo-wide testing-infrastructure question that deserves its own
explicit decision, not a side effect of a CHECK-constraint PR.

**8. Rủi ro còn lại là gì?**
Low. The one residual finding
(`TreasuryWalletsSchemaTest::test_custodian_party_composite_foreign_key_is_enforced`
silently passing under real MySQL instead of throwing, because this
repo's testing config disables FK checks for the whole MySQL test
session) is real but pre-existing, repo-wide, and unrelated to the
CHECK constraints this Gate 3 request is about -- the composite FK
itself is still correctly defined in the schema; only the *test's*
ability to observe MySQL enforcing it is affected by this pre-existing
environment setting. Separately, the new
`treasury-check-constraints-mysql` CI job's full-suite step currently
takes roughly 30 minutes against real MySQL (RefreshDatabase-heavy,
network-bound) -- an operational cost, not a correctness risk, worth
optimizing in a future pass but not blocking here.

**9. Có thể hoàn tác không?**
Yes, trivially. Every affected table is newly created by this same PR
(no pre-existing production data to migrate or lose); `down()` in
every migration is an unmodified `Schema::dropIfExists()`, and
dropping a table on both MySQL and SQLite automatically drops its
CHECK constraints (and, on SQLite, any triggers) with it -- no
separate constraint-removal step exists or is needed.

**10. Đề xuất của đội kỹ thuật:**
Ready to release. The specific technical decision Gate 2 authorized
(native CHECK constraints, Option B) is implemented completely,
matches all 15 approved invariants byte-for-byte, and is independently
verified -- not merely tested for write-rejection, but confirmed to
physically exist in each engine's own schema catalog -- on both of
this repository's supported database engines including a genuine real
MySQL 8.0 CI run. The one residual finding is honestly out of scope
and explicitly not hidden.

**Quyết định của chủ doanh nghiệp:** ☑ Phát hành &nbsp;☐ Yêu cầu chỉnh sửa nghiệp vụ &nbsp;☐ Hoãn phát hành

## What the owner is NOT being asked to decide

Not being asked to reopen or re-approve GAP-037 Gate 1, Gate 2
architecture, `02-design-v17.md`, or `03-release.md` -- all remain
exactly as previously approved, untouched by this PR. Not being asked
to reopen or re-approve GAP-038 Gate 1 or Gate 2 (`01-request.md`,
`02-design.md`) -- both remain exactly as previously approved,
untouched by this PR. Not being asked to decide the unrelated
`FOREIGN_KEY_CHECKS`-disabled-for-MySQL-testing finding (§7/§8) --
that is reported as a residual finding requiring its own future
decision, not bundled into this release decision. Not being asked to
inspect CI logs, source code, or review comments directly -- only
whether the demonstrated behavior (native CHECK constraints,
independently verified on both engines) and the residual risk
described above are acceptable to release.
