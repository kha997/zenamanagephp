# 📋 Remaining Tasks Summary

**Date:** January 19, 2025  
**Status:** Critical tasks identified and prioritized

---

## 🔴 PHASE 1: CRITICAL FOUNDATION (Weeks 1-2) - **URGENT**

### 1.1 Complete Policy Coverage ⚠️ **CRITICAL GAP**

**Current Status:** 4/15 policies fully implemented (26%)  
**Risk:** Security vulnerabilities, unauthorized access

**Remaining Work:**
- [ ] Verify and complete 11 policies:
  - [ ] `DocumentPolicy` - Verify completeness, add missing methods
  - [ ] `ComponentPolicy` - Verify completeness, add missing methods
  - [ ] `TeamPolicy` - Verify completeness, add missing methods
  - [ ] `NotificationPolicy` - Verify completeness, add missing methods
  - [ ] `ChangeRequestPolicy` - Verify completeness, add missing methods
  - [ ] `RfiPolicy` - Verify completeness, add missing methods
  - [ ] `QcPlanPolicy` - Verify completeness, add missing methods
  - [ ] `QcInspectionPolicy` - Verify completeness, add missing methods
  - [ ] `NcrPolicy` - Verify completeness, add missing methods
  - [ ] `TemplatePolicy` - Verify completeness, add missing methods
  - [ ] `InvitationPolicy` - Verify completeness, add missing methods

**Verification Tasks:**
- [ ] All policies enforce tenant isolation
- [ ] All policies check user permissions
- [ ] All policies have unit tests
- [ ] All routes protected by policies

**Estimated Effort:** 2-3 days

---

### 1.2 Route Security Audit ⚠️ **CRITICAL GAP**

**Current Status:** 11 routes still have `withoutMiddleware(['auth'])`  
**Risk:** Unauthorized access, security vulnerabilities

**Remaining Work:**
- [ ] Audit all routes in `routes/web.php`
- [ ] Audit all routes in `routes/api_v1.php`
- [ ] Remove all `withoutMiddleware(['auth'])` calls
- [ ] Add proper middleware stack:
  - [ ] `auth:sanctum` for API routes
  - [ ] `auth` for web routes
  - [ ] `ability:tenant` for tenant-scoped routes
  - [ ] `ability:admin` for admin routes
  - [ ] `tenant` middleware for tenant isolation

**Verification Tasks:**
- [ ] All routes require authentication
- [ ] All routes enforce tenant isolation
- [ ] All routes check permissions via Policies/Gates
- [ ] Test unauthorized access returns 401/403

**Estimated Effort:** 1-2 days

---

### 1.3 Policy Tests ⚠️ **CRITICAL GAP**

**Current Status:** 0% test coverage for policies  
**Risk:** Security regressions, undetected bugs

**Remaining Work:**
- [ ] Create `tests/Unit/Policies/ProjectPolicyTest.php`
- [ ] Create `tests/Unit/Policies/TaskPolicyTest.php`
- [ ] Create `tests/Unit/Policies/DocumentPolicyTest.php`
- [ ] Create `tests/Unit/Policies/ComponentPolicyTest.php`
- [ ] Create `tests/Unit/Policies/TeamPolicyTest.php`
- [ ] Create `tests/Unit/Policies/NotificationPolicyTest.php`
- [ ] Create `tests/Unit/Policies/ChangeRequestPolicyTest.php`
- [ ] Create `tests/Unit/Policies/RfiPolicyTest.php`
- [ ] Create `tests/Unit/Policies/QcPlanPolicyTest.php`
- [ ] Create `tests/Unit/Policies/QcInspectionPolicyTest.php`
- [ ] Create `tests/Unit/Policies/NcrPolicyTest.php`
- [ ] Create `tests/Unit/Policies/TemplatePolicyTest.php`
- [ ] Create `tests/Unit/Policies/InvitationPolicyTest.php`

**Test Requirements:**
- [ ] Test tenant isolation (tenant A cannot access tenant B's data)
- [ ] Test role-based access (PM can create, Member can view only)
- [ ] Test permission checks (view, create, update, delete)
- [ ] Test edge cases (soft-deleted records, inactive users)

**Estimated Effort:** 3-4 days

---

## 🟡 PHASE 2: CORE ENHANCEMENTS (Weeks 3-6) - **MEDIUM PRIORITY**

### 2.1 Media Pipeline Enhancement

**Current Status:** Basic upload exists, needs full pipeline  
**Remaining Work:**
- [ ] Install ClamAV or integrate VirusTotal API
- [ ] Create `ScanFileForVirusJob` (already exists, verify)
- [ ] Implement EXIF stripping (already exists, verify)
- [ ] Create `ProcessImageJob` for thumbnails/optimization (already exists, verify)
- [ ] Implement signed URLs (already exists, verify)
- [ ] Configure CDN integration (needs configuration)
- [ ] Verify `MediaService` has full pipeline

**Estimated Effort:** 2-3 days

---

### 2.2 Distributed Tracing (OpenTelemetry)

**Current Status:** Basic tracing implemented, needs OpenTelemetry integration  
**Remaining Work:**
- [ ] Install OpenTelemetry PHP SDK (`composer require open-telemetry/opentelemetry`)
- [ ] Configure trace exporters (Jaeger, Zipkin, OTLP)
- [ ] Enhance `TracingMiddleware` with OpenTelemetry
- [ ] Instrument HTTP requests/responses (already done)
- [ ] Instrument database queries
- [ ] Instrument queue jobs
- [ ] Create tracing dashboards (P95/P99 latency, error rates)

**Estimated Effort:** 3-4 days

---

### 2.3 RBAC Sync FE/BE

**Current Status:** Partial implementation  
**Remaining Work:**
- [ ] Add `x-abilities` to OpenAPI spec for each endpoint (already started)
- [ ] Update type generation script (already exists, verify)
- [ ] Generate permission types from OpenAPI
- [ ] Update React route guards to use generated types
- [ ] Verify React uses `/api/v1/me/nav` for navigation

**Estimated Effort:** 2-3 days

---

### 2.4 Search Indexing (Meilisearch)

**Current Status:** Infrastructure in place, needs verification  
**Remaining Work:**
- [ ] Verify Meilisearch server is installed and running
- [ ] Verify Laravel Scout is configured correctly
- [ ] Verify `Searchable` trait is added to all models (Project, Task, Document)
- [ ] Verify tenant isolation in search queries
- [ ] Create search API endpoint (if not exists)
- [ ] Add search analytics
- [ ] Test search performance

**Estimated Effort:** 2-3 days

---

## 🟢 PHASE 3: LONG-TERM (Weeks 7-12) - **LOW PRIORITY**

### 3.1 CQRS-lite
- [ ] Identify heavy read/write domains
- [ ] Create read models (projections)
- [ ] Implement event sourcing
- [ ] Separate read/write databases (optional)

### 3.2 Sharding Strategy
- [ ] Design sharding strategy
- [ ] Implement shard routing
- [ ] Create data migration tools
- [ ] Support cross-shard queries

### 3.3 Zero-Downtime Deployment
- [ ] Setup blue-green deployment
- [ ] Database migration strategy
- [ ] Health check endpoints (already exists)
- [ ] Rollback procedures

### 3.4 SSO/OIDC
- [ ] OIDC provider integration
- [ ] SAML 2.0 support
- [ ] JWT token validation
- [ ] User provisioning

### 3.5 Feature Flags
- [ ] Database-driven feature flags
- [ ] Admin UI for flag management
- [ ] A/B testing support
- [ ] Rollout strategies

---

## 🎨 FRONTEND IMPROVEMENTS

### React Enhancements
- [ ] Create `ErrorBoundary` component
- [ ] Implement Suspense for lazy routes
- [ ] Update React Query keys to include tenant_id
- [ ] Standardize design tokens
- [ ] A11y audit and fixes
- [ ] i18n coverage (English/Vietnamese)

---

## 🧪 TESTING IMPROVEMENTS

### E2E Tests
- [ ] Tenant isolation E2E tests
- [ ] A11y E2E tests
- [ ] Visual regression tests
- [ ] CI integration for E2E tests

---

## 📊 PRIORITY SUMMARY

### 🔴 **IMMEDIATE (Do First)**
1. **Policy Coverage** (2-3 days) - Security risk
2. **Route Security Audit** (1-2 days) - Security risk
3. **Policy Tests** (3-4 days) - Quality assurance

**Total: 6-9 days**

### 🟡 **NEXT (After Critical)**
4. Media Pipeline Enhancement (2-3 days)
5. Distributed Tracing (3-4 days)
6. RBAC Sync FE/BE (2-3 days)
7. Search Indexing (2-3 days)

**Total: 9-13 days**

### 🟢 **LATER (When Needed)**
- CQRS-lite, Sharding, Zero-downtime, SSO/OIDC, Feature Flags
- Frontend improvements
- Testing improvements

---

## ✅ **COMPLETED (Infrastructure Ready)**

- ✅ Unified Controllers separated
- ✅ Global Tenant Scope implemented
- ✅ OpenAPI v1 auto-generation + PR Gate
- ✅ Idempotency-Key for write operations
- ✅ Cache prefixing + invalidation tests
- ✅ Security headers & CORS audit
- ✅ WebSocket auth hardening
- ✅ Transactional Outbox pattern
- ✅ Cursor-based pagination
- ✅ Production setup guide and scripts

---

**Next Steps:** Start with Phase 1 (Critical Foundation) tasks to address security gaps.

