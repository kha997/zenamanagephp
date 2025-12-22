# ROUND 203 – FIX SOFT DELETE TEST + FRONTEND PROJECT TASKS (CHECKLIST VIEW)

## TL;DR

- **Step 0 (Backend):** Fixed soft delete logic in `TaskTemplateManagementService::deleteTaskTemplateForTemplate()` - updated to properly find active records before attempting delete. Test still failing (404) - needs further investigation.
- **Step 1 (Frontend):** Added ProjectTasks checklist view to Project detail page:
  - API client function `listProjectTasks()` in `frontend/src/features/projects/api.ts`
  - React Query hook `useProjectChecklistTasks()` in `frontend/src/features/projects/hooks.ts`
  - UI component `ProjectTaskList.tsx` to display checklist tasks
  - Integrated into ProjectDetailPage Tasks tab

---

## Implementation Details by File

### Backend

#### `app/Services/TaskTemplateManagementService.php`

**Changes:**
- Updated `deleteTaskTemplateForTemplate()` method to:
  1. First try to find active task template (not soft-deleted) using `whereNull('deleted_at')`
  2. If not found, check with `withTrashed()` to see if it's already soft-deleted
  3. If found but already soft-deleted, return 404 (can't delete twice)
  4. If found and active, perform soft delete

**Issue:**
- Test `test_it_soft_deletes_task_template_for_template_of_current_tenant` still failing with 404
- Possible causes:
  - Tenant ID mismatch between test setup and controller resolution
  - Type casting issues with IDs
  - Query not finding the record due to scope filtering
- Needs further debugging to identify root cause

### Frontend

#### `frontend/src/features/projects/api.ts`

**Added:**
- `ProjectTask` interface:
  ```typescript
  export interface ProjectTask {
    id: string;
    project_id: string;
    template_task_id?: string | null;
    name: string;
    description?: string | null;
    sort_order: number;
    is_milestone: boolean;
    status?: string | null;
    due_date?: string | null;
    metadata?: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
  }
  ```

- `listProjectTasks()` function:
  - GET `/api/v1/app/projects/{projectId}/tasks`
  - Supports filters: `status`, `is_milestone`, `is_hidden`, `search`
  - Supports pagination: `page`, `per_page`
  - Returns `{ data: ProjectTask[], meta?: any, links?: any }`

#### `frontend/src/features/projects/hooks.ts`

**Added:**
- `useProjectChecklistTasks()` hook:
  - Uses React Query with query key: `['projects', projectId, 'checklist-tasks', filters, pagination]`
  - Calls `projectsApi.listProjectTasks()`
  - Enabled only when `projectId` is provided

#### `frontend/src/features/projects/components/ProjectTaskList.tsx`

**New Component:**
- Displays checklist tasks in a table format
- Columns:
  - # (index)
  - Task Name (with description if available)
  - Milestone (badge if `is_milestone`)
  - Status (badge with color coding)
  - Due Date (formatted as dd/MM/yyyy)
  - Source (icon indicator if created from template)
- Features:
  - Loading state with skeleton
  - Error state with message
  - Empty state with helpful message
  - Sorted by `sort_order` then `created_at`

#### `frontend/src/features/projects/pages/ProjectDetailPage.tsx`

**Changes:**
- Added import for `ProjectTaskList` component
- Integrated `ProjectTaskList` into Tasks tab (below regular tasks section)
- Shows checklist tasks auto-generated from TaskTemplates

---

## Behavior & UI Flow

### User Flow

1. User navigates to Project detail page
2. Clicks on "Tasks" tab
3. Sees two sections:
   - Regular tasks (existing functionality)
   - Checklist tasks (new - from templates)
4. Checklist tasks show:
   - Tasks auto-generated from TaskTemplates when project was created from template
   - Sorted by `sort_order`
   - Milestone indicators
   - Status badges
   - Due dates
   - Source indicators (template icon)

### UI Features

- **Table Layout:** Clean table with sortable columns
- **Status Badges:** Color-coded by status (completed=green, in_progress=blue, pending=yellow, etc.)
- **Milestone Badges:** Purple badge for milestone tasks
- **Date Formatting:** Vietnamese locale (dd/MM/yyyy)
- **Empty State:** Helpful message when no tasks exist
- **Loading State:** Skeleton loader during data fetch
- **Error Handling:** User-friendly error messages

---

## Tests

### Backend Tests

**Command:**
```bash
php artisan test --filter TaskTemplateApiTest
```

**Results:**
- ✅ `test_it_lists_task_templates_for_template_of_current_tenant` - PASS
- ✅ `test_it_creates_task_template_for_template_of_current_tenant` - PASS
- ✅ `test_it_validates_required_fields_on_create` - PASS
- ⚠️ `test_it_updates_task_template_for_template_of_current_tenant` - FAIL (404)
- ⚠️ `test_it_soft_deletes_task_template_for_template_of_current_tenant` - FAIL (404)
- ✅ `test_it_does_not_allow_cross_tenant_access_to_task_templates` - PASS

**Summary:** 4/6 tests passing (67%)

**Issue:**
- Both update and delete tests failing with 404
- Suggests issue with query logic when using `withoutGlobalScope('tenant')`
- Needs investigation into tenant ID resolution and type casting

### Frontend Tests

- No frontend tests added in this round
- Manual testing recommended:
  1. Create project from template with TaskTemplates
  2. Navigate to project detail page
  3. Click "Tasks" tab
  4. Verify checklist tasks are displayed
  5. Verify sorting, status badges, milestone indicators work correctly

---

## Notes / Risks / TODO

### Completed ✅

- Backend soft delete logic improved (though test still failing)
- Frontend API client for ProjectTasks
- React Query hook for ProjectTasks
- ProjectTaskList component with full UI
- Integration into ProjectDetailPage

### Pending / TODO ⏳

1. **Backend Test Fix:**
   - Debug why `test_it_soft_deletes_task_template_for_template_of_current_tenant` returns 404
   - Check tenant ID resolution in test vs controller
   - Verify type casting of IDs in queries
   - Fix update test which also fails with 404

2. **Frontend Enhancements (Future Rounds):**
   - Allow update status / complete task
   - Re-order tasks (drag-drop)
   - Group tasks by phase/discipline from metadata
   - Add activity log when auto-creating tasks
   - Add filters for milestone, status, etc.

3. **Testing:**
   - Add frontend unit tests for ProjectTaskList component
   - Add integration tests for ProjectTasks API
   - Fix backend soft delete test

### Risks

- **Backend:** Soft delete test failure suggests potential issue with tenant isolation or query logic
- **Frontend:** No tests yet - manual testing required
- **Performance:** Large number of tasks might impact rendering (consider pagination or virtualization)

---

## Files Changed

### Backend
- `app/Services/TaskTemplateManagementService.php`

### Frontend
- `frontend/src/features/projects/api.ts`
- `frontend/src/features/projects/hooks.ts`
- `frontend/src/features/projects/components/ProjectTaskList.tsx` (new)
- `frontend/src/features/projects/pages/ProjectDetailPage.tsx`

### Documentation
- `docs/ROUND_203_IMPLEMENTATION_REPORT.md` (this file)

---

*Round 203 completed on 2025-01-XX*
*Next: Fix backend test failures and add frontend tests*

