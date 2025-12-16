# Tasks Domain Helper Guide

**For:** Future Agent (Builder)  
**Purpose:** Comprehensive implementation guide for Tasks Domain test organization  
**Reference:** `docs/work-packages/tasks-domain.md` (main work package)  
**Audit:** `docs/work-packages/tasks-domain-audit.md` (file inventory)

---

## Overview

This guide will help you implement the Tasks Domain test organization work package. The goal is to:

1. Add `@group tasks` annotations to all tasks-related test files
2. Verify test suites are working (already done in Core Infrastructure)
3. Implement `seedTasksDomain()` method in `TestDataSeeder`
4. Create fixtures file structure
5. Add Playwright projects (if applicable)
6. Add NPM scripts

**Fixed Seed:** `34567` (must be used consistently for reproducibility)

---

## Prerequisites

Before starting, ensure:

- [ ] Core Infrastructure work is complete and reviewed by Codex
- [ ] `phpunit.xml` contains `tasks-unit`, `tasks-feature`, `tasks-integration` test suites
- [ ] `DomainTestIsolation` trait is available in `tests/Traits/DomainTestIsolation.php`
- [ ] `TestDataSeeder` class exists and is accessible
- [ ] You have read `docs/work-packages/tasks-domain-audit.md` for file inventory

---

## File Inventory

### Files to Add @group Annotations (19 files)

**Feature Tests (12 files):**
1. `tests/Feature/TaskApiTest.php`
2. `tests/Feature/TasksApiIntegrationTest.php`
3. `tests/Feature/TaskAssignmentTest.php`
4. `tests/Feature/TaskCreationTest.php`
5. `tests/Feature/TaskDependenciesTest.php`
6. `tests/Feature/TaskEditTest.php`
7. `tests/Feature/TaskTest.php`
8. `tests/Feature/Api/TaskApiTest.php`
9. `tests/Feature/Api/TaskCommentApiTest.php`
10. `tests/Feature/Api/TaskDependenciesTest.php`
11. `tests/Feature/Api/Tasks/TasksContractTest.php`
12. `tests/Feature/ProjectTaskControllerTest.php`

**Unit Tests (4 files):**
1. `tests/Unit/Models/TaskTest.php`
2. `tests/Unit/Services/TaskManagementServiceTest.php`
3. `tests/Unit/Services/TaskDependencyServiceTest.php`
4. `tests/Unit/TaskServiceTest.php`

**Browser Tests (3 files):**
1. `tests/Browser/TaskManagementTest.php`
2. `tests/Browser/TaskEditBrowserTest.php`
3. `tests/Browser/Smoke/TasksFlowTest.php`

---

## Step-by-Step Implementation

### Phase 1: Add @group Annotations

**Goal:** Add `@group tasks` annotation to all tasks test files.

#### Example: Adding Annotation

**Before:**
```php
<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Feature tests cho Task API endpoints
 */
class TaskApiTest extends TestCase
{
    // ...
}
```

**After:**
```php
<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * @group tasks
 * Feature tests cho Task API endpoints
 */
class TaskApiTest extends TestCase
{
    // ...
}
```

#### Verification

After adding annotations, verify:
```bash
grep -r "@group tasks" tests/Feature/ tests/Unit/ tests/Integration/ tests/Browser/
```

Expected: All 19 files should appear.

---

### Phase 2: Verify Test Suites

**Goal:** Ensure test suites are working (already configured in Core Infrastructure).

#### Verification Commands

```bash
php artisan test --testsuite=tasks-unit
php artisan test --testsuite=tasks-feature
php artisan test --testsuite=tasks-integration
php artisan test --group=tasks --seed=34567
```

---

### Phase 3: Implement seedTasksDomain Method

**Goal:** Add `seedTasksDomain()` method to `TestDataSeeder` class.

#### Method Signature

```php
/**
 * Seed tasks domain test data with fixed seed for reproducibility
 * 
 * This method creates a complete tasks domain test setup including:
 * - Tenant
 * - Users (project manager, team members)
 * - Projects (for tasks to belong to)
 * - Components (for tasks to belong to)
 * - Tasks with different statuses and priorities
 * - Task assignments
 * - Task dependencies
 * 
 * @param int $seed Fixed seed value (default: 34567)
 * @return array{
 *     tenant: \App\Models\Tenant,
 *     users: \App\Models\User[],
 *     projects: \App\Models\Project[],
 *     components: \App\Models\Component[],
 *     tasks: \App\Models\Task[],
 *     task_assignments: \App\Models\TaskAssignment[],
 *     task_dependencies: \App\Models\TaskDependency[]
 * }
 */
public static function seedTasksDomain(int $seed = 34567): array
```

#### Implementation Template

```php
public static function seedTasksDomain(int $seed = 34567): array
{
    // Set fixed seed for reproducibility
    mt_srand($seed);
    
    // Create tenant
    $tenant = self::createTenant([
        'name' => 'Tasks Test Tenant',
        'slug' => 'tasks-test-tenant',
        'status' => 'active',
    ]);
    
    // Create users
    $users = [];
    $users['project_manager'] = self::createUserWithRole('project_manager', $tenant, [
        'name' => 'Tasks PM User',
        'email' => 'pm@tasks-test.test',
        'password' => 'password',
    ]);
    
    $users['team_member_1'] = self::createUserWithRole('member', $tenant, [
        'name' => 'Tasks Team Member 1',
        'email' => 'member1@tasks-test.test',
        'password' => 'password',
    ]);
    
    $users['team_member_2'] = self::createUserWithRole('member', $tenant, [
        'name' => 'Tasks Team Member 2',
        'email' => 'member2@tasks-test.test',
        'password' => 'password',
    ]);
    
    // Create a project for tasks
    $project = \App\Models\Project::create([
        'tenant_id' => $tenant->id,
        'code' => 'TASK-PROJ-001',
        'name' => 'Tasks Test Project',
        'description' => 'Project for tasks domain testing',
        'status' => 'active',
        'owner_id' => $users['project_manager']->id,
        'budget_total' => 50000.00,
        'start_date' => now(),
        'end_date' => now()->addMonths(3),
    ]);
    
    // Create a component for tasks
    $component = \App\Models\Component::create([
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
        'name' => 'Tasks Test Component',
        'type' => 'phase',
        'status' => 'active',
    ]);
    
    // Create tasks with different statuses
    $tasks = [];
    
    // Backlog task
    $tasks['backlog'] = \App\Models\Task::create([
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
        'component_id' => $component->id,
        'name' => 'Backlog Task',
        'title' => 'Backlog Task',
        'description' => 'A task in backlog',
        'status' => 'backlog',
        'priority' => 'normal',
        'estimated_hours' => 8.0,
        'actual_hours' => 0.0,
        'progress_percent' => 0.0,
    ]);
    
    // In progress task
    $tasks['in_progress'] = \App\Models\Task::create([
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
        'component_id' => $component->id,
        'name' => 'In Progress Task',
        'title' => 'In Progress Task',
        'description' => 'A task in progress',
        'status' => 'in_progress',
        'priority' => 'high',
        'estimated_hours' => 16.0,
        'actual_hours' => 4.0,
        'progress_percent' => 25.0,
        'assignee_id' => $users['team_member_1']->id,
        'created_by' => $users['project_manager']->id,
    ]);
    
    // Blocked task
    $tasks['blocked'] = \App\Models\Task::create([
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
        'component_id' => $component->id,
        'name' => 'Blocked Task',
        'title' => 'Blocked Task',
        'description' => 'A blocked task',
        'status' => 'blocked',
        'priority' => 'urgent',
        'estimated_hours' => 12.0,
        'actual_hours' => 0.0,
        'progress_percent' => 0.0,
    ]);
    
    // Done task
    $tasks['done'] = \App\Models\Task::create([
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
        'component_id' => $component->id,
        'name' => 'Done Task',
        'title' => 'Done Task',
        'description' => 'A completed task',
        'status' => 'done',
        'priority' => 'normal',
        'estimated_hours' => 10.0,
        'actual_hours' => 10.0,
        'progress_percent' => 100.0,
        'assignee_id' => $users['team_member_2']->id,
        'created_by' => $users['project_manager']->id,
    ]);
    
    // Create task assignments
    $taskAssignments = [];
    $taskAssignments[] = \App\Models\TaskAssignment::create([
        'tenant_id' => $tenant->id,
        'task_id' => $tasks['in_progress']->id,
        'user_id' => $users['team_member_1']->id,
        'split_percent' => 100.0,
        'role' => 'assignee',
    ]);
    
    $taskAssignments[] = \App\Models\TaskAssignment::create([
        'tenant_id' => $tenant->id,
        'task_id' => $tasks['done']->id,
        'user_id' => $users['team_member_2']->id,
        'split_percent' => 100.0,
        'role' => 'assignee',
    ]);
    
    // Create task dependencies
    $taskDependencies = [];
    // Task depends on backlog task
    $taskDependencies[] = \App\Models\TaskDependency::create([
        'tenant_id' => $tenant->id,
        'task_id' => $tasks['in_progress']->id,
        'dependency_id' => $tasks['backlog']->id,
    ]);
    
    // Blocked task depends on in_progress task
    $taskDependencies[] = \App\Models\TaskDependency::create([
        'tenant_id' => $tenant->id,
        'task_id' => $tasks['blocked']->id,
        'dependency_id' => $tasks['in_progress']->id,
    ]);
    
    return [
        'tenant' => $tenant,
        'users' => array_values($users),
        'projects' => [$project],
        'components' => [$component],
        'tasks' => array_values($tasks),
        'task_assignments' => $taskAssignments,
        'task_dependencies' => $taskDependencies,
    ];
}
```

#### Key Points

- Use `mt_srand($seed)` at the start for reproducibility
- Create tasks with different statuses: backlog, in_progress, blocked, done, canceled
- Create task assignments linking users to tasks
- Create task dependencies to test dependency relationships
- Tasks must belong to a project (and optionally a component)
- Return structured array with all created entities

---

### Phase 4: Create Fixtures File

**Goal:** Create `tests/fixtures/domains/tasks/fixtures.json` for reference data.

#### File Structure

```json
{
  "seed": 34567,
  "domain": "tasks",
  "task_statuses": ["backlog", "in_progress", "blocked", "done", "canceled"],
  "task_priorities": ["low", "normal", "high", "urgent"],
  "tasks": [
    {
      "name": "Backlog Task",
      "status": "backlog",
      "priority": "normal"
    },
    {
      "name": "In Progress Task",
      "status": "in_progress",
      "priority": "high"
    },
    {
      "name": "Blocked Task",
      "status": "blocked",
      "priority": "urgent"
    },
    {
      "name": "Done Task",
      "status": "done",
      "priority": "normal"
    }
  ]
}
```

---

### Phase 5: Playwright Projects (Optional)

**Note:** This may be handled by Codex Agent in the Frontend E2E Organization work package.

---

### Phase 6: NPM Scripts

**Goal:** Add NPM scripts to `package.json` for running tasks tests.

#### Scripts to Add

```json
{
  "scripts": {
    "test:tasks": "php artisan test --group=tasks",
    "test:tasks:unit": "php artisan test --testsuite=tasks-unit",
    "test:tasks:feature": "php artisan test --testsuite=tasks-feature",
    "test:tasks:integration": "php artisan test --testsuite=tasks-integration",
    "test:tasks:e2e": "playwright test --project=tasks-e2e-chromium"
  }
}
```

---

## Common Pitfalls

### 1. Task Status Values

**Problem:** Using invalid task status values.

**Solution:** Use valid statuses: `backlog`, `in_progress`, `blocked`, `done`, `canceled`

### 2. Missing Project Reference

**Problem:** Tasks must belong to a project.

**Solution:** Always set `project_id` when creating tasks:
```php
$task = Task::create([
    'tenant_id' => $tenant->id,
    'project_id' => $project->id, // Required
    'name' => 'Task Name',
    // ...
]);
```

### 3. Task Dependencies

**Problem:** Creating circular dependencies or invalid dependency relationships.

**Solution:** Ensure dependencies are acyclic and valid:
- Task A can depend on Task B
- Task B should not depend on Task A (circular)
- Both tasks must exist before creating dependency

### 4. Task Assignments

**Problem:** Task assignments not properly linked.

**Solution:** Use `TaskAssignment` model to link users to tasks:
```php
TaskAssignment::create([
    'tenant_id' => $tenant->id,
    'task_id' => $task->id,
    'user_id' => $user->id,
    'split_percent' => 100.0,
    'role' => 'assignee',
]);
```

---

## Verification Steps

1. **Check annotations:** `grep -r "@group tasks" tests/Feature/ tests/Unit/ ...`
2. **Run test suites:** `php artisan test --testsuite=tasks-feature`
3. **Verify reproducibility:** Run same seed twice, compare results
4. **Test seedTasksDomain:** `php artisan test --group=tasks --seed=34567`

---

## Completion Checklist

- [ ] All 19 files have `@group tasks` annotation
- [ ] Test suites run successfully
- [ ] `seedTasksDomain()` method exists and works correctly
- [ ] Fixtures file created
- [ ] NPM scripts added (if applicable)
- [ ] Reproducibility verified (same seed = same results)
- [ ] All tests pass with fixed seed `34567`

---

## Additional Resources

- **Main Work Package:** `docs/work-packages/tasks-domain.md`
- **File Audit:** `docs/work-packages/tasks-domain-audit.md`
- **Test Groups Documentation:** `docs/TEST_GROUPS.md`
- **DomainTestIsolation Trait:** `tests/Traits/DomainTestIsolation.php`
- **TestDataSeeder Class:** `tests/Helpers/TestDataSeeder.php`

---

**Last Updated:** 2025-11-08  
**Prepared By:** Cursor Agent  
**For:** Future Agent (Builder)

