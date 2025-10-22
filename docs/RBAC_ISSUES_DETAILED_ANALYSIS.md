# RBAC Issues Detailed Analysis & Tickets

## RBAC-ISSUE-001: Test Data Structure Issues
**Priority**: HIGH  
**Status**: Open  
**Description**: Test data structure issues - missing developer, client, guest user data  
**Impact**: Cannot test Developer, Client, and Guest role permissions  
**Steps to Reproduce**: 
1. Run RBAC tests: `npx playwright test --project=regression-chromium --grep "@regression RBAC"`
2. Tests fail at `testData.developer.email`, `testData.client.email`, `testData.guest.email`
3. Error: `TypeError: Cannot read properties of undefined (reading 'email')`
**Expected**: `testData` should contain all role types (developer, client, guest)  
**Actual**: Missing developer, client, guest user data in test structure  
**Files Affected**: `tests/e2e/helpers/data.ts`, test data structure  
**Evidence**: 
- Playwright logs: "TypeError: Cannot read properties of undefined (reading 'email')"
- Screenshots: Available in test-results/regression-rbac-rbac-matri-*/test-failed-*.png
- Error context: Available in test-results/regression-rbac-rbac-matri-*/error-context.md
**Backend Owner**: Test Data Team  
**Frontend Owner**: Test Data Team  
**Resolution Plan**: Add missing user roles to test data structure  
**Retest Command**: `npx playwright test --project=regression-chromium --grep "@regression RBAC"`

---

## RBAC-ISSUE-002: Strict Mode Violations in Tenant Detection
**Priority**: MEDIUM  
**Status**: Open  
**Description**: Strict mode violations in tenant detection - locator resolves to multiple elements  
**Impact**: Cannot properly detect tenant-specific data  
**Steps to Reproduce**: 
1. Run tenant isolation tests
2. Tests fail with strict mode violation
3. Error: `Error: strict mode violation: locator('text=/ZENA|zena/i') resolved to 23 elements`
**Expected**: Locator should resolve to single element or use `.first()`  
**Actual**: Locator resolves to 17-23 elements causing strict mode violation  
**Files Affected**: `tests/e2e/regression/rbac/rbac-isolation.spec.ts`  
**Evidence**: 
- Playwright logs: "Error: strict mode violation: locator('text=/ZENA|zena/i') resolved to 23 elements"
- Screenshots: Available in test-results/regression-rbac-rbac-isola-*/test-failed-*.png
- Error context: Available in test-results/regression-rbac-rbac-isola-*/error-context.md
**Backend Owner**: N/A (Test Issue)  
**Frontend Owner**: QA Team  
**Resolution Plan**: Fix locator strict mode violations using `.first()`  
**Retest Command**: `npx playwright test --project=regression-chromium --grep "@regression RBAC"`

---

## RBAC-ISSUE-003: API Endpoints Return HTML Instead of JSON
**Priority**: HIGH  
**Status**: Open  
**Description**: API endpoints return HTML instead of JSON - causing JSON parsing errors  
**Impact**: Cannot test API authorization properly  
**Steps to Reproduce**: 
1. Run API endpoint tests
2. Tests fail with JSON parsing errors
3. Error: `SyntaxError: Unexpected token '<', "<!DOCTYPE "... is not valid JSON`
**Expected**: API endpoints should return JSON responses  
**Actual**: API endpoints return HTML pages (DOCTYPE)  
**Files Affected**: API routes, middleware, authentication  
**Evidence**: 
- Playwright logs: "SyntaxError: Unexpected token '<', "<!DOCTYPE "... is not valid JSON"
- Screenshots: Available in test-results/regression-rbac-rbac-isola-*/test-failed-*.png
- Error context: Available in test-results/regression-rbac-rbac-isola-*/error-context.md
**Backend Owner**: API Team  
**Frontend Owner**: N/A  
**Resolution Plan**: Fix API endpoints to return JSON instead of HTML  
**Retest Command**: `npx playwright test --project=regression-chromium --grep "@regression RBAC"`

---

## RBAC-ISSUE-004: Missing API Endpoints
**Priority**: HIGH  
**Status**: Open  
**Description**: Missing API endpoints - Admin Tenants, Admin Dashboard return 404  
**Impact**: Cannot test admin functionality  
**Steps to Reproduce**: 
1. Run API endpoint tests
2. Admin endpoints return 404
3. Error: `❌ Super Admin: Admin Tenants List - Expected 200, got 404`
**Expected**: Admin endpoints should exist and return data  
**Actual**: Admin endpoints return 404 Not Found  
**Files Affected**: API routes, admin controllers  
**Evidence**: 
- Playwright logs: "❌ Super Admin: Admin Tenants List - Expected 200, got 404"
- Screenshots: Available in test-results/regression-rbac-rbac-matri-*/test-failed-*.png
- Error context: Available in test-results/regression-rbac-rbac-matri-*/error-context.md
**Backend Owner**: Admin API Team  
**Frontend Owner**: N/A  
**Resolution Plan**: Implement missing admin endpoints  
**Retest Command**: `npx playwright test --project=regression-chromium --grep "@regression RBAC"`

---

## RBAC-ISSUE-005: Insufficient Permission Restrictions
**Priority**: CRITICAL  
**Status**: Open  
**Description**: Insufficient permission restrictions - non-admin roles can access admin functions  
**Impact**: Security vulnerability - unauthorized access to admin functions  
**Steps to Reproduce**: 
1. Login as Project Manager, Developer, Client, or Guest
2. Access admin endpoints or perform restricted actions
3. Actions succeed when they should be restricted
4. Error: `❌ Project Manager: Admin Users List - Should be restricted, got 200`
**Expected**: Non-admin roles should be restricted from admin functions  
**Actual**: Non-admin roles can access admin endpoints and perform restricted actions  
**Files Affected**: API middleware, permission checks, role-based access control  
**Evidence**: 
- Playwright logs: "❌ Project Manager: Admin Users List - Should be restricted, got 200"
- Screenshots: Available in test-results/regression-rbac-rbac-matri-*/test-failed-*.png
- Error context: Available in test-results/regression-rbac-rbac-matri-*/error-context.md
**Backend Owner**: Security Team  
**Frontend Owner**: Security Team  
**Resolution Plan**: Implement proper permission restrictions for non-admin roles  
**Retest Command**: `npx playwright test --project=regression-chromium --grep "@regression RBAC"`

---

## RBAC-ISSUE-006: Cross-Tenant Resource Access
**Priority**: CRITICAL  
**Status**: Open  
**Description**: Cross-tenant resource access - users can access resources from other tenants  
**Impact**: Security vulnerability - tenant isolation not working  
**Steps to Reproduce**: 
1. Login as ZENA user
2. Access resources with TTF tenant IDs
3. Resources are accessible when they should be restricted
4. Error: `❌ Resource /api/projects/999 accessible for cross-tenant access`
**Expected**: Users should only access resources from their own tenant  
**Actual**: Users can access resources from other tenants  
**Files Affected**: Tenant isolation middleware, database queries  
**Evidence**: 
- Playwright logs: "❌ Resource /api/projects/999 accessible for cross-tenant access"
- Screenshots: Available in test-results/regression-rbac-rbac-isola-*/test-failed-*.png
- Error context: Available in test-results/regression-rbac-rbac-isola-*/error-context.md
**Backend Owner**: Security Team  
**Frontend Owner**: Security Team  
**Resolution Plan**: Fix tenant isolation to prevent cross-tenant access  
**Retest Command**: `npx playwright test --project=regression-chromium --grep "@regression RBAC"`

---

## RBAC Issues Summary
- **Total Issues**: 6
- **Critical Priority**: 2 (RBAC-ISSUE-005, RBAC-ISSUE-006)
- **High Priority**: 3 (RBAC-ISSUE-001, RBAC-ISSUE-003, RBAC-ISSUE-004)
- **Medium Priority**: 1 (RBAC-ISSUE-002)
- **Status**: All issues documented and ready for development team

## Resolution Priority
1. **RBAC-ISSUE-005**: Implement proper permission restrictions for non-admin roles (CRITICAL)
2. **RBAC-ISSUE-006**: Fix tenant isolation to prevent cross-tenant access (CRITICAL)
3. **RBAC-ISSUE-003**: Fix API endpoints to return JSON instead of HTML (HIGH)
4. **RBAC-ISSUE-004**: Implement missing admin endpoints (HIGH)
5. **RBAC-ISSUE-001**: Add missing user roles to test data structure (HIGH)
6. **RBAC-ISSUE-002**: Fix locator strict mode violations using `.first()` (MEDIUM)

## Retest Plan
When development team marks tickets as "Ready for QA":
1. Run full RBAC suite: `npx playwright test --project=regression-chromium --grep "@regression RBAC"`
2. Update `docs/TASK_LOG_PHASE_4.md` with retest results
3. Close tickets based on test results
4. Update `CHANGELOG.md` with resolution status
