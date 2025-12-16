# UAT Playbook - User Acceptance Testing Guide

**Version**: 1.1  
**Last Updated**: January 2025  
**Status**: UAT Ready - Core flows completed and tested  
**Purpose**: Comprehensive guide for User Acceptance Testing of ZenaManage system

---

## Overview

This playbook provides step-by-step test procedures for all critical user flows in ZenaManage. Each scenario includes:
- Prerequisites
- Step-by-step procedures
- Expected results
- Known limitations

---

## Pre-UAT Configuration

### Feature Flags Status

- **Public Signup**: `DISABLED` (`PUBLIC_SIGNUP_ENABLED=false`)
  - Self-registration is disabled for UAT
  - Admin must create tenants and users manually
  - Signup button is hidden in UI

- **WebSocket**: `DISABLED` (`WEBSOCKET_ENABLE_DASHBOARD=false`, `WEBSOCKET_ENABLE_ALERTS=false`)
  - Dashboard and alerts work via HTTP polling only
  - WebSocket is experimental and not required for UAT
  - All features function without WebSocket

### Test Environment Setup

1. **Database**: Fresh database with test data seeded
2. **Users**: Pre-created test users with different roles:
   - Super Admin: `admin@zena.local` / `password`
   - Project Manager: `pm@zena.local` / `password`
   - Member: `member@zena.local` / `password`
3. **Tenants**: Pre-created test tenants

---

## Golden Path Scenarios

### GP1: Authentication → Dashboard

**Objective**: Verify user can log in and access dashboard with proper RBAC

**Prerequisites**:
- Test user exists with valid credentials
- User is assigned to a tenant

**Steps**:
1. Navigate to `/login`
2. Enter email and password
3. Click "Login"
4. Verify redirect to `/app/dashboard` (or `/admin/dashboard` for admin users)
5. Verify dashboard loads with KPIs visible
6. Verify navigation menu shows appropriate items based on role

**Expected Results**:
- ✅ Login succeeds
- ✅ Dashboard displays KPIs (Total Projects, Active Projects, Total Tasks, In Progress, Overdue Tasks)
- ✅ Navigation menu adapts to user role
- ✅ Regular users don't see admin menu items
- ✅ Super admin sees admin menu items

**Error Scenarios**:
- Invalid credentials → 401 error, stay on login page
- Account locked → 429 error, show lockout message
- No tenant access → 403 error, show appropriate message

**Test Files**:
- `tests/e2e/golden-paths/auth-to-dashboard.spec.ts`
- `tests/Feature/GoldenPaths/AuthToDashboardTest.php`

---

### GP2: Projects → Tasks (Kanban)

**Objective**: Verify project and task creation, Kanban board functionality

**Prerequisites**:
- User logged in with `projects.create` and `tasks.create` permissions

**Steps**:
1. Navigate to `/app/projects`
2. Click "Create Project"
3. Fill project form (name, description, status)
4. Submit → verify project created
5. Open project → navigate to Tasks tab
6. Create task → verify task appears in Kanban
7. Drag task to different status column → verify status updates

**Expected Results**:
- ✅ Project created successfully
- ✅ Task created and visible in Kanban board
- ✅ Task drag-drop updates status correctly
- ✅ Invalid status transitions blocked with clear error

**Test Files**:
- `tests/e2e/golden-paths/projects-tasks-kanban.spec.ts`
- `tests/Feature/GoldenPaths/ProjectsTasksKanbanTest.php`

---

## Authentication Scenarios

### AUTH-1: Login

**Steps**:
1. Navigate to `/login`
2. Enter valid email and password
3. Click "Login"
4. Verify redirect to dashboard

**Expected**: Success, redirect to appropriate dashboard

**Test**: `tests/e2e/golden-paths/auth-to-dashboard.spec.ts`

---

### AUTH-2: Forgot Password

**Status**: ✅ **READY** - UI, backend, and E2E tests complete

**Steps**:
1. Navigate to `/login`
2. Click "Forgot password" link
3. Enter email address
4. Submit → verify success message
5. Check email for reset link
6. Click reset link → navigate to reset password page
7. Enter new password and confirmation
8. Submit → verify success
9. Login with new password → verify success
10. Try login with old password → verify failure

**Expected**:
- ✅ Success message shown (even for non-existent emails - security)
- ✅ Reset link received in email
- ✅ Password reset succeeds
- ✅ New password works for login
- ✅ Old password no longer works

**Test**: `tests/e2e/auth/password-reset-flow.spec.ts`

---

### AUTH-3: Change Password

**Status**: ✅ **READY** - UI, backend (token revocation), and E2E tests complete

**Steps**:
1. Login successfully
2. Navigate to `/app/settings`
3. Click "Security" tab
4. Verify warning message: "Sau khi đổi mật khẩu, bạn sẽ phải đăng nhập lại trên tất cả thiết bị"
5. Fill change password form:
   - Current password
   - New password
   - Password confirmation
6. Submit → verify success message: "Mật khẩu đã được thay đổi. Vui lòng đăng nhập lại."
7. Logout
8. Try login with old password → verify failure
9. Login with new password → verify success

**Expected**:
- ✅ Warning message displayed about token revocation
- ✅ Password change succeeds
- ✅ All tokens revoked after password change (user must login again)
- ✅ Old password no longer works
- ✅ New password works
- ⚠️ Note: Current session may remain valid (session-based auth), but API tokens are revoked

**Test**: `tests/e2e/auth/change-password.spec.ts`

**Error Scenarios**:
- Wrong current password → 422 validation error
- Password mismatch → Client-side validation error
- Password policy violation → 422 error with policy message

---

## Tasks Scenarios

### TASK-1: Assign User to Task

**Steps**:
1. Login as PM
2. Navigate to `/app/tasks`
3. Create a task (or open existing task)
4. Open task detail drawer
5. Assign user X to task
6. Reload page → verify assignee X still visible
7. Change assignee to user Y → verify UI + API update
8. Verify assignee appears in task list view

**Expected**:
- ✅ User assigned successfully
- ✅ Assignment persists after reload
- ✅ Reassignment works correctly
- ✅ Assignee visible in task list

**Test**: `tests/e2e/core/tasks/tasks-assign.spec.ts`

---

### TASK-2: Task Status Visibility

**Steps**:
1. Create task with status "backlog"
2. Verify task appears in "Backlog" column in Kanban
3. Change status to "in_progress"
4. Verify task moves to "In Progress" column
5. Change status to "done"
6. Verify task moves to "Done" column

**Expected**:
- ✅ Task appears in correct status column
- ✅ Status changes update UI immediately
- ✅ Task persists in correct column after reload

**Test**: `tests/e2e/golden-paths/projects-tasks-kanban.spec.ts`

---

## Projects Scenarios

### PROJ-1: Create Project

**Steps**:
1. Navigate to `/app/projects`
2. Click "Create Project"
3. Fill form:
   - Name: "Test Project"
   - Description: "Test description"
   - Status: "active"
4. Submit
5. Verify project appears in projects list

**Expected**:
- ✅ Project created successfully
- ✅ Project visible in list
- ✅ Project details accessible

**Test**: `tests/e2e/golden-paths/projects-tasks-kanban.spec.ts`

---

### PROJ-2: Project Status Visibility & Transitions

**Status**: ✅ **READY** - Business rules, API endpoints, PHPUnit tests, and E2E tests complete

**Steps**:
1. Create project A (status: active)
2. Verify project appears in "Active" filter
3. Change status to "on_hold"
4. Change status to "completed"
5. Verify:
   - "Open/Active" filter does NOT show project A
   - "All" filter shows project A
   - "Completed" filter shows project A
6. Change status to "archived"
7. Verify:
   - "Archived" filter shows project A
   - Project cannot be changed from archived (terminal state)
8. Change back to "active" (if not archived)
9. Verify project A appears in "Open/Active" filter

**Expected**:
- ✅ Status filters work correctly
- ✅ Completed projects hidden from active view
- ✅ Completed projects visible in "All" and "Completed" views
- ✅ Archived projects visible in "Archived" filter
- ✅ Status transitions follow business rules (see PROJECT_STATUS_BUSINESS_RULES.md)

**Test**: `tests/e2e/core/projects/project-status-visibility.spec.ts`

**Business Rules**:
- Projects can only be archived from `completed` or `cancelled` status
- Archived is a terminal state (cannot transition from it)
- Planning → Completed: Only allowed if no unfinished tasks
- Active → Planning: Only allowed if no active tasks (in_progress/done)

**Reference**: `docs/PROJECT_STATUS_BUSINESS_RULES.md`

---

### PROJ-3: Delete Project

**Status**: ✅ **READY** - Delete rules, error handling (409 CONFLICT, PROJECT_HAS_TASKS), and E2E tests complete

**Steps**:
1. Create project without tasks
2. Navigate to project detail
3. Click "Delete" → confirm
4. Verify project deleted (soft-deleted)
5. Verify project no longer appears in active list

**Alternative - Delete with Tasks**:
1. Create project with active tasks
2. Try to delete project
3. Verify error: "Không thể xoá dự án vì vẫn còn {count} công việc đang tồn tại. Vui lòng xoá hoặc hoàn thành tất cả công việc trước khi xoá dự án."

**Expected**:
- ✅ Project without tasks can be deleted
- ✅ Project with tasks cannot be deleted (409 CONFLICT error)
- ✅ Error code: `PROJECT_HAS_TASKS`
- ✅ Error message is clear and actionable (includes task count)
- ✅ Project is soft-deleted (can be restored)

**Test**: `tests/Feature/Api/Projects/ProjectDeleteTest.php`, `tests/e2e/core/projects/project-delete.spec.ts`

**Business Rules**:
- Projects cannot be deleted if they have ANY tasks (including soft-deleted tasks)
- Error response includes task count for user clarity

---

## Dashboard Scenarios

### DASH-1: View Dashboard

**Steps**:
1. Login
2. Navigate to `/app/dashboard`
3. Verify KPIs visible:
   - Total Projects
   - Active Projects
   - Total Tasks
   - In Progress
   - Overdue Tasks
4. Verify alerts section (if any overdue items)
5. Verify recent projects/tasks section
6. Verify activity feed

**Expected**:
- ✅ Dashboard loads within 500ms (p95)
- ✅ All KPIs display correct values
- ✅ Alerts show overdue items
- ✅ Recent items visible

**Test**: `tests/e2e/golden-paths/auth-to-dashboard.spec.ts`

---

### DASH-2: Overdue Items

**Status**: ✅ **READY** - OverdueService created, BE/FE logic consolidated, and E2E tests complete

**Steps**:
1. Seed test data: 1 project + 2 tasks with `end_date = yesterday`, status active
2. Login → navigate to Dashboard
3. Verify KPI "Overdue Tasks" > 0
4. Verify KPI "Overdue Projects" > 0
5. Click "View overdue tasks" → navigate to `/app/tasks?status=overdue`
6. Verify list contains only overdue tasks (not completed/cancelled)
7. Verify list respects tenant isolation
8. Navigate directly to `/app/tasks?status=overdue` (without going through dashboard)
9. Verify filter still works correctly

**Expected**:
- ✅ Overdue KPIs show correct counts
- ✅ Clicking "View overdue" navigates to filtered list
- ✅ Overdue list shows only overdue items
- ✅ Completed/cancelled tasks excluded
- ✅ Tenant isolation maintained
- ✅ Direct URL access to overdue filter works

**Test**: `tests/e2e/dashboard/dashboard-overdue.spec.ts`

**Overdue Rules**:
- **Task overdue**: `end_date < today` AND `status NOT IN [done, canceled, completed, cancelled]`
- **Project overdue**: `end_date < today` AND `status IN [active, on_hold]`
- Rules are consistent across all queries and views

---

## Alerts Scenarios

### ALERT-1: View Overdue Alerts

**Steps**:
1. Create overdue project (end_date < today, status = active)
2. Create overdue task (end_date < today, status != done)
3. Navigate to Dashboard
4. Verify alerts section shows overdue items
5. Click on alert → verify navigation to relevant item

**Expected**:
- ✅ Overdue alerts appear in dashboard
- ✅ Alerts link to relevant projects/tasks
- ✅ Alerts respect tenant isolation

**Test**: `tests/Feature/Api/V1/App/ProjectsAlertsTest.php`, `tests/Feature/Api/V1/App/TasksAlertsTest.php`

---

## Notifications Scenarios

### NOTIF-1: View Notifications

**Status**: ✅ **READY** - Badge count, dropdown, and E2E tests complete

**Steps**:
1. Login
2. Verify notification bell icon in header
3. Click bell → verify dropdown opens
4. Verify notifications list displayed
5. Verify unread count badge shows correct number

**Expected**:
- ✅ Bell icon visible in header
- ✅ Dropdown opens on click
- ✅ Notifications list displayed
- ✅ Unread count badge shows correct number
- ✅ Empty state shown if no notifications

**Test**: `tests/e2e/core/notifications-basic.spec.ts`

---

### NOTIF-2: Mark Notification as Read

**Status**: ✅ **READY** - Mark read functionality and E2E tests complete

**Steps**:
1. Login (with unread notifications)
2. Click notification bell
3. Click "Mark Read" on a notification (or click notification itself)
4. Verify badge count decreases
5. Verify notification marked as read in UI

**Expected**:
- ✅ Notification marked as read
- ✅ Badge count decreases
- ✅ UI updates immediately
- ✅ API call succeeds

**Test**: `tests/e2e/core/notifications-basic.spec.ts`

---

### NOTIF-3: Mark All Notifications as Read

**Status**: ✅ **READY** - Mark all read functionality and E2E tests complete

**Steps**:
1. Login (with multiple unread notifications)
2. Click notification bell
3. Click "Mark All Read" button
4. Verify badge count becomes 0
5. Verify all notifications marked as read

**Expected**:
- ✅ All notifications marked as read
- ✅ Badge count becomes 0
- ✅ UI updates immediately
- ✅ API call succeeds

**Test**: `tests/e2e/core/notifications-basic.spec.ts`

---

## Known Limitations

### WebSocket (Experimental)
- WebSocket features are **disabled** for UAT
- Dashboard and alerts work via HTTP polling only
- Real-time updates are not available
- This is intentional for UAT safety

### Public Signup (Disabled)
- Self-registration is **disabled** for UAT
- Admin must create tenants and users manually
- Signup button is hidden in UI
- Route `/api/public/auth/register` returns 403

### Token Revocation
- Password change **revokes all API tokens** (Sanctum tokens)
- User must login again after password change (for API access)
- Session-based auth for web routes may still work (session not invalidated)
- This is intentional for security - all tokens are revoked

---

## Error Handling Verification

### Standard Error Envelope

All API errors should follow this format:
```json
{
  "ok": false,
  "code": "ERROR_CODE",
  "message": "Human-readable message",
  "traceId": "req_abc12345"
}
```

### Common Error Codes

- `UNAUTHORIZED` (401): Authentication required
- `FORBIDDEN` (403): Insufficient permissions
- `NOT_FOUND` (404): Resource not found
- `VALIDATION_FAILED` (422): Input validation error
- `CONFLICT` (409): Business rule violation (e.g., cannot delete project with tasks)
- `RATE_LIMIT_EXCEEDED` (429): Too many requests
- `SERVER_ERROR` (500): Internal server error

---

## Performance Benchmarks

### API Endpoints
- **p95 Latency**: < 300ms
- **Error Rate**: < 0.1% (excluding 4xx client errors)

### Pages
- **p95 Load Time**: < 500ms (for 20-50 rows)
- **Time to Interactive**: < 1s

### Dashboard
- **Initial Load**: < 500ms
- **KPI Refresh**: < 300ms

---

## Tenant Isolation Verification

All scenarios must verify:
- ✅ User from tenant A cannot access tenant B data
- ✅ All queries filtered by `tenant_id`
- ✅ Cross-tenant operations return 403/404

**Test**: `tests/Feature/MultiTenant/CrossTenantIsolationTest.php`

---

## RBAC Verification

### Permission Levels

- **Super Admin**: `admin.access` - Full system access
- **Tenant Admin**: `admin.access.tenant` - Tenant management
- **Project Manager**: `projects.manage`, `tasks.manage` - Project/task management
- **Member**: `projects.view`, `tasks.view` - View only
- **Client**: Limited view access

### Verification Points

- ✅ Navigation menu adapts to user permissions
- ✅ Unauthorized actions return 403
- ✅ UI hides features user cannot access

**Test**: `tests/Feature/RBAC/PolicyMatrixTest.php`

---

## Test Execution Checklist

Before starting UAT:

- [ ] Database seeded with test data
- [ ] Feature flags configured (signup disabled, WebSocket disabled)
- [ ] Test users created with appropriate roles
- [ ] Test tenants created
- [ ] All E2E tests pass
- [ ] All Feature tests pass
- [ ] Performance benchmarks met

During UAT:

- [ ] Execute all golden path scenarios
- [ ] Execute all authentication scenarios
- [ ] Execute all task scenarios
- [ ] Execute all project scenarios
- [ ] Execute all dashboard scenarios
- [ ] Verify error handling
- [ ] Verify tenant isolation
- [ ] Verify RBAC
- [ ] Document any issues found

After UAT:

- [ ] Document test results
- [ ] Document any issues found
- [ ] Update baseline documentation
- [ ] Create follow-up action items

---

## Related Documentation

- [Golden Paths](GOLDEN_PATHS.md) - Detailed golden path documentation
- [WebSocket Status](WEBSOCKET_STATUS.md) - WebSocket experimental status
- [API Documentation](API_DOCUMENTATION.md) - API endpoint reference
- [Error Envelope Contract](ERROR_ENVELOPE_CONTRACT.md) - Error format specification
- [Multi-Tenant Architecture](MULTI_TENANT_ARCHITECTURE.md) - Tenant isolation details
- [RBAC Documentation](RBAC_DOCUMENTATION.md) - Permission system

---

*This playbook should be updated as new features are added or scenarios change.*

