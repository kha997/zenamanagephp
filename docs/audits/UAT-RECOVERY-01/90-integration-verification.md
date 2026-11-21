# Integration verification (Draft PR) — S1–S4d on `main` cb958ad48

**DRAFT ONLY — NOT APPROVED FOR MERGE OR DEPLOYMENT.** SQLite/PHPUnit only. **MySQL: NOT_VERIFIED.** Backup test classes were **not executed** (they run real `backup:run` on real `storage/backups`).

## Method
Fresh worktree from `origin/main` (`cb958ad48`, 83 commits newer than the local checkout the work was developed on). Hunks were transferred by scoped patches (3-way); upstream had restructured `routes/api.php` and the permission seeds, so some edits were re-applied by hand (see "Integration adjustments"). Pristine `origin/main` was run first as the baseline, then the branch; failures are compared **by test identity**. Same Backup-class filter on both. Raw logs are summarised in `evidence/integration-baseline-vs-branch.txt`.

## Results
| Suite | Baseline `main` | Branch | New failures |
|---|---|---|---|
| Feature + Architecture (Backup x4 excluded) | 2081 tests, 7 F | 2215 tests, 7 F (+134 Recovery tests, all pass) | **0** |
| `tests/Feature/Api` | 825 OK | 825 OK | **0** |
| `tests/Unit` | 960 tests, 7 F | 960 tests, 7 F | **0** |
Pre-existing failures (identical on baseline and branch, not touched): `Feature\Dashboard\DashboardApiTest` ×7, `Unit\OwnerGovernance\EvidenceFreshnessLiveCheckTest` ×5, `Unit\ZenaInvariantsDiscoveryTest` ×2 (14 total; the "15" in earlier reports was measured on the older local base).
**Not "whole repository green".** Known failures remain (above) plus the quarantined tests below.

## Quarantined tests (visible, not passing)
`tests/Feature/Recovery/QUARANTINE.md`, guard test, `scripts/ci/run-recovery-quarantine-diagnostic.sh` (non-blocking). Three tests fail today by design and are excluded **by group only**: D2 PM passes api gate (O-2/O-8), D3 member "my work" (O-18), D2 seeded admin creates users (O-2/O-8) — `evidence/integration-quarantine-diagnostic.txt`. The fourth originally quarantined test (route permissions exist) passes on current `main` thanks to upstream GAP-098 and runs in ordinary CI.

## Integration adjustments (all disclosed)
1. **S1 catalog superseded** by merged upstream GAP-098/GAP-079/GAP-030 → not carried into the PR (zero permission delta vs `main`; `PermissionSeeder` untouched). 5 falsified assertions retired, text preserved in `85-retired-assertions.md` (O-19). Passing S1 tests kept (null-name repair, no wildcard/super_admin, role.assign only System Admin, reseed stability).
2. `routes/api.php` S3b gates re-applied on upstream's new group structure (same 7 routes/middleware/names).
3. `TaskPolicy` Task/User imports restored; fixtures merged with upstream formatting.
4. The released D3 test no longer writes an evidence file into the repo (assertions unchanged).
5. Docs live in `docs/audits/UAT-RECOVERY-01/`, not `docs/owner-decisions/` (that path is lint-governed: YAML frontmatter + canonical Work ID the Owner has not allocated).

## Security checks on the branch tip
No `users.role` reads or admin bypass added (the `hasRole([...'super_admin','admin'...])` text in `DocumentPolicy` is pre-existing legacy, now additive with a permission check). Seeder diff = exactly one new permission, `project.assign_manager`, additive grant to the global System Admin role only. `task.delete` does not imply `task.update`; task writes require assignee or owning PM; tenant isolation enforced in policies and PM assignment; System Admin cannot appoint itself; PM changes audited (`project.manager.changed`) in the same transaction. No migrations, composer/package, config or workflow changes. O-12 not implemented. O-17, O-18 open.

## O-18 on the integrated base
Project Member grants: pass 1 = **20**, pass 2/3 = **31** (+11 view codes, identical to the original finding; base counts are higher than the earlier 15/26 because upstream added its own grants). System Admin 249, Project Manager 60, stable. `project.assign_manager` holder after any pass: System Admin only. Not changed here; see `80-o18-permission-impact-matrix.md` (includes the 7 gated POST routes).

## Merge blockers (open)
O-18 Owner Gate; Work ID/governance conversion of these records (Owner); O-17 decision; the 3 quarantined tests' decisions; CI green incl. MySQL; Backup tests against an isolated storage fixture; the 14 pre-existing failures; review of the S1 supersession (O-19).
