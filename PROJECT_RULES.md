# ZENAMANAGE PROJECT RULES
## NON-NEGOTIABLE PRINCIPLES (MUST FOLLOW)

### 1.1 Architecture & Scope
- **UI renders only** — business lives in the API
- **Web (Blade/React)**: session auth + tenant scope
- **API /api/v1/***: token auth:sanctum + ability (admin / tenant)
- **No side-effects in UI routes**. All writes via POST/PATCH/DELETE API
- **Clear spaces**: 
  - `/admin/*` (system-wide) ≠ `/app/*` (tenant-scoped)
  - `/_debug/*` for tests only
- **Legacy must have a removal plan** (Announce → 301 → 410)

### 1.2 Naming & Standardization
- **Routes**: kebab-case; plural for lists (`/app/projects`), singular+id for detail (`/app/projects/{id}`)
- **Controllers/Services**: PascalCase with verbs (`ProjectService.updateBudget`)
- **DB schema**: snake_case; FK required; soft delete (`deleted_at`); unique codes (e.g., `project_code`)
- **Enums** (fixed sets only):
  - `status ∈ {planning, active, on_hold, completed, canceled}`
  - `health ∈ {good, at_risk, critical}`

### 1.3 Error Handling & API Contracts
**Standard error envelope** (i18n-ready):
```json
{
  "error": {
    "id": "req_7f1a",
    "code": "E001.INVALID_INPUT", 
    "message": "Invalid input",
    "details": {...}
  }
}
```

- Always include `error.id` (correlates with logs / X-Request-Id)
- `code` is stable, machine-parsable (DOMAIN.CODE), `message` localizable
- **HTTP mapping** (mandatory): 
  - 400 validation, 401 auth, 403 authz, 404 not found
  - 409 conflict, 422 domain validation, 429 rate limit
  - 500 internal, 503 maintenance
- Include `Retry-After` for 429/503
- **Validation errors** (422):
```json
{
  "error": {
    "code": "E422.VALIDATION",
    "fields": {
      "name": ["Required"]
    }
  }
}
```
- **Localization**: server returns message in user locale (`Accept-Language`), fallback `en`
- Do not leak internals in messages; use `error.id` to look up details in logs

### 1.4 Logging, Monitoring & Incident Response
**Structured logs** (JSON) with: timestamp, level, tenant_id, user_id, X-Request-Id, route, latency, result (success/error), redacted PII

- **Severity**: DEBUG (dev only), INFO (normal ops), WARN (degraded), ERROR (4xx actionable), CRITICAL (5xx)
- **500/CRITICAL auto-notify**: push minimal incident card to Slack/Email channel with error.id, route, tenant, p95 snapshot; link to runbook
- **Metrics**: per-tenant QPS, error rate, p95 latency; dashboards for API & UI
- **Tracing**: propagate X-Request-Id; log it in every layer
- **Redaction policy**: mask secrets/PII in logs and errors

### 1.5 Testing Strategy (Unit + Integration + E2E)
- **Unit tests**: services, mappers, validators (fast, isolated)
- **Integration tests**: controllers + DB + auth + RBAC + multi-tenant scoping
- **E2E** (smoke & critical paths): login → dashboard → projects → tasks (Cypress/Playwright)
- **Factories** (Laravel): deterministic model factories for consistent data; no test seeds in prod; separate TestSeeder
- **CI gate** (required): tests must pass; coverage threshold agreed; flakiness = failed build

### 1.6 Documentation & Versioning
- **OpenAPI/Swagger** generated for `/api/v1/*` (models, enums, examples, errors)
- **Publish docs artifact** per release
- **Docs versioning**: maintain docs per API version (`/docs/api/v1`, `/docs/api/v2`)
- **Deprecations** come with dates & migration notes
- **Change log**: human-readable release notes (features, fixes, migrations, flags)

### 1.7 Multi-Tenant Scalability & Isolation
- **Mandatory scoping**: every query filters by `tenant_id`; enforce at repository/service layer
- **Isolation tests**: explicit tests prove tenant A cannot read B (negative tests)
- **Indexes**: composite indexes on `(tenant_id, foreign_key)` for hot tables (tasks, projects, documents)
- **Hot-path optimization**: pagination with stable sort (id/created_at), avoid N+1; prefer projections
- **Growth plan**: document path for scale-up → read replicas, table partitioning by tenant_id or time, and—if needed—sharding strategy (hash by tenant_id) with routing layer
- **Background jobs**: queue keys include tenant_id; workers must be idempotent

### 1.8 Performance & UX
- **Budgets**: page p95 < 500ms (20–50 rows); list API < 300ms
- **Cache**: KPI/insights 60s per tenant; invalidate on writes
- **Realtime**: Alerts & Focus/Now Panel only; others poll 60s
- **Empty/Error states** include suggested action (Create, Clear filters, Retry)
- **Compact by default**: dense tables, collapsible panels, drawers; sticky toolbars; minimal scrolling
- **Universal Page Frame**: Header → Global Nav → Page Nav → KPI Strip → Alert Bar → Main Content → Activity
- **Mobile-first**: Responsive design with FAB, hamburger menu, card layouts
- **Accessibility**: WCAG 2.1 AA compliance with keyboard navigation and screen reader support

### 1.9 Security & Permissions
- **RBAC explicit**: super_admin / PM / Member / Client…
- **Admin API**: auth:sanctum + ability:admin. **App API**: auth:sanctum + ability:tenant
- **Web**: CSRF; **API**: tokens only. CSP, CORS, HSTS, secure cookies
- **Rate-limit** public endpoints. Secrets not in repo; key rotation policy
- **Data retention**: logs 90d (configurable); soft-delete 30–90d before purge

### 1.10 No Duplicates / No Orphans
- **One function = one route/view**. No duplicate screens with same intent
- **FK + ON DELETE rules** (restrict/cascade) defined for each relation
- **Every record has tenant_id**; documents/tasks link project_id when required
- **Nightly job** reports orphan data and fails CI if threshold exceeded

### 1.11 Debug / Legacy / Tests (Routes)
- **All tests & mocks** under `/_debug/*` with DebugGate (env + IP)
- **Forbidden**: tests at root, `/app/*`, `/admin/*`
- **Legacy lifecycle**: Announce → 301 → 410, tracked in `legacy-map.json` with dates & traffic

### 1.12 CI/CD & Deployment (with Rollback)
**Pipeline** (required gates): Lint → Unit → Integration → Build → OpenAPI gen → E2E (staging) → Security checks → Manual approval → Deploy

- **Zero-downtime migrations**: additive first (columns/tables), backfill jobs, then code switch, then drop legacy fields
- **Feature flags**: ship dark; enable per tenant/role; instant disable for rollback
- **Deployment strategy**: blue/green or canary (small % traffic) with auto-rollback on error-rate/latency SLO breach
- **Post-deploy smoke**: ping health, load dashboard, list projects/tasks, create+rollback test entity
- **Rollback plan**: documented N previous artifacts; DB reversibility noted in migration PRs; feature_flag OFF as first response

### 1.13 Definition of Done (DoD)
- No TODOs/console logs/test routes left
- Route map has zero UI side-effects
- Lint/format pass; i18n pass; basic a11y (keyboard/ARIA)
- Mermaid diagram & docs match code; QA checklist passes; OpenAPI updated

---

## 🚨 **ENFORCEMENT POLICY**

### **Violation Levels:**
- **CRITICAL**: Architecture violations, security issues, data leaks
- **HIGH**: Performance degradation, broken tests, missing documentation
- **MEDIUM**: Naming violations, missing error handling
- **LOW**: Code style, minor optimizations

### **Consequences:**
- **CRITICAL/HIGH**: Block merge, require immediate fix
- **MEDIUM**: Block merge, fix in same PR
- **LOW**: Warning, fix in next PR

### **Review Process:**
1. Automated checks (lint, tests, security)
2. Peer review against this document
3. Architecture review for CRITICAL changes
4. Final approval by tech lead

---

## 📋 **CHECKLIST FOR EVERY PR**

### **Before Coding:**
- [ ] Architecture decision documented
- [ ] API contract defined (OpenAPI)
- [ ] Error handling strategy planned
- [ ] Multi-tenant scoping considered
- [ ] Performance impact assessed

### **During Development:**
- [ ] Follows naming conventions
- [ ] Includes proper error handling
- [ ] Has tenant isolation
- [ ] Includes logging
- [ ] No side-effects in UI routes

### **Before Merge:**
- [ ] All tests pass
- [ ] Documentation updated
- [ ] OpenAPI spec updated
- [ ] Performance benchmarks met
- [ ] Security review completed
- [ ] No TODOs or debug code

---

## 🔄 **REVIEW CYCLE**

This document should be reviewed and updated:
- **Monthly**: Performance metrics, error patterns
- **Quarterly**: Architecture decisions, technology stack
- **Annually**: Complete review and modernization

---

*Last Updated: September 24, 2025*
*Version: 1.0*
*Next Review: October 24, 2025*
