# Tasks Domain Test Files Audit

**Date:** 2025-11-08  
**Purpose:** Complete inventory of all tasks-related test files for Tasks Domain organization work package  
**Seed:** 34567 (fixed for reproducibility)

---

## Summary

**Total Tasks Test Files:** 19  
**Files with @group tasks:** 0  
**Files needing @group tasks:** 19

---

## Test Files Inventory

### Feature Tests

| File | Class Name | @group Status | Test Methods | Notes |
|------|-----------|---------------|---------------|-------|
| `tests/Feature/TaskApiTest.php` | `TaskApiTest` | ❌ **MISSING** | 4+ | Task API CRUD operations |
| `tests/Feature/TasksApiIntegrationTest.php` | `TasksApiIntegrationTest` | ❌ **MISSING** | Multiple | Comprehensive API integration tests |
| `tests/Feature/TaskAssignmentTest.php` | `TaskAssignmentTest` | ❌ **MISSING** | Multiple | Task assignment functionality |
| `tests/Feature/TaskCreationTest.php` | `TaskCreationTest` | ❌ **MISSING** | Multiple | Task creation tests |
| `tests/Feature/TaskDependenciesTest.php` | `TaskDependenciesTest` | ❌ **MISSING** | Multiple | Task dependencies tests |
| `tests/Feature/TaskEditTest.php` | `TaskEditTest` | ❌ **MISSING** | Multiple | Task editing tests |
| `tests/Feature/TaskTest.php` | `TaskTest` | ❌ **MISSING** | Multiple | General task feature tests |
| `tests/Feature/Api/TaskApiTest.php` | `TaskApiTest` | ❌ **MISSING** | Multiple | Task API tests (Api namespace) |
| `tests/Feature/Api/TaskCommentApiTest.php` | `TaskCommentApiTest` | ❌ **MISSING** | Multiple | Task comment API tests |
| `tests/Feature/Api/TaskDependenciesTest.php` | `TaskDependenciesTest` | ❌ **MISSING** | Multiple | Task dependencies API tests |
| `tests/Feature/Api/Tasks/TasksContractTest.php` | `TasksContractTest` | ❌ **MISSING** | Multiple | Tasks API contract tests |
| `tests/Feature/ProjectTaskControllerTest.php` | `ProjectTaskControllerTest` | ❌ **MISSING** | Multiple | Project task controller tests |

### Unit Tests

| File | Class Name | @group Status | Test Methods | Notes |
|------|-----------|---------------|---------------|-------|
| `tests/Unit/Models/TaskTest.php` | `TaskTest` | ❌ **MISSING** | 29+ | Task model unit tests |
| `tests/Unit/Services/TaskManagementServiceTest.php` | `TaskManagementServiceTest` | ❌ **MISSING** | Multiple | Task management service unit tests |
| `tests/Unit/Services/TaskDependencyServiceTest.php` | `TaskDependencyServiceTest` | ❌ **MISSING** | Multiple | Task dependency service unit tests |
| `tests/Unit/TaskServiceTest.php` | `TaskServiceTest` | ❌ **MISSING** | Multiple | Task service unit tests |

### Browser Tests

| File | Class Name | @group Status | Test Methods | Notes |
|------|-----------|---------------|---------------|-------|
| `tests/Browser/TaskManagementTest.php` | `TaskManagementTest` | ❌ **MISSING** | Multiple | Browser/Dusk task management tests |
| `tests/Browser/TaskEditBrowserTest.php` | `TaskEditBrowserTest` | ❌ **MISSING** | Multiple | Browser task editing tests |
| `tests/Browser/Smoke/TasksFlowTest.php` | `TasksFlowTest` | ❌ **MISSING** | Multiple | Smoke tests for tasks flow |

---

## Detailed File Analysis

### Feature Tests (12 files)

#### 1. `tests/Feature/TaskApiTest.php`
- **Status:** ❌ Missing `@group tasks`
- **Class:** `TaskApiTest`
- **Test Methods:** 4+ (using `@test` annotations)
  - `test_api_tasks_index_returns_correct_data()`
  - `test_api_tasks_with_filters()`
  - `test_api_tasks_search_functionality()`
  - `test_api_tasks_pagination()`
- **Action Required:** Add `@group tasks` annotation in PHPDoc block

#### 2. `tests/Feature/TasksApiIntegrationTest.php`
- **Status:** ❌ Missing `@group tasks`
- **Class:** `TasksApiIntegrationTest`
- **Test Methods:** Multiple (comprehensive integration tests)
- **Action Required:** Add `@group tasks` annotation in PHPDoc block

#### 3-12. Other Feature Test Files
- All missing `@group tasks` annotation
- Action Required: Add `@group tasks` annotation to each file

### Unit Tests (4 files)

#### 1. `tests/Unit/Models/TaskTest.php`
- **Status:** ❌ Missing `@group tasks`
- **Class:** `TaskTest`
- **Test Methods:** 29+ (using `@test` annotations)
- **Action Required:** Add `@group tasks` annotation in PHPDoc block

#### 2-4. Other Unit Test Files
- All missing `@group tasks` annotation
- Action Required: Add `@group tasks` annotation to each file

### Browser Tests (3 files)

#### 1. `tests/Browser/TaskManagementTest.php`
- **Status:** ❌ Missing `@group tasks`
- **Class:** `TaskManagementTest`
- **Test Methods:** Multiple
- **Action Required:** Add `@group tasks` annotation in PHPDoc block

#### 2-3. Other Browser Test Files
- All missing `@group tasks` annotation
- Action Required: Add `@group tasks` annotation to each file

---

## Checklist for Future Agent

### Phase 1: Add @group Annotations

- [ ] `tests/Feature/TaskApiTest.php` - Add `@group tasks` to PHPDoc
- [ ] `tests/Feature/TasksApiIntegrationTest.php` - Add `@group tasks` to PHPDoc
- [ ] `tests/Feature/TaskAssignmentTest.php` - Add `@group tasks` to PHPDoc
- [ ] `tests/Feature/TaskCreationTest.php` - Add `@group tasks` to PHPDoc
- [ ] `tests/Feature/TaskDependenciesTest.php` - Add `@group tasks` to PHPDoc
- [ ] `tests/Feature/TaskEditTest.php` - Add `@group tasks` to PHPDoc
- [ ] `tests/Feature/TaskTest.php` - Add `@group tasks` to PHPDoc
- [ ] `tests/Feature/Api/TaskApiTest.php` - Add `@group tasks` to PHPDoc
- [ ] `tests/Feature/Api/TaskCommentApiTest.php` - Add `@group tasks` to PHPDoc
- [ ] `tests/Feature/Api/TaskDependenciesTest.php` - Add `@group tasks` to PHPDoc
- [ ] `tests/Feature/Api/Tasks/TasksContractTest.php` - Add `@group tasks` to PHPDoc
- [ ] `tests/Feature/ProjectTaskControllerTest.php` - Add `@group tasks` to PHPDoc
- [ ] `tests/Unit/Models/TaskTest.php` - Add `@group tasks` to PHPDoc
- [ ] `tests/Unit/Services/TaskManagementServiceTest.php` - Add `@group tasks` to PHPDoc
- [ ] `tests/Unit/Services/TaskDependencyServiceTest.php` - Add `@group tasks` to PHPDoc
- [ ] `tests/Unit/TaskServiceTest.php` - Add `@group tasks` to PHPDoc
- [ ] `tests/Browser/TaskManagementTest.php` - Add `@group tasks` to PHPDoc
- [ ] `tests/Browser/TaskEditBrowserTest.php` - Add `@group tasks` to PHPDoc
- [ ] `tests/Browser/Smoke/TasksFlowTest.php` - Add `@group tasks` to PHPDoc

### Verification Command

After adding annotations, verify with:
```bash
grep -r "@group tasks" tests/Feature/ tests/Unit/ tests/Integration/ tests/Browser/
```

Expected output should show all 19 files.

---

## Test Suite Organization

### Current Test Suites (from Core Infrastructure)

The following test suites are already configured in `phpunit.xml`:

- `tasks-unit` - Unit tests with `@group tasks`
- `tasks-feature` - Feature tests with `@group tasks`
- `tasks-integration` - Integration tests with `@group tasks`

### Browser Tests

Browser tests (Dusk) are not included in PHPUnit test suites by default. Consider:
- Adding to Playwright E2E tests (handled by Codex)
- Or creating separate Dusk test suite if needed

---

## Notes

1. **E2E Tests:** Tasks E2E tests in `tests/e2e/tasks/` (if exists) are handled separately.

2. **Test Methods Count:** Some test methods use `@test` annotations instead of `test_` prefix. Both should be included in the `@group tasks` annotation.

3. **Namespace Conflicts:** There are multiple task-related test classes:
   - `Tests\Feature\TaskApiTest`
   - `Tests\Feature\Api\TaskApiTest`
   - `Tests\Feature\TaskDependenciesTest`
   - `Tests\Feature\Api\TaskDependenciesTest`
   - All should have `@group tasks` annotation.

4. **Browser Tests:** Browser/Dusk tests may need special handling. Consider if they should be part of the tasks domain or handled separately.

5. **Task Dependencies:** Task dependency tests are important for this domain - ensure they're included.

---

## Next Steps

1. Future agent should add `@group tasks` annotations to all 19 files
2. Verify all annotations are correct using grep command
3. Run test suite to ensure tests are grouped correctly:
   ```bash
   php artisan test --group=tasks --seed=34567
   ```
4. Verify test suites work:
   ```bash
   php artisan test --testsuite=tasks-feature
   php artisan test --testsuite=tasks-unit
   php artisan test --testsuite=tasks-integration
   ```

---

**Last Updated:** 2025-11-08  
**Maintainer:** Cursor Agent (Prepared for future agent)
