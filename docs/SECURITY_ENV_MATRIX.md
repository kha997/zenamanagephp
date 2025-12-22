# Security Environment Matrix

**Version**: 1.0  
**Last Updated**: January 2025  
**Status**: Active  
**Purpose**: Single source of truth for security configuration across environments

---

## Overview

This matrix defines security settings for DEV, STAGING, and PROD environments to prevent misconfiguration.

---

## Environment Matrix

| Setting | DEV | STAGING | PROD |
|---------|-----|---------|------|
| **CORS** | | | |
| Allowed Origins | `*` (all) | Specific domains | Specific domains only |
| Allowed Methods | `GET, POST, PUT, DELETE, PATCH` | `GET, POST, PUT, DELETE, PATCH` | `GET, POST, PUT, DELETE, PATCH` |
| Allowed Headers | `*` | `Authorization, Content-Type, X-Request-Id` | `Authorization, Content-Type, X-Request-Id` |
| Credentials | `true` | `true` | `true` |
| **Rate Limiting** | | | |
| Login | 10/min | 5/min | 5/min |
| API (authenticated) | 100/min | 60/min | 60/min |
| API (public) | 20/min | 10/min | 10/min |
| Password Reset | 3/hour | 3/hour | 3/hour |
| **MFA** | | | |
| Enabled | `false` | `true` (optional) | `true` (required for admin) |
| Required for Admin | `false` | `true` | `true` |
| **Debug Routes** | | | |
| `/debug/*` | `enabled` | `disabled` | `disabled` |
| `/test/*` | `enabled` | `disabled` | `disabled` |
| Telescope | `enabled` | `enabled` (restricted) | `disabled` |
| **Security Headers** | | | |
| CSP | `report-only` | `enforce` | `enforce` |
| HSTS | `disabled` | `enabled` | `enabled` (max-age=31536000) |
| X-Frame-Options | `SAMEORIGIN` | `DENY` | `DENY` |
| X-Content-Type-Options | `nosniff` | `nosniff` | `nosniff` |
| **Session** | | | |
| Secure Cookies | `false` | `true` | `true` |
| SameSite | `Lax` | `Strict` | `Strict` |
| Lifetime | 120 min | 60 min | 30 min |
| **Logging** | | | |
| Level | `DEBUG` | `INFO` | `WARN` |
| PII Redaction | `disabled` | `enabled` | `enabled` |
| **Error Reporting** | | | |
| Show Errors | `true` | `false` | `false` |
| Log Errors | `true` | `true` | `true` |

---

## Configuration Files

### CORS

**File:** `config/cors.php`

**DEV:**
```php
'allowed_origins' => ['*'],
'allowed_methods' => ['*'],
'allowed_headers' => ['*'],
'supports_credentials' => true,
```

**STAGING/PROD:**
```php
'allowed_origins' => env('CORS_ALLOWED_ORIGINS', 'https://app.zenamanage.com'),
'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'],
'allowed_headers' => ['Authorization', 'Content-Type', 'X-Request-Id'],
'supports_credentials' => true,
```

### Rate Limiting

**File:** `config/rate-limiting.php`

**DEV:**
```php
'login' => ['limit' => 10, 'period' => 60],
'api' => ['limit' => 100, 'period' => 60],
'public' => ['limit' => 20, 'period' => 60],
```

**STAGING/PROD:**
```php
'login' => ['limit' => 5, 'period' => 60],
'api' => ['limit' => 60, 'period' => 60],
'public' => ['limit' => 10, 'period' => 60],
```

### Security Headers

**File:** `config/security-headers.php`

**DEV:**
```php
'csp' => 'report-only',
'hsts' => false,
'x_frame_options' => 'SAMEORIGIN',
```

**STAGING/PROD:**
```php
'csp' => 'enforce',
'hsts' => true,
'hsts_max_age' => 31536000,
'x_frame_options' => 'DENY',
```

---

## Decision Rules

### When to Enable/Disable Features

| Feature | Enable When | Disable When |
|---------|-------------|--------------|
| Debug Routes | DEV only | STAGING/PROD |
| Telescope | DEV/STAGING (restricted) | PROD |
| MFA | STAGING/PROD | DEV |
| PII Redaction | STAGING/PROD | DEV |
| Secure Cookies | STAGING/PROD | DEV (HTTPS not available) |
| HSTS | STAGING/PROD | DEV (HTTPS not available) |

---

## Validation

### Pre-Deployment Checklist

- [ ] CORS configured correctly for environment
- [ ] Rate limits match environment matrix
- [ ] MFA settings match environment requirements
- [ ] Debug routes disabled in STAGING/PROD
- [ ] Security headers configured correctly
- [ ] Session settings match environment
- [ ] Logging level appropriate for environment
- [ ] Error reporting disabled in STAGING/PROD

---

## References

- [Advanced Security Config](config/advanced-security.php)
- [Rate Limiting Config](config/rate-limiting.php)
- [Security Headers Config](config/security-headers.php)
- [Middleware Stack](MIDDLEWARE_STACK.md)

---

*This matrix should be updated whenever security configuration changes.*

