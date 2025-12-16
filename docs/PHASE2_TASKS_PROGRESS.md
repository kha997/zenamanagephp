# Phase 2: Tasks Domain Seed Integration - Progress

**Date:** 2025-11-09  
**Agent:** Cursor  
**Status:** In Progress  
**Domain:** Tasks

---

## Summary

Tasks domain tests are being updated to use `seedTasksDomain()` for reproducible test data.

---

## Test Files Found (19 files)

1. `tests/Feature/TaskTest.php`
2. `tests/Feature/TaskCreationTest.php`
3. `tests/Feature/TaskApiTest.php`
4. `tests/Feature/TaskAssignmentTest.php`
5. `tests/Feature/TaskDependenciesTest.php`
6. `tests/Feature/TaskEditTest.php`
7. `tests/Feature/TasksApiIntegrationTest.php`
8. `tests/Feature/Api/TaskApiTest.php`
9. `tests/Feature/Api/TaskCommentApiTest.php`
10. `tests/Feature/Api/TaskDependenciesTest.php`
11. `tests/Feature/Api/Tasks/TasksContractTest.php`
12. `tests/Unit/Models/TaskTest.php`
13. `tests/Unit/TaskServiceTest.php`
14. `tests/Unit/Services/TaskManagementServiceTest.php`
15. `tests/Unit/Services/TaskDependencyServiceTest.php`
16. `tests/Browser/TaskManagementTest.php`
17. `tests/Browser/TaskEditBrowserTest.php`
18. `tests/Browser/Smoke/TasksFlowTest.php`
19. `tests/Unit/Helpers/TestDataSeederVerificationTest.php` (verification test)

---

## Seed Data Available

From `seedTasksDomain(34567)`:
- **Tenant:** `Tasks Test Tenant` (slug: `tasks-test-tenant-34567`)
- **Users:**
  - `pm@tasks-test.test` (project_manager role)
  - `member1@tasks-test.test` (member role)
  - `member2@tasks-test.test` (member role)
- **Projects:** 1 project (Tasks Test Project)
- **Components:** 1 component (Tasks Test Component)
- **Tasks:**
  - Pending task
  - In Progress task
  - Completed task
  - Blocked task
- **Task Assignments:** Links users to tasks
- **Task Dependencies:** Links tasks to each other

---

## Pattern to Use

```php
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;

class MyTaskTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;
    
    protected $tenant;
    protected $user;
    protected $seedData; // Store to avoid re-seeding
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(34567);
        $this->setDomainName('tasks');
        $this->setupDomainIsolation();
        
        // Seed tasks domain test data (only once)
        $this->seedData = TestDataSeeder::seedTasksDomain($this->getDomainSeed());
        $this->tenant = $this->seedData['tenant'];
        $this->storeTestData('tenant', $this->tenant);
        
        // Use project manager user from seed data
        $this->user = collect($this->seedData['users'])->firstWhere('email', 'pm@tasks-test.test');
        if (!$this->user) {
            $this->user = $this->seedData['users'][0];
        }
        
        // Authenticate if needed
        Sanctum::actingAs($this->user);
    }
}
```

---

## Completed Files (5 files updated)

1. ✅ **TaskTest.php** (Feature) - UPDATED
2. ✅ **TaskTest.php** (Unit/Models) - UPDATED
3. ✅ **TaskCreationTest.php** - UPDATED
4. ✅ **TaskAssignmentTest.php** - UPDATED
5. ✅ **TaskDependenciesTest.php** - UPDATED

## Progress

- **Files Updated:** 5/19 (26%)
- **Status:** In Progress...

---

**Last Updated:** 2025-11-09  
**Status:** Tasks Domain - Starting...

---

## Round 20: Tenant Invitation Lifecycle (Accept/Decline) ✅ DONE

**Date:** 2025-11-21  
**Status:** ✅ Complete  
**Tests:** 18/18 passed

### Scope Summary

- **Public Preview Endpoint**: `GET /api/v1/tenant/invitations/{token}` - No auth required, returns invitation metadata (tenant name, email, role, status, is_expired) for landing page rendering
- **Accept Invitation**: `POST /api/v1/tenant/invitations/{token}/accept` - Requires auth:sanctum, validates email match, attaches user to tenant, handles already-member case idempotently, sets is_default=true if user has no other tenants
- **Decline Invitation**: `POST /api/v1/tenant/invitations/{token}/decline` - Requires auth:sanctum, marks invitation as declined
- **Edge Cases Handled**: Invalid token (404), expired invitations (auto-mark as expired), already accepted/revoked/declined states (422), email mismatch (422), tenant isolation verification

### Implementation Details

- **Model**: Added `STATUS_DECLINED = 'declined'` to `TenantInvitation` model
- **Service**: Created `TenantInvitationLifecycleService` with methods for token lookup, validation, accept/decline operations
- **Controller**: Created `TenantInvitationLifecycleController` with showPublic, accept, decline methods
- **Routes**: Added routes outside `/app` group (no tenant.scope) for invitee-side operations
- **Tests**: Comprehensive Feature tests covering all scenarios (18 test cases, all passing)

**Last Updated:** 2025-11-21

---

## Round 23: Tenant Self-service Leave ✅ DONE

**Date:** 2025-01-08  
**Status:** ✅ Complete

### Scope Summary

- **Self-service Leave Endpoint**: `POST /api/v1/app/tenant/leave` - Allows any member to leave tenant themselves
- **Last Owner Protection**: Prevents last owner from leaving tenant (HTTP 422, code `TENANT_LAST_OWNER_PROTECTED`)
- **Default Tenant Reassignment**: When leaving default tenant, automatically reassigns `is_default=true` to another tenant (earliest `created_at`)
- **Frontend UI**: "Leave this workspace" section in TenantMembersPage with proper error handling

### Implementation Details

- **Service**: `TenantMembersService::selfLeaveTenant()` - Handles membership soft delete, last owner check, default reassignment
- **Controller**: `TenantMembersController::leaveSelf()` - API endpoint with proper error codes
- **Route**: `/api/v1/app/tenant/leave` - No permission middleware (self-service), uses `auth:sanctum` + `ability:tenant` + `tenant.scope`
- **Frontend**: `useLeaveCurrentTenant()` hook + `TenantMembersPage` UI with error handling
- **Tests**: Comprehensive Feature tests covering all scenarios (member/viewer/admin leave, last owner protection, default reassignment)

**Last Updated:** 2025-01-08

---

## Round 24: Ownership & Membership Hardening (Tests Only) ✅ DONE

**Date:** 2025-01-08  
**Status:** ✅ Complete  
**Type:** Hardening / Test Coverage (No Behavior Changes)

### Scope Summary

- **Backend Tests**: Added multi-tenant default reassignment test (3+ tenants)
- **Backend Tests**: Added API-level test for non-member leave scenario
- **Frontend Tests**: Added generic error handling test for leave workspace
- **Documentation**: Updated progress tracking

### Implementation Details

- **Test**: `test_default_tenant_reassignment_order_with_multiple_tenants()` - Verifies default reassignment selects tenant with earliest `created_at` when user has 3+ tenants
- **Test**: `test_non_member_cannot_leave_tenant_via_api_and_other_memberships_stay_unchanged()` - Verifies non-member cannot leave tenant and other memberships remain unchanged
- **Test**: `shows_generic_error_message_when_leave_tenant_fails_with_unknown_error` - Verifies fallback error message for unknown errors

### Non-Goals (Important)

- ❌ No behavior changes to `selfLeaveTenant`, `assertNotLastOwner`, `leaveSelf`
- ❌ No HTTP status/error code changes
- ❌ No new features or UX flows

**Last Updated:** 2025-01-08

---

## Round 29: RBAC & Multi-tenant Hardening for Search, Observability, Dashboard & Media (Tests Only) ✅ DONE

**Date:** 2025-01-08  
**Status:** ✅ Complete  
**Type:** Hardening / Test Coverage (No Behavior Changes)

### Scope Summary

- **Search API Tests**: Added permission and tenant isolation tests for `/api/v1/app/search` (requires `tenant.view_projects`)
- **Observability API Tests**: Added permission and tenant isolation tests for `/api/v1/app/observability/*` (requires `tenant.view_analytics`)
- **Dashboard API Tests**: Added permission, widget mutations, and alert isolation tests for `/api/v1/app/dashboard/*` (requires `tenant.view_analytics`)
- **Media API Tests**: Added permission and tenant isolation tests for `/api/v1/app/media/*` (quota: `tenant.view_projects`, signed-url: `tenant.manage_projects`)
- **Controller Fix**: Fixed `SearchController` to use `getTenantId()` from request context instead of `$user->tenant_id`

### Implementation Details

- **Test Files Created**:
  1. `tests/Feature/Api/Tenants/TenantSearchPermissionTest.php` - Search permission and isolation tests
  2. `tests/Feature/Api/Tenants/TenantObservabilityPermissionTest.php` - Observability permission and isolation tests
  3. `tests/Feature/Api/Tenants/TenantDashboardPermissionTest.php` - Dashboard permission, widget mutations, and alert isolation tests
  4. `tests/Feature/Api/Tenants/TenantMediaPermissionTest.php` - Media permission and isolation tests

- **Controller Updates**:
  - `app/Http/Controllers/SearchController.php`: Added `ResolvesTenantContext` trait, fixed to pass `tenant_id` to `SearchService.search()`, fixed response format handling

- **Test Coverage**:
  - Permission enforcement: All endpoints verify `tenant.permission:*` middleware is working
  - Tenant isolation: All endpoints verify cross-tenant data leak prevention
  - Alert isolation: Dashboard alerts verify user-level isolation (user A cannot mark user B's alerts as read)
  - Widget mutations: Dashboard widget operations verify permission requirements

### Routes Covered

- **Search**: `GET /api/v1/app/search` (requires `tenant.view_projects`)
- **Observability**: 
  - `GET /api/v1/app/observability/metrics` (requires `tenant.view_analytics`)
  - `GET /api/v1/app/observability/percentiles` (requires `tenant.view_analytics`)
  - `GET /api/v1/app/observability/trace-context` (requires `tenant.view_analytics`)
- **Dashboard**:
  - `GET /api/v1/app/dashboard/*` (requires `tenant.view_analytics`)
  - `POST/PUT/DELETE /api/v1/app/dashboard/widgets*` (requires `tenant.view_analytics`)
  - `PUT /api/v1/app/dashboard/alerts/{id}/read` (user-scoped, no permission middleware)
  - `PUT /api/v1/app/dashboard/alerts/read-all` (user-scoped, no permission middleware)
- **Media**:
  - `GET /api/v1/app/media/quota` (requires `tenant.view_projects`)
  - `GET /api/v1/app/media/signed-url` (requires `tenant.manage_projects`)

### Non-Goals (Important)

- ❌ No behavior changes to existing endpoints
- ❌ No HTTP status/error code changes
- ❌ No new features or UX flows
- ❌ No route/method/schema changes

**Last Updated:** 2025-01-08

---

## Round 30: RBAC Gap Sweep & Missing Modules + "No-permission" Strict Tests ✅ DONE

**Date:** 2025-01-08  
**Status:** ✅ Complete  
**Type:** Hardening / Test Coverage + Controller Fixes

### Scope Summary

- **Discovery**: Scanned all `/api/v1/app/*` routes and identified missing RBAC tests for Documents, Settings, and Reports modules
- **Controller Hardening**: Fixed `DocumentsController` and `ReportsController` to use `ResolvesTenantContext` trait and `getTenantId()` instead of `$user->tenant_id`
- **Route Updates**: Updated document routes to remove route model binding (changed from `{document}` to `{id}`)
- **New Test Files**: Created comprehensive RBAC and isolation tests for Documents, Settings, and Reports modules
- **Strict Negative Tests**: Added guest role tests for Search, Observability, Dashboard, and Media to verify strict permission enforcement

### Implementation Details

- **Controller Updates**:
  - `app/Http/Controllers/Api/DocumentsController.php`: Added `ResolvesTenantContext` trait, replaced all `$user->tenant_id` with `getTenantId($request)`, removed route model binding
  - `app/Http/Controllers/Api/ReportsController.php`: Added `ResolvesTenantContext` trait, replaced all `$user->tenant_id` with `getTenantId($request)`
  - `routes/api_v1.php`: Updated document routes to use `{id}` instead of `{document}` for route model binding

- **Test Files Created**:
  1. `tests/Feature/Api/Tenants/TenantDocumentsPermissionTest.php` - Documents permission and isolation tests
  2. `tests/Feature/Api/Tenants/TenantDocumentsIsolationTest.php` - Documents cross-tenant isolation tests
  3. `tests/Feature/Api/Tenants/TenantSettingsPermissionTest.php` - Settings permission tests
  4. `tests/Feature/Api/Tenants/TenantSettingsIsolationTest.php` - Settings tenant isolation tests
  5. `tests/Feature/Api/Tenants/TenantReportsPermissionTest.php` - Reports permission tests

- **Strict Negative Tests Added**:
  - `TenantSearchPermissionTest::test_search_denies_guest_without_view_projects_permission()` - Guest role test for search
  - `TenantObservabilityPermissionTest::test_metrics_denies_guest_without_view_analytics_permission()` - Guest role test for observability
  - `TenantDashboardPermissionTest::test_dashboard_denies_guest_without_view_analytics_permission()` - Guest role test for dashboard
  - `TenantMediaPermissionTest::test_media_quota_denies_guest_without_view_projects_permission()` - Guest role test for media quota
  - `TenantMediaPermissionTest::test_media_signed_url_denies_guest_without_manage_projects_permission()` - Guest role test for media signed URL

### Modules Covered

- **Documents**: Full RBAC + isolation tests (GET, POST, PUT, PATCH, DELETE, download, TTL links, KPIs, alerts, activity)
- **Settings**: Full RBAC tests (GET, PUT for general/notifications/appearance/security/privacy/integrations)
- **Reports**: RBAC tests (GET for KPIs, alerts, activity - only view endpoints exist)

### Routes Covered

- **Documents**: 
  - `GET /api/v1/app/documents` (requires `tenant.view_documents`)
  - `GET /api/v1/app/documents/{id}` (requires `tenant.view_documents`)
  - `POST /api/v1/app/documents` (requires `tenant.manage_documents`)
  - `PUT/PATCH /api/v1/app/documents/{id}` (requires `tenant.manage_documents`)
  - `DELETE /api/v1/app/documents/{id}` (requires `tenant.manage_documents`)
  - `GET /api/v1/app/documents/kpis` (requires `tenant.view_documents`)
  - `GET /api/v1/app/documents/alerts` (requires `tenant.view_documents`)
  - `GET /api/v1/app/documents/activity` (requires `tenant.view_documents`)
- **Settings**:
  - `GET /api/v1/app/settings` (requires `tenant.view_settings`)
  - `PUT /api/v1/app/settings/general` (requires `tenant.manage_settings`)
  - `PUT /api/v1/app/settings/notifications` (requires `tenant.manage_settings`)
- **Reports**:
  - `GET /api/v1/app/reports/kpis` (requires `tenant.view_reports`)
  - `GET /api/v1/app/reports/alerts` (requires `tenant.view_reports`)
  - `GET /api/v1/app/reports/activity` (requires `tenant.view_reports`)

### Test Coverage

- **Permission Enforcement**: All endpoints verify `tenant.permission:*` middleware is working
- **Tenant Isolation**: All endpoints verify cross-tenant data leak prevention
- **Strict Negative Tests**: Guest role tests verify that roles without permissions are strictly denied (403 + `TENANT_PERMISSION_DENIED`)

### Non-Goals (Important)

- ❌ No behavior changes to existing endpoints (except controller tenant scoping fixes)
- ❌ No HTTP status/error code changes
- ❌ No new features or UX flows
- ❌ No route/method/schema changes (only route parameter name changes for documents)

**Last Updated:** 2025-01-08

