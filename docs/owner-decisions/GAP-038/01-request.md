---
work_id: GAP-038
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: null
  plan: null
  branch: docs/GAP-038-project-treasury-implementation-gate1-prep
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-18T09:45:00+07:00"
  owner_response_reference: "Owner Gate 1 decision -- APPROVE, recorded in-session on 2026-08-18 in direct reply to the GAP-038 packet set (this Gate 1 request delivered alongside GAP-038's Gate 2 design-deviation decision). Owner's verbatim reply: 'APPROVE Option B' -- read in context as approving both documents this single reply responded to: Gate 1 (business request: build the already-Owner-approved GAP-037 v17 schema as real code) and Gate 2 (see 02-design.md's own decision_provenance for the Option B selection itself). Authorization scope: this Gate 1 approval confirms the problem is real and correctly scoped and authorizes GAP-038 to proceed toward Gate 2-approved implementation. It does not, by itself, authorize merge, deploy, or any production change -- that is GAP-038's own future Gate 3, not opened by this decision. It does not reopen or alter GAP-037 Gate 1, Gate 2 architecture, 02-design-v17.md, 03-release.md, or PR #263."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-18T09:10:00+07:00"
  updated_at: "2026-08-18T09:45:00+07:00"
generated_by: agent
---

## Decision Recorded

**Resolved 2026-08-18T09:45:00+07:00 — Owner Decision: APPROVE.** See
`decision_provenance.owner_response_reference` above for the exact verbatim
reply and authorization scope.

## Owner Summary

GAP-037 already produced and the Owner already APPROVED a complete, frozen
Project Treasury database schema (`02-design-v17.md`, merged to `main` at
`dbe662972755493f675970e30022083622a9f066`) and a docs/governance-only Gate 3
release (`03-release.md`, same commit). **GAP-037's own Gate 3 explicitly did
not authorize building that schema as real code** — it authorized merging the
design documents only. This request (GAP-038) is that next, distinct step:
build the approved v17 schema as actual Laravel migrations/models/tests, and
resolve one specific, already-identified technical conformance question about
how one class of the approved schema's invariants gets enforced.

## Vấn đề vận hành

The business problem GAP-037 exists to solve (tracking Project Treasury —
funding, payment routing, cost settlement, advances, reconciliation — as its
own auditable ledger, separate from `Cost`/`Cash`/`Revenue`/`Profit`) remains
unsolved in production: **zero Treasury code exists on `main` today.** Only
the design/governance documents exist. A preliminary, technically complete
implementation was built as exploratory engineering work on a local,
never-pushed branch (`worktree-gap037-treasury-migrations`, HEAD
`cdf17b91dceb9b5bc7a578a5d4884144f926bf06`) — all 14 approved tables, models,
and migrations are present and its schema/model test suite (40 tests, 93
assertions) passes live as of this preflight. That branch was explicitly
**not authorized** by any GAP-037 gate and is preserved untouched pending this
decision.

## Người dùng bị ảnh hưởng

Same population GAP-037's design already identified: Project/Finance-facing
roles that need funding, payment-routing, cost-settlement, advance, and
reconciliation visibility distinct from Contract-level cost/cash reporting.
No new role is introduced by this request.

## Bằng chứng

- `docs/owner-decisions/GAP-037/02-design-v17.md` on `main`: `gate_status:
  approved`, `owner_decision.value: approved` — the schema this request would
  implement is not new design, it is already-decided design.
- `docs/owner-decisions/GAP-037/03-release.md` on `main`: `gate_status:
  approved` — explicitly scoped as docs/governance-only; its own
  `mandatory_technical_gate_summary` and `owner_response_reference` both
  state in terms that no migration/model/controller/service/route/UI/runtime
  test is authorized by that decision.
- Preflight audit performed this session (live, re-verified, not assumed):
  `main` HEAD `dbe66297` unchanged since the GAP-037 merge; PR #245 (head
  `cd8b79d861f4c1bae5278b6c57f29cd14e505594`) and PR #257 (head
  `ded7cf9f558bd7960b5eff5836140b1e15255b9a`) both OPEN, unchanged, zero
  drift; the local WIP branch's 14 migration table names match v17's 14
  approved table names exactly, 1:1; 40/40 Treasury schema+model tests pass
  live against the preserved WIP head.
- `docs/superpowers/specs/2026-08-15-zena-one-page-management-canonical-semantics.md`
  (OWN-2026-009, approved, on `main`): "each future implementation slice
  requires its own Work ID and its own Gate 1→2→3 lifecycle" — this is the
  binding convention this request follows in allocating GAP-038 rather than
  reopening GAP-037 or inventing a Gate 4 on it.

## Tác động nếu không xử lý

Project Treasury remains fully undelivered in production — funding, payment
routing, cost settlement, advances, and reconciliation continue to have no
dedicated system of record, and the `Cost≠Cash≠Revenue≠Profit` separation
GAP-037's architecture exists to enforce stays unbuilt. The preliminary WIP
branch, if left indefinitely unauthorized, also risks silent drift from a
`main` that keeps moving.

## Phạm vi đề xuất

Authorize opening GAP-038 as the implementation Work ID for GAP-037's
approved v17 schema: build the 14 tables, their Eloquent models, and their
migrations exactly as `02-design-v17.md` specifies, using the preserved WIP
branch as engineering-evidence starting material (not as something already
approved for merge). GAP-038's own Gate 2 (`02-design.md`, delivered
alongside this Gate 1 request) resolves the one open technical conformance
question this preflight found: whether v17's single-row `CHECK` constraints
are satisfied by the WIP's current application-layer enforcement
(`EnforcesRowInvariants`) or must be re-implemented as native database
constraints before implementation proceeds further.

## Loại trừ rõ ràng

This request does **not** reopen GAP-037 Gate 1, Gate 2 architecture, the
approved `02-design-v17.md` schema content, the approved `03-release.md`
Gate 3 record, or PR #263's history — all of those remain frozen exactly as
Owner-approved. This request does not touch `ContractPayment`,
`ContractExpense`, `MaterialReceiptLine`, `ReportPageController::cashflow()`,
Finance Control, Project OPPM, GAP-036, or Today Workspace. This request does
not, by itself, authorize any merge, deploy, or production data change — that
remains GAP-038's own future Gate 3, not yet opened.

## Đề xuất

Proceed: the schema is already Owner-approved, a technically-complete
prototype already exists and passes its own test suite, and the one
outstanding technical question (Gate 2, this same delivery) is narrow and
well-defined. Recommend: approve Gate 1 so GAP-038's Gate 2 CHECK-conformance
decision (delivered alongside this packet) can be acted on next.

## Decision Needed

Owner chooses one: **Approve** (the Treasury implementation is a real,
correctly-scoped next step, proceed to Gate 2) / **Request more information**
/ **Decline** / **Defer**.

## What the owner is NOT being asked to decide

Not being asked to re-approve the v17 schema itself (already approved and
frozen under GAP-037), not being asked to approve any merge/deploy, and not
being asked yet to choose between the CHECK-constraint options — that specific
choice is GAP-038's Gate 2 packet (`02-design.md`), delivered separately in
this same packet set.
