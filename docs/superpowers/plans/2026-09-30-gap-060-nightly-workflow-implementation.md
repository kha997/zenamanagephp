---
work_id: GAP-060
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-060/02-design.md
---

# GAP-060 — Nightly workflow: implementation plan

Design: `docs/owner-decisions/GAP-060/02-design-v2.md` (Option 1v2, approved
2026-09-30; supersedes v1). The frontmatter points at `02-design.md` because
the governance lint derives the Gate-2 record path without a version suffix
(same as GAP-052); v1 there is approved and carries `superseded_by` → v2.

## Task 1 — RED guard
`tests/Architecture/WorkflowReferencesExistTest.php`: every repository script a
workflow references exists; every `--group` a workflow selects is carried by a
test (`@group` or `#[Group]`). Base: 1 missing script
(`.github/scripts/ci_prepare_testing_env.sh`), 2 empty groups
(`performance_budget`, `performance_heavy`).

## Task 2 — workflow
`.github/workflows/a11y-perf-testing.yml`: rename to "Nightly MySQL Cold-Start
Proof (GAP-040)"; keep one job (`gap040-cold-start-proof`, from `e2e-tests`)
with MySQL service, install, migrate, GAP-039 preflight, and
`php artisan test tests/E2E/TransactionIsolationColdStartTest.php
--fail-on-empty-test-suite`; remove accessibility-tests, performance-budget,
performance-heavy, lighthouse-ci, test-summary, the tests/E2E suite step and the
E2E report/upload steps; drop the unused NODE_VERSION env.

## Task 3 — verify
Guard green; OwnerGovernance + Architecture suites; YAML parses; push; run the
workflow on the branch via `gh workflow run a11y-perf-testing.yml --ref <branch>`
and record the run id and test count; full PR CI; Gate 3.
