# 🏗️ Architecture Review & Improvement Plan

**Date:** January 19, 2025  
**Status:** Comprehensive Review & Prioritized Action Plan  
**Purpose:** Address identified weaknesses and scale system safely

---

## 📊 EXECUTIVE SUMMARY

### Current State Assessment

**✅ Completed (Immediate Phase - 1-2 weeks):**
- ✅ Unified Controllers separated (API vs Web)
- ✅ Global Tenant Scope implemented (BelongsToTenant trait)
- ✅ OpenAPI v1 auto-generation + PR Gate
- ✅ Idempotency-Key for write operations
- ✅ Cache prefixing + invalidation tests
- ✅ Security headers & CORS audit
- ✅ WebSocket auth hardening
- ✅ Transactional Outbox pattern
- ✅ Cursor-based pagination

**⚠️ In Progress (30-60 days):**
- ⚠️ Search indexing (Meilisearch/Elastic)
- ⚠️ Tracing + correlationId (basic implemented, needs OpenTelemetry)
- ⚠️ RBAC sync FE/BE (partial)
- ⚠️ Media pipeline (basic upload exists, needs full pipeline)

**❌ Not Started (90+ days):**
- ❌ CQRS-lite
- ❌ Sharding strategy
- ❌ Zero-downtime deploy
- ❌ SSO/OIDC
- ❌ Feature flags

---

## 🎯 PRIORITIZED ACTION PLAN

### 🔴 PHASE 1: CRITICAL FOUNDATION (Weeks 1-2) - **IMMEDIATE**

#### 1.1 Complete Policy Coverage ⚠️ **CRITICAL GAP**

**Current Status:** 4/15 policies implemented (26%)  
**Risk:** Security vulnerabilities, unauthorized access

**Action Items:**
- [ ] Complete remaining 11 policies:
  - [ ] `DocumentPolicy` - ✅ Exists, verify completeness
  - [ ] `ComponentPolicy` - ✅ Exists, verify completeness
  - [ ] `TeamPolicy` - ✅ Exists, verify completeness
  - [ ] `NotificationPolicy` - ✅ Exists, verify completeness
  - [ ] `ChangeRequestPolicy` - ✅ Exists, verify completeness
  - [ ] `RfiPolicy` - ✅ Exists, verify completeness
  - [ ] `QcPlanPolicy` - ✅ Exists, verify completeness
  - [ ] `QcInspectionPolicy` - ✅ Exists, verify completeness
  - [ ] `NcrPolicy` - ✅ Exists, verify completeness
  - [ ] `TemplatePolicy` - ✅ Exists, verify completeness
  - [ ] `InvitationPolicy` - ✅ Exists, verify completeness

**Verification:**
- [ ] All policies enforce tenant isolation
- [ ] All policies check user permissions
- [ ] All policies have unit tests
- [ ] All routes protected by policies

**Files:**
- `app/Policies/*.php` - Verify all policies exist and are complete
- `tests/Unit/Policies/*Test.php` - Create/verify policy tests
- `app/Providers/AuthServiceProvider.php` - Verify policy registration

**Estimated Effort:** 2-3 days

---

#### 1.2 Route Security Audit ⚠️ **CRITICAL GAP**

**Current Status:** 11 routes still have `withoutMiddleware(['auth'])`  
**Risk:** Unauthorized access, security vulnerabilities

**Action Items:**
- [ ] Audit all routes in `routes/web.php` and `routes/api_v1.php`
- [ ] Remove all `withoutMiddleware(['auth'])` calls
- [ ] Add proper middleware stack:
  - [ ] `auth:sanctum` for API routes
  - [ ] `auth` for web routes
  - [ ] `ability:tenant` for tenant-scoped routes
  - [ ] `ability:admin` for admin routes
  - [ ] `tenant` middleware for tenant isolation

**Verification:**
- [ ] All routes require authentication
- [ ] All routes enforce tenant isolation
- [ ] All routes check permissions via Policies/Gates
- [ ] Test unauthorized access returns 401/403

**Files:**
- `routes/web.php` - Audit and fix routes
- `routes/api_v1.php` - Audit and fix routes
- `app/Http/Middleware/*.php` - Verify middleware stack

**Estimated Effort:** 1-2 days

---

#### 1.3 Policy Tests ⚠️ **CRITICAL GAP**

**Current Status:** 0/5 policy test files (0%)  
**Risk:** Security regressions, unauthorized access

**Action Items:**
- [ ] Create policy test files:
  - [ ] `tests/Unit/Policies/ProjectPolicyTest.php`
  - [ ] `tests/Unit/Policies/TaskPolicyTest.php`
  - [ ] `tests/Unit/Policies/DocumentPolicyTest.php`
  - [ ] `tests/Unit/Policies/ComponentPolicyTest.php`
  - [ ] `tests/Unit/Policies/UserPolicyTest.php`

**Test Coverage:**
- [ ] Tenant isolation (user from tenant A cannot access tenant B data)
- [ ] Role-based access (PM can create, Member can view)
- [ ] Permission inheritance
- [ ] Edge cases (soft-deleted records, archived projects)

**Files:**
- `tests/Unit/Policies/*Test.php` - Create policy tests
- `tests/Helpers/TestDataSeeder.php` - Use for test data

**Estimated Effort:** 2-3 days

---

### 🟡 PHASE 2: MEDIUM PRIORITY (Weeks 3-6) - **30-60 DAYS**

#### 2.1 Media Pipeline Enhancement ⚠️ **IN PROGRESS**

**Current Status:** Basic upload exists, needs full pipeline  
**Risk:** Security vulnerabilities, performance issues

**Action Items:**
- [ ] **Virus Scanning:**
  - [ ] Integrate ClamAV or cloud service (e.g., VirusTotal API)
  - [ ] Queue-based scanning for large files
  - [ ] Quarantine infected files
  - [ ] Log security events

- [ ] **EXIF Stripping:**
  - [ ] Strip EXIF data from images on upload
  - [ ] Preserve essential metadata (dimensions, format)
  - [ ] Configurable per tenant (GDPR compliance)

- [ ] **Image Resizing Queue:**
  - [ ] Generate thumbnails (multiple sizes)
  - [ ] Optimize images (WebP conversion)
  - [ ] Lazy loading support
  - [ ] CDN integration

- [ ] **Signed URLs:**
  - [ ] Generate time-limited signed URLs
  - [ ] Support for private file access
  - [ ] Revocation mechanism

- [ ] **CDN Integration:**
  - [ ] Configure CDN (CloudFlare, AWS CloudFront)
  - [ ] Asset optimization
  - [ ] Cache invalidation

**Files:**
- `app/Services/MediaService.php` - Create new service
- `app/Jobs/ScanFileForVirusJob.php` - Virus scanning job
- `app/Jobs/ProcessImageJob.php` - Image processing job
- `app/Http/Controllers/Api/V1/App/MediaController.php` - Media API
- `database/migrations/*_create_media_processing_queue.php` - Queue table

**Estimated Effort:** 5-7 days

---

#### 2.2 Distributed Tracing (OpenTelemetry) ⚠️ **PARTIAL**

**Current Status:** Basic correlation ID implemented, needs OpenTelemetry  
**Risk:** Limited observability, difficult debugging

**Action Items:**
- [ ] **OpenTelemetry Integration:**
  - [ ] Install OpenTelemetry PHP SDK
  - [ ] Configure trace exporters (Jaeger, Zipkin, or cloud service)
  - [ ] Instrument HTTP requests/responses
  - [ ] Instrument database queries
  - [ ] Instrument queue jobs
  - [ ] Instrument external API calls

- [ ] **Trace Context Propagation:**
  - [ ] Propagate trace context across services
  - [ ] Support for distributed systems
  - [ ] Frontend trace context injection

- [ ] **Metrics Collection:**
  - [ ] Request latency (p50, p95, p99)
  - [ ] Error rates
  - [ ] Database query performance
  - [ ] Queue job duration

- [ ] **Dashboards:**
  - [ ] Service map visualization
  - [ ] Trace search and filtering
  - [ ] Performance metrics dashboard

**Files:**
- `config/opentelemetry.php` - OpenTelemetry configuration
- `app/Http/Middleware/TracingMiddleware.php` - Enhance with OpenTelemetry
- `app/Services/TracingService.php` - Tracing service
- `app/Providers/TracingServiceProvider.php` - Service provider

**Estimated Effort:** 4-5 days

---

#### 2.3 RBAC Sync FE/BE ⚠️ **PARTIAL**

**Current Status:** Backend permissions exist, FE types not generated  
**Risk:** FE/BE permission drift, type safety issues

**Action Items:**
- [ ] **OpenAPI x-abilities Extension:**
  - [ ] Add `x-abilities` to OpenAPI spec for each endpoint
  - [ ] Document required permissions per route
  - [ ] Generate TypeScript types from OpenAPI

- [ ] **Frontend Type Generation:**
  - [ ] Update `npm run generate:api-types` to include permissions
  - [ ] Generate permission types (e.g., `Permission`, `Ability`)
  - [ ] Generate route permission mappings

- [ ] **Frontend Permission Checks:**
  - [ ] Use generated types for permission checks
  - [ ] Route guards based on permissions
  - [ ] UI component visibility based on permissions

- [ ] **Backend-Driven Navigation:**
  - [ ] `/api/v1/me/nav` already returns permission-filtered nav
  - [ ] Verify React uses this endpoint
  - [ ] Remove hardcoded navigation items

**Files:**
- `docs/api/openapi.yaml` - Add x-abilities to endpoints
- `frontend/scripts/generate-api-types.js` - Update type generation
- `frontend/src/shared/types/permissions.ts` - Generated permission types
- `frontend/src/router/guards.ts` - Permission-based route guards

**Estimated Effort:** 3-4 days

---

#### 2.4 Search Indexing (Meilisearch/Elastic) ⚠️ **NOT STARTED**

**Current Status:** MySQL full-text search only  
**Risk:** Poor search performance, limited search features

**Action Items:**
- [ ] **Meilisearch Integration:**
  - [ ] Install Meilisearch server
  - [ ] Configure Laravel Scout with Meilisearch driver
  - [ ] Index models (Projects, Tasks, Documents, Users)
  - [ ] Tenant isolation in search index
  - [ ] Real-time index updates

- [ ] **Search Features:**
  - [ ] Full-text search with typo tolerance
  - [ ] Faceted search (filter by status, priority, etc.)
  - [ ] Search ranking and relevance
  - [ ] Search analytics

- [ ] **Background Indexing:**
  - [ ] Queue-based indexing for bulk operations
  - [ ] Incremental index updates
  - [ ] Index health monitoring

**Files:**
- `config/scout.php` - Scout configuration
- `app/Models/Project.php` - Add `Searchable` trait
- `app/Models/Task.php` - Add `Searchable` trait
- `app/Jobs/IndexModelJob.php` - Indexing job
- `app/Http/Controllers/Api/V1/App/SearchController.php` - Search API

**Estimated Effort:** 4-5 days

---

### 🟢 PHASE 3: LONG-TERM (Weeks 7-12) - **90+ DAYS**

#### 3.1 CQRS-lite Pattern

**Purpose:** Separate read/write models for heavy domains

**Action Items:**
- [ ] Identify heavy read/write domains (e.g., Dashboard, Reports)
- [ ] Create read models (projections)
- [ ] Implement event sourcing for write models
- [ ] Separate read/write databases (optional)

**Estimated Effort:** 10-14 days

---

#### 3.2 Sharding Strategy

**Purpose:** Scale large tenants across multiple databases

**Action Items:**
- [ ] Design sharding strategy (by tenant_id hash)
- [ ] Implement shard routing
- [ ] Data migration tools
- [ ] Cross-shard query support

**Estimated Effort:** 14-21 days

---

#### 3.3 Zero-Downtime Deployment

**Purpose:** Blue-green or canary deployments

**Action Items:**
- [ ] Blue-green deployment setup
- [ ] Database migration strategy
- [ ] Health check endpoints
- [ ] Rollback procedures

**Estimated Effort:** 7-10 days

---

#### 3.4 SSO/OIDC Integration

**Purpose:** Enterprise authentication

**Action Items:**
- [ ] OIDC provider integration
- [ ] SAML 2.0 support
- [ ] JWT token validation
- [ ] User provisioning

**Estimated Effort:** 10-14 days

---

#### 3.5 Feature Flags

**Purpose:** Gradual feature rollout

**Action Items:**
- [ ] Database-driven feature flags
- [ ] Admin UI for flag management
- [ ] A/B testing support
- [ ] Rollout strategies

**Estimated Effort:** 5-7 days

---

## 🎨 FRONTEND IMPROVEMENTS

### React Enhancements

#### Error Boundary + Suspense
- [ ] Create `ErrorBoundary` component
- [ ] Implement Suspense for lazy-loaded routes
- [ ] Error recovery mechanisms
- [ ] User-friendly error messages

**Files:**
- `frontend/src/components/shared/ErrorBoundary.tsx`
- `frontend/src/router/index.tsx` - Add Suspense

**Estimated Effort:** 2-3 days

---

#### React Query Keys by Tenant
- [ ] Update query keys to include tenant_id
- [ ] Tenant-scoped cache invalidation
- [ ] Prevent cross-tenant cache pollution

**Files:**
- `frontend/src/hooks/useProjects.ts` - Update query keys
- `frontend/src/hooks/useTasks.ts` - Update query keys

**Estimated Effort:** 1-2 days

---

#### Design Tokens
- [ ] Audit existing design tokens
- [ ] Standardize token naming
- [ ] Ensure Blade and React use same tokens
- [ ] Document token usage

**Files:**
- `frontend/src/shared/tokens/*.ts` - Design tokens
- `resources/css/app.css` - CSS variables

**Estimated Effort:** 2-3 days

---

#### A11y/I18n Improvements
- [ ] WCAG 2.1 AA compliance audit
- [ ] Keyboard navigation testing
- [ ] Screen reader testing
- [ ] i18n coverage (English/Vietnamese)
- [ ] RTL support (if needed)

**Estimated Effort:** 5-7 days

---

## 🧪 TESTING IMPROVEMENTS

### E2E Test Coverage

#### Tenant Isolation E2E
- [ ] Test tenant A cannot access tenant B data
- [ ] Test tenant switching
- [ ] Test cross-tenant API calls

**Files:**
- `tests/e2e/tenant-isolation/*.spec.ts`

**Estimated Effort:** 2-3 days

---

#### A11y E2E Tests
- [ ] Keyboard navigation tests
- [ ] Screen reader compatibility
- [ ] Color contrast validation
- [ ] Focus management

**Files:**
- `tests/e2e/a11y/*.spec.ts`

**Estimated Effort:** 3-4 days

---

#### Visual Regression Tests
- [ ] Setup Playwright visual comparison
- [ ] Baseline screenshots
- [ ] CI integration
- [ ] Review workflow

**Files:**
- `tests/e2e/visual/*.spec.ts`

**Estimated Effort:** 3-4 days

---

## 📋 IMPLEMENTATION CHECKLIST

### Week 1-2: Critical Foundation
- [ ] Complete Policy Coverage (1.1)
- [ ] Route Security Audit (1.2)
- [ ] Policy Tests (1.3)

### Week 3-4: Medium Priority
- [ ] Media Pipeline Enhancement (2.1) - Start
- [ ] Distributed Tracing (2.2) - Start
- [ ] RBAC Sync FE/BE (2.3)

### Week 5-6: Medium Priority (Continued)
- [ ] Media Pipeline Enhancement (2.1) - Complete
- [ ] Distributed Tracing (2.2) - Complete
- [ ] Search Indexing (2.4) - Start

### Week 7-12: Long-Term
- [ ] CQRS-lite (3.1)
- [ ] Sharding Strategy (3.2)
- [ ] Zero-Downtime Deployment (3.3)
- [ ] SSO/OIDC (3.4)
- [ ] Feature Flags (3.5)

### Ongoing: Frontend & Testing
- [ ] React Error Boundary + Suspense
- [ ] React Query Keys by Tenant
- [ ] Design Tokens Standardization
- [ ] A11y/I18n Improvements
- [ ] E2E Test Coverage

---

## 🎯 SUCCESS METRICS

### Security
- ✅ 100% policy coverage
- ✅ 0 routes without authentication
- ✅ 100% policy test coverage
- ✅ 0 tenant isolation violations

### Performance
- ✅ API p95 < 300ms
- ✅ Page p95 < 500ms
- ✅ Search response < 200ms
- ✅ Media processing < 5s

### Observability
- ✅ 100% request tracing
- ✅ Distributed trace correlation
- ✅ Error rate < 0.1%
- ✅ SLO compliance > 99.9%

### Quality
- ✅ E2E test coverage > 80%
- ✅ A11y compliance (WCAG 2.1 AA)
- ✅ Visual regression coverage
- ✅ Type safety (TypeScript strict mode)

---

## 🚨 RISKS & MITIGATION

### High Risk
1. **Policy Coverage Gap** → Security vulnerabilities
   - **Mitigation:** Complete Phase 1.1 immediately

2. **Route Security Issues** → Unauthorized access
   - **Mitigation:** Complete Phase 1.2 immediately

3. **Media Pipeline Security** → Virus/malware uploads
   - **Mitigation:** Implement virus scanning (Phase 2.1)

### Medium Risk
1. **Observability Gaps** → Difficult debugging
   - **Mitigation:** Implement OpenTelemetry (Phase 2.2)

2. **Search Performance** → Poor user experience
   - **Mitigation:** Implement Meilisearch (Phase 2.4)

### Low Risk
1. **Feature Flags** → Limited rollout control
   - **Mitigation:** Implement in Phase 3.5

---

## 📚 REFERENCES

- [Complete System Documentation](COMPLETE_SYSTEM_DOCUMENTATION.md)
- [OpenAPI Specification](docs/api/openapi.yaml)
- [Security Review](docs/SECURITY_REVIEW.md)
- [Performance Benchmarks](docs/PERFORMANCE_BENCHMARKS.md)
- [Testing Strategy](TEST_SUITE_SUMMARY.md)

---

**Last Updated:** January 19, 2025  
**Next Review:** February 2, 2025 (after Phase 1 completion)

