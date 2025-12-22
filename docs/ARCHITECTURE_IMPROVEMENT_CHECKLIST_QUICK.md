# ✅ Architecture Improvement Checklist - Quick Reference

**Last Updated:** 2025-01-20  
**Status:** 🚨 Mức ĐỎ đang triển khai

---

## 🚨 MỨC ĐỎ (2 Tuần) - Làm Ngay

### 1. UnifiedController Deprecation
- [ ] Tạo `Api/V1/App/UsersController.php`
- [ ] Tạo `Api/V1/App/SubtasksController.php`
- [ ] Tạo `Api/V1/App/TaskCommentsController.php`
- [ ] Tạo `Api/V1/App/TaskAttachmentsController.php`
- [ ] Tạo `Api/V1/App/ProjectAssignmentsController.php`
- [ ] Tạo `Api/V1/App/TaskAssignmentsController.php`
- [ ] Tạo `Web/UsersController.php` (chỉ return view)
- [ ] Tạo `Web/SubtasksController.php` (chỉ return view)
- [ ] Tạo `Web/TaskCommentsController.php` (chỉ return view)
- [ ] Tạo `Web/TaskAttachmentsController.php` (chỉ return view)
- [ ] Update `routes/api_v1.php` - chỉ dùng `Api/V1/*`
- [ ] Update `routes/app.php` - chỉ dùng `Web/*`
- [ ] Thêm `@deprecated` annotation vào Unified/* controllers
- [ ] Log warning khi Unified/* được gọi
- [ ] Integration tests cho tất cả controllers mới
- [ ] Xóa Unified/* sau 2 tuần (verify không còn usage)

### 2. Tenant-Safety Hardening
- [ ] Verify tất cả models có `BelongsToTenant` trait
- [ ] Test GlobalScope không thể bypass (trừ super admin)
- [ ] Test raw queries vẫn bị filter
- [ ] Verify `tenant_id NOT NULL` trên tất cả tables
- [ ] Thêm migration nếu thiếu constraints
- [ ] Composite unique indexes: `(tenant_id, slug)` với `deleted_at IS NULL`
- [ ] Verify cache key format: `{env}:{tenant}:{domain}:{resource}:{id}`
- [ ] Test cache invalidation khi tenant context thay đổi
- [ ] Test tenant A không thể đọc data của tenant B
- [ ] Test tenant A không thể tạo data cho tenant B
- [ ] Test super admin có thể bypass (nhưng log lại)
- [ ] Tạo `tests/Feature/TenantIsolationHardeningTest.php`

### 3. OpenAPI Contract Enforcement
- [ ] Verify `l5-swagger` hoạt động đúng
- [ ] Generate spec từ annotations
- [ ] Publish `/api/v1/openapi.json`
- [ ] Update `.github/workflows/openapi-contract-test.yml`
- [ ] Diff spec giữa PR và main
- [ ] Fail nếu breaking change mà không bump version
- [ ] Verify response format matches spec
- [ ] Update `frontend/scripts/generate-api-types.js`
- [ ] Auto-run type generation trong CI
- [ ] Response validation middleware
- [ ] Test response validation trong test environment

### 4. Idempotency Audit
- [ ] List tất cả POST/PUT/PATCH endpoints
- [ ] Verify middleware `idempotency` được apply
- [ ] Document endpoints không cần idempotency (nếu có)
- [ ] Test cùng `idempotency_key` gọi 2 lần → trả về cùng response
- [ ] Test `X-Idempotent-Replayed` header
- [ ] Test cache + DB persistence
- [ ] Standardize idempotency key format: `{resource}_{action}_{timestamp}_{nonce}`
- [ ] Document trong OpenAPI
- [ ] FE helper function để generate key
- [ ] Tạo `tests/Feature/IdempotencyTest.php`

### 5. WebSocket Hardening
- [ ] Verify channel format: `{tenant}:{resource}:{id}`
- [ ] Check permission mỗi subscribe
- [ ] Revoke khi user bị khóa
- [ ] Limit messages per connection (backpressure)
- [ ] Queue overflow protection
- [ ] Disconnect slow consumers
- [ ] Connection count per tenant (metrics)
- [ ] Message rate per channel (metrics)
- [ ] Error rate (metrics)
- [ ] Healthcheck endpoint: `/ws/health`
- [ ] Listen to `UserDisabled` event
- [ ] Close all connections của user đó
- [ ] Notify user trước khi disconnect
- [ ] Tạo `tests/Feature/WebSocketAuthTest.php`

---

## 🟡 MỨC VÀNG (30-60 Ngày)

### 6. Transactional Outbox
- [ ] Verify `outbox` table exists
- [ ] Verify `OutboxService` hoạt động
- [ ] Verify `ProcessOutboxJob` chạy đúng
- [ ] Test events được ghi vào outbox
- [ ] Test worker tiêu thụ events
- [ ] Test idempotent processing
- [ ] Dashboard cho outbox queue length
- [ ] Alert nếu queue quá dài
- [ ] Metrics cho processing time

### 7. Cursor Pagination
- [ ] List tất cả list endpoints
- [ ] Migrate từ offset sang cursor
- [ ] Support cả 2 (backward compatible)
- [ ] Composite indexes: `(tenant_id, created_at)`
- [ ] Verify query plans
- [ ] Performance tests
- [ ] Document cursor pagination trong OpenAPI
- [ ] Examples trong OpenAPI
- [ ] FE helper functions

### 8. Observability (OpenTelemetry)
- [ ] FE gửi `traceparent` header
- [ ] BE propagate qua services
- [ ] Log `traceId` trong mọi layer
- [ ] Install `open-telemetry/opentelemetry-php`
- [ ] Configure trace exporters (Jaeger/Zipkin)
- [ ] Instrument HTTP, DB, Queue
- [ ] p95/p99 latency per route
- [ ] Error rate per endpoint
- [ ] Tenant-level metrics
- [ ] Real-time monitoring dashboard
- [ ] Alerts cho error spikes
- [ ] Performance trends

### 9. Media Pipeline
- [ ] ClamAV integration
- [ ] Queue job: `ScanFileVirusJob`
- [ ] Block upload nếu virus detected
- [ ] Strip EXIF từ images
- [ ] Queue job: `StripExifJob`
- [ ] Generate thumbnails, medium, large
- [ ] Queue job: `ProcessImageJob`
- [ ] Store variants trong S3/CDN
- [ ] Generate signed URLs cho downloads
- [ ] TTL: 1 hour
- [ ] CDN integration
- [ ] Track storage per tenant
- [ ] Enforce limits
- [ ] Alert khi gần limit

### 10. RBAC Sync FE/BE
- [ ] Add `x-abilities` to all endpoints
- [ ] Document required permissions
- [ ] Generate types từ OpenAPI
- [ ] Generate permission types
- [ ] Update `authStore` to use generated types
- [ ] Route guards based on permissions
- [ ] Test tất cả role/permission combinations
- [ ] Verify FE/BE consistency
- [ ] Document expected behavior

### 11. Error Envelope Standardization
- [ ] Unified error format: `{ok: false, error: {code, message, details, traceId}}`
- [ ] Fixed error codes per domain
- [ ] Document trong OpenAPI
- [ ] FE error handler
- [ ] Error codes có translation keys
- [ ] FE có thể translate
- [ ] Fallback to English

---

## 🟢 MỨC XANH (90+ Ngày)

### 12. CQRS-lite
- [ ] Separate read/write models cho Dashboard
- [ ] Separate read/write models cho Reports
- [ ] Event sourcing cho audit trail

### 13. Feature Flags
- [ ] Install Unleash/GrowthBook
- [ ] Database-driven feature flags
- [ ] Gradual rollout mechanism

### 14. Supply-chain Security
- [ ] SBOM generation (Syft)
- [ ] Dependabot/Renovate setup
- [ ] Provenance (SLSA-lite)

### 15. Zero-downtime Migration
- [ ] Blue-green deployment setup
- [ ] Canary deployment setup
- [ ] Forward-compatible migration rules

### 16. Frontend Improvements
- [ ] React Query keys theo tenant
- [ ] Optimistic update + rollback cho Kanban move
- [ ] ErrorBoundary + retry/backoff chuẩn hoá
- [ ] A11y: axe checks trong Playwright
- [ ] Design tokens: CSS vars dùng chung Blade/React

---

## 📊 PROGRESS TRACKING

### Mức ĐỎ (2 Tuần)
- **Started:** 2025-01-20
- **Target:** 2025-02-03
- **Progress:** 0/5 items completed

### Mức VÀNG (30-60 Ngày)
- **Started:** TBD
- **Target:** TBD
- **Progress:** 0/6 items completed

### Mức XANH (90+ Ngày)
- **Started:** TBD
- **Target:** TBD
- **Progress:** 0/5 items completed

---

## 🎯 NEXT ACTIONS

1. **Tuần 1 (Day 1-5)**:
   - [ ] Bắt đầu deprecate Unified/* controllers
   - [ ] Hardening tenant-safety
   - [ ] OpenAPI contract enforcement

2. **Tuần 2 (Day 6-10)**:
   - [ ] Idempotency audit
   - [ ] WebSocket hardening
   - [ ] Integration tests & documentation

---

**Note:** Checklist này được sync với `ARCHITECTURE_IMPROVEMENT_PLAN_DETAILED.md`

