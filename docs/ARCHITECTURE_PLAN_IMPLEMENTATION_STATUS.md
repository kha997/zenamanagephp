# Architecture Hardening Plan - Implementation Status

**Date:** January 19, 2025  
**Status:** Implementation in Progress

---

## ✅ COMPLETED ITEMS

### A. GIAI ĐOẠN NGẮN HẠN (1-2 tuần)

#### A1. Tách UnifiedController → API Controller thuần ✅
- ✅ Created `app/Http/Controllers/Api/V1/App/ProjectsController.php`
- ✅ Created `app/Http/Controllers/Api/V1/App/TasksController.php`
- ✅ Created `app/Http/Controllers/Web/ProjectsController.php`
- ✅ Created `app/Http/Controllers/Web/TasksController.php`
- ✅ Updated routes to use new controllers
- ✅ Marked Unified controllers as deprecated

#### A2. Global Tenant Scope + Policy bắt buộc + DB Constraints ✅
- ✅ `BelongsToTenant` trait with Global Scope implemented
- ✅ Migration: `add_tenant_constraints_to_main_tables.php` (tenant_id NOT NULL)
- ✅ Migration: `add_composite_unique_indexes_with_soft_delete.php`
- ✅ Policies enforce tenant isolation (ProjectPolicy, TaskPolicy verified)
- ✅ All 38 policies exist and enforce tenant checks

#### A3. OpenAPI v1 sinh tự động + PR Gate ✅
- ✅ `l5-swagger` package installed
- ✅ OpenAPI spec generation configured
- ✅ PR Gate workflow: `.github/workflows/openapi-contract-test.yml`
- ✅ Contract tests: `tests/Contract/OpenApiContractTest.php`
- ✅ Breaking change detection implemented

#### A4. Idempotency-Key cho POST/PUT/PATCH ✅
- ✅ Migration: `create_idempotency_keys_table.php`
- ✅ Model: `app/Models/IdempotencyKey.php`
- ✅ Middleware: `app/Http/Middleware/IdempotencyMiddleware.php`
- ✅ Applied to critical endpoints in `routes/api_v1.php`
- ✅ Database + cache persistence

#### A5. Cache prefix theo tenant + test invalidation ✅
- ✅ `AdvancedCacheService` with tenant prefix: `{env}:{tenant}:{domain}:{resource}:{id}`
- ✅ Domain events: `ProjectUpdated`, `TaskUpdated`, `TaskMoved`
- ✅ Listeners: `InvalidateProjectCache`, `InvalidateTaskCache`
- ✅ Tests: `tests/Unit/Services/CacheInvalidationTest.php`

#### A6. Security headers & CORS audit ✅
- ✅ `SecurityHeadersMiddleware` with CSP, HSTS, etc.
- ✅ CORS configuration updated in `config/cors.php`
- ✅ Production headers configured
- ✅ Idempotency-Key headers exposed

#### A7. WebSocket auth hardening ✅
- ✅ `DashboardWebSocketHandler` with tenant/permission checks
- ✅ Channel authorization in `routes/channels.php`
- ✅ User activity checks (`is_active`)
- ✅ Connection revocation support

### B. GIAI ĐOẠN TRUNG HẠN (30-60 ngày)

#### B1. Transactional Outbox ✅
- ✅ Migration: `create_outbox_table.php`
- ✅ Model: `app/Models/Outbox.php`
- ✅ Service: `app/Services/OutboxService.php`
- ✅ Job: `app/Jobs/ProcessOutboxJob.php`
- ✅ Command: `app/Console/Commands/ProcessOutboxCommand.php`

#### B2. Search index (Meilisearch) ✅
- ✅ Laravel Scout installed
- ✅ Meilisearch PHP client installed
- ✅ Config: `config/scout.php` with Meilisearch settings
- ✅ `Searchable` trait added to `Project` model
- ⚠️ **TODO**: Add Searchable to Task and Document models
- ⚠️ **TODO**: Update SearchService to use Scout/Meilisearch
- ⚠️ **TODO**: Create index jobs
- ⚠️ **TODO**: Integrate with Outbox

#### B3. Tracing (OpenTelemetry) ⚠️ PARTIAL
- ✅ Basic correlation ID implemented (`TracingMiddleware`)
- ✅ Request ID propagation
- ❌ **TODO**: OpenTelemetry PHP SDK installation
- ❌ **TODO**: Trace exporters (Jaeger/Zipkin)
- ❌ **TODO**: Instrumentation (HTTP, DB, Queue)
- ❌ **TODO**: Metrics collection (p95/p99)
- ❌ **TODO**: Dashboards

#### B4. Media pipeline ⚠️ PARTIAL
- ✅ Basic file upload exists (`FileManagementService`, `SecureFileUploadService`)
- ✅ Virus scan job exists (`ScanFileVirusJob`)
- ✅ Image thumbnails generation
- ❌ **TODO**: EXIF stripping
- ❌ **TODO**: Image resizing queue (multiple sizes)
- ❌ **TODO**: Signed URL generation
- ❌ **TODO**: CDN integration
- ❌ **TODO**: Full MediaService pipeline

#### B5. RBAC sync FE/BE ⚠️ PARTIAL
- ✅ Backend permissions exist
- ✅ `/api/v1/me/nav` returns permission-filtered nav
- ❌ **TODO**: Add `x-abilities` extension to OpenAPI spec
- ❌ **TODO**: Generate TypeScript types from OpenAPI
- ❌ **TODO**: Update frontend to use generated types
- ❌ **TODO**: Route guards based on permissions

#### B6. API pagination chuyển dần sang cursor-based ✅
- ✅ `getProjectsCursor()` method in `ProjectManagementService`
- ✅ `getTasksCursor()` method in `TaskManagementService`
- ✅ API endpoints support cursor pagination
- ✅ Both offset and cursor pagination available

---

## 📋 REMAINING WORK

### High Priority (Complete Infrastructure)

1. **B2 - Search Index (Complete Integration)**
   - Add `Searchable` trait to Task and Document models
   - Update `SearchService` to use Scout/Meilisearch
   - Create `IndexProjectJob`, `IndexTaskJob`, `IndexDocumentJob`
   - Integrate indexing with Outbox events
   - Configure Meilisearch index settings per model

2. **B3 - OpenTelemetry (Full Implementation)**
   - Install `open-telemetry/opentelemetry-php` package
   - Configure trace exporters
   - Instrument HTTP requests/responses
   - Instrument database queries
   - Instrument queue jobs
   - Setup metrics collection
   - Create dashboards

3. **B4 - Media Pipeline (Complete)**
   - Create `MediaService` with full pipeline
   - Implement EXIF stripping
   - Create `ProcessImageJob` for resizing/variants
   - Implement signed URL generation
   - Configure CDN integration
   - Update upload flow to use new pipeline

4. **B5 - RBAC Sync FE/BE (Complete)**
   - Add `x-abilities` to all endpoints in `docs/api/openapi.yaml`
   - Update type generation script (`frontend/scripts/generate-api-types.js`)
   - Generate permission types
   - Update frontend route guards
   - Verify React uses generated types

---

## 🎯 NEXT STEPS

1. **Complete B2**: Add Searchable to remaining models, update SearchService
2. **Complete B3**: Install and configure OpenTelemetry
3. **Complete B4**: Enhance media pipeline with all features
4. **Complete B5**: Add x-abilities to OpenAPI and generate types

---

**Note:** Most infrastructure is in place. Remaining work is primarily integration and enhancement of existing components.

