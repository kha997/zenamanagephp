# Kế hoạch cải tiến ZenaManage - Sprint tiếp theo

**Ngày tạo**: 2025-01-19  
**Mục tiêu**: Tối ưu "độ sạch" và giảm trùng lặp để chạy nhanh – an – dễ mở rộng  
**Thời gian**: 2-4 tuần (2 sprints)

---

## 📊 Tổng quan

### Điểm mạnh hiện tại ✅
- ✅ Kiến trúc "Laravel monolith hybrid" gọn, chuẩn API/Envelope nhất quán
- ✅ Multi-tenant & RBAC bài bản
- ✅ FE đã vào khuôn React v1 (feature-sliced + tokens)
- ✅ CI/CD + Playwright chạy đủ vòng
- ✅ Tài liệu dày

### Lỗ hổng cần xử ngay ⚠️
1. **Trùng lặp Blade vs React** - double maintenance (header, nav, dashboard)
2. **WebSocket hardening** - thiếu auth/tenant guard/rate-limit tương đương HTTP
3. **OpenAPI & hợp đồng FE/BE** - chưa có nguồn chân lý OpenAPI cho lint contract + generate types
4. **Cache & invalidation chéo lớp** - thiếu "bảng tra" invalidation khi drag-drop/summary KPI
5. **Ràng buộc DB cho tenant** - cần soát đủ unique composite theo (tenant_id, …), FK on-delete rules, index đúng pattern
6. **Idempotency & retry jobs** - thiếu tiêu chuẩn idempotency key, retry/back-off/throttling theo tenant
7. **Observability chưa "3 tín hiệu"** - thiếu trace + metrics + logs hợp nhất theo request_id/tenant_id

---

## 🎯 Quick Wins (1–2 tuần) – ít rủi ro, tác động lớn

### A. Dứt điểm khung giao diện dùng chung

**Mục tiêu**: Khóa "Universal Frame" chỉ 1 nguồn

**Công việc**:
1. **Blade**: `resources/views/layouts/universal-frame.blade.php` (hoặc `layouts/app.blade.php`)
2. **React**: `frontend/src/shared/ui/HeaderShell.tsx` (hoặc `frontend/src/components/layout/HeaderShell.tsx`)
3. **Navigation inventory**: Export từ 1 file `resources/shared/nav.json` → React & Blade cùng parse

**Deliverables**:
- [ ] File `resources/shared/nav.json` chứa navigation schema
- [ ] Blade component đọc từ `nav.json` (hoặc API `/api/v1/me/nav`)
- [ ] React component đọc từ cùng nguồn
- [ ] Test E2E verify navigation consistency giữa Blade và React

**PR**: `feat: unify-navigation-single-source`

---

### B. Chuẩn hoá OpenAPI → types FE

**Mục tiêu**: Sinh OpenAPI từ mã nguồn → commit vào `docs/api/openapi.yaml` → generate TypeScript types

**Công việc**:
1. Sinh OpenAPI từ mã nguồn (PHP attributes hoặc FormRequest)
2. Commit vào `docs/api/openapi.yaml` (single source of truth)
3. Tạo script `npm run gen:api` sinh TypeScript clients/types (DTOs, Zod schemas)
4. Dùng trong `frontend/src/shared/api`

**Deliverables**:
- [ ] OpenAPI spec đầy đủ cho tất cả endpoints (hiện có `docs/api/openapi.yaml` nhưng cần update)
- [ ] Script `npm run gen:api` generate types từ OpenAPI
- [ ] Refactor hooks dùng types mới (bắt đầu từ `entities/tasks/api.ts`)
- [ ] CI check: OpenAPI spec validation + type generation test

**PR**: `feat: openapi-types-generation`

---

### C. Kiểm tra ràng buộc DB theo tenant

**Mục tiêu**: Thêm index/unique dạng:
- `projects`: UNIQUE(tenant_id, code)
- `tasks`: INDEX(tenant_id, project_id, status)
- `documents`: UNIQUE(tenant_id, slug)

**Công việc**:
1. Review FK on delete: `project -> tasks` (cascade), `tenant -> everything` (restrict)
2. Thêm composite unique indexes
3. Thêm composite indexes cho performance

**Deliverables**:
- [ ] Migration: composite unique indexes
- [ ] Migration: composite indexes cho pagination/filtering
- [ ] Review FK on-delete rules
- [ ] Test: verify tenant isolation không bị vi phạm

**PR**: `feat: tenant-db-constraints`

**Note**: Đã có một số migrations (`2025_11_18_034512_enforce_tenant_constraints_and_indexes.php`, `2025_11_17_143955_add_composite_unique_indexes_with_soft_delete.php`) - cần review và bổ sung nếu thiếu.

---

### D. WebSocket guard

**Mục tiêu**: Bắt buộc handshake có Sanctum/Personal Access Token, map tenant_id, áp rate-limit theo user_id + tenant_id, cấm subscribe khác tenant

**Công việc**:
1. Tạo `app/WebSocket/AuthGuard.php`: verify Sanctum, set tenant_id, limit channels
2. Áp vào `websocket_server.php` trước khi accept connection
3. Rate-limit theo user_id + tenant_id
4. Enforce tenant isolation trong subscription

**Deliverables**:
- [ ] `app/WebSocket/AuthGuard.php` với Sanctum verification
- [ ] Rate-limit middleware cho WebSocket
- [ ] Tenant isolation check trong subscription handler
- [ ] Test: verify cross-tenant subscription bị reject

**PR**: `feat: websocket-auth-guard`

**Note**: Đã có `DashboardWebSocketHandler` với một số logic auth - cần refactor và harden.

---

### E. Chuẩn cache invalidation

**Mục tiêu**: Bản đồ "khi mutation X → invalidate keys Y"

**Công việc**:
1. Tạo `frontend/src/shared/api/invalidateMap.ts`:
   ```typescript
   export const invalidateMap = {
     'task.move': ['tasks', 'task', 'tasks.kpis', 'dashboard'],
     'task.update': ['tasks', 'task', 'tasks.kpis'],
     'project.update': ['project', 'projects', 'dashboard'],
     // ...
   };
   ```
2. Viết helper `invalidateFor(action, context)` dùng chung cho hooks
3. Refactor tất cả mutation hooks gọi `invalidateFor("task.move", ctx)`

**Deliverables**:
- [ ] File `invalidateMap.ts` với mapping đầy đủ
- [ ] Helper `invalidateFor()` function
- [ ] Refactor hooks: `useCreateTask`, `useUpdateTask`, `useMoveTask`, `useDeleteTask`, etc.
- [ ] Test: verify cache invalidation đúng sau mutations

**PR**: `feat: cache-invalidation-map`

---

## 🏗️ Cải tiến cấu trúc (2–4 tuần)

### 1. Feature flags cứng

**Mục tiêu**: Toggle Blade↔React theo route (safe rollout), kèm smoke tests cho cả hai

**Công việc**:
1. Database-driven feature flags (đã có `FeatureFlagService`)
2. Route-level flags: `/app/tasks` → React, `/app/projects` → Blade (hoặc ngược lại)
3. Smoke tests cho cả hai paths

**Deliverables**:
- [ ] Feature flag config cho routes
- [ ] Middleware route switching
- [ ] Smoke tests cho Blade và React paths
- [ ] Rollout plan (gradual migration)

**PR**: `feat: feature-flag-route-switching`

---

### 2. Job idempotency

**Mục tiêu**: Tiêu chuẩn hoá idempotency_key (UUID theo (tenant,user,action,payloadHash)); middleware job từ chối lặp; retry policy exponential backoff + DLQ

**Công việc**:
1. Standardize idempotency key format: `{tenant}_{user}_{action}_{payloadHash}`
2. Job middleware check idempotency
3. Retry policy: exponential backoff + dead letter queue
4. Throttling theo tenant

**Deliverables**:
- [ ] Idempotency middleware cho jobs
- [ ] Retry policy với exponential backoff
- [ ] Dead letter queue cho failed jobs
- [ ] Throttling per tenant
- [ ] Tests: verify idempotency

**PR**: `feat: job-idempotency-retry`

---

### 3. SLO/SLA nội bộ

**Mục tiêu**:
- API p95 < 300ms; WS subscribe < 200ms; error rate < 0.5%
- Dashboard cập nhật "freshness" ≤ 5s sau mutation quan trọng

**Công việc**:
1. Define SLO targets
2. Metrics collection (đã có `MetricsService`)
3. Alerting khi vi phạm SLO
4. Dashboard freshness tracking

**Deliverables**:
- [ ] SLO definition document
- [ ] Metrics collection cho SLO
- [ ] Alerting rules
- [ ] Dashboard freshness tracking
- [ ] Performance budgets enforcement trong CI

**PR**: `feat: slo-sla-tracking`

---

### 4. Observability 3-in-1

**Mục tiêu**: Attach request_id & tenant_id vào log line, metric labels, trace span; thêm performance-budgets.json vào CI để fail sớm

**Công việc**:
1. Unified logging: request_id + tenant_id trong mọi log
2. Metrics labels: request_id + tenant_id
3. Trace spans: request_id + tenant_id
4. CI check: performance-budgets.json validation

**Deliverables**:
- [ ] Unified logging format
- [ ] Metrics với labels đầy đủ
- [ ] Trace integration (nếu có APM)
- [ ] CI check: performance budgets
- [ ] Dashboard: observability 3-in-1

**PR**: `feat: observability-3-signals`

---

### 5. Security drill

**Mục tiêu**: Test 2FA bắt buộc theo role; kịch bản stolen token/CSRF; WS auth fuzzing

**Công việc**:
1. 2FA enforcement tests
2. Stolen token scenario tests
3. CSRF tests
4. WebSocket auth fuzzing

**Deliverables**:
- [ ] Security test suite
- [ ] 2FA enforcement tests
- [ ] Token security tests
- [ ] CSRF tests
- [ ] WebSocket security tests

**PR**: `feat: security-drill-tests`

---

## 📋 PR cụ thể "đóng tiền ngay"

### PR #1: Composite unique theo tenant

**File**: `database/migrations/YYYY_MM_DD_HHMMSS_add_tenant_unique_constraints.php`

```php
Schema::table('projects', function (Blueprint $t) {
    $t->unique(['tenant_id','code'], 'projects_tenant_code_unique');
});

Schema::table('documents', function (Blueprint $t) {
    $t->unique(['tenant_id','slug'], 'documents_tenant_slug_unique');
});

// Repeat for clients(email), etc.
```

**Checklist**:
- [ ] Migration file
- [ ] Test: verify unique constraint
- [ ] Test: verify tenant isolation

---

### PR #2: Invalidation map FE

**File**: `frontend/src/shared/api/invalidateMap.ts`

```typescript
export const invalidateMap = {
  'task.move': ['tasks', 'task', 'tasks.kpis', 'dashboard'],
  'task.update': ['tasks', 'task', 'tasks.kpis'],
  'task.create': ['tasks', 'tasks.kpis', 'dashboard'],
  'task.delete': ['tasks', 'tasks.kpis', 'dashboard'],
  'project.update': ['project', 'projects', 'dashboard'],
  'project.create': ['projects', 'dashboard'],
  'project.delete': ['projects', 'dashboard'],
  // ...
};

export function invalidateFor(
  action: keyof typeof invalidateMap,
  context: { queryClient: QueryClient; tenantId?: string; resourceId?: string }
) {
  const keys = invalidateMap[action];
  keys.forEach(key => {
    context.queryClient.invalidateQueries({ queryKey: [key] });
  });
}
```

**Checklist**:
- [ ] File `invalidateMap.ts`
- [ ] Helper `invalidateFor()`
- [ ] Refactor hooks: `useCreateTask`, `useUpdateTask`, `useMoveTask`, `useDeleteTask`
- [ ] Test: verify cache invalidation

---

### PR #3: WebSocket Auth Guard

**File**: `app/WebSocket/AuthGuard.php`

```php
class AuthGuard
{
    public function verifyToken(string $token): ?User
    {
        // Sanctum verification
        $user = Auth::guard('sanctum')->setToken($token)->user();
        
        if (!$user || !$user->is_active) {
            return null;
        }
        
        return $user;
    }
    
    public function canSubscribe(User $user, string $channel): bool
    {
        // Tenant isolation check
        // Rate limit check
        // Permission check
    }
}
```

**Checklist**:
- [ ] `AuthGuard.php`
- [ ] Integration vào `DashboardWebSocketHandler`
- [ ] Rate-limit middleware
- [ ] Test: verify auth + tenant isolation

---

### PR #4: OpenAPI → Types

**Files**:
- `docs/api/openapi.yaml` (update)
- `package.json` scripts:
  ```json
  {
    "scripts": {
      "gen:api": "openapi-typescript docs/api/openapi.yaml -o frontend/src/shared/api/types.gen.ts"
    }
  }
  ```
- Refactor hooks dùng types mới

**Checklist**:
- [ ] OpenAPI spec đầy đủ
- [ ] Script `gen:api`
- [ ] CI check: OpenAPI validation
- [ ] Refactor hooks (bắt đầu từ `entities/tasks/api.ts`)

---

### PR #5: Header/Navigation 1 nguồn

**Files**:
- `resources/shared/nav.json` (hoặc dùng API `/api/v1/me/nav`)
- Blade component đọc từ nguồn
- React component đọc từ cùng nguồn

**Checklist**:
- [ ] Navigation schema (JSON hoặc API)
- [ ] Blade component integration
- [ ] React component integration
- [ ] Test E2E: verify consistency

---

## 🎯 Chốt mục tiêu Sprint sắp tới

### Sprint 1 (1 tuần)

**Mục tiêu**: Quick wins - dứt điểm trùng lặp và chuẩn hóa

**PRs**:
1. ✅ PR #1: Composite unique theo tenant
2. ✅ PR #2: Invalidation map FE
3. ✅ PR #5: Header/Navigation 1 nguồn
4. ✅ Smoke tests Blade/React
5. ✅ Feature flag chuyển dần `/app/tasks` sang React

**Deliverables**:
- [ ] 3 PRs merged
- [ ] Smoke tests pass
- [ ] Feature flag enabled cho `/app/tasks` → React

---

### Sprint 2 (1 tuần)

**Mục tiêu**: Hardening và observability

**PRs**:
1. ✅ PR #3: WebSocket Auth Guard
2. ✅ PR #4: OpenAPI → Types
3. ✅ Metrics + performance budgets
4. ✅ 2 kịch bản Playwright cho WS + cache freshness

**Deliverables**:
- [ ] 2 PRs merged
- [ ] Metrics dashboard
- [ ] Performance budgets enforced
- [ ] E2E tests cho WS + cache

---

## 📊 KPI để tự soi

### Performance
- [ ] p95 API theo route top-10
- [ ] Cache freshness dashboard sau mutation
- [ ] Tỉ lệ lỗi 4xx/5xx theo tenant

### Quality
- [ ] Tỉ lệ test E2E pass
- [ ] Drift OpenAPI vs runtime (contract tests)
- [ ] Code coverage (backend + frontend)

### Observability
- [ ] Request correlation (request_id trong logs/metrics/traces)
- [ ] Tenant isolation violations (0 expected)
- [ ] Cache hit rate

---

## 📝 Notes

- Tất cả PRs phải có tests
- Tất cả PRs phải update documentation
- Tất cả PRs phải pass CI/CD
- Performance budgets phải được enforce trong CI

---

**Next Steps**:
1. Review kế hoạch với team
2. Assign PRs
3. Bắt đầu Sprint 1

