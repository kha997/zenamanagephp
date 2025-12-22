# Kanban UX Features - Test Implementation Specification

**Date:** 2025-01-19  
**Purpose:** Comprehensive test specification for Kanban UX improvements  
**Status:** Ready for implementation  
**Target:** Continue/Codex AI Assistants

---

## 📋 Overview

This document specifies all tests needed for the Kanban UX improvement features implemented in Phases 1-4:

- **Phase 1:** Enhanced Error Response & Mini Modal
- **Phase 2:** Proactive Validation on DragOver
- **Phase 3:** Smooth Rollback Animation
- **Phase 4:** Passive Learning Tooltips

## 🎯 Testing Strategy

### Test Pyramid
```
        /\
       /E2E\     ← End-to-End Tests (Playwright)
      /______\
     /Feature\   ← API Integration Tests
    /__________\
   /   Unit    \ ← Service Unit Tests
  /____________\
```

### Test Categories

1. **Unit Tests** - `TaskStatusTransitionService`, error utilities, validation hooks
2. **Feature Tests** - `PATCH /api/tasks/{id}/move` endpoint
3. **Integration Tests** - Project status sync, dependencies validation
4. **E2E Tests** - Complete Kanban drag-drop workflows with error handling

---

## 📁 Test Files to Create

### 1. Unit Tests

#### 1.1 TaskStatusTransitionServiceTest.php
**Location:** `tests/Unit/Services/TaskStatusTransitionServiceTest.php`  
**Pattern:** Follow `tests/Unit/Services/ProjectServiceTest.php` structure

**Required Setup:**
```php
<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\Project;
use App\Services\TaskStatusTransitionService;
use App\Services\ValidationResult;
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
        
        // Setup domain isolation
        $this->setDomainSeed(45678); // Fixed seed for reproducibility
        $this->setDomainName('tasks');
        $this->setupDomainIsolation();
        
        // Seed test data
        $this->seedData = TestDataSeeder::seedTasksDomain($this->getDomainSeed());
        
        $this->service = new TaskStatusTransitionService();
    }
}
```

**Test Cases to Implement:**

1. **test_can_transition_from_backlog_to_in_progress()**
   - Create task with status `backlog`
   - Validate transition to `in_progress`
   - Assert `ValidationResult::isValid === true`

2. **test_can_transition_from_backlog_to_canceled()**
   - Create task with status `backlog`
   - Validate transition to `canceled` with reason
   - Assert valid

3. **test_cannot_transition_from_backlog_to_done()**
   - Create task with status `backlog`
   - Validate transition to `done`
   - Assert `ValidationResult::isValid === false`
   - Assert error code is `invalid_transition`
   - Assert error details contain `allowed_transitions`

4. **test_can_transition_from_in_progress_to_done()**
   - Create task with status `in_progress`
   - Validate transition to `done`
   - Assert valid

5. **test_can_transition_from_in_progress_to_blocked()**
   - Create task with status `in_progress`
   - Validate transition to `blocked` with reason
   - Assert valid

6. **test_can_transition_from_in_progress_to_canceled()**
   - Create task with status `in_progress`
   - Validate transition to `canceled` with reason
   - Assert valid

7. **test_can_transition_from_in_progress_to_backlog()**
   - Create task with status `in_progress`
   - Validate transition to `backlog`
   - Assert valid

8. **test_can_transition_from_blocked_to_in_progress()**
   - Create task with status `blocked`
   - Validate transition to `in_progress`
   - Assert valid

9. **test_can_transition_from_blocked_to_canceled()**
   - Create task with status `blocked`
   - Validate transition to `canceled` with reason
   - Assert valid

10. **test_can_transition_from_done_to_in_progress()**
    - Create task with status `done`
    - Validate transition to `in_progress` (reopen)
    - Assert valid

11. **test_can_transition_from_canceled_to_backlog()**
    - Create task with status `canceled`
    - Validate transition to `backlog` (reactivate)
    - Assert valid

12. **test_project_status_blocks_transition_when_archived()**
    - Create project with status `archived`
    - Create task in that project
    - Try to transition task
    - Assert `ValidationResult::isValid === false`
    - Assert error code is `project_status_restricted`
    - Assert error details contain project info

13. **test_project_status_blocks_transition_when_completed()**
    - Create project with status `completed`
    - Create task with status `in_progress`
    - Try to transition to `done`
    - Assert invalid with `project_status_restricted` error

14. **test_project_status_allows_transition_when_active()**
    - Create project with status `active`
    - Create task with status `backlog`
    - Validate transition to `in_progress`
    - Assert valid

15. **test_dependencies_must_be_complete_to_start()**
    - Create task A with status `backlog`
    - Create task B with status `backlog` that depends on A
    - Try to transition B to `in_progress`
    - Assert invalid with `dependencies_incomplete` error
    - Assert error details contain dependency IDs

16. **test_can_start_when_all_dependencies_done()**
    - Create task A with status `done`
    - Create task B with status `backlog` that depends on A
    - Validate transition B to `in_progress`
    - Assert valid

17. **test_reason_required_for_blocked()**
    - Create task with status `in_progress`
    - Try to transition to `blocked` without reason
    - Assert invalid with `reason_required` error

18. **test_reason_required_for_canceled()**
    - Create task with status `in_progress`
    - Try to transition to `canceled` without reason
    - Assert invalid with `reason_required` error

19. **test_can_cancel_with_reason()**
    - Create task with status `in_progress`
    - Validate transition to `canceled` with reason "Client request"
    - Assert valid

20. **test_warning_when_canceling_with_active_dependents()**
    - Create task A with status `in_progress`
    - Create task B with status `in_progress` that depends on A
    - Try to cancel A
    - Assert `ValidationResult::isValid === true` (warning, not error)
    - Assert warning code is `dependents_active`
    - Assert warning details contain dependent IDs

21. **test_calculate_progress_sets_100_when_done()**
    - Create task with progress 50%
    - Calculate progress for `done` status
    - Assert result is 100

22. **test_calculate_progress_sets_0_when_backlog()**
    - Create task with progress 50%
    - Calculate progress for `backlog` status
    - Assert result is 0

23. **test_calculate_progress_preserves_when_in_progress()**
    - Create task with progress 50%
    - Calculate progress for `in_progress` status
    - Assert result is 50

**Example Implementation:**
```php
public function test_can_transition_from_backlog_to_in_progress(): void
{
    $tenant = $this->seedData['tenant'];
    $project = $this->seedData['projects'][0];
    $user = $this->seedData['users'][0];
    
    $task = Task::create([
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
        'name' => 'Test Task',
        'status' => TaskStatus::BACKLOG->value,
        'created_by' => $user->id,
    ]);
    
    $result = $this->service->validateTransition(
        $task,
        TaskStatus::IN_PROGRESS
    );
    
    $this->assertTrue($result->isValid);
    $this->assertNull($result->error);
}
```

---

#### 1.2 ErrorExplanationUtilityTest.ts
**Location:** `frontend/src/features/tasks/utils/__tests__/errorExplanation.test.ts`  
**Pattern:** Standard Jest/Vitest unit test

**Test Cases:**
1. `test_returns_correct_explanation_for_dependencies_incomplete()`
2. `test_returns_correct_explanation_for_project_status_restricted()`
3. `test_returns_correct_explanation_for_invalid_transition()`
4. `test_returns_correct_explanation_for_optimistic_lock_conflict()`
5. `test_returns_correct_explanation_for_dependents_active()`
6. `test_returns_default_explanation_for_unknown_error()`

---

#### 1.3 useTaskTransitionValidationHookTest.ts
**Location:** `frontend/src/features/tasks/hooks/__tests__/useTaskTransitionValidation.test.ts`  
**Pattern:** React Testing Library + Jest/Vitest

**Test Cases:**
1. `test_allows_valid_transitions()`
2. `test_blocks_invalid_transitions()`
3. `test_returns_correct_reason_for_blocked_transition()`

---

### 2. Feature Tests

#### 2.1 MoveTaskEndpointTest.php
**Location:** `tests/Feature/Api/Tasks/MoveTaskEndpointTest.php`  
**Pattern:** Follow `tests/Feature/TaskDependenciesTest.php` structure

**Required Setup:**
```php
<?php declare(strict_types=1);

namespace Tests\Feature\Api\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\Project;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class MoveTaskEndpointTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;
    
    protected array $seedData;
    private $user;
    private $tenant;
    private $project;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->setDomainSeed(56789);
        $this->setDomainName('tasks');
        $this->setupDomainIsolation();
        
        $this->seedData = TestDataSeeder::seedTasksDomain($this->getDomainSeed());
        $this->tenant = $this->seedData['tenant'];
        $this->user = $this->seedData['users'][0];
        $this->project = $this->seedData['projects'][0];
        
        Sanctum::actingAs($this->user);
    }
}
```

**Test Cases to Implement:**

1. **test_can_move_task_from_backlog_to_in_progress()**
   - Create task with status `backlog`
   - POST to `PATCH /api/tasks/{id}/move` with `to_status: in_progress`
   - Assert 200 response
   - Assert task status updated
   - Assert task version incremented

2. **test_returns_422_for_invalid_transition()**
   - Create task with status `backlog`
   - POST to move endpoint with `to_status: done`
   - Assert 422 response
   - Assert error code is `invalid_transition`
   - Assert error details contain `allowed_transitions`

3. **test_returns_422_when_dependencies_incomplete()**
   - Create task A with status `backlog`
   - Create task B with status `backlog` that depends on A
   - POST to move B to `in_progress`
   - Assert 422 response
   - Assert error code is `dependencies_incomplete`
   - Assert error details contain dependency IDs

4. **test_returns_422_when_project_archived()**
   - Create project with status `archived`
   - Create task in that project
   - POST to move endpoint
   - Assert 422 response
   - Assert error code is `project_status_restricted`

5. **test_returns_422_when_reason_missing_for_blocked()**
   - Create task with status `in_progress`
   - POST to move to `blocked` without reason
   - Assert 422 response
   - Assert error code is `reason_required`

6. **test_returns_409_for_optimistic_lock_conflict()**
   - Create task with version 1
   - Update task version to 2 in database
   - POST to move endpoint with `version: 1`
   - Assert 409 response
   - Assert error code is `CONFLICT`

7. **test_calculates_position_correctly()**
   - Create 3 tasks in same column
   - Move middle task to new position
   - Assert `order` field updated correctly

8. **test_updates_progress_to_100_when_moved_to_done()**
   - Create task with progress 50%
   - POST to move to `done`
   - Assert task progress is 100

9. **test_updates_progress_to_0_when_moved_to_backlog()**
   - Create task with progress 50%
   - POST to move to `backlog`
   - Assert task progress is 0

10. **test_requires_authentication()**
    - POST to move endpoint without auth
    - Assert 401 response

11. **test_enforces_tenant_isolation()**
    - Create task in tenant A
    - Authenticate as user from tenant B
    - POST to move task
    - Assert 403 response

12. **test_logs_activity_on_successful_move()**
    - Create task
    - POST to move endpoint
    - Assert activity log entry created
    - Assert log contains old/new status, reason, positions

13. **test_dispatches_task_moved_event()**
    - Create task
    - POST to move endpoint
    - Assert `TaskMoved` event dispatched
    - Assert event contains correct data

---

### 3. Integration Tests

#### 3.1 TaskStatusSyncTest.php
**Location:** `tests/Integration/TaskStatusSyncTest.php`

**Test Cases:**
1. `test_project_completed_syncs_tasks_to_done()`
2. `test_project_cancelled_syncs_tasks_to_canceled()`
3. `test_project_on_hold_syncs_in_progress_to_blocked()`
4. `test_project_archived_prevents_task_changes()`

---

### 4. E2E Tests (Playwright)

#### 4.1 kanban-drag-drop-error-handling.spec.ts
**Location:** `tests/e2e/core/tasks/kanban-drag-drop-error-handling.spec.ts`  
**Pattern:** Follow `tests/e2e/core/tasks/tasks-edit-delete-status.spec.ts`

**Test Cases:**

1. **test_displays_error_modal_on_invalid_move()**
   - Navigate to Kanban board
   - Drag task from backlog to done (invalid)
   - Assert error modal appears
   - Assert modal shows correct error message
   - Assert modal has "Got it" button

2. **test_displays_error_modal_on_dependencies_incomplete()**
   - Create task with incomplete dependencies
   - Drag to in_progress column
   - Assert error modal appears
   - Assert modal shows dependency error
   - Assert "View Dependencies" button exists
   - Click button and assert navigation

3. **test_shows_red_border_on_invalid_drag_over()**
   - Start dragging task
   - Drag over invalid column
   - Assert red border appears on column
   - Assert tooltip shows reason

4. **test_rollback_animation_on_failed_move()**
   - Drag task to invalid position
   - Drop task
   - Assert task animates back to original position
   - Assert animation is smooth (~300ms)

5. **test_tooltip_appears_on_hover()**
   - Hover over task in archived project
   - Wait 1.5 seconds
   - Assert tooltip appears
   - Assert tooltip shows correct message

6. **test_reason_modal_for_blocked_move()**
   - Drag task to blocked column
   - Assert reason modal appears
   - Enter reason
   - Submit
   - Assert task moved successfully

7. **test_reason_modal_for_canceled_move()**
   - Drag task to canceled column
   - Assert reason modal appears
   - Enter reason
   - Submit
   - Assert task moved successfully

8. **test_optimistic_ui_update()**
   - Drag task to valid column
   - Assert task moves immediately (optimistic)
   - Wait for API response
   - Assert task stays in new position

9. **test_rollback_on_api_error()**
   - Mock API to return error
   - Drag task to valid column
   - Assert task moves optimistically
   - Wait for error response
   - Assert task rolls back to original position

---

## 📝 Implementation Guidelines

### Code Patterns

1. **Always use fixed seeds:**
   ```php
   protected function setUp(): void
   {
       $this->setDomainSeed(45678); // Fixed, not random
   }
   ```

2. **Use DomainTestIsolation trait:**
   ```php
   use Tests\Traits\DomainTestIsolation;
   ```

3. **Use TestDataSeeder:**
   ```php
   $this->seedData = TestDataSeeder::seedTasksDomain($this->getDomainSeed());
   ```

4. **Store test data:**
   ```php
   $this->storeTestData('tenant', $tenant);
   $tenant = $this->getTestData('tenant');
   ```

### Assertion Patterns

1. **ValidationResult assertions:**
   ```php
   $this->assertTrue($result->isValid);
   $this->assertEquals('invalid_transition', $result->errorCode);
   $this->assertArrayHasKey('allowed_transitions', $result->details);
   ```

2. **API response assertions:**
   ```php
   $response->assertStatus(422);
   $response->assertJsonPath('error.code', 'invalid_transition');
   $response->assertJsonPath('error.details.allowed_transitions', ['in_progress', 'canceled']);
   ```

3. **Database assertions:**
   ```php
   $this->assertDatabaseHas('tasks', [
       'id' => $task->id,
       'status' => 'in_progress',
       'version' => 2,
   ]);
   ```

### Error Code Reference

- `invalid_transition` - Transition not allowed by matrix
- `project_status_restricted` - Project status blocks operation
- `dependencies_incomplete` - Dependencies not done
- `dependents_active` - Warning when canceling with active dependents
- `reason_required` - Reason missing for blocked/canceled
- `CONFLICT` - Optimistic locking conflict (409)

---

## ✅ Success Criteria

### Unit Tests
- [ ] All 23 test cases for `TaskStatusTransitionServiceTest` pass
- [ ] All error explanation utility tests pass
- [ ] All validation hook tests pass
- [ ] 100% code coverage for `TaskStatusTransitionService`
- [ ] 100% code coverage for error utilities

### Feature Tests
- [ ] All 13 test cases for `MoveTaskEndpointTest` pass
- [ ] All integration tests pass
- [ ] 100% code coverage for move endpoint

### E2E Tests
- [ ] All 9 Playwright test cases pass
- [ ] Tests run reliably (no flakiness)
- [ ] Tests cover all UX scenarios

### Code Quality
- [ ] All tests follow existing patterns
- [ ] All tests use fixed seeds
- [ ] All tests use DomainTestIsolation
- [ ] No production code changes
- [ ] All tests are deterministic

---

## 🚨 Safety Rules

### NEVER DO:
- ❌ Modify production code (`app/`, `routes/`, `database/`)
- ❌ Use random seeds
- ❌ Skip DomainTestIsolation
- ❌ Create tests without following patterns
- ❌ Commit without running tests

### ALWAYS DO:
- ✅ Use fixed seeds for reproducibility
- ✅ Use DomainTestIsolation trait
- ✅ Use TestDataSeeder for test data
- ✅ Follow existing test patterns
- ✅ Write descriptive test names
- ✅ Assert both success and failure cases
- ✅ Test error codes and details

---

## 📚 Reference Files

### Patterns to Follow:
- `tests/Unit/Services/ProjectServiceTest.php` - Unit test structure
- `tests/Feature/TaskDependenciesTest.php` - Feature test structure
- `tests/e2e/core/tasks/tasks-edit-delete-status.spec.ts` - E2E test structure

### Documentation:
- `docs/TEST_ORGANIZATION_BEST_PRACTICES.md` - Test organization guide
- `docs/testing/COMPREHENSIVE_TESTING_SUITE.md` - Testing suite overview
- `docs/api/TASK_MOVE_API.md` - API documentation
- `docs/TASK_STATUS_BUSINESS_RULES.md` - Business rules

### Source Code:
- `app/Services/TaskStatusTransitionService.php` - Service to test
- `app/Http/Controllers/Api/TasksController.php` - Controller to test
- `app/Enums/TaskStatus.php` - Status enum
- `app/Services/ValidationResult.php` - Validation result class

---

## 🎯 Implementation Order

1. **Unit Tests** (Start here)
   - TaskStatusTransitionServiceTest.php
   - ErrorExplanationUtilityTest.ts
   - useTaskTransitionValidationHookTest.ts

2. **Feature Tests**
   - MoveTaskEndpointTest.php

3. **Integration Tests**
   - TaskStatusSyncTest.php

4. **E2E Tests**
   - kanban-drag-drop-error-handling.spec.ts

---

**Last Updated:** 2025-01-19  
**Version:** 1.0  
**Status:** Ready for AI Assistant Implementation
