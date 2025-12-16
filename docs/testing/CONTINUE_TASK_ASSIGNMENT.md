# Task Assignment for Continue AI Assistant

**Date:** 2025-01-19  
**Task:** Implement comprehensive tests for Kanban UX features  
**Priority:** High  
**Estimated Effort:** 6 test files, ~50 test cases

---

## 🎯 Objective

Implement all test files specified in `docs/testing/KANBAN_UX_TEST_SPEC.md` to ensure comprehensive coverage of Kanban UX improvement features (Phases 1-4).

---

## 📋 Task Overview

You need to create **6 test files** with approximately **50 test cases** total:

1. **Unit Tests** (3 files)
   - `TaskStatusTransitionServiceTest.php` - 23 test cases
   - `errorExplanation.test.ts` - 6 test cases
   - `useTaskTransitionValidation.test.ts` - 3 test cases

2. **Feature Tests** (1 file)
   - `MoveTaskEndpointTest.php` - 13 test cases

3. **Integration Tests** (1 file)
   - `TaskStatusSyncTest.php` - 4 test cases

4. **E2E Tests** (1 file)
   - `kanban-drag-drop-error-handling.spec.ts` - 9 test cases

---

## 📚 Required Reading (MUST READ FIRST)

Before starting, please read these documents in order:

1. **`docs/testing/KANBAN_UX_TEST_SPEC.md`** ⭐ **PRIMARY REFERENCE**
   - Complete test specification
   - All test cases with expected behavior
   - Code examples and patterns
   - Error code reference

2. **`docs/TEST_ORGANIZATION_BEST_PRACTICES.md`**
   - Testing best practices
   - Domain isolation patterns
   - Test data seeding guidelines

3. **`.cursorrules`** (Testing Mode section)
   - ALLOW_PATHS and BLOCK_PATHS
   - Safety rules
   - Testing conventions

---

## 🚨 CRITICAL SAFETY RULES

### ❌ NEVER DO:
- **DO NOT** modify any files in `app/`, `routes/`, `database/`, `config/`
- **DO NOT** create test files outside the ALLOW_PATHS list
- **DO NOT** use random seeds (always use fixed seeds)
- **DO NOT** skip DomainTestIsolation trait
- **DO NOT** modify existing test files (only create new ones)

### ✅ ALWAYS DO:
- **ALWAYS** follow the test specification exactly
- **ALWAYS** use fixed domain seeds (e.g., `45678`, `56789`)
- **ALWAYS** use `DomainTestIsolation` trait
- **ALWAYS** use `TestDataSeeder::seedTasksDomain()` for test data
- **ALWAYS** follow existing test patterns from reference files

---

## 📁 Files You Can Create/Modify

### ALLOWED PATHS (Only these files):
```
✅ tests/Unit/Services/TaskStatusTransitionServiceTest.php
✅ tests/Feature/Api/Tasks/MoveTaskEndpointTest.php
✅ tests/Integration/TaskStatusSyncTest.php
✅ tests/e2e/core/tasks/kanban-drag-drop-error-handling.spec.ts
✅ frontend/src/features/tasks/utils/__tests__/errorExplanation.test.ts
✅ frontend/src/features/tasks/hooks/__tests__/useTaskTransitionValidation.test.ts
```

### BLOCKED PATHS (Never modify):
```
❌ app/** (all production code)
❌ routes/** (all routes)
❌ database/migrations/** (all migrations)
❌ config/** (all config files)
❌ All other test files
```

---

## 🎯 Implementation Order

Implement tests in this order (start with Unit Tests):

### Phase 1: Unit Tests (Start Here)
1. `tests/Unit/Services/TaskStatusTransitionServiceTest.php`
2. `frontend/src/features/tasks/utils/__tests__/errorExplanation.test.ts`
3. `frontend/src/features/tasks/hooks/__tests__/useTaskTransitionValidation.test.ts`

### Phase 2: Feature Tests
4. `tests/Feature/Api/Tasks/MoveTaskEndpointTest.php`

### Phase 3: Integration Tests
5. `tests/Integration/TaskStatusSyncTest.php`

### Phase 4: E2E Tests
6. `tests/e2e/core/tasks/kanban-drag-drop-error-handling.spec.ts`

---

## 📝 Implementation Guidelines

### 1. Follow Existing Patterns

**For PHP Unit Tests:**
- Reference: `tests/Unit/Services/ProjectServiceTest.php`
- Use `RefreshDatabase` and `DomainTestIsolation` traits
- Use fixed domain seed: `$this->setDomainSeed(45678)`
- Use `TestDataSeeder::seedTasksDomain()` for data

**For PHP Feature Tests:**
- Reference: `tests/Feature/TaskDependenciesTest.php`
- Use `Sanctum::actingAs()` for authentication
- Use fixed domain seed: `$this->setDomainSeed(56789)`

**For E2E Tests:**
- Reference: `tests/e2e/core/tasks/tasks-edit-delete-status.spec.ts`
- Use `AuthHelper` for authentication
- Follow Playwright best practices

### 2. Code Structure Template

**PHP Unit Test Template:**
```php
<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\TaskStatusTransitionService;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaskStatusTransitionServiceTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;
    
    private TaskStatusTransitionService $service;
    protected array $seedData;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // ALWAYS use fixed seed
        $this->setDomainSeed(45678);
        $this->setDomainName('tasks');
        $this->setupDomainIsolation();
        
        // ALWAYS use TestDataSeeder
        $this->seedData = TestDataSeeder::seedTasksDomain($this->getDomainSeed());
        
        $this->service = new TaskStatusTransitionService();
    }
    
    public function test_can_transition_from_backlog_to_in_progress(): void
    {
        // Implementation here - see spec for details
    }
}
```

### 3. Test Naming Convention

Use descriptive names following this pattern:
- `test_can_transition_from_{status}_to_{status}()`
- `test_cannot_transition_from_{status}_to_{status}()`
- `test_returns_{code}_when_{condition}()`
- `test_{action}_with_{condition}()`

### 4. Assertion Patterns

**For ValidationResult:**
```php
$this->assertTrue($result->isValid);
$this->assertEquals('invalid_transition', $result->errorCode);
$this->assertArrayHasKey('allowed_transitions', $result->details);
```

**For API Responses:**
```php
$response->assertStatus(422);
$response->assertJsonPath('error.code', 'invalid_transition');
$response->assertJsonPath('error.details.allowed_transitions', ['in_progress', 'canceled']);
```

**For Database:**
```php
$this->assertDatabaseHas('tasks', [
    'id' => $task->id,
    'status' => 'in_progress',
    'version' => 2,
]);
```

---

## 🔍 Reference Files

### Patterns to Follow:
- **Unit Tests:** `tests/Unit/Services/ProjectServiceTest.php`
- **Feature Tests:** `tests/Feature/TaskDependenciesTest.php`
- **E2E Tests:** `tests/e2e/core/tasks/tasks-edit-delete-status.spec.ts`

### Source Code to Understand:
- `app/Services/TaskStatusTransitionService.php` - Service being tested
- `app/Http/Controllers/Api/TasksController.php` - Controller being tested
- `app/Enums/TaskStatus.php` - Status enum
- `app/Services/ValidationResult.php` - Validation result class

### Documentation:
- `docs/testing/KANBAN_UX_TEST_SPEC.md` - **PRIMARY REFERENCE**
- `docs/api/TASK_MOVE_API.md` - API documentation
- `docs/TASK_STATUS_BUSINESS_RULES.md` - Business rules

---

## ✅ Success Criteria

### For Each Test File:
- [ ] All test cases from spec are implemented
- [ ] Tests follow existing patterns
- [ ] Tests use fixed seeds (no random data)
- [ ] Tests use DomainTestIsolation trait
- [ ] Tests use TestDataSeeder
- [ ] Test names are descriptive
- [ ] All tests pass locally

### Overall:
- [ ] All 6 test files created
- [ ] ~50 test cases implemented
- [ ] No production code modified
- [ ] All tests pass
- [ ] Code follows project conventions

---

## 🐛 Common Pitfalls to Avoid

1. **Random Seeds** ❌
   ```php
   // WRONG
   $this->setDomainSeed(rand());
   
   // CORRECT
   $this->setDomainSeed(45678);
   ```

2. **Missing DomainTestIsolation** ❌
   ```php
   // WRONG
   class TaskStatusTransitionServiceTest extends TestCase
   {
       // Missing trait
   }
   
   // CORRECT
   class TaskStatusTransitionServiceTest extends TestCase
   {
       use RefreshDatabase, DomainTestIsolation;
   }
   ```

3. **Not Using TestDataSeeder** ❌
   ```php
   // WRONG
   $task = Task::factory()->create();
   
   // CORRECT
   $this->seedData = TestDataSeeder::seedTasksDomain($this->getDomainSeed());
   $task = $this->seedData['tasks'][0];
   ```

4. **Modifying Production Code** ❌
   ```php
   // NEVER DO THIS
   // Modifying app/Services/TaskStatusTransitionService.php
   ```

---

## 📞 If You Get Stuck

1. **Re-read the specification:** `docs/testing/KANBAN_UX_TEST_SPEC.md`
2. **Check reference files:** Look at existing test patterns
3. **Verify ALLOW_PATHS:** Make sure you're only modifying allowed files
4. **Check error messages:** Laravel/PHPUnit errors usually point to the issue
5. **Review test patterns:** Compare with `ProjectServiceTest.php` or `TaskDependenciesTest.php`

---

## 🎯 Deliverables

After completion, you should have:

1. ✅ `tests/Unit/Services/TaskStatusTransitionServiceTest.php` (23 tests)
2. ✅ `tests/Feature/Api/Tasks/MoveTaskEndpointTest.php` (13 tests)
3. ✅ `tests/Integration/TaskStatusSyncTest.php` (4 tests)
4. ✅ `tests/e2e/core/tasks/kanban-drag-drop-error-handling.spec.ts` (9 tests)
5. ✅ `frontend/src/features/tasks/utils/__tests__/errorExplanation.test.ts` (6 tests)
6. ✅ `frontend/src/features/tasks/hooks/__tests__/useTaskTransitionValidation.test.ts` (3 tests)

**Total:** ~50 test cases across 6 files

---

## 🚀 Getting Started

1. **Read** `docs/testing/KANBAN_UX_TEST_SPEC.md` completely
2. **Review** `.cursorrules` Testing Mode section
3. **Examine** reference test files to understand patterns
4. **Start** with `TaskStatusTransitionServiceTest.php` (Phase 1)
5. **Implement** one test file at a time
6. **Verify** each file passes before moving to next
7. **Check** that no production code was modified

---

## 📊 Progress Tracking

As you complete each file, you can track progress:

- [ ] Phase 1: Unit Tests (3 files)
  - [ ] TaskStatusTransitionServiceTest.php
  - [ ] errorExplanation.test.ts
  - [ ] useTaskTransitionValidation.test.ts
- [ ] Phase 2: Feature Tests (1 file)
  - [ ] MoveTaskEndpointTest.php
- [ ] Phase 3: Integration Tests (1 file)
  - [ ] TaskStatusSyncTest.php
- [ ] Phase 4: E2E Tests (1 file)
  - [ ] kanban-drag-drop-error-handling.spec.ts

---

## ✨ Final Reminder

**Your primary goal:** Implement all tests exactly as specified in `docs/testing/KANBAN_UX_TEST_SPEC.md` while keeping production code 100% safe.

**Your constraints:** Only modify files in ALLOW_PATHS, never touch production code.

**Your quality standard:** Follow existing patterns, use fixed seeds, ensure all tests pass.

---

**Good luck! 🚀**

---

**Last Updated:** 2025-01-19  
**Version:** 1.0  
**Status:** Ready for Implementation
