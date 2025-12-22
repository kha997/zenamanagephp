# Sprint Plan - Improvement Implementation

## Sprint 1: Quick Wins (1 tuần)

**Mục tiêu**: Dứt điểm trùng lặp và chuẩn hóa

### Day 1-2: PR #1 - Composite unique theo tenant

**Owner**: Backend Developer

**Tasks**:
1. Review existing migrations
2. Create new migration for missing constraints
3. Add composite indexes
4. Review FK on-delete rules
5. Write tests
6. Update documentation

**Deliverables**:
- Migration file
- Tests
- Documentation update

**Definition of Done**:
- [ ] Migration runs successfully
- [ ] All tests pass
- [ ] Tenant isolation verified
- [ ] Documentation updated

---

### Day 2-3: PR #2 - Invalidation map FE

**Owner**: Frontend Developer

**Tasks**:
1. Create `invalidateMap.ts`
2. Create `invalidateFor()` helper
3. Refactor task hooks
4. Refactor project hooks
5. Refactor document hooks
6. Write tests

**Deliverables**:
- `invalidateMap.ts` file
- Helper function
- Refactored hooks
- Tests

**Definition of Done**:
- [ ] All hooks use `invalidateFor()`
- [ ] Cache invalidation verified
- [ ] Tests pass
- [ ] Documentation updated

---

### Day 3-4: PR #5 - Header/Navigation 1 nguồn

**Owner**: Full-stack Developer

**Tasks**:
1. Review current navigation implementation
2. Decide: JSON file or API endpoint?
3. Create navigation schema
4. Update Blade component
5. Update React component
6. Write E2E tests

**Deliverables**:
- Navigation schema
- Updated Blade component
- Updated React component
- E2E tests

**Definition of Done**:
- [ ] Blade và React dùng cùng nguồn
- [ ] Navigation hiển thị giống nhau
- [ ] E2E tests pass
- [ ] Documentation updated

---

### Day 4-5: Smoke tests + Feature flag

**Owner**: QA + DevOps

**Tasks**:
1. Create smoke tests cho Blade paths
2. Create smoke tests cho React paths
3. Set up feature flag cho `/app/tasks` → React
4. Verify feature flag works
5. Monitor rollout

**Deliverables**:
- Smoke test suite
- Feature flag config
- Monitoring dashboard

**Definition of Done**:
- [ ] Smoke tests pass
- [ ] Feature flag enabled
- [ ] No regressions
- [ ] Monitoring in place

---

## Sprint 2: Hardening và Observability (1 tuần)

### Day 1-2: PR #3 - WebSocket Auth Guard

**Owner**: Backend Developer

**Tasks**:
1. Create `AuthGuard.php`
2. Create rate-limit middleware
3. Integrate vào `DashboardWebSocketHandler`
4. Write tests
5. Update documentation

**Deliverables**:
- `AuthGuard.php`
- Rate-limit middleware
- Updated WebSocket handler
- Tests

**Definition of Done**:
- [ ] Auth guard works
- [ ] Tenant isolation enforced
- [ ] Rate limiting works
- [ ] Tests pass
- [ ] Documentation updated

---

### Day 2-4: PR #4 - OpenAPI → Types

**Owner**: Full-stack Developer

**Tasks**:
1. Review current OpenAPI spec
2. Update OpenAPI spec (đầy đủ endpoints)
3. Create `gen:api` script
4. Generate types
5. Refactor hooks
6. Add CI check
7. Write contract tests

**Deliverables**:
- Updated OpenAPI spec
- Type generation script
- Generated types
- Refactored hooks
- CI check
- Contract tests

**Definition of Done**:
- [ ] OpenAPI spec complete
- [ ] Types generated successfully
- [ ] Hooks use generated types
- [ ] CI check passes
- [ ] Contract tests pass
- [ ] Documentation updated

---

### Day 4-5: Metrics + Performance budgets + E2E

**Owner**: DevOps + QA

**Tasks**:
1. Set up metrics collection
2. Enforce performance budgets trong CI
3. Create E2E tests cho WebSocket
4. Create E2E tests cho cache freshness
5. Set up monitoring dashboard

**Deliverables**:
- Metrics collection
- CI performance budget check
- E2E test suite
- Monitoring dashboard

**Definition of Done**:
- [ ] Metrics collected
- [ ] Performance budgets enforced
- [ ] E2E tests pass
- [ ] Monitoring dashboard works

---

## Tracking

### Daily Standup Questions
1. What did you complete yesterday?
2. What will you work on today?
3. Any blockers?

### Sprint Review Checklist
- [ ] All PRs merged
- [ ] All tests pass
- [ ] Documentation updated
- [ ] Performance budgets met
- [ ] No regressions
- [ ] Monitoring in place

### Sprint Retrospective
- What went well?
- What could be improved?
- Action items for next sprint

---

## Risks & Mitigation

### Risk 1: Migration breaks existing data
**Mitigation**: 
- Test migration on staging first
- Have rollback plan
- Backup database before migration

### Risk 2: Cache invalidation causes performance issues
**Mitigation**:
- Monitor cache hit rate
- Use debouncing for rapid mutations
- Test with realistic data volumes

### Risk 3: WebSocket auth breaks existing connections
**Mitigation**:
- Gradual rollout
- Monitor connection errors
- Have rollback plan

### Risk 4: OpenAPI spec drift
**Mitigation**:
- CI check for spec validation
- Contract tests
- Regular reviews

---

## Success Criteria

### Sprint 1
- ✅ 3 PRs merged
- ✅ Smoke tests pass
- ✅ Feature flag enabled
- ✅ No regressions

### Sprint 2
- ✅ 2 PRs merged
- ✅ Metrics dashboard operational
- ✅ Performance budgets enforced
- ✅ E2E tests pass
- ✅ Observability 3-in-1 working

---

## Next Steps After Sprint 2

1. **Feature flags rollout**: Gradually migrate more routes to React
2. **Job idempotency**: Implement job idempotency system
3. **SLO/SLA tracking**: Set up SLO tracking and alerting
4. **Security drill**: Run security test suite
5. **Observability enhancement**: Add more metrics and traces

