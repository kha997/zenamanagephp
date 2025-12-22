# Middleware Stack

**Version**: 1.0  
**Last Updated**: January 2025  
**Status**: Active  
**Purpose**: Single source of truth for middleware order and purpose in ZenaManage

---

## Overview

This document describes the middleware stack for each route group, explaining the order, purpose, and when middleware can be safely disabled.

---

## Global Middleware (All Requests)

**Order:**
```
TrustProxies → HandleCors → PreventRequestsDuringMaintenance → ValidatePostSize → 
TrimStrings → ConvertEmptyStringsToNull → TracingMiddleware → UnifiedSecurityMiddleware
```

| Middleware | Purpose | Can Disable in Dev? | Required in Prod? |
|------------|---------|---------------------|-------------------|
| `TrustProxies` | Trust proxy headers (for load balancers) | ✅ Yes | ✅ Yes |
| `HandleCors` | CORS headers for SPA | ✅ Yes | ✅ Yes |
| `PreventRequestsDuringMaintenance` | Block requests during maintenance | ✅ Yes | ✅ Yes |
| `ValidatePostSize` | Validate POST request size | ✅ Yes | ✅ Yes |
| `TrimStrings` | Trim string inputs | ✅ Yes | ✅ Yes |
| `ConvertEmptyStringsToNull` | Convert empty strings to null | ✅ Yes | ✅ Yes |
| `TracingMiddleware` | Generate X-Request-Id correlation ID | ⚠️ No (breaks observability) | ✅ Yes |
| `UnifiedSecurityMiddleware` | Security headers (CSP, HSTS, etc.) | ✅ Yes | ✅ Yes |

---

## `/api/v1/app/*` (Tenant-scoped API)

**Middleware Stack:**
```
RequestCorrelationMiddleware → MetricsMiddleware → EnsureFrontendRequestsAreStateful → 
StartSession → ThrottleRequests → SubstituteBindings → LogSamplingMiddleware → 
PerformanceLoggingMiddleware → ErrorEnvelopeMiddleware → OpenApiResponseValidator → 
QueryBudgetMiddleware → [Route-specific: auth:sanctum, ability:tenant, tenant.isolation]
```

### Detailed Breakdown

| Middleware | Purpose | Can Disable in Dev? | Required in Prod? |
|------------|---------|---------------------|-------------------|
| `RequestCorrelationMiddleware` | Generate X-Request-Id | ⚠️ No (breaks tracing) | ✅ Yes |
| `MetricsMiddleware` | Track latency, error rate | ✅ Yes | ✅ Yes |
| `EnsureFrontendRequestsAreStateful` | Sanctum stateful auth | ⚠️ No (breaks auth) | ✅ Yes |
| `StartSession` | Session support for CSRF | ⚠️ No (breaks CSRF) | ✅ Yes |
| `ThrottleRequests` | Rate limiting | ✅ Yes | ✅ Yes |
| `SubstituteBindings` | Route model binding | ⚠️ No (breaks routes) | ✅ Yes |
| `LogSamplingMiddleware` | Sample logs to reduce noise | ✅ Yes | ✅ Yes |
| `PerformanceLoggingMiddleware` | Log slow requests | ✅ Yes | ✅ Yes |
| `ErrorEnvelopeMiddleware` | Standardize error format | ⚠️ No (breaks error handling) | ✅ Yes |
| `OpenApiResponseValidator` | Validate responses against OpenAPI | ✅ Yes | ⚠️ Recommended |
| `QueryBudgetMiddleware` | Enforce query budget limits | ✅ Yes | ✅ Yes |
| `auth:sanctum` | Authenticate user | ⚠️ No (breaks auth) | ✅ Yes |
| `ability:tenant` | Check tenant ability | ⚠️ No (breaks RBAC) | ✅ Yes |
| `tenant.isolation` | Set tenant context | ⚠️ No (breaks tenant isolation) | ✅ Yes |

### Route-Specific Middleware

**Example:**
```php
Route::middleware(['auth:sanctum', 'ability:tenant', 'tenant.isolation'])
    ->prefix('app')
    ->group(function () {
        Route::get('/projects', [ProjectsController::class, 'index']);
    });
```

---

## `/api/v1/admin/*` (System-wide Admin API)

**Middleware Stack:**
```
RequestCorrelationMiddleware → MetricsMiddleware → EnsureFrontendRequestsAreStateful → 
StartSession → ThrottleRequests → SubstituteBindings → LogSamplingMiddleware → 
PerformanceLoggingMiddleware → ErrorEnvelopeMiddleware → OpenApiResponseValidator → 
QueryBudgetMiddleware → [Route-specific: auth:sanctum, ability:admin]
```

### Differences from `/api/v1/app/*`

- **No `tenant.isolation`**: Admin endpoints are system-wide
- **`ability:admin`** instead of `ability:tenant`: Requires admin permissions

| Middleware | Purpose | Can Disable in Dev? | Required in Prod? |
|------------|---------|---------------------|-------------------|
| `auth:sanctum` | Authenticate user | ⚠️ No (breaks auth) | ✅ Yes |
| `ability:admin` | Check admin ability | ⚠️ No (breaks RBAC) | ✅ Yes |

---

## `/web` (Blade Views)

**Middleware Stack:**
```
EncryptCookies → AddQueuedCookiesToResponse → StartSession → SetLocaleMiddleware → 
ShareErrorsFromSession → VerifyCsrfToken → SubstituteBindings → PerformanceLoggingMiddleware
```

### Detailed Breakdown

| Middleware | Purpose | Can Disable in Dev? | Required in Prod? |
|------------|---------|---------------------|-------------------|
| `EncryptCookies` | Encrypt cookies | ⚠️ No (breaks sessions) | ✅ Yes |
| `AddQueuedCookiesToResponse` | Add queued cookies | ⚠️ No (breaks cookies) | ✅ Yes |
| `StartSession` | Start session | ⚠️ No (breaks sessions) | ✅ Yes |
| `SetLocaleMiddleware` | Set locale | ✅ Yes | ✅ Yes |
| `ShareErrorsFromSession` | Share validation errors | ⚠️ No (breaks forms) | ✅ Yes |
| `VerifyCsrfToken` | CSRF protection | ✅ Yes (for testing) | ✅ Yes |
| `SubstituteBindings` | Route model binding | ⚠️ No (breaks routes) | ✅ Yes |
| `PerformanceLoggingMiddleware` | Log slow requests | ✅ Yes | ✅ Yes |

### Route-Specific Middleware

**Example:**
```php
Route::middleware(['web', 'auth:web'])
    ->group(function () {
        Route::get('/admin/dashboard', [AdminDashboardController::class, 'index']);
    });
```

---

## Middleware Decision Matrix

### When to Use Which Middleware

| Scenario | Required Middleware |
|----------|-------------------|
| Public API endpoint | `RequestCorrelationMiddleware`, `MetricsMiddleware`, `ThrottleRequests` |
| Protected API endpoint | Above + `auth:sanctum`, `ability:tenant` or `ability:admin` |
| Tenant-scoped endpoint | Above + `tenant.isolation` |
| Blade view | `web` group + `auth:web` |
| Form submission | `web` group + `VerifyCsrfToken` |
| File upload | Above + `QueryBudgetMiddleware` (for large files) |

---

## Troubleshooting

### Issue: "X-Request-Id missing"

**Cause:** `RequestCorrelationMiddleware` not applied  
**Solution:** Ensure middleware is in `api` middleware group

### Issue: "Tenant isolation not working"

**Cause:** `tenant.isolation` middleware not applied  
**Solution:** Add `tenant.isolation` to route middleware

### Issue: "CSRF token mismatch"

**Cause:** `VerifyCsrfToken` middleware not applied or CSRF cookie not set  
**Solution:** Ensure `web` middleware group includes `VerifyCsrfToken`

### Issue: "Rate limit exceeded"

**Cause:** `ThrottleRequests` middleware applied  
**Solution:** Adjust rate limit config or disable in dev (not recommended for prod)

---

## Performance Considerations

### Middleware Overhead

| Middleware | Overhead | Impact |
|------------|----------|--------|
| `MetricsMiddleware` | ~1-2ms | Low |
| `PerformanceLoggingMiddleware` | ~0.5ms | Low |
| `QueryBudgetMiddleware` | ~1ms | Low |
| `OpenApiResponseValidator` | ~5-10ms | Medium (dev only) |
| `ErrorEnvelopeMiddleware` | ~0.5ms | Low |

### Optimization Tips

1. **Disable in Dev:** `OpenApiResponseValidator` (adds 5-10ms overhead)
2. **Sample Logs:** Use `LogSamplingMiddleware` to reduce log volume
3. **Cache Metrics:** `MetricsMiddleware` uses cache, minimal overhead

---

## Security Considerations

### Critical Middleware (Never Disable in Production)

- `auth:sanctum` / `auth:web` - Authentication
- `ability:tenant` / `ability:admin` - Authorization
- `tenant.isolation` - Tenant isolation
- `VerifyCsrfToken` - CSRF protection
- `UnifiedSecurityMiddleware` - Security headers

### Optional Middleware (Can Disable)

- `MetricsMiddleware` - Observability (recommended to keep)
- `PerformanceLoggingMiddleware` - Performance monitoring
- `OpenApiResponseValidator` - Response validation (dev only)

---

## References

- [Authentication & Tenant Flow](AUTH_TENANT_FLOW.md)
- [Service Catalog](SERVICE_CATALOG.md)
- [Error Envelope Contract](ERROR_ENVELOPE_CONTRACT.md)

---

*This document should be updated whenever middleware is added, removed, or reordered.*

