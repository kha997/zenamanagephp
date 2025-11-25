# Architecture PR Checklist

Use this checklist for PRs that involve architectural changes, new features, or significant refactoring.

## Pre-Submission Checklist

### Documentation
- [ ] Updated OpenAPI spec (if API changes)
- [ ] Updated cache invalidation map (if cache-related changes)
- [ ] Updated test groups documentation (if test changes)
- [ ] Updated architecture documentation (if structural changes)

### Code Quality
- [ ] Follows Architecture Layering Guide (`docs/ARCHITECTURE_LAYERING_GUIDE.md`)
- [ ] No deprecated services/middleware used (run `php scripts/check-deprecated-usage.php`)
- [ ] No service calls in Blade views (run `php scripts/audit-blade-views.php`)
- [ ] Layer boundaries respected (Controller → Service → Repository)

### Security & Isolation
- [ ] Tenant isolation verified (all queries filter by tenant_id)
- [ ] RBAC matrix updated (if new permissions added)
- [ ] Security implications reviewed
- [ ] No secrets or sensitive data exposed

### Testing
- [ ] Unit tests added/updated
- [ ] Feature tests added/updated
- [ ] Integration tests added/updated (if applicable)
- [ ] All tests passing (`php artisan test --testsuite=quick`)
- [ ] Test coverage maintained or improved

### Performance
- [ ] Performance impact assessed
- [ ] Query budget respected (no N+1 queries)
- [ ] Cache strategy considered
- [ ] API response time within budget (< 300ms p95)

### Observability
- [ ] Logging added with correlation IDs (X-Request-Id)
- [ ] Metrics updated (if applicable)
- [ ] Error handling includes error.id

### Multi-Tenant
- [ ] Tenant isolation tests added
- [ ] Global scope verified (if model changes)
- [ ] Tenant context properly set

### WebSocket (if applicable)
- [ ] WebSocket contract = REST (same auth/tenant/RBAC)
- [ ] WebSocket metrics updated
- [ ] WebSocket tests added

### Cache (if applicable)
- [ ] Cache invalidation via `CacheInvalidationService`
- [ ] Cache invalidation map updated
- [ ] Cache keys follow naming convention

### Frontend (if applicable)
- [ ] Design tokens used (no hardcoded colors/values)
- [ ] Accessibility requirements met (WCAG 2.1 AA)
- [ ] Mobile-responsive
- [ ] i18n keys added (if new strings)

## Review Questions

1. **Does this change affect multiple layers?**
   - If yes, ensure layer boundaries are respected

2. **Does this introduce new dependencies?**
   - If yes, ensure they're necessary and documented

3. **Does this change affect performance?**
   - If yes, include performance benchmarks

4. **Does this change affect security?**
   - If yes, include security review notes

5. **Does this require database migrations?**
   - If yes, ensure migrations are reversible and tested

## Additional Notes

Add any additional context, concerns, or notes for reviewers:

---

**By submitting this PR, I confirm that:**
- [ ] I have reviewed this checklist
- [ ] All applicable items are checked
- [ ] I have run the relevant scripts and tests
- [ ] I am ready for code review

