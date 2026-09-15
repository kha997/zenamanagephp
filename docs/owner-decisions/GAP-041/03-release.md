---
work_id: GAP-041
gate: 3
gate_status: blocked_technical
technical_readiness:
  value: blocked
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: null
references:
  spec: docs/superpowers/specs/2026-08-21-gap-041-ci-test-selection-truthfulness-design.md
  plan: docs/superpowers/plans/2026-09-15-gap-041-ci-test-selection-truthfulness-implementation.md
  branch: fix/GAP-041-ci-test-selection-truthfulness
  pr: "https://github.com/kha997/zenamanagephp/pull/316"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-15T08:13:59+07:00"
  updated_at: "2026-09-15T08:24:32+07:00"
generated_by: agent
residual_risk_rating: medium
mandatory_technical_gate_summary: "Option D and its full behavioral LIVE acceptance contract are implemented and proven, but the release-governance requirement that every current-head check be green has not passed: truthful execution exposes three existing DashboardPerformanceTest assertions, and the evidence-freshness policy therefore rejects awaiting_owner/ready. Resolving that contradiction would require out-of-scope performance/application work, GAP-045 threshold work, or a separately authorized governance/design decision."
technical_evidence:
  subject_sha: "4f549bd0acf25f1de20c8dbfd8362bd7725ac54f"
  implementation_tree_digest: "not_computed_while_blocked"
  verified_pr_head_sha: null
  verified_at: null
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-041 — Gate 3 release decision packet

## BLOCKED — Owner/design reconciliation required before Gate 3 can be presented

The approved Gate-2 Option D and its behavioral LIVE acceptance contract are
implemented and proven. However, exact-head CI exposed a conflict between that
truthful behavior and the repository's Gate-3 evidence-freshness policy: the
Dashboard performance leg now runs 19 tests and truthfully fails three existing
assertions, while the policy forbids `awaiting_owner/ready` until every
current-head check is green. PR #316 remains Draft. No Owner decision is
recorded or implied, and merge, release, and deployment remain unauthorized.

## Current-main reconciliation

Implementation began only after fetching current refs and proving that
`origin/main`, the new worktree starting HEAD, and their merge base were exactly
the Owner-specified canonical SHA
`adacc5cc5fb8a08353cc90576076724e45e6e8bc`. The complete approved Gate-1
record from PR #276 and Gate-2 v3 record from PR #277 were re-read from their
approved commits. Current-main workflow, PHPUnit, performance-test, CI-helper,
and governance surfaces were compared with the approved assumptions.

No material drift invalidated Option D. The performance file matrix still
omitted the `performance` group selector and permitted an empty suite to pass;
the two tier jobs and their `test-summary` references remained present; the
relevant `phpunit.xml` behavior remained unchanged. The only relevant
performance-test evolution was the already-released GAP-043 portability change.
GAP-045's 450ms threshold remained present and outside this Work ID.

The carried Gate-2 packet has one administrative current-schema normalization:
historical `owner_decision.value: approve` is represented as `approved` and an
approved packet no longer carries a pending `decision_requested`. Its approved
Option-D semantics, provenance, scope, and acceptance contract are unchanged;
the original approved blob remains identified in `02-design.md`.

## Bounded implementation plan and changes

The pre-mutation plan is
`docs/superpowers/plans/2026-09-15-gap-041-ci-test-selection-truthfulness-implementation.md`.
It bounded the work to authority carry-forward, selector/fail-closed repair,
phantom-tier retirement, register reconciliation, verification, LIVE positive
and negative proofs, and this Gate-3 handoff.

The implementation-tree diff contains exactly these nine files (this Gate-3
packet is excluded from the canonical implementation digest by repository
definition):

- `.github/workflows/automated-testing.yml`
- `.github/workflows/a11y-perf-testing.yml`
- `OPERATIONAL_GAP_REGISTER.md`
- `docs/audits/2026-08-21-gap-041-zero-test-performance-ci-evidence.md`
- `docs/owner-decisions/GAP-041/01-request.md`
- `docs/owner-decisions/GAP-041/02-design.md`
- `docs/superpowers/plans/2026-09-15-gap-041-ci-test-selection-truthfulness-implementation.md`
- `docs/superpowers/specs/2026-08-21-gap-041-ci-test-selection-truthfulness-design.md`
- `tests/bootstrap.php` (caller-inventory comment only; no behavior change)

The surviving file-matrix command is now:

```text
php artisan test "${{ matrix.perf_file }}" --group=performance --fail-on-empty-test-suite
```

Repository-stack characterization proved that Laravel 12.63.0 / Collision
8.9.4 forwards PHPUnit 11.5.56's native `--fail-on-empty-test-suite` option.
That is the smallest mechanism satisfying the approved behavioral contract.

## RED and local proof

- Baseline RED: the previous exact command against
  `DashboardPerformanceTest.php` returned `INFO No tests found.` with exit 0.
- Mechanism characterization: the same Artisan/Collision path with a guaranteed
  nonexistent group plus `--fail-on-empty-test-suite` returned exit 1.
- Intended population discovery found 19 `performance` tests in
  `DashboardPerformanceTest.php` and 10 in `PerformanceMonitoringTest.php`.
- MySQL fail-closed helper tests passed 6/6; MySQL claim-truthfulness tests
  passed 8/8; live workflow claims lint passed across 14 workflows.
- Owner-governance lint and gate-ordering lint passed; docs lint passed;
  both edited YAML workflows parsed and all `needs` targets resolved.
- Route guardrails passed. `git diff --check` passed and the tree was clean at
  freeze.

Local results above remain classified LOCAL/STATIC. None is represented as
LIVE evidence.

## Mandatory LIVE evidence

### Positive performance execution — run 34875648924

GitHub Actions run
`https://github.com/kha997/zenamanagephp/actions/runs/34875648924` checked out
the exact frozen subject `4f549bd0acf25f1de20c8dbfd8362bd7725ac54f`.

- Dashboard leg, job `104082059649`: genuine MySQL preflight succeeded at
  `127.0.0.1:3306/zenamanage_test` before the exact corrected Artisan command;
  19 tests executed with 154 assertions. The leg truthfully failed on three
  real assertions instead of reporting success from zero selection.
- Monitoring leg, job `104082059815`: the same genuine MySQL preflight
  succeeded before the corrected command; 10 tests executed with 45 assertions
  and passed.

The workflow's overall `failure` is therefore truthful, not a GAP-041 failure:
all intended matrix populations executed after fail-closed MySQL preflight.

### Disposable zero-selection proof — run 34915725855

An isolated child commit `1ff8cc7a09eb0a0af4d873699b51c774dc630470`
changed only the group to guaranteed-nonexistent
`gap041-zero-selection-live-proof`, retaining the native fail-on-empty flag.
GitHub Actions run
`https://github.com/kha997/zenamanagephp/actions/runs/34915725855` proved:

- Dashboard job `104212799009`: MySQL preflight succeeded; PHPUnit reported
  `No tests found`; the process exited 1.
- Monitoring job `104212799206`: MySQL preflight succeeded; PHPUnit reported
  `No tests found`; the process exited 1.

After capture, the disposable branch was deleted locally and remotely. The
implementation worktree was restored cleanly to the frozen subject. The proof
commit is not an ancestor of PR #316 and is not part of its tree.

### Phantom-tier retirement — run 34875662364

Static exhaustive search found no live-source occurrence of the retired job
names, group names, tier environment controls, missing helper caller, dangling
`needs.*` expression, or tier-specific producer/consumer reference. YAML parsing
found exactly four jobs: Accessibility, Lighthouse, E2E, and Test Summary.

GitHub Actions run
`https://github.com/kha997/zenamanagephp/actions/runs/34875662364`, on the exact
frozen subject, instantiated only those four jobs. Test Summary job
`104082793889` ran successfully under `always()` and resolved only the three
surviving producers. No phantom job was instantiated and no dependency
resolution error occurred.

## Unrelated failures exposed or observed

GAP-041 deliberately does not repair truthful performance failures:

- Dashboard alerts median: 514.10ms versus the protected 450ms GAP-045
  threshold.
- Mark-alerts-as-read: 1023.63ms versus its existing 1000ms assertion.
- Role-based filtering: HTTP 403 versus the existing expected 200.

The retained a11y workflow also reported pre-existing/out-of-scope failures in
Accessibility report generation, Lighthouse migration setup, and E2E tests.
They are recorded for truthfulness but were not investigated or modified under
GAP-041 because the approved design expressly forbids unrelated
A11y/Lighthouse/E2E cleanup.

## Frozen subject and digest

- canonical starting main: `adacc5cc5fb8a08353cc90576076724e45e6e8bc`
- implementation subject: `4f549bd0acf25f1de20c8dbfd8362bd7725ac54f`
- repository-canonical implementation-tree digest:
  `f8146dd94046233a13401bb8cf57d0e824f5b1dbafdbc062f45101ef51c09f82`

The digest was computed with
`owner_governance_compute_implementation_tree_digest()` and excludes only this
exact Gate-3 record, as defined by repository governance. Any change to another
file invalidates this Gate-3 presentation and requires fresh verification.

The frontmatter uses the repository-required
`not_computed_while_blocked` placeholder while this active packet is
`blocked_technical`; the verified digest above is retained as engineering
evidence and remains reproducible from the frozen subject.

## Exact-head CI blocker

At PR head `6ec937232d434eecafae35975548381cabba385f`, 27 checks had passed and
the truthful Dashboard performance leg had failed with the same 19-test,
3-failure result. The Owner Governance job's structural lint and digest checks
passed far enough to enter its bounded live-check polling, then exited non-zero
with the explicit finding that `awaiting_owner/ready` is invalid while other
current-head checks are not green. This is not implementation-tree drift; it is
the exact policy consequence of making the previously false-green test surface
truthful.

No in-scope correction can make both requirements true. Changing the existing
assertions, changing GAP-045's threshold, optimizing application behavior, or
weakening/exempting the evidence-freshness policy would all exceed the approved
GAP-041 contract. Owner/design reconciliation is therefore required before this
packet may return to `awaiting_owner`.

## Next decision needed before Gate 3

Choose a separately authorized disposition for the pre-existing Dashboard
assertion failures or reconcile the Gate-3 all-checks-green rule with the
approved truthfulness contract. GAP-041 itself should not silently absorb either
change.

This blocked packet requests no Gate-3 approval. It must remain blocked until a
new authorized design basis makes exact-head evidence freshness pass.

## What the Owner is not being asked to decide

The Owner is not being asked to approve release, inspect raw CI logs, or accept
the unrelated performance/a11y failures as fixed. Any next instruction must be
a scope/design reconciliation, not a Gate-3 approval of the currently blocked
packet.
