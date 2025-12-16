# Middleware Consolidation

**Version**: 1.0  
**Last Updated**: 2025-01-XX  
**Status**: Active

---

## Overview

This document describes the consolidation of security-related middleware into a single unified middleware to reduce complexity and improve maintainability.

---

## Consolidated Middleware

### UnifiedSecurityMiddleware

**Location**: `app/Http/Middleware/Unified/UnifiedSecurityMiddleware.php`

**Replaces**:
- `SecurityHeadersMiddleware` ✅ Fully replaced
- `EnhancedSecurityHeadersMiddleware` ✅ Fully replaced
- `ProductionSecurityMiddleware` ✅ Fully replaced
- `AdvancedSecurityMiddleware` ⚠️ Partially replaced (basic features only)

**Note on AdvancedSecurityMiddleware**:
- AdvancedSecurityMiddleware provides advanced threat detection via `AdvancedSecurityService`
- For basic security, use UnifiedSecurityMiddleware
- For advanced threat detection, use `AdvancedSecurityService` directly in specific routes
- AdvancedSecurityMiddleware is deprecated but may be kept for routes requiring advanced threat detection

**Features**:
1. **Security Headers**:
   - Content Security Policy (CSP) - environment-aware
   - HTTP Strict Transport Security (HSTS)
   - X-Frame-Options
   - X-Content-Type-Options
   - X-XSS-Protection
   - Referrer-Policy
   - Permissions-Policy
   - Cross-Origin policies (production only)

2. **Security Checks**:
   - Suspicious pattern detection (XSS, directory traversal, etc.)
   - Request size validation
   - Malicious content detection (SQL injection patterns)
   - Production route blocking (debug/test endpoints)

3. **Logging**:
   - Security event logging
   - Request correlation ID tracking

---

## Deprecated Middleware

### SecurityHeadersMiddleware

**Status**: `@deprecated since 2025-01-XX`

**Migration**: Replace with `UnifiedSecurityMiddleware` in `Kernel.php`

**What was consolidated**:
- Basic security headers (CSP, HSTS, X-Frame-Options, etc.)
- Environment-aware CSP generation

### EnhancedSecurityHeadersMiddleware

**Status**: `@deprecated since 2025-01-XX`

**Migration**: Replace with `UnifiedSecurityMiddleware`

**What was consolidated**:
- Enhanced security headers
- Permissions Policy

### ProductionSecurityMiddleware

**Status**: `@deprecated since 2025-01-XX`

**Migration**: Replace with `UnifiedSecurityMiddleware`. Route blocking is automatically applied in production.

**What was consolidated**:
- Production route blocking (blocks debug/test routes)
- SimpleUserController route blocking

### AdvancedSecurityMiddleware

**Status**: `@deprecated since 2025-01-XX`

**Migration**: 
- For basic security: Use `UnifiedSecurityMiddleware`
- For advanced threat detection: Use `AdvancedSecurityService` directly in specific routes

**What was consolidated**:
- Basic threat detection patterns (consolidated into UnifiedSecurityMiddleware)
- Security headers (consolidated into UnifiedSecurityMiddleware)

**What remains separate**:
- Advanced threat detection via `AdvancedSecurityService` (use service directly if needed)
- Intrusion prevention (use `AdvancedSecurityService` directly if needed)
- IP blocking (use `AdvancedSecurityService` directly if needed)

**Note**: AdvancedSecurityMiddleware may be kept for routes requiring advanced threat detection, but it's recommended to use `AdvancedSecurityService` directly instead.

---

## Configuration

### Kernel.php

**Before**:
```php
protected $middleware = [
    // ...
    \App\Http\Middleware\SecurityHeadersMiddleware::class,
];
```

**After**:
```php
protected $middleware = [
    // ...
    \App\Http\Middleware\Unified\UnifiedSecurityMiddleware::class,
];
```

### Security Configuration

Security settings can be configured in `config/security.php`:

```php
'headers' => [
    'csp_enabled' => env('SECURITY_CSP_ENABLED', true),
    'hsts_enabled' => env('SECURITY_HSTS_ENABLED', true),
    'hsts_max_age' => env('SECURITY_HSTS_MAX_AGE', 31536000),
    'frame_options' => env('SECURITY_FRAME_OPTIONS', 'DENY'),
    'content_type_options' => env('SECURITY_CONTENT_TYPE_OPTIONS', true),
    'xss_protection' => env('SECURITY_XSS_PROTECTION', true),
],

'max_request_size' => env('SECURITY_MAX_REQUEST_SIZE', 10485760), // 10MB
```

---

## Migration Checklist

- [x] Enhanced `UnifiedSecurityMiddleware` with all features
- [x] Marked old middleware as `@deprecated`
- [x] Updated `Kernel.php` to use `UnifiedSecurityMiddleware`
- [ ] Update any route-specific middleware usage
- [ ] Remove deprecated middleware (after migration period)
- [ ] Update tests to use `UnifiedSecurityMiddleware`

---

## Testing

### Unit Tests

Test `UnifiedSecurityMiddleware` functionality:
```bash
php artisan test tests/Unit/Middleware/UnifiedSecurityMiddlewareTest.php
```

### Integration Tests

Test security headers in responses:
```bash
php artisan test tests/Feature/Security/SecurityHeadersTest.php
```

### Manual Testing

1. **Security Headers**: Check response headers in browser DevTools
2. **CSP**: Verify CSP header is present and correct for environment
3. **Production Blocking**: Test that debug routes are blocked in production
4. **Suspicious Patterns**: Test that suspicious patterns are detected and logged

---

## Benefits

1. **Reduced Complexity**: Single middleware instead of multiple
2. **Consistency**: All security features in one place
3. **Maintainability**: Easier to update and test
4. **Performance**: Single middleware pass instead of multiple
5. **Clear Migration Path**: Deprecated middleware clearly marked

---

## References

- [Architecture Layering Guide](ARCHITECTURE_LAYERING_GUIDE.md)
- [Security Review](SECURITY_REVIEW.md)
- [ADR-001: Service Layering Guide](adr/ADR-001-Service-Layering-Guide.md)

