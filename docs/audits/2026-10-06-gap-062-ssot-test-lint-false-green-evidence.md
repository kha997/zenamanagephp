# GAP-062 — SSOT test lint is false-green in CI: Gate-1 evidence

**Date:** 2026-10-06 (+07:00)

**Canonical base:** `1c2b01607c6ee014adad777a9f6656cd0e9298c4`

**Branch:** `docs/GAP-062-ssot-test-lint-false-green`

**Scope:** Read-only investigation and Gate-1 documentation. No script,
baseline, test, workflow or deployment change.

## Candidate-ID audit

Register and `docs/owner-decisions/` top out at GAP-061; `git grep GAP-062`
over every remote branch: no match. No register row mentions `lint_tests.sh`
or ripgrep.

## Finding 1 — the gate cannot fail in CI

`ci-cd.yml:142` runs `composer ssot:lint`, which runs
`bash scripts/ssot/lint_tests.sh`. Its collectors
(`collect_hardcoded`, `collect_denylist_hits`, `collect_raw_user`,
`collect_raw_model_create`, `collect_raw_model_feature`,
`collect_raw_model_integration`, `collect_raw_model_create_zena`, and the `rg`
part of `collect_skip_sources`) shell out to `rg`. GitHub's `ubuntu-latest`
image has no ripgrep; each pipeline ends in `sort -u > file || true`, so a
missing binary yields an **empty inventory**, which trivially has "no new
violations".

LIVE: CI/CD Pipeline run `37343750155` on `main` at `ec487a48` prints ten
`scripts/ssot/lint_tests.sh: line N: rg: command not found` lines (lines 51,
107, 116, 117, 118, 127, 128, 137, 146, 159) followed by
`SSOT test lint passed (no new violations beyond baseline).` The same
behaviour was noted on 2026-07-29 (PR #232) but never registered.

## Finding 2 — accumulated violations hidden by Finding 1

Running the same script locally with ripgrep 15.1.0 on the canonical base
exits 1. Each reported line was compared with its baseline ignoring the line
number (to separate line drift from new code): **all 99 are genuinely new**,
none is line drift.

| Category | Baseline | New | Files |
|---|---|---|---|
| `denylist_hits` | 0 | 10 | `tests/Feature/Audit/AudSupportTicketAuthorizationTest.php` (5), `tests/Feature/LegacyWidgetOwnershipTest.php` (5) |
| `raw_model_create` (`tests/Feature/Api`, any `Model::create`) | 7 | 24 | `RfiApiTest.php` (11), `EventRecordOutboxApiTest.php` (8), `ChangeRequestStakeholderNotificationTest.php` (3), `ExportTenantIsolationTest.php` (1), `SubmittalResubmitLifecycleTest.php` (1) |
| `raw_model_create_feature` (User/Tenant/Role/Permission/Project in `tests/Feature`) | 0 | 64 | `GAP042RbacProductionFidelityTest.php` (27), `GAP042Gate3Round1CorrectionsTest.php` (20), `RfiApiTest.php` (11), `GAP042Gate3Round2CorrectionsTest.php` (5), `SubmittalResubmitLifecycleTest.php` (1) |
| `raw_model_create_zena` | 0 | 1 | `tests/Feature/Zena/PermissionCanonicalIdentityRegressionTest.php:32` |

(`RfiApiTest.php`/`SubmittalResubmitLifecycleTest.php` lines appear in two
overlapping categories.) Categories `hardcoded_api_paths`, `raw_user_create`,
`raw_model_create_integration` and the skip inventory pass.

## Finding 3 — the denylist itself is stale

`scripts/ssot/denylist_endpoints.txt` calls five endpoints "known dead".
Against `artisan route:list --json` on the canonical base:

| Denylisted | Live routes |
|---|---|
| `/api/v1/users` | 0 |
| `/api/v1/templates` | 0 |
| `/api/dashboards` | 5 |
| `/api/widgets` | 3 (POST, PUT, DELETE) |
| `/api/support/tickets` | 4 |

The ten `denylist_hits` are security regression tests deliberately probing
those **live** routes (widget ownership IDOR; support-ticket authorization).
Removing the tests would delete real coverage; the denylist entries are what
is wrong.

## Nature of the new raw creates (sampled)

Many are deliberate: e.g. the GAP-044 regression test must insert a
`permissions` row with `name = NULL` to reproduce RoleSeeder's real shape, and
the GAP-042 fidelity tests create raw roles/permissions to test RBAC storage
shapes. Others (e.g. `RfiApiTest.php`, written 2025-09) predate or ignore the
factory convention. Deciding per call is test-design work, not needed to make
the gate truthful.

## Options for Gate 2 (decision needed)

- **1. Make the gate truthful, freeze today's inventory (recommended):**
  install ripgrep in the CI job(s) that run `composer ssot:lint` and make
  `lint_tests.sh` exit non-zero when `rg` is missing (fail-closed); remove the
  three live endpoints from the denylist; accept the remaining current
  violations into the baselines at one SHA (documented debt). New violations
  then fail CI from day one; no test rewrite.
- **2. Same as 1, but annotate instead of baseline:** add the existing
  `ssot-allow-raw-model*` markers with reasons to each of ~89 lines in 11 test
  files — more churn in test files, same end state.
- **3. Same as 1, plus refactor raw creates to factories:** large test
  rewrite across RBAC fidelity tests whose point is raw storage shape; risk of
  weakening those tests. Not recommended now.
- **4. Retire the rg-based checks:** removes the false-green by removing the
  gate; loses the SSOT test conventions.

## Out of scope for this Gate 1

Any change; the orphan-route and domain-ownership steps of `ssot:lint`
(pure PHP, not affected).
