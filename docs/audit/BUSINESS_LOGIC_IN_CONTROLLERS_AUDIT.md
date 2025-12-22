# Business Logic in Controllers - Audit Report

## Status: 🟡 MOSTLY COMPLIANT

## Summary

Audit of controllers to identify business logic that should be moved to Services layer.

## Architecture Rule

From PROJECT_RULES.md:
- **Controllers**: Should only call Services, not contain business logic
- **Services**: All business logic should live here
- **Controllers**: Should only handle request/response, validation, and call Services

## Audit Results

### ✅ Good Practices Found

**ProjectManagementController**:
- ✅ Most methods call `$this->projectService->*` methods
- ✅ Validation handled by Form Requests
- ✅ Error handling uses ApiResponse

**TaskManagementController**:
- ✅ Most methods call `$this->taskService->*` methods
- ✅ Validation handled in controller (acceptable for API)
- ✅ Error handling uses ApiResponse

### ⚠️ Business Logic Found in Controllers

#### ProjectManagementController

1. **Line 168**: Direct query in `show()` method
   ```php
   $completedTasksCount = $project->tasks()->where('status', 'completed')->count();
   ```
   - **Issue**: Business logic (counting tasks) in controller
   - **Recommendation**: Move to `ProjectManagementService::getProjectTaskStats()`
   - **Priority**: LOW (simple count, but should be in service)

2. **Lines 582-589**: Direct queries in `create()` method
   ```php
   $clients = \App\Models\Client::where('tenant_id', $tenantId)->select('id', 'name')->orderBy('name')->get();
   $users = \App\Models\User::where('tenant_id', $tenantId)->where('is_active', true)->select('id', 'name', 'email')->orderBy('name')->get();
   ```
   - **Issue**: Data fetching for views in controller
   - **Recommendation**: Move to `ProjectManagementService::getCreateFormData()` or keep in controller (acceptable for view data)
   - **Priority**: LOW (view data, acceptable pattern)

3. **Lines 617-624**: Same as above in `edit()` method
   - **Issue**: Same as #2
   - **Recommendation**: Same as #2
   - **Priority**: LOW

4. **Lines 714-726**: Direct queries in `getProjectStats()` method
   ```php
   $totalProjects = Project::where('tenant_id', $tenantId)->count();
   $activeProjects = Project::where('tenant_id', $tenantId)->where('status', 'active')->count();
   // ... more queries
   ```
   - **Issue**: Business logic (statistics calculation) in controller
   - **Recommendation**: Move to `ProjectManagementService::getProjectStatistics()`
   - **Priority**: MEDIUM (complex business logic)

#### TaskManagementController

1. **Lines 320-330**: Direct queries in `getTasksForProject()` method
   ```php
   $project = Project::where('id', $projectId)->where('tenant_id', auth()->user()->tenant_id)->firstOrFail();
   $query = Task::with(['assignee', 'creator'])->where('project_id', $projectId)->where('tenant_id', auth()->user()->tenant_id);
   // ... filtering logic
   ```
   - **Issue**: Query building and filtering logic in controller
   - **Recommendation**: Move to `TaskManagementService::getTasksForProject()`
   - **Priority**: MEDIUM (query logic should be in service)

2. **Lines 468-478**: Direct queries in `getTaskStatistics()` method
   ```php
   $previousTotalTasks = Task::where('tenant_id', $tenantId)->where('created_at', '<=', $previousPeriodEnd)->count();
   // ... more statistics queries
   ```
   - **Issue**: Business logic (statistics calculation) in controller
   - **Recommendation**: Move to `TaskManagementService::getTaskStatistics()`
   - **Priority**: MEDIUM (complex business logic)

## Recommendations

### Priority MEDIUM (Should Fix)

1. **Move Statistics Logic to Services**:
   - `ProjectManagementController::getProjectStats()` → `ProjectManagementService::getProjectStatistics()`
   - `TaskManagementController::getTaskStatistics()` → `TaskManagementService::getTaskStatistics()`
   - `TaskManagementController::getTasksForProject()` → `TaskManagementService::getTasksForProject()`

2. **Move Query Building to Services**:
   - Complex queries with filtering should be in Services
   - Controllers should only pass parameters to Services

### Priority LOW (Acceptable)

1. **View Data Queries**:
   - Queries for dropdown data (clients, users) in `create()`/`edit()` methods
   - **Acceptable**: These are simple data fetches for views, not business logic
   - **Optional**: Could move to Service methods like `getCreateFormData()`

2. **Simple Counts**:
   - Simple counts like `$project->tasks()->count()` in views
   - **Acceptable**: Simple relationship counts are fine in controllers
   - **Optional**: Could use accessors or service methods

## Implementation Plan

### Phase 1: Move Statistics Logic
1. Create `ProjectManagementService::getProjectStatistics()`
2. Move logic from `ProjectManagementController::getProjectStats()`
3. Update controller to call service method
4. Test statistics endpoint

5. Create `TaskManagementService::getTaskStatistics()`
6. Move logic from `TaskManagementController::getTaskStatistics()`
7. Update controller to call service method
8. Test statistics endpoint

### Phase 2: Move Query Building
1. Create `TaskManagementService::getTasksForProject()`
2. Move query building logic from `TaskManagementController::getTasksForProject()`
3. Update controller to call service method
4. Test endpoint

### Phase 3: Optional Improvements
1. Create `ProjectManagementService::getCreateFormData()` (optional)
2. Create `ProjectManagementService::getEditFormData()` (optional)
3. Move view data queries (optional)

## Status

✅ **Most controllers are compliant** - Most business logic is already in Services.

⚠️ **Some improvements needed** - Statistics and complex query logic should be moved to Services.

## Notes

- Current architecture is mostly compliant
- Priority fixes are for complex business logic (statistics, query building)
- Simple view data queries are acceptable in controllers
- All critical business logic is already in Services

