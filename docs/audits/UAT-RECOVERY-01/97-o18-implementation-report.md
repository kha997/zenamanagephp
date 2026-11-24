# O-18 — Local implementation report (Option D, including Project Manager)

**Status: see the final decision line and the status update below.** The patch was built and verified locally on top of PR #422 head `b4440a3d2`. It was then committed as `103bc5c19` on the Owner's instruction "Commit and push". No staging or production permissions were read or changed.

**Owner decisions (2026-11-24):**
- "Option D (26 codes)".
- "YES — INCLUDE PROJECT MANAGER".

## 1. What changed
| File | Change |
|---|---|
| `database/seeders/PermissionSeeder.php` | The Project Member list no longer names the 11 modern `.view` codes, which are the cause of the non-determinism. Project Manager and Project Member are granted with `syncWithoutDetaching` instead of `sync()`, so re-seeding never deletes manual grants. System Admin is unchanged. |
| `database/seeders/ZenaProjectManagerRolePermissionSeeder.php` | New `O18_OPERATIONAL_PERMISSION_CODES` holds `task.view`, `site_diary.view`, `design-item.view`, `material.read`, `material-receipt.view` and `material.view`. They are granted additively to Project Member **and** Project Manager. `grantStrict()` throws if any of these codes does not exist yet, so a silent skip cannot happen again. The seeder already runs after `ZenaPermissionsSeeder`, so **seed order is unchanged**. |
| `app/Console/Commands/ProductionRbacCommand.php` | Docblock only, so the comment matches the new behaviour. No logic change. |
| `tests/Feature/Recovery/UatRecovery05O18PermissionBaselineTest.php` | **New.** 9 tests, listed in §3. |
| `tests/Unit/Seeders/ZenaProjectManagerRolePermissionSeederTest.php` | The exact PM and Member sets this test pins now include the six approved codes. This follows directly from the decision. |
| `tests/Feature/Recovery/UatRecovery02RbacFoundationTest.php` | The characterisation `test_CHAR_member_grants_depend_on_seeder_ordering_pre_existing` pinned the bug that O-18 fixes. It is inverted into `test_O18_member_grants_no_longer_depend_on_seeder_ordering`. The original text is below. |
| `tests/Feature/Recovery/UatRecovery01IdentityRbacTest.php`, `UatRecovery01QuarantineGuardTest.php`, `QUARANTINE.md`, `phpunit.xml` (comment), `scripts/ci/run-recovery-quarantine-diagnostic.sh` (text) | `test_D3_project_member_can_open_my_work` now **passes with its assertion unchanged**, so it was released from quarantine under the documented condition ("the Owner decides O-18 and a single seed run yields the decided state"). The quarantine is now exactly two tests, both under O-2/O-8. |

Original text of the inverted characterisation test, preserved here:
```php
    /** Characterization of PRE-EXISTING drift (not introduced here): see decision package finding S1-F1. */
    #[Group('uat-recovery-02-char')]
    public function test_CHAR_member_grants_depend_on_seeder_ordering_pre_existing(): void
    {
        $first = $this->grantedCodes('Project Member');
        (new \Database\Seeders\PermissionSeeder())->run(); // second pass: codes created later by ZenaPermissionsSeeder now exist
        $second = $this->grantedCodes('Project Member');

        $this->assertContains('task.view', $second, 'PermissionSeeder intends to grant task.view to Project Member');
        $this->assertNotContains('task.view', $first, 'but a fresh DatabaseSeeder run drops it (ordering) — OWNER_DECISION_REQUIRED, not changed in this slice');
    }
```

## 2. Exact effective permission differences
Source: `evidence/o18-effective-permission-diff.txt`.

| Path | Role | Before | After (runs 1/2/3) | Difference |
|---|---|---|---|---|
| `DatabaseSeeder` | System Admin | 249 / 249 | 249 / 249 / 249 | none |
| `DatabaseSeeder` | Project Manager | 60 / 60 | **66 / 66 / 66** | + the six approved codes |
| `DatabaseSeeder` | Project Member | **20 / 31** | **26 / 26 / 26** | compared with fresh seed: + the six codes; compared with re-seed: − `crm.view`, `contract.view`, `boq.view`, `vendor.view`, `event-record.view` |
| `production:rbac` (GAP-115), fresh DB | Project Manager | 62 | 67 | + `design-item.view`, `material-receipt.view`, `material.read`, `material.view`, `site_diary.view` (it already had `task.view` from the command's own baseline) |
| `production:rbac` (GAP-115), fresh DB | Project Member | **32** | **27** | − the five withheld codes. The 27th code is `project.view`, from the command's existing GAP-115 `BASELINE_GRANTS`, not from O-18 |

- **MariaDB 10.4.28** (isolated scratch server): `DatabaseSeeder` gives the same sets as SQLite, 249 / 66 / 26 on every run.

### Important for existing environments: nothing is revoked
- Simulated upgrade: a DB seeded twice with the old code (PM 60, Member 31) was then re-seeded and run through `production:rbac` with the patch.
- **Nothing was removed.** PM gained the six codes plus `project.view`, and Member gained `project.view` (32).
- This is exactly what instructions 5 and 9 require. The consequence: **an environment that already holds the five withheld codes keeps them** until a separately approved convergence step removes them (`95-o18-decision-package.md` §7).

**Production is likely affected.** Production was provisioned through the go-live runbook. If that ran `production:rbac`, its Project Members very probably already hold all five withheld codes: before the patch, this command granted them on its **first** run. This is not verified, because no production access was used. A read-only inventory needs your approval.

## 3. Regression tests (`UatRecovery05O18PermissionBaselineTest`)
1. A first seed gives Project Member exactly the 26-code baseline.
2. Project Manager is exactly the 60 pinned pre-O-18 codes plus the six, and the constant equals the approved six.
3. Seeds 1, 2 and 3 give identical grants for all three roles. System Admin keeps every permission.
4. Re-seeding keeps manual grants (Member `document.update`, PM `webhook.view`) and adds nothing beyond the baseline.
5. Across 3 seed runs, Project Member and Project Manager never receive a withheld commercial code or an admin-only code: `role.*`, `permission.*`, `audit.view`, `project.assign_manager`, `ai.suggest`, `crm.manage`, `payroll.*`. This also closes O-19 G-2.
6. Project Member and Project Manager get 200 on `/app/my-work`, `/app/today`, `/operator/schedule` and `/app/time`.
7. Project Member **and** Project Manager are denied:
   - 8 API routes: CRM, contracts, vendors, BOQs and event records, all 403;
   - 8 web routes: CRM, CRM reports, contracts, vendors, BOQs and the activity feed, none returning 200;
   - the AI-summary POST is also denied for the Member.
8. Tenant isolation: a foreign-tenant task is not readable by id, and it does not appear in the task list or in my-work.
9. `task.view` does not open other people's timesheets:
   - edit and delete of another user's entry are refused and the entry is unchanged;
   - report, CSV, the review page and review approve all return 403.

- **RED check.** On the unpatched seeders, 8 of the 9 failed. The denial test already passes there, because a single old seed grants no commercial codes; test 5 covers the re-seed case.

## 4. Verification
Evidence: `evidence/o18-local-verification.txt`. In every run below, the 4 Backup classes were excluded because they write to the real `storage/backups`.

| Check | Result |
|---|---|
| **SQLite** | |
| Recovery suite | **OK: 144 tests, 957 assertions** |
| Feature + Api + Architecture | **3050 tests, 0 failures**, 33 skipped |
| Unit | 960 tests, 7 failures, all pre-existing and identical to `main` (EvidenceFreshnessLiveCheck ×5, ZenaInvariantsDiscovery ×2) |
| New O-18 class | **9 / 9 pass** (82 assertions). On the unpatched seeders 8 / 9 fail (RED check) |
| Quarantine group | 2 tests, 2 failures (D2 ×2, O-2/O-8). D3 released and passing |
| **Isolated MariaDB 10.4.28** (scratch server, now stopped and deleted) | |
| `DatabaseSeeder` ×3 | 249 / 66 / 26 on every run; sets identical to SQLite |
| Recovery + seeder unit test | 146 tests, 2 failures. Both are 04b `split_percent` failures, **identical on unpatched code**, so pre-existing (§5) |
| **Static checks** | |
| PHPStan (project config) | no errors |
| php-cs-fixer (changed files) | clean |
| SSOT orphan routes / lint_tests / domain ownership | pass |

**Not verified:**
- Real **MySQL 8**, which is what CI uses. MariaDB is the closest server available locally.
- Any staging or production database. None was touched.
- The 4 Backup classes locally. They passed in CI before this patch.

## 5. Findings outside O-18 (not changed)
- **MySQL-only defect, pre-existing.**
  - `task_assignments.split_percent` is used by the CoreProject v1 task-assignment request, model and controller, but **no migration creates the column**.
  - On real MySQL or MariaDB, `POST /api/v1/task-assignments` therefore fails with SQLSTATE 42S22, and the endpoint returns 500.
  - SQLite hides the bug: it reads the unknown double-quoted identifier as a string literal, so the sum is 0.
  - On MariaDB this fails two 04b Recovery tests (`test_owning_project_pm_is_authorised_on_the_assignment_surfaces` and `test_unauthorised_actors_cannot_assign_reassign_or_unassign_on_any_surface`).
  - Recommended: give it its own Work ID.
- **The quarantine still holds two tests** (O-2/O-8).

## Decision
**O18_LOCAL_PATCH_VERIFIED.** This applied to the local patch, verified on SQLite and on MariaDB-level storage.

**Status update (2026-11-24):**
- Committed as `103bc5c19` and pushed fast-forward to `integration/uat-recovery-s1-s4d`, with no force.
- CI on `103bc5c19`: 35 of 35 checks pass. CI runs real MySQL 8, which was not available locally (§4).
- PR #422 is still a Draft and MERGE_NO_GO. Nothing is merged or deployed.

**Before any deploy:**
- A read-only inventory of what each environment currently grants.
- An approved convergence step to remove the five withheld codes where they are already granted. This is likely needed on production.
