# PR Checklist - Improvement Plan

## PR #1: Composite unique theo tenant

### Files
- [ ] `database/migrations/YYYY_MM_DD_HHMMSS_add_tenant_unique_constraints.php`

### Code
- [ ] Unique constraint: `projects(tenant_id, code)`
- [ ] Unique constraint: `documents(tenant_id, slug)`
- [ ] Unique constraint: `clients(tenant_id, email)` (nếu có)
- [ ] Composite indexes: `tasks(tenant_id, project_id, status)`
- [ ] FK on-delete rules review

### Tests
- [ ] Test: verify unique constraint works
- [ ] Test: verify tenant isolation (tenant A cannot create duplicate code of tenant B)
- [ ] Test: verify soft delete respects unique constraint

### Documentation
- [ ] Update migration notes
- [ ] Update database schema docs

---

## PR #2: Invalidation map FE

### Files
- [ ] `frontend/src/shared/api/invalidateMap.ts`
- [ ] `frontend/src/shared/api/invalidateHelpers.ts` (optional)

### Code
- [ ] Define `invalidateMap` với đầy đủ actions
- [ ] Helper `invalidateFor(action, context)`
- [ ] Refactor `useCreateTask` → dùng `invalidateFor`
- [ ] Refactor `useUpdateTask` → dùng `invalidateFor`
- [ ] Refactor `useMoveTask` → dùng `invalidateFor`
- [ ] Refactor `useDeleteTask` → dùng `invalidateFor`
- [ ] Refactor project hooks
- [ ] Refactor document hooks

### Tests
- [ ] Unit test: `invalidateFor` function
- [ ] Integration test: verify cache invalidation sau mutations
- [ ] E2E test: verify dashboard freshness sau task move

### Documentation
- [ ] Update cache invalidation guide
- [ ] Update hooks documentation

---

## PR #3: WebSocket Auth Guard

### Files
- [ ] `app/WebSocket/AuthGuard.php`
- [ ] `app/WebSocket/RateLimitGuard.php` (optional)
- [ ] Update `app/WebSocket/DashboardWebSocketHandler.php`

### Code
- [ ] `AuthGuard::verifyToken()` - Sanctum verification
- [ ] `AuthGuard::canSubscribe()` - tenant isolation + permission check
- [ ] Rate-limit middleware cho WebSocket
- [ ] Integration vào `DashboardWebSocketHandler`

### Tests
- [ ] Test: verify token authentication
- [ ] Test: verify tenant isolation (cross-tenant subscription rejected)
- [ ] Test: verify rate limiting
- [ ] Test: verify permission checks

### Documentation
- [ ] Update WebSocket documentation
- [ ] Update security documentation

---

## PR #4: OpenAPI → Types

### Files
- [ ] `docs/api/openapi.yaml` (update)
- [ ] `package.json` (add script)
- [ ] `frontend/src/shared/api/types.gen.ts` (generated)
- [ ] Refactor hooks

### Code
- [ ] Update OpenAPI spec (đầy đủ endpoints)
- [ ] Script `npm run gen:api`
- [ ] CI check: OpenAPI validation
- [ ] Refactor `entities/tasks/api.ts` → dùng generated types
- [ ] Refactor other API files

### Tests
- [ ] Test: OpenAPI spec validation
- [ ] Test: Type generation
- [ ] Test: Contract tests (OpenAPI vs runtime)

### Documentation
- [ ] Update API documentation
- [ ] Update type generation guide

---

## PR #5: Header/Navigation 1 nguồn

### Files
- [ ] `resources/shared/nav.json` (hoặc dùng API `/api/v1/me/nav`)
- [ ] Update Blade component
- [ ] Update React component

### Code
- [ ] Navigation schema (JSON hoặc API endpoint)
- [ ] Blade component đọc từ nguồn
- [ ] React component đọc từ cùng nguồn
- [ ] Verify consistency

### Tests
- [ ] Test: navigation schema validation
- [ ] E2E test: verify Blade và React hiển thị giống nhau
- [ ] Test: verify navigation filtering theo permissions

### Documentation
- [ ] Update navigation documentation
- [ ] Update component documentation

---

## General Checklist (All PRs)

### Code Quality
- [ ] Code follows project conventions
- [ ] No hardcoded values
- [ ] Proper error handling
- [ ] Proper logging (with request_id/tenant_id)

### Security
- [ ] Tenant isolation verified
- [ ] RBAC checks in place
- [ ] Input validation
- [ ] Output sanitization

### Performance
- [ ] No N+1 queries
- [ ] Proper indexes
- [ ] Cache strategy considered
- [ ] Performance budgets respected

### Testing
- [ ] Unit tests written
- [ ] Integration tests written
- [ ] E2E tests written (if applicable)
- [ ] All tests pass

### Documentation
- [ ] Code comments
- [ ] API documentation updated
- [ ] Architecture docs updated (if applicable)
- [ ] Migration notes (if applicable)

### CI/CD
- [ ] All CI checks pass
- [ ] No linter errors
- [ ] No type errors
- [ ] Performance budgets enforced

