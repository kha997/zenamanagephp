# Security Environment Matrix

**Version**: 1.0  
**Last Updated**: January 2025  
**Status**: Active  
**Purpose**: Defines security configuration for each environment (dev, staging, production)

---

## Overview

Security settings vary by environment to balance security, usability, and development experience. This matrix defines what's enabled/disabled in each environment.

---

## Environment Matrix

### Development (local, development, testing)

**Purpose**: Developer-friendly with relaxed security for debugging

| Feature | Status | Configuration | Notes |
|---------|--------|--------------|-------|
| **Security Headers** | Partial | Relaxed CSP, no HSTS | Allow Vite HMR, localhost connections |
| **Debug Mode** | Enabled | `APP_DEBUG=true` | Stack traces, error details visible |
| **Rate Limiting** | Relaxed | Higher limits | 200 req/min for API |
| **CORS** | Permissive | `localhost:*` allowed | Allow all localhost ports |
| **HTTPS Required** | Disabled | HTTP allowed | Local development |
| **Error Details** | Enabled | Full stack traces | For debugging |
| **Test Routes** | Enabled | `/debug/*` accessible | Development tools |
| **Logging** | Verbose | All logs enabled | Debug logging active |
| **Threat Detection** | Disabled | No blocking | Allow testing |
| **File Upload Validation** | Relaxed | Larger limits | 50MB max |
| **Session Security** | Relaxed | `SameSite=Lax` | Allow localhost cookies |

#### CSP (Content Security Policy)

```php
// Development: Relaxed for Vite HMR
"default-src 'self' 'unsafe-inline' 'unsafe-eval' localhost:* ws://localhost:*; " .
"script-src 'self' 'unsafe-inline' 'unsafe-eval' localhost:*; " .
"style-src 'self' 'unsafe-inline' localhost:*;"
```

#### Rate Limits

```php
'api' => [
    'requests_per_minute' => 200,  // Higher for development
    'burst_limit' => 300,
],
```

---

### Staging

**Purpose**: Production-like but with additional logging and debugging

| Feature | Status | Configuration | Notes |
|---------|--------|--------------|-------|
| **Security Headers** | Full | Strict CSP, HSTS enabled | Production-like |
| **Debug Mode** | Disabled | `APP_DEBUG=false` | No stack traces |
| **Rate Limiting** | Standard | Production limits | Same as production |
| **CORS** | Restricted | Staging domains only | Limited origins |
| **HTTPS Required** | Enabled | HTTPS only | SSL/TLS required |
| **Error Details** | Limited | Generic messages | No stack traces |
| **Test Routes** | Disabled | `/debug/*` blocked | No debug routes |
| **Logging** | Enhanced | Extra tracing | More detailed logs |
| **Threat Detection** | Enabled | Logging only | Don't block, just log |
| **File Upload Validation** | Standard | 10MB max | Production limits |
| **Session Security** | Strict | `SameSite=Strict` | Secure cookies |

#### CSP (Content Security Policy)

```php
// Staging: Production-like but allow staging domains
"default-src 'self' https://staging.zenamanage.com; " .
"script-src 'self'; " .
"style-src 'self' 'unsafe-inline';"
```

#### Rate Limits

```php
'api' => [
    'requests_per_minute' => 120,  // Standard limits
    'burst_limit' => 200,
],
```

---

### Production

**Purpose**: Maximum security with all hardening enabled

| Feature | Status | Configuration | Notes |
|---------|--------|--------------|-------|
| **Security Headers** | Full | Strict CSP, HSTS, COEP/COOP | All headers enabled |
| **Debug Mode** | Disabled | `APP_DEBUG=false` | Never enable in production |
| **Rate Limiting** | Strict | Lower limits | 60 req/min for API |
| **CORS** | Restricted | Production domains only | Whitelist only |
| **HTTPS Required** | Enforced | HTTPS only, redirect HTTP | Force SSL |
| **Error Details** | Hidden | Generic messages only | No sensitive info |
| **Test Routes** | Blocked | `/debug/*` returns 404 | No debug access |
| **Logging** | Secure | PII redaction | Mask sensitive data |
| **Threat Detection** | Enabled | Block suspicious requests | Active blocking |
| **File Upload Validation** | Strict | 10MB max, virus scan | Full validation |
| **Session Security** | Maximum | `SameSite=Strict`, `Secure` | Secure cookies only |

#### CSP (Content Security Policy)

```php
// Production: Strict policy
"default-src 'self'; " .
"script-src 'self'; " .
"style-src 'self'; " .
"img-src 'self' data:; " .
"connect-src 'self'; " .
"frame-ancestors 'none';"
```

#### Rate Limits

```php
'api' => [
    'requests_per_minute' => 60,   // Stricter limits
    'burst_limit' => 100,
],
'auth' => [
    'requests_per_minute' => 10,   // Very strict for auth
    'burst_limit' => 15,
],
```

---

## Security Headers Configuration

### Development

| Header | Value | Notes |
|--------|-------|-------|
| `Content-Security-Policy` | Relaxed (allows `unsafe-inline`, `unsafe-eval`) | For Vite HMR |
| `Strict-Transport-Security` | Disabled | No HTTPS in dev |
| `X-Frame-Options` | `SAMEORIGIN` | Allow iframes for testing |
| `X-Content-Type-Options` | `nosniff` | Enabled |
| `X-XSS-Protection` | `1; mode=block` | Enabled |
| `Referrer-Policy` | `no-referrer-when-downgrade` | Relaxed |
| `Permissions-Policy` | Permissive | Allow most features |
| `Cross-Origin-Embedder-Policy` | Disabled | Not needed in dev |
| `Cross-Origin-Opener-Policy` | Disabled | Not needed in dev |
| `Cross-Origin-Resource-Policy` | Disabled | Not needed in dev |

### Staging

| Header | Value | Notes |
|--------|-------|-------|
| `Content-Security-Policy` | Strict (production-like) | No unsafe-inline |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | Enabled |
| `X-Frame-Options` | `DENY` | Enabled |
| `X-Content-Type-Options` | `nosniff` | Enabled |
| `X-XSS-Protection` | `1; mode=block` | Enabled |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Enabled |
| `Permissions-Policy` | Restrictive | Limited permissions |
| `Cross-Origin-Embedder-Policy` | `require-corp` | Enabled |
| `Cross-Origin-Opener-Policy` | `same-origin` | Enabled |
| `Cross-Origin-Resource-Policy` | `same-origin` | Enabled |

### Production

| Header | Value | Notes |
|--------|-------|-------|
| `Content-Security-Policy` | Maximum strict | No unsafe-* |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains; preload` | Full HSTS |
| `X-Frame-Options` | `DENY` | Enabled |
| `X-Content-Type-Options` | `nosniff` | Enabled |
| `X-XSS-Protection` | `1; mode=block` | Enabled |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Enabled |
| `Permissions-Policy` | Maximum restrictive | Minimal permissions |
| `Cross-Origin-Embedder-Policy` | `require-corp` | Enabled |
| `Cross-Origin-Opener-Policy` | `same-origin` | Enabled |
| `Cross-Origin-Resource-Policy` | `same-origin` | Enabled |

---

## Rate Limiting Configuration

### Development

```php
'public' => ['requests_per_minute' => 100],
'app' => ['requests_per_minute' => 200],
'admin' => ['requests_per_minute' => 100],
'auth' => ['requests_per_minute' => 50],
```

### Staging

```php
'public' => ['requests_per_minute' => 30],
'app' => ['requests_per_minute' => 120],
'admin' => ['requests_per_minute' => 60],
'auth' => ['requests_per_minute' => 20],
```

### Production

```php
'public' => ['requests_per_minute' => 30],
'app' => ['requests_per_minute' => 60],   // Stricter
'admin' => ['requests_per_minute' => 60],
'auth' => ['requests_per_minute' => 10],  // Very strict
```

---

## Environment Variables

### Development (.env)

```env
APP_ENV=local
APP_DEBUG=true
SECURITY_HEADERS_ENABLED=true
SECURITY_CSP_ENABLED=true
SECURITY_HSTS_ENABLED=false
SECURITY_RATE_LIMITING_ENABLED=true
SECURITY_THREAT_DETECTION_ENABLED=false
SECURITY_LOG_FAILED_LOGINS=true
CORS_ALLOWED_ORIGINS=http://localhost:5173,http://localhost:3000
SESSION_SECURE=false
SESSION_SAME_SITE=lax
```

### Staging (.env.staging)

```env
APP_ENV=staging
APP_DEBUG=false
SECURITY_HEADERS_ENABLED=true
SECURITY_CSP_ENABLED=true
SECURITY_HSTS_ENABLED=true
SECURITY_RATE_LIMITING_ENABLED=true
SECURITY_THREAT_DETECTION_ENABLED=true
SECURITY_LOG_FAILED_LOGINS=true
CORS_ALLOWED_ORIGINS=https://staging.zenamanage.com
SESSION_SECURE=true
SESSION_SAME_SITE=strict
```

### Production (.env.production)

```env
APP_ENV=production
APP_DEBUG=false
SECURITY_HEADERS_ENABLED=true
SECURITY_CSP_ENABLED=true
SECURITY_HSTS_ENABLED=true
SECURITY_RATE_LIMITING_ENABLED=true
SECURITY_THREAT_DETECTION_ENABLED=true
SECURITY_LOG_FAILED_LOGINS=true
CORS_ALLOWED_ORIGINS=https://app.zenamanage.com
SESSION_SECURE=true
SESSION_SAME_SITE=strict
SECURITY_HIDE_DEBUG_INFO=true
SECURITY_DISABLE_ERROR_DETAILS=true
SECURITY_DISABLE_STACK_TRACES=true
SECURITY_REQUIRE_HTTPS=true
```

---

## Pre-Deployment Checklist

### Development → Staging

- [ ] `APP_DEBUG=false`
- [ ] Security headers enabled
- [ ] HSTS enabled
- [ ] Rate limiting enabled
- [ ] CORS restricted to staging domains
- [ ] HTTPS required
- [ ] Test routes disabled
- [ ] Error details hidden

### Staging → Production

- [ ] All staging checks passed
- [ ] Threat detection blocking enabled (not just logging)
- [ ] Maximum strict CSP
- [ ] HSTS preload enabled
- [ ] All debug routes blocked
- [ ] PII redaction in logs enabled
- [ ] File upload virus scanning enabled
- [ ] Session security maximum
- [ ] HTTPS redirect enforced
- [ ] Error details completely hidden

---

## Security Testing

### Verify Security Headers

```bash
# Development
curl -I http://localhost:8000/api/v1/health | grep -i "x-"

# Staging
curl -I https://staging.zenamanage.com/api/v1/health | grep -i "x-"

# Production
curl -I https://app.zenamanage.com/api/v1/health | grep -i "x-"
```

### Verify Rate Limiting

```bash
# Test rate limit (should return 429 after limit)
for i in {1..70}; do curl http://localhost:8000/api/v1/app/projects; done
```

### Verify HTTPS Redirect

```bash
# Production: HTTP should redirect to HTTPS
curl -I http://app.zenamanage.com/api/v1/health
# Should return 301/302 redirect
```

---

## Troubleshooting

### Issue: "CSP blocking Vite HMR in development"

**Solution**: Ensure `APP_ENV=local` and CSP allows `localhost:*` and `unsafe-inline`

### Issue: "Rate limiting too strict in development"

**Solution**: Check `config/rate-limiting.php` - development should have higher limits

### Issue: "Security headers not applied"

**Solution**: 
1. Verify middleware is registered in `app/Http/Kernel.php`
2. Check `SECURITY_HEADERS_ENABLED=true` in `.env`
3. Clear config cache: `php artisan config:clear`

---

## References

- [Security Configuration](../../config/security.php)
- [Security Headers Configuration](../../config/security-headers.php)
- [Rate Limiting Configuration](../../config/rate-limiting.php)
- [Advanced Security Configuration](../../config/advanced-security.php)
- [Production Security Checklist](../PRODUCTION_SECURITY_CHECKLIST.md)

---

*This matrix must be updated whenever security configuration changes.*

