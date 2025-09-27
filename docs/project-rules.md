# ZENAMANAGE PROJECT RULES
## Source of Truth - Architectural Foundation

### 🎯 **PROJECT OVERVIEW**
ZenaManage is a Laravel-based multi-tenant project management system with strict architectural principles, clear separation between UI and API layers, and comprehensive quality standards.

---

## 🏗️ **1. ARCHITECTURE COMPLIANCE**

### **1.1 Core Principles**
- **UI renders only** — all business logic lives in the API
- **Web routes**: session auth + tenant scope only
- **API routes**: token auth:sanctum + ability (admin/tenant)
- **No side-effects** in UI routes - all writes via API
- **Clear separation**: `/admin/*` (system-wide) ≠ `/app/*` (tenant-scoped)

### **1.2 Route Architecture**
```
/admin/*     - System-wide administration (web+auth+admin)
/app/*       - Tenant-scoped application (web+auth+tenant)
/_debug/*    - Debug routes (DebugGate middleware)
/api/v1/*    - REST API with error envelope
```

### **1.3 Middleware Stack**
- **Web Routes**: `web`, `auth`, `tenant.isolation`, `rbac:admin|tenant`
- **API Routes**: `auth:sanctum`, `ability:admin|tenant`
- **Debug Routes**: `DebugGate` (env+IP restrictions)

---

## 📝 **2. NAMING CONVENTIONS**

### **2.1 Routes**
- **Web**: kebab-case (`/app/projects`, `/admin/users`)
- **API**: kebab-case with version (`/api/v1/projects`)

### **2.2 Files & Folders**
- **PHP Classes**: PascalCase.php + proper namespace
- **Blade Views**: kebab-case.blade.php in `resources/views/admin|app/`
- **React Components**: PascalCase.tsx
- **Hooks**: useX.ts
- **Utils**: camelCase.ts

### **2.3 Database**
- **Tables**: snake_case
- **Columns**: snake_case
- **Foreign Keys**: Required with proper indexes
- **Composite Indexes**: `(tenant_id, foreign_key)` for hot tables

---

## 🎨 **3. UX/UI DESIGN STANDARDS**

### **3.1 Universal Page Frame**
```
Header (fixed) → Global Navigation → Page Navigation → 
KPI Strip (1-2 rows) → Alert Bar → Main Content → Activity
```

### **3.2 Layout Components**
- **Header**: Logo, greeting, avatar, notifications, theme toggle
- **Global Nav**: Role-aware navigation (Admin vs Tenant)
- **Page Nav**: Breadcrumbs, local tabs, primary actions
- **KPI Strip**: 4-8 cards, tappable deep links, 60s cache
- **Alert Bar**: Up to 3 critical alerts with actions
- **Main Content**: Lists/forms with smart search and filters

### **3.3 Mobile-First Design**
- **No Hamburger Menu**: Always visible navigation
- **Responsive Breakpoints**: Adapt layout, maintain visibility
- **Touch Targets**: Minimum 44px
- **KPI Stacking**: 2-per-row on mobile
- **FAB**: Floating action button for primary actions

### **3.4 Accessibility (WCAG 2.1 AA)**
- **Keyboard Navigation**: All elements tabbable
- **Screen Reader**: Proper ARIA labels
- **Color Contrast**: 4.5:1 minimum
- **Focus Indicators**: Visible focus states

---

## 🔒 **4. SECURITY & RBAC**

### **4.1 Multi-Tenant Isolation**
- **Mandatory**: Every query must filter by `tenant_id`
- **Enforcement**: At repository/service layer
- **Testing**: Explicit tests to prove tenant A cannot read B
- **Indexes**: Composite indexes on `(tenant_id, foreign_key)`

### **4.2 Role-Based Access Control**
- **Roles**: super_admin, PM, Member, Client
- **Admin API**: `auth:sanctum + ability:admin`
- **Tenant API**: `auth:sanctum + ability:tenant`
- **Web Routes**: `rbac:admin|tenant` middleware

### **4.3 Security Headers**
- **CSP**: Content Security Policy
- **CORS**: Cross-Origin Resource Sharing
- **HSTS**: HTTP Strict Transport Security
- **Secure Cookies**: HttpOnly, Secure, SameSite

---

## ⚡ **5. PERFORMANCE STANDARDS**

### **5.1 Performance Budgets**
- **Page p95**: < 500ms
- **API p95**: < 300ms
- **KPI Cache**: 60s per tenant
- **Smart Search**: Cached per query + tenant for 60s

### **5.2 Optimization Strategies**
- **N+1 Prevention**: Eager loading, projections
- **Pagination**: Stable sort (id/created_at)
- **Caching**: KPI precompute, query results
- **Database**: Composite indexes, query optimization

---

## 🚨 **6. ERROR HANDLING**

### **6.1 Error Envelope (i18n-ready)**
```json
{
  "error": {
    "id": "req_7f1a",
    "code": "E001.INVALID_INPUT",
    "message": "Invalid input",
    "details": {}
  }
}
```

### **6.2 HTTP Status Codes**
- **400**: Bad Request
- **401**: Unauthorized
- **403**: Forbidden
- **404**: Not Found
- **409**: Conflict
- **422**: Validation Error
- **429**: Rate Limited (with Retry-After)
- **500**: Internal Server Error
- **503**: Service Unavailable (with Retry-After)

### **6.3 Logging**
- **Structured Logs**: JSON format
- **Correlation ID**: X-Request-Id in all logs
- **PII Redaction**: Sensitive data masked
- **Retention**: Audit logs 1 year, app logs 90 days

---

## 🧪 **7. TESTING REQUIREMENTS**

### **7.1 Test Strategy**
- **Unit Tests**: Services, mappers, validators (fast, isolated)
- **Integration Tests**: Controllers + DB + auth + RBAC + multi-tenant
- **E2E Tests**: Critical user paths (login → dashboard → projects → tasks)
- **Isolation Tests**: Prove tenant separation

### **7.2 Test Data**
- **Factories**: Deterministic test data
- **Seeders**: Test-only, never leak to prod
- **Cleanup**: Proper teardown after tests

### **7.3 CI Gates**
```
Lint → Unit → Integration → Build → OpenAPI gen → 
a11y audit → E2E (staging) → Security checks → 
Manual approve → Deploy
```

---

## 📊 **8. FOCUS MODE STATE MACHINE**

### **8.1 State Management**
- **One Active Session**: Per user enforcement
- **State Machine**: Start → Active → Pause → Stop
- **API Endpoints**: `/api/v1/app/focus/*`
- **WebSocket**: Real-time updates with polling fallback
- **Audit**: All focus actions logged

### **8.2 Implementation**
- **Session Lock**: Prevent multiple active sessions
- **State Persistence**: Database + cache
- **Real-time Updates**: WebSocket with fallback
- **Mobile Support**: Background timer, notifications

---

## 🔄 **9. LEGACY 3-PHASE MIGRATION**

### **9.1 Phase 1: Announce**
- **Legacy Routes**: Marked as deprecated
- **Documentation**: Migration guides
- **Timeline**: 30 days notice

### **9.2 Phase 2: Redirect (301)**
- **HTTP 301**: Permanent redirects
- **Legacy Map**: `/public/legacy-map.json`
- **Timeline**: 60 days

### **9.3 Phase 3: Gone (410)**
- **HTTP 410**: Gone status
- **Cleanup**: Remove legacy code
- **Timeline**: 90 days

---

## 📋 **10. DEFINITION OF DONE**

### **10.1 Code Quality**
- [ ] No TODO/console/test routes left
- [ ] Legacy plan updated
- [ ] All tests pass
- [ ] Performance budgets met
- [ ] Security review completed

### **10.2 Accessibility**
- [ ] A11y audits pass (no critical issues)
- [ ] WCAG 2.1 AA compliance
- [ ] Keyboard navigation working
- [ ] Screen reader compatible

### **10.3 Performance**
- [ ] Page p95 < 500ms
- [ ] API p95 < 300ms
- [ ] No N+1 queries
- [ ] Proper caching implemented

### **10.4 Documentation**
- [ ] OpenAPI + versioned docs updated
- [ ] Mermaid map matches code
- [ ] Architecture decisions recorded
- [ ] Performance benchmarks documented

### **10.5 UX/UI**
- [ ] Error/empty states implemented
- [ ] User preferences persist
- [ ] Mobile-optimized
- [ ] Smart search and filters

---

## 🚫 **11. ABSOLUTE PROHIBITIONS**

### **11.1 NEVER DO**
- Create routes without proper middleware
- Write code without tenant isolation
- Skip error handling or use generic errors
- Commit code without tests
- Use hardcoded values or magic numbers
- Create duplicate functionality
- Bypass security validations
- Ignore performance budgets
- Leave debug code in production
- Create orphaned data

### **11.2 NEVER ACCEPT**
- PRs without proper tests
- Code that violates architecture principles
- Changes without documentation updates
- Performance regressions
- Security vulnerabilities
- Data isolation violations

---

## ✅ **12. MANDATORY ACTIONS**

### **12.1 ALWAYS DO**
- Validate all inputs against schemas
- Include proper error handling with error.id
- Add tenant_id to all queries
- Write tests for new functionality
- Update documentation for changes
- Follow naming conventions strictly
- Include logging with correlation IDs
- Check performance impact
- Verify security implications
- Ensure multi-tenant isolation

---

## 🔍 **13. CODE REVIEW CHECKLIST**

### **13.1 Before Any Change**
- [ ] Architecture compliance verified
- [ ] Security implications assessed
- [ ] Performance impact evaluated
- [ ] Multi-tenant isolation confirmed
- [ ] Error handling planned

### **13.2 During Development**
- [ ] Tests written and passing
- [ ] Naming conventions followed
- [ ] Error handling implemented
- [ ] Logging added with correlation IDs
- [ ] Documentation updated

### **13.3 Before Completion**
- [ ] All tests pass
- [ ] Performance budgets met
- [ ] Security review completed
- [ ] Documentation complete
- [ ] Architecture compliance verified

---

## 📊 **14. SUCCESS METRICS**

### **14.1 Code Quality**
- 100% test coverage for new code
- 0 security vulnerabilities
- < 500ms p95 latency for pages
- < 300ms p95 latency for APIs
- 0 tenant isolation violations

### **14.2 Documentation**
- 100% API endpoints documented
- All error codes with examples
- Architecture decisions recorded
- Performance benchmarks documented

---

## 🚨 **15. ESCALATION RULES**

### **15.1 CRITICAL Issues (Block Everything)**
- Security vulnerabilities
- Data isolation violations
- Performance regressions
- Architecture violations

### **15.2 HIGH Priority (Block Merge)**
- Missing tests
- Documentation gaps
- Error handling gaps
- Performance issues

### **15.3 MEDIUM Priority (Fix in PR)**
- Naming violations
- Code style issues
- Minor optimizations

---

## 🔄 **16. CONTINUOUS IMPROVEMENT**

### **16.1 Weekly**
- Performance metrics analysis
- Error pattern identification
- Security assessment
- Test coverage evaluation

### **16.2 Monthly**
- Architecture decision validation
- Documentation completeness
- Process improvement
- Technology evaluation

---

*This document is the source of truth for all ZenaManage development*
*Last Updated: January 24, 2025*
*Version: 1.0*
*Next Review: February 24, 2025*

