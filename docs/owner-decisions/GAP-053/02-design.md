---
work_id: GAP-053
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-09-15-gap-053-dashboard-rbac-performance-fixture-evidence.md
  plan: null
  branch: docs/GAP-053-dashboard-rbac-performance-fixture-gate1
  pr: https://github.com/kha997/zenamanagephp/pull/317
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-15T17:06:20+07:00"
  updated_at: "2026-09-15T17:06:20+07:00"
generated_by: agent
---

# GAP-053 — Gate 2 canonical RBAC performance-fixture design

## Decision boundary and evidence binding

This is a design-only packet. It is bound to approved Gate-1 evidence in the
referenced audit and `01-request.md`, canonical base
`adacc5cc5fb8a08353cc90576076724e45e6e8bc`, approved Gate-1 record head
`c0017d5b137d46a003495984463cdffe4fe78685`, and lifecycle Draft PR #317.
Gate 1 classified the failure as **A — stale test fixture**: a canonically
assigned `client_rep`/`client` identity returns 200, so application and security
behavior are correct.

No implementation, implementation plan, Gate 3, Ready transition, merge,
release, or deployment is authorized by this packet.

## Recommended design

In the role loop of
`DashboardPerformanceTest::it_can_handle_role_based_filtering_performance()`:

1. Replace the direct scalar-only `User::create()` call with the class's
   existing `FixtureFactory::createTenantUserWithRbac()` helper.
2. Pass the loop value unchanged as the scalar application/dashboard role.
3. Pass an explicit canonical RBAC role selected by this total mapping:

   | Scalar dashboard role | Canonical RBAC role |
   |---|---|
   | `project_manager` | `project_manager` |
   | `site_engineer` | `site_engineer` |
   | `qc_inspector` | `qc_inspector` |
   | `client_rep` | `client` |

4. Preserve the current name, email, password, and tenant values through the
   helper's override argument.
5. Keep genuine `apiAs()` login/Bearer authentication, the request to
   `/api/v1/dashboard/role-based/widgets`, expected HTTP 200, and the existing
   500 ms assertion exactly as they are.

The helper is the repository's canonical and smallest expressive choice. This
performance class already uses it in `setUp()`. It preserves `users.role`,
creates/reuses the named `Role`, and attaches that role to both `user_roles`
and `system_user_roles`. No permission codes are needed for this route, so the
permissions argument remains empty. The mapping grants `client`, never
`admin`, to the `client_rep` iteration.

## Alternatives considered

1. **Recommended — `createTenantUserWithRbac()`:** uses the class's existing
   SSOT fixture surface, establishes both pivots, preserves scalar dashboard
   identity, and makes the least-privilege mapping explicit.
2. **Lower-level `createTenantUser()`:** behaviorally capable of the same
   result, but bypasses the performance class's established FixtureFactory
   surface and makes the identity contract less obvious at the call site.
3. **Manual Role/pivot construction:** duplicates tested fixture plumbing and
   increases setup and drift risk without adding coverage.
4. **Blanket `admin`, middleware aliasing, or policy relaxation:** rejected.
   These approaches conceal the fixture defect or alter security semantics.

## Exact future implementation scope

If Gate 2 is approved, a later Gate 3 may change only:

- `tests/Performance/DashboardPerformanceTest.php`, limited to the user
  construction inside `it_can_handle_role_based_filtering_performance()`;
- the same file's `use App\Models\User;` import may be removed if it becomes
  unused as a direct mechanical consequence.

The implementation is one fixture-construction substitution plus the explicit
four-entry mapping. It does not introduce a new helper, new role, permission,
route, threshold, status expectation, or product behavior.

## Explicit exclusions

The future correction must not:

- change any file under `app/`, `routes/`, `config/`, `database/`, or any
  runtime/RBAC/middleware policy;
- add `client_rep` as a canonical middleware role or treat the scalar role as
  a substitute for canonical assignment;
- grant `admin` or fabricate permissions merely to obtain a green response;
- change expected HTTP 200, the 500 ms threshold, timing boundaries, dataset,
  endpoint, or genuine authentication path;
- change GAP-041 or PR #316, GAP-045 thresholds, or evidence-freshness policy;
- modify `DashboardE2ETest.php` or `FinalSystemTest.php`; their adjacent
  scalar-only fixtures remain separately tracked debt and are not absorbed;
- create a plan, Gate-3/release packet, workflow implementation, merge,
  release, or deployment during Gate 2.

This correction does not enter Project/shared-domain semantics, so the
repository Design Dependency Preflight is not triggered by the approved scope.
Any future expansion into that domain must stop and run the preflight first.

## Proof-first / TDD implementation contract

Future Gate-3 implementation must follow a frozen-subject RED → minimal change
→ GREEN sequence; evidence collected before the change cannot be presented as
post-change evidence.

### RED — unchanged baseline

1. On the exact approved implementation base, run the exact current
   `it_can_handle_role_based_filtering_performance` method without source
   modification.
2. Record the complete four-role sequence and prove `client_rep` returns 403
   after successful genuine authentication while its `user_roles` and
   `system_user_roles` assignments are empty.
3. Record at least one known-working scalar-only control (normally
   `project_manager`) so compatibility-path success is not mistaken for a
   canonical identity.

Expected RED signature: `project_manager`, `site_engineer`, and `qc_inspector`
reach 200; `client_rep` receives `RBAC_ACCESS_DENIED`/403.

### Minimal implementation

Apply only the helper substitution and explicit mapping in this packet. Do not
change production code or make any authorization adjustment in response to the
RED result.

### GREEN and identity proof

Against the exact post-change subject:

1. Run the exact performance method and record all four iterations returning
   200 within the unchanged threshold.
2. Inspect each generated user and record that:
   - `users.role` exactly equals its original loop value;
   - both canonical assignment relations contain the mapped role;
   - no iteration receives `admin`;
   - specifically, `client_rep` has `client` in both `user_roles` and
     `system_user_roles` and retains scalar `users.role = client_rep`.
3. Run `GAP052DashboardWidgetContractTest` unchanged and require it to remain
   green.
4. Run the targeted Dashboard performance test against genuine MySQL, using
   the repository's real-MySQL CI environment and a direct, non-empty PHPUnit
   selection. Because GAP-041's truthful selector is not yet released, this
   proof may use a disposable child verification ref derived from the frozen
   GAP-053 subject solely to invoke the existing test directly. Such a proof
   commit must not enter PR #317 or PR #316, and its ref must be removed after
   evidence capture. A zero-test run is not evidence.
5. Show the final PR diff contains no application/RBAC/runtime/workflow change,
   and run owner-governance lint plus route guardrails against the exact head.

Temporary diagnostic code used only to expose pivot state must remain
uncommitted or be removed before the implementation subject is frozen. The
released contract tests, production code, and thresholds are not edited to
manufacture GREEN.

## Acceptance contract

Gate 3 may be presented only when all of the following are evidenced:

1. The unchanged pre-correction method reproduces `client_rep -> 403`.
2. The corrected method uses canonical assignments for all four identities and
   every iteration returns 200.
3. Every scalar dashboard role remains unchanged.
4. `client_rep` has canonical `client` pivots and no `admin` assignment.
5. The released GAP-052 contract remains green without modification.
6. The targeted Dashboard performance method passes on genuine MySQL with a
   demonstrably non-empty selection.
7. No application, RBAC, middleware, route, runtime, workflow, threshold, or
   security behavior changes.
8. GAP-041 can consume released GAP-053 through normal integration of updated
   main and rerun its existing LIVE acceptance without redesign or changes to
   its authored implementation.

## Blast radius and relationship to GAP-041

The intended blast radius is test-only: one identity-construction loop in one
performance test, plus removal of its unused model import if applicable.
Production users, API responses, tenant isolation, authorization, dashboard
role resolution, and released GAP-051/GAP-052 contracts are unchanged.

GAP-041 PR #316 remains an unmodified blocked consumer. GAP-041 made the test
execute truthfully; it did not cause the fixture defect. After GAP-053 is
approved, implemented, and released to canonical main, PR #316 may integrate
updated main and rerun its existing LIVE acceptance unchanged. No GAP-041
redesign, workflow change, threshold change, or application change is required.

## Owner decision requested

Approve this exact test-only design, request changes, or decline it. Approval
would authorize preparation of a separate implementation plan/Gate 3 later; it
would not itself authorize implementation, merge, release, or deployment.
