# 🏗️ Kế Hoạch Chi Tiết Cải Tiến Hệ Thống ZenaManage

**Ngày tạo:** 2025-01-20  
**Trạng thái:** Đang triển khai  
**Timeline:** 2 tuần (Mức ĐỎ) → 30-60 ngày (Mức VÀNG) → 90+ ngày (Mức XANH)

---

## 📊 TÓM TẮT TIẾN BỘ (Điểm Cộng)

### ✅ Đã Hoàn Thành
1. **Tách bạch API/Web**: Có `Api/V1/*`, `routes/app.php`, tài liệu API nội bộ
2. **Tenant-isolation có test & policy**: `TenantIsolationTest`, `AdminOnlyMiddleware`, policy tests
3. **Service layer đậm đặc**: `TaskManagementService`, `DashboardService`, `SearchService`
4. **Frontend dọn domain**: `frontend/src/entities/*`, `components/layout/HeaderShell.tsx` + E2E Playwright
5. **Universal Page Frame**: Khung UI thống nhất, tokens/design system sơ bộ
6. **DevOps/CI**: Workflows, scripts, perf budgets, docker setup

---

## 🚨 MỨC ĐỎ (Làm Sớm - 2 Tuần)

### 1. UnifiedController vẫn tồn tại song song

**Vấn đề:**
- `Unified/*` controllers vẫn còn 8 files:
  - `UserManagementController`
  - `ProjectManagementController` (đã deprecated nhưng chưa xóa)
  - `TaskManagementController` (đã deprecated nhưng chưa xóa)
  - `SubtaskManagementController`
  - `TaskCommentManagementController`
  - `TaskAttachmentManagementController`
  - `ProjectAssignmentController`
  - `TaskAssignmentController`
- Rủi ro drift API & web behavior (điều kiện `wantsJson()`...), khó enforce hợp đồng

**Giải pháp:**
1. **Tạo Api/V1/* controllers thuần** cho tất cả resources:
   - `Api/V1/App/UsersController.php`
   - `Api/V1/App/SubtasksController.php`
   - `Api/V1/App/TaskCommentsController.php`
   - `Api/V1/App/TaskAttachmentsController.php`
   - `Api/V1/App/ProjectAssignmentsController.php`
   - `Api/V1/App/TaskAssignmentsController.php`
2. **Tạo Web/* controllers** chỉ return `view()`:
   - `Web/UsersController.php`
   - `Web/SubtasksController.php`
   - `Web/TaskCommentsController.php`
   - `Web/TaskAttachmentsController.php`
3. **Update routes**:
   - `routes/api_v1.php`: Chỉ dùng `Api/V1/*`
   - `routes/app.php`: Chỉ dùng `Web/*`
4. **Deprecate Unified/***:
   - Thêm `@deprecated` annotation
   - Log warning khi Unified/* được gọi
   - Xóa sau 2 tuần (sau khi verify không còn usage)

**Timeline:** 3-5 ngày  
**Dependencies:** None  
**Tests:** 
- Verify tất cả API endpoints trả về JSON
- Verify tất cả Web routes trả về views
- Integration tests cho mỗi controller

---

### 2. Tenant-safety chưa khóa cứng ở tầng Model/DB

**Vấn đề:**
- Đang dựa nhiều vào service/policy
- Chỉ cần quên validate ở 1 nhánh là có thể rò rỉ tenant
- GlobalScope có thể bị bypass nếu không cẩn thận

**Giải pháp:**
1. **Verify GlobalScope hoạt động 100%**:
   - Test tất cả models có `BelongsToTenant` trait
   - Test GlobalScope không thể bypass (trừ super admin)
   - Test raw queries vẫn bị filter
2. **DB Constraints**:
   - Verify `tenant_id NOT NULL` trên tất cả tables
   - Thêm migration nếu thiếu
   - Composite unique indexes: `(tenant_id, slug)` với `deleted_at IS NULL`
3. **Cache key namespace**:
   - Verify format: `{env}:{tenant}:{domain}:{resource}:{id}`
   - Test cache invalidation khi tenant context thay đổi
4. **Tenant isolation violation tests**:
   - Test tenant A không thể đọc data của tenant B
   - Test tenant A không thể tạo data cho tenant B
   - Test super admin có thể bypass (nhưng log lại)

**Timeline:** 2-3 ngày  
**Dependencies:** None  
**Tests:**
- `tests/Feature/TenantIsolationHardeningTest.php`
- Test GlobalScope trên tất cả models
- Test DB constraints
- Test cache isolation

---

### 3. Hợp đồng API & kiểm soát breaking change

**Vấn đề:**
- Có docs nhưng chưa thấy OpenAPI/contract test tự động
- FE generate types nhưng chưa có CI enforcement
- Không có breaking change detection

**Giải pháp:**
1. **OpenAPI spec generation**:
   - Verify `l5-swagger` hoạt động đúng
   - Generate spec từ annotations
   - Publish `/api/v1/openapi.json`
2. **CI Contract Tests**:
   - Workflow: `.github/workflows/openapi-contract-test.yml`
   - Diff spec giữa PR và main
   - Fail nếu breaking change mà không bump version
   - Verify response format matches spec
3. **FE Type Generation**:
   - Script: `frontend/scripts/generate-api-types.js`
   - Auto-run trong CI
   - Verify types match OpenAPI spec
4. **Response Validation Middleware**:
   - Validate API responses match OpenAPI spec
   - Log warnings nếu không match
   - Fail trong test environment

**Timeline:** 3-4 ngày  
**Dependencies:** OpenAPI spec phải đầy đủ  
**Tests:**
- `tests/Contract/OpenApiContractTest.php`
- CI workflow test
- Type generation test

---

### 4. Idempotency cho endpoints "ghi"

**Vấn đề:**
- Move task, create/update có nguy cơ double-submit/retry
- Đã có middleware nhưng cần verify coverage

**Giải pháp:**
1. **Audit Idempotency Coverage**:
   - List tất cả POST/PUT/PATCH endpoints
   - Verify middleware `idempotency` được apply
   - Document endpoints không cần idempotency (nếu có)
2. **Test Double-Submit Scenarios**:
   - Test cùng `idempotency_key` gọi 2 lần → trả về cùng response
   - Test `X-Idempotent-Replayed` header
   - Test cache + DB persistence
3. **Idempotency Key Format**:
   - Standardize format: `{resource}_{action}_{timestamp}_{nonce}`
   - Document trong OpenAPI
   - FE helper function để generate key

**Timeline:** 1-2 ngày  
**Dependencies:** None  
**Tests:**
- `tests/Feature/IdempotencyTest.php`
- Test tất cả write endpoints
- Test double-submit scenarios

---

### 5. WebSocket/realtime

**Vấn đề:**
- Có server riêng nhưng chưa thấy quy tắc auth/permission per-channel & backpressure
- Chưa có metrics/healthcheck

**Giải pháp:**
1. **Channel Auth Per-Tenant**:
   - Verify channel format: `{tenant}:{resource}:{id}`
   - Check permission mỗi subscribe
   - Revoke khi user bị khóa
2. **Backpressure Handling**:
   - Limit messages per connection
   - Queue overflow protection
   - Disconnect slow consumers
3. **Metrics & Healthcheck**:
   - Connection count per tenant
   - Message rate per channel
   - Error rate
   - Healthcheck endpoint: `/ws/health`
4. **Revoke on User Disable**:
   - Listen to `UserDisabled` event
   - Close all connections của user đó
   - Notify user trước khi disconnect

**Timeline:** 2-3 ngày  
**Dependencies:** WebSocket server phải chạy  
**Tests:**
- `tests/Feature/WebSocketAuthTest.php`
- Test channel permissions
- Test revoke on user disable
- Test backpressure

---

## 🟡 MỨC VÀNG (30-60 Ngày)

### 6. Transactional Outbox

**Vấn đề:**
- Sự kiện (audit/notification/indexing/ws) nên đi qua outbox để chống mất/nhân đôi
- Đã có implementation nhưng cần verify

**Giải pháp:**
1. **Verify Outbox Implementation**:
   - Check `outbox` table exists
   - Check `OutboxService` hoạt động
   - Check `ProcessOutboxJob` chạy đúng
2. **Event Delivery Tests**:
   - Test events được ghi vào outbox
   - Test worker tiêu thụ events
   - Test idempotent processing
3. **Monitoring**:
   - Dashboard cho outbox queue length
   - Alert nếu queue quá dài
   - Metrics cho processing time

**Timeline:** 3-5 ngày  
**Dependencies:** Outbox table & service  
**Tests:**
- `tests/Feature/OutboxTest.php`
- Test event delivery
- Test idempotency

---

### 7. Pagination & Query shape

**Vấn đề:**
- Với multi-tenant + bảng lớn, offset-based sẽ đuối
- Đã có cursor-based nhưng chưa migrate hết

**Giải pháp:**
1. **Migrate Endpoints**:
   - List tất cả list endpoints
   - Migrate từ offset sang cursor
   - Support cả 2 (backward compatible)
2. **Index Optimization**:
   - Composite indexes: `(tenant_id, created_at)`
   - Verify query plans
   - Performance tests
3. **API Documentation**:
   - Document cursor pagination
   - Examples trong OpenAPI
   - FE helper functions

**Timeline:** 5-7 ngày  
**Dependencies:** None  
**Tests:**
- Performance tests: offset vs cursor
- Test với large datasets
- Test backward compatibility

---

### 8. Observability chuẩn

**Vấn đề:**
- Đã có metrics, nhưng thiếu correlation/tracing từ FE→BE
- Chưa có OpenTelemetry

**Giải pháp:**
1. **W3C Traceparent Header**:
   - FE gửi `traceparent` header
   - BE propagate qua services
   - Log `traceId` trong mọi layer
2. **OpenTelemetry Integration**:
   - Install `open-telemetry/opentelemetry-php`
   - Configure trace exporters (Jaeger/Zipkin)
   - Instrument HTTP, DB, Queue
3. **Metrics Collection**:
   - p95/p99 latency per route
   - Error rate per endpoint
   - Tenant-level metrics
4. **Dashboards**:
   - Real-time monitoring
   - Alerts cho error spikes
   - Performance trends

**Timeline:** 7-10 ngày  
**Dependencies:** OpenTelemetry infrastructure  
**Tests:**
- Test trace propagation
- Test metrics collection
- Test dashboard accuracy

---

### 9. Media pipeline

**Vấn đề:**
- Có Document/Upload nhưng thiếu vệ sinh sản phẩm
- Chưa có virus scan, EXIF strip, variants

**Giải pháp:**
1. **Virus Scanning**:
   - ClamAV integration
   - Queue job: `ScanFileVirusJob`
   - Block upload nếu virus detected
2. **EXIF Stripping**:
   - Strip EXIF từ images
   - Privacy protection
   - Queue job: `StripExifJob`
3. **Image Variants**:
   - Generate thumbnails, medium, large
   - Queue job: `ProcessImageJob`
   - Store variants trong S3/CDN
4. **Signed URLs**:
   - Generate signed URLs cho downloads
   - TTL: 1 hour
   - CDN integration
5. **Quota Per Tenant**:
   - Track storage per tenant
   - Enforce limits
   - Alert khi gần limit

**Timeline:** 10-14 ngày  
**Dependencies:** ClamAV, S3/CDN  
**Tests:**
- Test virus scanning
- Test EXIF stripping
- Test image variants
- Test signed URLs
- Test quota enforcement

---

### 10. RBAC drift FE/BE

**Vấn đề:**
- FE có authStore, BE có Policy/Permission; nguy cơ lệch
- Chưa có sync mechanism

**Giải pháp:**
1. **OpenAPI x-abilities Extension**:
   - Add `x-abilities` to all endpoints
   - Document required permissions
   - Generate types từ OpenAPI
2. **FE Type Generation**:
   - Generate permission types
   - Update `authStore` to use generated types
   - Route guards based on permissions
3. **Policy-Matrix Tests**:
   - Test tất cả role/permission combinations
   - Verify FE/BE consistency
   - Document expected behavior

**Timeline:** 5-7 ngày  
**Dependencies:** OpenAPI spec  
**Tests:**
- Policy-matrix tests
- FE/BE consistency tests
- Route guard tests

---

### 11. Error envelope & mã lỗi chuẩn

**Vấn đề:**
- FE khó xử lý nếu BE trả lỗi không thống nhất
- Chưa có error code mapping

**Giải pháp:**
1. **Unified Error Format**:
   ```json
   {
     "ok": false,
     "error": {
       "code": "TASK_NOT_FOUND",
       "message": "Task with ID 123 not found",
       "details": {},
       "traceId": "req_abc123"
     }
   }
   ```
2. **Error Code Mapping**:
   - Fixed error codes per domain
   - Document trong OpenAPI
   - FE error handler
3. **i18n Hints**:
   - Error codes có translation keys
   - FE có thể translate
   - Fallback to English

**Timeline:** 3-5 ngày  
**Dependencies:** None  
**Tests:**
- Test error format consistency
- Test error code mapping
- Test i18n

---

## 🟢 MỨC XANH (Nice-to-Have / Dài Hơi)

### 12. CQRS-lite cho Dashboard/Reports

**Timeline:** 14-21 ngày  
**Description:** Separate read/write models cho heavy domains (Dashboard, Reports)

---

### 13. Feature Flags server-driven

**Timeline:** 7-10 ngày  
**Description:** Unleash/GrowthBook integration cho gradual rollout

---

### 14. Supply-chain security

**Timeline:** 5-7 ngày  
**Description:** SBOM (Syft), Dependabot/Renovate, provenance (SLSA-lite)

---

### 15. Zero-downtime migration

**Timeline:** 10-14 ngày  
**Description:** Blue-green/canary + rule "migrate forward-compatible"

---

### 16. Frontend đề xuất nhanh

**Timeline:** Ongoing  
**Description:**
- React Query keys theo tenant
- Optimistic update + rollback cho Kanban move
- ErrorBoundary + retry/backoff chuẩn hoá
- A11y & i18n: axe checks trong Playwright
- Design tokens: đẩy token sang CSS vars dùng chung Blade/React

---

## 📋 CHECKLIST "LÀM NGAY" (2 Tuần)

### Tuần 1
- [ ] **Day 1-2**: Deprecate Unified/* → Tạo Api/V1/* thuần (Users, Subtasks, Comments, Attachments, Assignments)
- [ ] **Day 3-4**: Hardening Tenant-safety (GlobalScope verify, DB constraints, cache namespace)
- [ ] **Day 5**: OpenAPI contract enforcement (CI diff, FE type gen, response validation)

### Tuần 2
- [ ] **Day 6-7**: Idempotency audit (coverage, double-submit tests)
- [ ] **Day 8-9**: WebSocket hardening (channel auth, revoke, metrics)
- [ ] **Day 10**: Integration tests & documentation

---

## 📊 SUCCESS METRICS

### Mức ĐỎ (2 Tuần)
- ✅ 0 Unified/* controllers còn active
- ✅ 100% models có GlobalScope + DB constraints
- ✅ 100% write endpoints có idempotency
- ✅ OpenAPI CI gate hoạt động
- ✅ WebSocket có metrics & healthcheck

### Mức VÀNG (30-60 Ngày)
- ✅ Outbox processing < 5s p95
- ✅ 100% list endpoints dùng cursor pagination
- ✅ OpenTelemetry tracing hoạt động
- ✅ Media pipeline có virus scan + EXIF strip
- ✅ RBAC FE/BE sync 100%
- ✅ Error envelope standardized

---

## 🚨 RISKS & MITIGATION

### Risk 1: Breaking Changes khi deprecate Unified/*
**Mitigation:** 
- Deprecate warning 2 tuần trước khi xóa
- Monitor logs cho usage
- Có rollback plan

### Risk 2: Performance impact của GlobalScope
**Mitigation:**
- Test với large datasets
- Optimize indexes
- Monitor query performance

### Risk 3: OpenAPI spec không đầy đủ
**Mitigation:**
- Review từng endpoint
- Add annotations dần
- CI enforce completeness

---

## 📚 DOCUMENTATION

### Cần Update
- [ ] API documentation (OpenAPI spec)
- [ ] Architecture decision records (ADRs)
- [ ] Deployment guide
- [ ] Testing guide

---

**Last Updated:** 2025-01-20  
**Next Review:** Sau khi hoàn thành Mức ĐỎ (2 tuần)

