# Zena* Alias Retirement Proposal

**Date:** 2026-07-14
**Branch:** `worktree-zena-project-model-consolidation` (PR #163)
**Status:** PROPOSAL — no files deleted yet. Awaiting user approval.

---

## Background

Six `Zena*` model aliases were created as thin wrappers during early development.
The SSOT (`docs/architecture/module-ownership-ssot.md`) froze them in place:
"keep thin, do not add behavior, do not create more." After batches 4-5 cleanup,
most have shed their test/factory consumers. This proposal audits each alias for
remaining references and recommends safe deletion or continued retention.

Two additional aliases (`ZenaPermission`, `ZenaRole`) were found to have heavy
seeder/test usage and are recommended for **retention**.

---

## Reference Audit

### `App\Models\ZenaTask` — 3 refs

| # | File | Type | Editable? |
|---|------|------|-----------|
| 1 | `app/Models/ZenaTask.php` | class itself | **Delete** |
| 2 | `database/factories/ZenaTaskFactory.php` | factory | **Delete** |
| 3 | `tests/Feature/Architecture/ModuleOwnershipSourceInvariantTest.php` | architecture test | Edit: remove alias from invariant check |

**Verdict: SAFE TO DELETE** — no migration reference, no seeder reference, no production code.

Proposed `git rm`:
```bash
git rm app/Models/ZenaTask.php
git rm database/factories/ZenaTaskFactory.php
# Edit ModuleOwnershipSourceInvariantTest.php to remove ZenaTask from alias list
```

---

### `App\Models\ZenaSubmittal` — 2 refs

| # | File | Type | Editable? |
|---|------|------|-----------|
| 1 | `app/Models/ZenaSubmittal.php` | class itself | **Delete** |
| 2 | `database/factories/ZenaSubmittalFactory.php` | factory | **Delete** |

**Verdict: SAFE TO DELETE** — no migration, no seeder, no tests outside factory.

Proposed `git rm`:
```bash
git rm app/Models/ZenaSubmittal.php
git rm database/factories/ZenaSubmittalFactory.php
```

---

### `App\Models\ZenaRfi` — 2 refs

| # | File | Type | Editable? |
|---|------|------|-----------|
| 1 | `app/Models/ZenaRfi.php` | class itself | **Delete** |
| 2 | `database/factories/ZenaRfiFactory.php` | factory | **Delete** |

**Verdict: SAFE TO DELETE** — no migration, no seeder, no tests outside factory.

Proposed `git rm`:
```bash
git rm app/Models/ZenaRfi.php
git rm database/factories/ZenaRfiFactory.php
```

---

### `App\Models\ZenaNotification` — 2 refs

| # | File | Type | Editable? |
|---|------|------|-----------|
| 1 | `app/Models/ZenaNotification.php` | class itself | **Delete** |
| 2 | `database/factories/ZenaNotificationFactory.php` | factory | **Delete** |

**Verdict: SAFE TO DELETE** — no migration, no seeder, no tests outside factory.

Proposed `git rm`:
```bash
git rm app/Models/ZenaNotification.php
git rm database/factories/ZenaNotificationFactory.php
```

---

### `App\Models\ZenaChangeRequest` — 2 refs

| # | File | Type | Editable? |
|---|------|------|-----------|
| 1 | `app/Models/ZenaChangeRequest.php` | class itself | **Delete** |
| 2 | `database/factories/ZenaChangeRequestFactory.php` | factory | **Delete** |

**Verdict: SAFE TO DELETE** — no migration, no seeder, no tests outside factory.

Proposed `git rm`:
```bash
git rm app/Models/ZenaChangeRequest.php
git rm database/factories/ZenaChangeRequestFactory.php
```

---

### `App\Models\ZenaProject` — 6 refs

| # | File | Type | Editable? |
|---|------|------|-----------|
| 1 | `app/Models/ZenaProject.php` | class itself | **Delete** |
| 2 | `database/factories/ZenaProjectFactory.php` | factory | **Delete** |
| 3 | `database/factories/DocumentFactory.php` | factory (imports ZenaProject) | Edit: change import to `App\Models\Project` |
| 4 | `database/migrations/2025_09_15_144442_unify_projects_table_schema.php` | historical migration | **MUST KEEP** — migrations are immutable |
| 5 | `tests/Feature/Api/SubmittalApiTest.php` | test (uses ZenaProjectFactory) | Edit: change to `ProjectFactory` |
| 6 | `tests/Feature/Api/SubmittalShowApiTest.php` | test (uses ZenaProjectFactory) | Edit: change to `ProjectFactory` |

**Verdict: SAFE TO DELETE** — with 3 file edits (DocumentFactory, 2 test files) and migration kept.

Proposed `git rm` + edits:
```bash
git rm app/Models/ZenaProject.php
git rm database/factories/ZenaProjectFactory.php
# Edit: DocumentFactory.php, SubmittalApiTest.php, SubmittalShowApiTest.php
# Migration stays (historical reference — cannot be edited)
```

---

### `App\Models\ZenaPermission` — 10 refs — RETAIN

| # | File | Type | Editable? |
|---|------|------|-----------|
| 1 | `app/Models/ZenaPermission.php` | class itself | Keep |
| 2 | `database/factories/ZenaPermissionFactory.php` | factory | Keep |
| 3 | `database/seeders/DatabaseSeeder.php` | seeder (production) | Active consumer |
| 4 | `database/seeders/ZenaAdminRolePermissionSeeder.php` | seeder (production) | Active consumer |
| 5 | `database/seeders/ZenaPermissionsSeeder.php` | seeder (production) | Active consumer |
| 6 | `database/seeders/ZenaRbacSeeder.php` | seeder (production) | Active consumer |
| 7 | `tests/Feature/Zena/ZenaRouteStackHygieneInvariantTest.php` | test | Active consumer |
| 8 | `tests/Feature/Zena/ZenaSeedParityInvariantTest.php` | test | Active consumer |
| 9 | `tests/Feature/Zena/ZenaSeederWiringInvariantTest.php` | test | Active consumer |
| 10 | `tests/Unit/AuthServiceTest.php` | unit test | Active consumer |

**Verdict: KEEP** — 4 active seeders and 4 tests depend on this alias. Removing would require
refactoring all seeder references to use `App\Models\Permission` directly, which is a separate
slice of work (seeder refactor + RBAC wiring verification).

---

### `App\Models\ZenaRole` — 5 refs — RETAIN

| # | File | Type | Editable? |
|---|------|------|-----------|
| 1 | `app/Models/ZenaRole.php` | class itself | Keep |
| 2 | `database/factories/ZenaRoleFactory.php` | factory | Keep |
| 3 | `database/migrations/2025_09_15_144442_unify_projects_table_schema.php` | historical migration | **MUST KEEP** |
| 4 | `database/seeders/ZenaRbacSeeder.php` | seeder (production) | Active consumer |
| 5 | `tests/Unit/AuthServiceTest.php` | unit test | Active consumer |

**Verdict: KEEP** — historical migration reference (migrations are immutable) plus active seeder
and test consumers. Same seeder-refactor dependency as ZenaPermission.

---

## Summary

| Alias | Refs | Verdict | Action |
|-------|------|---------|--------|
| `ZenaTask` | 3 | **SAFE TO DELETE** | `git rm` class + factory, edit 1 test |
| `ZenaSubmittal` | 2 | **SAFE TO DELETE** | `git rm` class + factory |
| `ZenaRfi` | 2 | **SAFE TO DELETE** | `git rm` class + factory |
| `ZenaNotification` | 2 | **SAFE TO DELETE** | `git rm` class + factory |
| `ZenaChangeRequest` | 2 | **SAFE TO DELETE** | `git rm` class + factory |
| `ZenaProject` | 6 | **SAFE TO DELETE** | `git rm` class + factory, edit 3 files, keep migration |
| `ZenaPermission` | 10 | **KEEP** | Active seeders + tests |
| `ZenaRole` | 5 | **KEEP** | Migration + seeder + tests |

---

## Recommended Execution Order

If user approves, execute in two batches:

**Batch 1 (low risk — no migration refs, no seeder refs):**
```bash
git rm app/Models/ZenaTask.php database/factories/ZenaTaskFactory.php
git rm app/Models/ZenaSubmittal.php database/factories/ZenaSubmittalFactory.php
git rm app/Models/ZenaRfi.php database/factories/ZenaRfiFactory.php
git rm app/Models/ZenaNotification.php database/factories/ZenaNotificationFactory.php
git rm app/Models/ZenaChangeRequest.php database/factories/ZenaChangeRequestFactory.php
# Edit: ModuleOwnershipSourceInvariantTest.php (remove ZenaTask)
```
Run: `php artisan test tests/Feature/Architecture/` → must pass.

**Batch 2 (needs file edits — ZenaProject):**
```bash
git rm app/Models/ZenaProject.php database/factories/ZenaProjectFactory.php
# Edit: DocumentFactory.php (ZenaProject→Project import)
# Edit: SubmittalApiTest.php, SubmittalShowApiTest.php (ZenaProjectFactory→ProjectFactory)
```
Run: `php artisan test tests/Feature/Architecture/ tests/Feature/Api/SubmittalApiTest.php tests/Feature/Api/SubmittalShowApiTest.php` → must pass.

**Final verification:**
```bash
php artisan test --testsuite=Feature   # baseline ~884
vendor/bin/phpstan analyse --memory-limit=1G  # exit 0
```

---

## SSOT Update

After execution, update `docs/architecture/module-ownership-ssot.md`:
- Remove `ZenaTask`, `ZenaSubmittal`, `ZenaRfi`, `ZenaNotification`, `ZenaChangeRequest`
  from the "Freeze thin model aliases" section
- Keep `ZenaPermission` and `ZenaRole` with updated ref notes
- Update relevant Ownership Matrix rows to remove alias mentions

---

**Conclusion: Chưa xóa gì — chờ user duyệt.**
