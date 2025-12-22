# Feature Flag Rollout Guide - Clients & Quotes

**Last Updated:** 2025-01-17  
**Status:** Ready for Rollout

---

## Overview

This guide provides step-by-step instructions for rolling out React migration for Clients and Quotes modules using feature flags. The rollout follows a safe, canary deployment strategy with instant rollback capability.

---

## Prerequisites

- ✅ Feature flags configured in `config/features.php`
- ✅ `AppModuleRoutingMiddleware` implemented
- ✅ React pages complete and tested
- ✅ API endpoints verified
- ✅ Monitoring/observability in place

---

## Rollout Strategy

### Phase 1: Staging Environment
1. Enable flags for staging
2. Test all functionality
3. Verify metrics

### Phase 2: Canary Rollout (10% of tenants)
1. Enable for selected tenants
2. Monitor metrics closely
3. Collect feedback

### Phase 3: Full Rollout
1. Enable for all tenants
2. Monitor metrics
3. Retire Blade routes

---

## Step-by-Step Rollout

### Step 1: Enable Flags for Staging

**Option A: Environment Variables (Recommended for staging)**

```bash
# .env.staging
FF_APP_CLIENTS=true
FF_APP_QUOTES=true
```

**Option B: API Endpoint (For testing)**

```bash
# Enable for staging (global)
curl -X POST https://staging.zenamanage.com/api/v1/admin/feature-flags/app.clients \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"enabled": true}'

curl -X POST https://staging.zenamanage.com/api/v1/admin/feature-flags/app.quotes \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"enabled": true}'
```

**Option C: Artisan Command (If created)**

```bash
php artisan feature-flag:enable app.clients
php artisan feature-flag:enable app.quotes
```

### Step 2: Verify Staging

**Test Checklist:**
- [ ] `/app/clients` loads React page
- [ ] `/app/clients/:id` loads React detail page
- [ ] `/app/clients/create` works
- [ ] `/app/clients/:id/edit` works
- [ ] `/app/quotes` loads React page
- [ ] `/app/quotes/:id` loads React detail page
- [ ] `/app/quotes/create` works
- [ ] `/app/quotes/:id/edit` works
- [ ] All CRUD operations work
- [ ] KPI Strip displays correctly
- [ ] Alert Bar displays correctly
- [ ] Activity Feed displays correctly
- [ ] Tabs work in detail pages
- [ ] Navigation works correctly
- [ ] Deep linking works (F5/refresh)

**Metrics to Check:**
- p95 API latency < 300ms
- Error rate < 1%
- Page load time < 500ms

### Step 3: Canary Rollout (10% of Tenants)

**Select Test Tenants:**
- Choose 10% of active tenants
- Prefer tenants with:
  - Active usage
  - Diverse data volumes
  - Different user roles

**Enable for Specific Tenants:**

```bash
# Enable for tenant ABC123
curl -X POST https://production.zenamanage.com/api/v1/admin/feature-flags/app.clients \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"enabled": true, "tenant_id": "ABC123"}'

curl -X POST https://production.zenamanage.com/api/v1/admin/feature-flags/app.quotes \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"enabled": true, "tenant_id": "ABC123"}'
```

**Monitor for 24-48 hours:**
- Error rates
- API latency
- User feedback
- Support tickets

### Step 4: Full Rollout

**Enable Globally:**

```bash
# Update .env.production
FF_APP_CLIENTS=true
FF_APP_QUOTES=true

# Or via API (global)
curl -X POST https://production.zenamanage.com/api/v1/admin/feature-flags/app.clients \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"enabled": true}'

curl -X POST https://production.zenamanage.com/api/v1/admin/feature-flags/app.quotes \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"enabled": true}'
```

**Clear Cache:**
```bash
curl -X DELETE https://production.zenamanage.com/api/v1/admin/feature-flags/cache \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

### Step 5: Retire Blade Routes

**After 1-2 weeks of stable operation:**

1. Verify all tenants using React
2. Remove Blade routes from `routes/web.php`:
   ```php
   // Remove these routes:
   // Route::get('/app-legacy/clients', ...);
   // Route::get('/app-legacy/quotes', ...);
   ```
3. Remove Blade views (optional, keep for reference):
   ```bash
   # Archive instead of delete
   mv resources/views/app/clients resources/views/_archived/app/clients
   mv resources/views/app/quotes resources/views/_archived/app/quotes
   ```

---

## Rollback Procedure

### Instant Rollback (Feature Flag)

**Disable via Environment Variable:**
```bash
# .env.production
FF_APP_CLIENTS=false
FF_APP_QUOTES=false
```

**Or via API:**
```bash
curl -X POST https://production.zenamanage.com/api/v1/admin/feature-flags/app.clients \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"enabled": false}'

curl -X POST https://production.zenamanage.com/api/v1/admin/feature-flags/app.quotes \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"enabled": false}'
```

**Clear Cache:**
```bash
curl -X DELETE https://production.zenamanage.com/api/v1/admin/feature-flags/cache \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

**Result:** All users immediately see Blade views again.

---

## API Endpoints

### Get All Feature Flags
```
GET /api/v1/admin/feature-flags?tenant_id=xxx&user_id=yyy
```

### Get Specific Flag Status
```
GET /api/v1/admin/feature-flags/{flag}?tenant_id=xxx&user_id=yyy
```

### Enable/Disable Flag
```
POST /api/v1/admin/feature-flags/{flag}
Body: {
  "enabled": true,
  "tenant_id": "optional",
  "user_id": "optional"
}
```

### Clear Cache
```
DELETE /api/v1/admin/feature-flags/cache?flag=xxx&tenant_id=yyy&user_id=zzz
```

---

## Monitoring

### Key Metrics

1. **API Latency:**
   - Target: p95 < 300ms
   - Alert if: p95 > 500ms

2. **Error Rate:**
   - Target: < 1%
   - Alert if: > 2%

3. **Page Load Time:**
   - Target: p95 < 500ms
   - Alert if: p95 > 1000ms

4. **Feature Flag Usage:**
   - Track: % of requests using React vs Blade
   - Monitor: Flag enable/disable events

### Dashboards

- Grafana: Feature Flag Rollout Dashboard
- Sentry: Error tracking with feature flag context
- Custom: Feature flag usage analytics

---

## Troubleshooting

### Issue: Flag not taking effect

**Solution:**
1. Clear cache: `DELETE /api/v1/admin/feature-flags/cache`
2. Verify flag value: `GET /api/v1/admin/feature-flags/{flag}`
3. Check middleware order in `app/Http/Kernel.php`
4. Verify route middleware applied

### Issue: Users see wrong version

**Solution:**
1. Check tenant-specific flags
2. Check user-specific flags
3. Verify cache cleared
4. Check browser cache (hard refresh)

### Issue: Performance degradation

**Solution:**
1. Check API latency metrics
2. Review database queries (N+1 issues)
3. Check cache hit rates
4. Review React bundle size
5. Consider rollback if critical

---

## Success Criteria

- ✅ All tenants using React (100% adoption)
- ✅ p95 API latency < 300ms
- ✅ Error rate < 1%
- ✅ Zero critical bugs
- ✅ User satisfaction maintained
- ✅ Blade routes retired

---

## Next Steps

After successful rollout:
1. Document lessons learned
2. Update migration progress
3. Proceed to Sprint 3 (Documents & Change-Requests)
4. Archive Blade views

---

## Related Documentation

- [React Migration Progress](./REACT_MIGRATION_PROGRESS.md)
- [Feature Flag Service](../app/Services/FeatureFlagService.php)
- [Route Architecture](./ROUTE_ARCHITECTURE.md)

