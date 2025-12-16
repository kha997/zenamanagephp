# ✅ Architecture Improvement Checklist

**Quick Reference:** Track progress on architecture improvements

---

## 🔴 PHASE 1: CRITICAL FOUNDATION (Weeks 1-2)

### 1.1 Complete Policy Coverage
- [ ] Verify all 11 policies exist and are complete
- [ ] Add tenant isolation checks to all policies
- [ ] Add permission checks to all policies
- [ ] Create policy unit tests
- [ ] Verify all routes use policies

### 1.2 Route Security Audit
- [ ] Audit `routes/web.php` - remove `withoutMiddleware(['auth'])`
- [ ] Audit `routes/api_v1.php` - add proper middleware
- [ ] Add `auth:sanctum` to all API routes
- [ ] Add `ability:tenant` to tenant-scoped routes
- [ ] Add `ability:admin` to admin routes
- [ ] Test unauthorized access (401/403)

### 1.3 Policy Tests
- [ ] `tests/Unit/Policies/ProjectPolicyTest.php`
- [ ] `tests/Unit/Policies/TaskPolicyTest.php`
- [ ] `tests/Unit/Policies/DocumentPolicyTest.php`
- [ ] `tests/Unit/Policies/ComponentPolicyTest.php`
- [ ] `tests/Unit/Policies/UserPolicyTest.php`
- [ ] Test tenant isolation in all policy tests
- [ ] Test role-based access in all policy tests

---

## 🟡 PHASE 2: MEDIUM PRIORITY (Weeks 3-6)

### 2.1 Media Pipeline Enhancement
- [ ] Install ClamAV or integrate VirusTotal API
- [ ] Create `ScanFileForVirusJob`
- [ ] Implement EXIF stripping
- [ ] Create `ProcessImageJob` (thumbnails, optimization)
- [ ] Implement signed URLs
- [ ] Configure CDN integration
- [ ] Create `MediaService` with full pipeline

### 2.2 Distributed Tracing (OpenTelemetry)
- [ ] Install OpenTelemetry PHP SDK
- [ ] Configure trace exporters
- [ ] Instrument HTTP requests/responses
- [ ] Instrument database queries
- [ ] Instrument queue jobs
- [ ] Create tracing dashboards

### 2.3 RBAC Sync FE/BE
- [ ] Add `x-abilities` to OpenAPI spec
- [ ] Update type generation script
- [ ] Generate permission types
- [ ] Update route guards
- [ ] Verify React uses `/api/v1/me/nav`

### 2.4 Search Indexing
- [ ] Install Meilisearch server
- [ ] Configure Laravel Scout
- [ ] Add `Searchable` trait to models
- [ ] Implement tenant isolation in search
- [ ] Create search API endpoint
- [ ] Add search analytics

---

## 🟢 PHASE 3: LONG-TERM (Weeks 7-12)

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
- [ ] Health check endpoints
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

## 📊 PROGRESS TRACKING

**Last Updated:** [Date]  
**Phase 1 Progress:** [X/3] tasks completed  
**Phase 2 Progress:** [X/4] tasks completed  
**Phase 3 Progress:** [X/5] tasks completed  
**Frontend Progress:** [X/6] tasks completed  
**Testing Progress:** [X/4] tasks completed

---

**See [ARCHITECTURE_REVIEW_AND_PLAN.md](ARCHITECTURE_REVIEW_AND_PLAN.md) for detailed implementation guide.**

