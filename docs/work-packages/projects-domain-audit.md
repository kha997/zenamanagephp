# Projects Domain Test Files Audit

**Date:** 2025-11-08  
**Purpose:** Complete inventory of all projects-related test files for Projects Domain organization work package  
**Seed:** 23456 (fixed for reproducibility)

---

## Summary

**Total Projects Test Files:** 31  
**Files with @group projects:** 0  
**Files needing @group projects:** 31

---

## Test Files Inventory

### Feature Tests

| File | Class Name | @group Status | Test Methods | Notes |
|------|-----------|---------------|---------------|-------|
| `tests/Feature/ProjectApiTest.php` | `ProjectApiTest` | ❌ **MISSING** | 8 | Project API CRUD operations |
| `tests/Feature/ProjectsApiIntegrationTest.php` | `ProjectsApiIntegrationTest` | ❌ **MISSING** | 11 | Comprehensive API integration tests |
| `tests/Feature/ProjectManagementTest.php` | `ProjectManagementTest` | ❌ **MISSING** | 1+ | Project management features |
| `tests/Feature/ProjectTest.php` | `ProjectTest` | ❌ **MISSING** | Multiple | General project feature tests |
| `tests/Feature/ProjectModuleTest.php` | `ProjectModuleTest` | ❌ **MISSING** | Multiple | Project module tests |
| `tests/Feature/ProjectTaskControllerTest.php` | `ProjectTaskControllerTest` | ❌ **MISSING** | Multiple | Project task controller tests |
| `tests/Feature/SimpleProjectMilestoneTest.php` | `SimpleProjectMilestoneTest` | ❌ **MISSING** | Multiple | Project milestone tests |
| `tests/Feature/VerySimpleProjectMilestoneTest.php` | `VerySimpleProjectMilestoneTest` | ❌ **MISSING** | Multiple | Simple milestone tests |
| `tests/Feature/Api/App/ProjectsControllerTest.php` | `ProjectsControllerTest` | ❌ **MISSING** | Multiple | App API projects controller |
| `tests/Feature/Api/ProjectManagerApiIntegrationTest.php` | `ProjectManagerApiIntegrationTest` | ❌ **MISSING** | Multiple | Project manager API integration |
| `tests/Feature/Api/Projects/ProjectsContractTest.php` | `ProjectsContractTest` | ❌ **MISSING** | Multiple | Projects API contract tests |
| `tests/Feature/Web/ProjectControllerTest.php` | `ProjectControllerTest` | ❌ **MISSING** | Multiple | Web project controller tests |
| `tests/Feature/Web/WebProjectControllerTest.php` | `WebProjectControllerTest` | ❌ **MISSING** | Multiple | Web project controller tests |
| `tests/Feature/Web/WebProjectControllerApiResponseDebugTest.php` | `WebProjectControllerApiResponseDebugTest` | ❌ **MISSING** | Multiple | Debug tests for API responses |
| `tests/Feature/Web/WebProjectControllerTenantDebugTest.php` | `WebProjectControllerTenantDebugTest` | ❌ **MISSING** | Multiple | Debug tests for tenant isolation |
| `tests/Feature/Web/WebProjectControllerShowDebugTest.php` | `WebProjectControllerShowDebugTest` | ❌ **MISSING** | Multiple | Debug tests for show method |
| `tests/Feature/Integration/CompleteProjectWorkflowTest.php` | `CompleteProjectWorkflowTest` | ❌ **MISSING** | Multiple | Complete project workflow |
| `tests/Feature/Integration/ProjectCalculationDebugTest.php` | `ProjectCalculationDebugTest` | ❌ **MISSING** | Multiple | Project calculation debug tests |
| `tests/Feature/Integration/ProjectsControllerTest.php` | `ProjectsControllerTest` | ❌ **MISSING** | Multiple | Projects controller integration tests |
| `tests/Feature/Integration/SimplifiedProjectsControllerTest.php` | `SimplifiedProjectsControllerTest` | ❌ **MISSING** | Multiple | Simplified controller tests |
| `tests/Feature/Integration/WebProjectControllerTest.php` | `WebProjectControllerTest` | ❌ **MISSING** | Multiple | Web controller integration tests |
| `tests/Feature/Unit/ProjectPolicyTest.php` | `ProjectPolicyTest` | ❌ **MISSING** | Multiple | Project policy tests (Feature namespace) |

### Unit Tests

| File | Class Name | @group Status | Test Methods | Notes |
|------|-----------|---------------|---------------|-------|
| `tests/Unit/Models/ProjectTest.php` | `ProjectTest` | ❌ **MISSING** | 14 | Project model unit tests |
| `tests/Unit/Services/ProjectServiceTest.php` | `ProjectServiceTest` | ❌ **MISSING** | Multiple | Project service unit tests |
| `tests/Unit/Repositories/ProjectRepositoryTest.php` | `ProjectRepositoryTest` | ❌ **MISSING** | Multiple | Project repository unit tests |
| `tests/Unit/Controllers/Api/ProjectManagerControllerTest.php` | `ProjectManagerControllerTest` | ❌ **MISSING** | Multiple | Project manager controller unit tests |
| `tests/Unit/ProjectPolicyTest.php` | `ProjectPolicyTest` | ❌ **MISSING** | Multiple | Project policy unit tests |
| `tests/Unit/Policies/ProjectPolicyTest.php` | `ProjectPolicyTest` | ❌ **MISSING** | Multiple | Project policy tests (Policies namespace) |
| `tests/Unit/Events/ProjectEventTest.php` | `ProjectEventTest` | ❌ **MISSING** | Multiple | Project event unit tests |

### Browser Tests

| File | Class Name | @group Status | Test Methods | Notes |
|------|-----------|---------------|---------------|-------|
| `tests/Browser/ProjectManagementTest.php` | `ProjectManagementTest` | ❌ **MISSING** | 7 | Browser/Dusk project management tests |
| `tests/Browser/Smoke/ProjectsFlowTest.php` | `ProjectsFlowTest` | ❌ **MISSING** | 4 | Smoke tests for projects flow |

---

## Detailed File Analysis

### Feature Tests (22 files)

#### 1. `tests/Feature/ProjectApiTest.php`
- **Status:** ❌ Missing `@group projects`
- **Class:** `ProjectApiTest`
- **Test Methods:** 8
  - `test_can_get_all_projects()`
  - `test_can_create_project()`
  - `test_can_get_single_project()`
  - `test_can_update_project()`
  - `test_can_delete_project()`
  - `test_create_project_validation_errors()`
  - `test_unauthorized_access_to_projects()`
  - `test_project_not_found()`
- **Action Required:** Add `@group projects` annotation in PHPDoc block

#### 2. `tests/Feature/ProjectsApiIntegrationTest.php`
- **Status:** ❌ Missing `@group projects`
- **Class:** `ProjectsApiIntegrationTest`
- **Test Methods:** 11+ (using `@test` annotations)
- **Action Required:** Add `@group projects` annotation in PHPDoc block

#### 3. `tests/Feature/ProjectManagementTest.php`
- **Status:** ❌ Missing `@group projects`
- **Class:** `ProjectManagementTest`
- **Test Methods:** 1+
- **Action Required:** Add `@group projects` annotation in PHPDoc block

#### 4-22. Other Feature Test Files
- All missing `@group projects` annotation
- Action Required: Add `@group projects` annotation to each file

### Unit Tests (7 files)

#### 1. `tests/Unit/Models/ProjectTest.php`
- **Status:** ❌ Missing `@group projects`
- **Class:** `ProjectTest`
- **Test Methods:** 14
  - `test_can_create_project_with_tenant_isolation()`
  - `test_tenant_isolation_prevents_cross_tenant_access()`
  - `test_project_belongs_to_tenant()`
  - `test_project_belongs_to_owner()`
  - `test_project_valid_statuses()`
  - `test_project_valid_priorities()`
  - `test_project_fillable_attributes()`
  - `test_project_casts()`
  - `test_project_default_attributes()`
  - `test_project_table_name()`
  - `test_project_primary_key()`
  - `test_project_key_type()`
  - `test_project_incrementing()`
  - `test_project_uses_ulid()`
- **Action Required:** Add `@group projects` annotation in PHPDoc block

#### 2-7. Other Unit Test Files
- All missing `@group projects` annotation
- Action Required: Add `@group projects` annotation to each file

### Browser Tests (2 files)

#### 1. `tests/Browser/ProjectManagementTest.php`
- **Status:** ❌ Missing `@group projects`
- **Class:** `ProjectManagementTest`
- **Test Methods:** 7 (using `@test` annotations)
- **Action Required:** Add `@group projects` annotation in PHPDoc block

#### 2. `tests/Browser/Smoke/ProjectsFlowTest.php`
- **Status:** ❌ Missing `@group projects`
- **Class:** `ProjectsFlowTest`
- **Test Methods:** 4 (using `@test` annotations)
- **Action Required:** Add `@group projects` annotation in PHPDoc block

---

## Checklist for Future Agent

### Phase 1: Add @group Annotations

- [ ] `tests/Feature/ProjectApiTest.php` - Add `@group projects` to PHPDoc
- [ ] `tests/Feature/ProjectsApiIntegrationTest.php` - Add `@group projects` to PHPDoc
- [ ] `tests/Feature/ProjectManagementTest.php` - Add `@group projects` to PHPDoc
- [ ] `tests/Feature/ProjectTest.php` - Add `@group projects` to PHPDoc
- [ ] ... (all 31 files need annotation)

### Verification Command

After adding annotations, verify with:
```bash
grep -r "@group projects" tests/Feature/ tests/Unit/ tests/Integration/ tests/Browser/
```

Expected output should show all 31 files.

---

## Test Suite Organization

### Current Test Suites (from Core Infrastructure)

The following test suites are already configured in `phpunit.xml`:

- `projects-unit` - Unit tests with `@group projects`
- `projects-feature` - Feature tests with `@group projects`
- `projects-integration` - Integration tests with `@group projects`

### Browser Tests

Browser tests (Dusk) are not included in PHPUnit test suites by default. Consider:
- Adding to Playwright E2E tests (handled by Codex)
- Or creating separate Dusk test suite if needed

---

## Notes

1. **E2E Tests:** Projects E2E tests in `tests/e2e/projects/` (if exists) are handled separately.

2. **Test Methods Count:** Some test methods use `@test` annotations instead of `test_` prefix. Both should be included in the `@group projects` annotation.

3. **Namespace Conflicts:** There are multiple `ProjectPolicyTest` classes:
   - `Tests\Feature\Unit\ProjectPolicyTest`
   - `Tests\Unit\ProjectPolicyTest`
   - `Tests\Unit\Policies\ProjectPolicyTest`
   - All should have `@group projects` annotation.

4. **Browser Tests:** Browser/Dusk tests may need special handling. Consider if they should be part of the projects domain or handled separately.

5. **Debug Tests:** Several debug test files exist (e.g., `WebProjectControllerApiResponseDebugTest.php`). These should also be included in the projects domain.

---

## Next Steps

1. Future agent should add `@group projects` annotations to all 31 files
2. Verify all annotations are correct using grep command
3. Run test suite to ensure tests are grouped correctly:
   ```bash
   php artisan test --group=projects --seed=23456
   ```
4. Verify test suites work:
   ```bash
   php artisan test --testsuite=projects-feature
   php artisan test --testsuite=projects-unit
   php artisan test --testsuite=projects-integration
   ```

---

**Last Updated:** 2025-11-08  
**Maintainer:** Cursor Agent (Prepared for future agent)
