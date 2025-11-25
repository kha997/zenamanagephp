# Prompt for Continue AI Assistant

Copy and paste this prompt into Continue:

---

## Task: Implement Tests for Kanban UX Features

I need you to implement comprehensive tests for Kanban UX improvement features. Please follow the specification document exactly.

### 📚 READ FIRST (Required):
1. **`docs/testing/KANBAN_UX_TEST_SPEC.md`** - Complete test specification with all test cases
2. **`.cursorrules`** - Check "Agent Guard (Testing Mode - Temporary)" section for ALLOW_PATHS and safety rules

### 🎯 What to Implement:

Create **6 test files** with ~50 test cases:

1. **`tests/Unit/Services/TaskStatusTransitionServiceTest.php`** - 23 test cases
   - Test all status transitions (backlog → in_progress, etc.)
   - Test project status restrictions
   - Test dependencies validation
   - Test reason requirements
   - Follow pattern: `tests/Unit/Services/ProjectServiceTest.php`

2. **`tests/Feature/Api/Tasks/MoveTaskEndpointTest.php`** - 13 test cases
   - Test PATCH /api/tasks/{id}/move endpoint
   - Test valid/invalid transitions
   - Test optimistic locking (409)
   - Test error responses (422)
   - Follow pattern: `tests/Feature/TaskDependenciesTest.php`

3. **`tests/Integration/TaskStatusSyncTest.php`** - 4 test cases
   - Test project status sync to tasks
   - Test dependencies validation

4. **`tests/e2e/core/tasks/kanban-drag-drop-error-handling.spec.ts`** - 9 test cases
   - Test error modal display
   - Test visual feedback (red borders)
   - Test rollback animation
   - Test tooltips
   - Follow pattern: `tests/e2e/core/tasks/tasks-edit-delete-status.spec.ts`

5. **`frontend/src/features/tasks/utils/__tests__/errorExplanation.test.ts`** - 6 test cases
   - Test error explanation utility

6. **`frontend/src/features/tasks/hooks/__tests__/useTaskTransitionValidation.test.ts`** - 3 test cases
   - Test validation hook

### 🚨 CRITICAL RULES:

**NEVER:**
- ❌ Modify files in `app/`, `routes/`, `database/`, `config/`
- ❌ Create test files outside ALLOW_PATHS
- ❌ Use random seeds (always use fixed seeds like `45678`, `56789`)
- ❌ Skip DomainTestIsolation trait

**ALWAYS:**
- ✅ Follow test spec exactly (`docs/testing/KANBAN_UX_TEST_SPEC.md`)
- ✅ Use fixed domain seeds: `$this->setDomainSeed(45678)`
- ✅ Use `DomainTestIsolation` trait
- ✅ Use `TestDataSeeder::seedTasksDomain()` for test data
- ✅ Follow existing test patterns from reference files

### 📋 Implementation Order:

1. Start with `TaskStatusTransitionServiceTest.php` (Unit Tests)
2. Then `errorExplanation.test.ts` and `useTaskTransitionValidation.test.ts`
3. Then `MoveTaskEndpointTest.php` (Feature Tests)
4. Then `TaskStatusSyncTest.php` (Integration Tests)
5. Finally `kanban-drag-drop-error-handling.spec.ts` (E2E Tests)

### ✅ Success Criteria:

- [ ] All 6 test files created
- [ ] ~50 test cases implemented
- [ ] All tests follow existing patterns
- [ ] All tests use fixed seeds
- [ ] All tests use DomainTestIsolation
- [ ] All tests pass locally
- [ ] No production code modified

### 📖 Reference Files:

- **Test Patterns:** `tests/Unit/Services/ProjectServiceTest.php`, `tests/Feature/TaskDependenciesTest.php`
- **Source Code:** `app/Services/TaskStatusTransitionService.php`, `app/Http/Controllers/Api/TasksController.php`
- **Specification:** `docs/testing/KANBAN_UX_TEST_SPEC.md` (READ THIS FIRST!)

### 🎯 Start Here:

Begin with `tests/Unit/Services/TaskStatusTransitionServiceTest.php`. Read the spec document for exact test cases and implementation details.

---

**Important:** The test specification document (`docs/testing/KANBAN_UX_TEST_SPEC.md`) contains all the details you need. Please read it carefully before starting.
