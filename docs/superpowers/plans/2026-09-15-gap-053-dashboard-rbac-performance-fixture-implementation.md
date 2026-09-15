---
work_id: GAP-053
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-053/02-design.md
---

# GAP-053 Dashboard RBAC performance-fixture implementation plan

**Goal:** Replace the confirmed scalar-only role fixture in
`DashboardPerformanceTest::it_can_handle_role_based_filtering_performance()`
with the existing canonical RBAC fixture helper, without changing application
or security semantics.

**Architecture:** Keep scalar dashboard identity and canonical middleware RBAC
identity distinct. The loop value remains `users.role`; an explicit mapping
provides the helper's RBAC role, with `client_rep -> client` and the other three
roles mapping to themselves. `FixtureFactory::createTenantUserWithRbac()`
continues to own both pivot assignments.

## Constraints

- Functional scope is only `tests/Performance/DashboardPerformanceTest.php`.
- Remove `use App\Models\User;` only if the approved substitution makes it
  unused.
- Preserve the endpoint, genuine `apiAs()` authentication, expected 200,
  timing boundary, dataset, and 500 ms threshold.
- Do not change application/RBAC/middleware/routes, permissions, workflows,
  GAP-041 PR #316, GAP-045, evidence freshness, or adjacent E2E/FinalSystem
  fixtures.
- Stop and return to Owner if evidence requires any production or scope change.

## Task 1 — Freeze RED evidence

**Files:** none.

- Run the current method with a truthful, fail-on-empty performance selector.
- Require one executed test and the sequence of three 200 responses followed
  by `client_rep` 403.
- Record the scalar-only empty-pivot condition from approved Gate-1 evidence.

**Verification:**

`php artisan test tests/Performance/DashboardPerformanceTest.php --group=performance --filter=it_can_handle_role_based_filtering_performance --fail-on-empty-test-suite`

**Rollback:** none; this task is read-only.

## Task 2 — Apply the minimal fixture correction

**Files:** modify `tests/Performance/DashboardPerformanceTest.php` only.

- Define the four-entry scalar-to-RBAC mapping adjacent to the loop.
- Replace `User::create()` with `createTenantUserWithRbac()`.
- Pass the existing user attributes via overrides and no permission grants.
- Remove the unused `User` import.

**Verification:** inspect the diff and require no other functional file.

**Rollback:** revert only this single-file substitution and import removal.

## Task 3 — Prove GREEN and canonical identity

**Files:** no committed file beyond Task 2; a disposable untracked diagnostic
test may be created and must be deleted after execution.

- Run the exact truthful targeted method; require all four 200 responses and
  the unchanged threshold.
- Inspect every loop identity: scalar role unchanged, both pivots contain the
  mapped role, no `admin`, and `client_rep` has `client` in both pivots.
- Run the unchanged GAP-052 integration contract.

**Verification:** targeted performance command, disposable pivot probe, and
`php artisan test tests/Integration/GAP052DashboardWidgetContractTest.php`.

**Rollback:** remove the disposable probe; if GREEN requires broader changes,
revert Task 2 and return to Owner.

## Task 4 — Obtain genuine-MySQL proof

**Files:** no workflow change may enter PR #317.

- Freeze the implementation subject.
- From that subject, create a disposable verification ref only if necessary to
  invoke the targeted test directly in the existing real-MySQL CI environment.
- Require a demonstrably non-empty Dashboard performance run and record the
  run/job, test count, assertion count, four role results, and threshold result.
- Delete the disposable remote ref after evidence capture. Never modify PR
  #316 or merge the proof-only selector overlay.

**Rollback:** delete the disposable verification ref; it is never part of the
lifecycle PR or release tree.

## Task 5 — Verify scope and prepare Gate 3

**Files:** update `docs/owner-decisions/GAP-053/03-release.md` only after the
implementation subject and evidence are frozen.

- Run governance lint, gate ordering, route guard, diff checks, targeted tests,
  and all exact-head PR checks triggered by the lifecycle diff.
- Prove the only implementation delta is the approved performance test file.
- Compute the implementation-tree digest using the canonical helper.
- Set Gate 3 to `awaiting_owner` only after mandatory evidence is green; keep
  PR #317 Draft and request a separate Owner release decision.

**Rollback:** keep Gate 3 `preparing` or mark it `blocked_technical` truthfully;
never weaken evidence requirements or mark the PR Ready.
