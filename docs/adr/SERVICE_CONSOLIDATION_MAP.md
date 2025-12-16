# Service Consolidation Map

**Last Updated**: 2025-11-18  
**Purpose**: Track service consolidation and deprecation

## Consolidation Status

### ✅ Consolidated

| Old Service | New Service | Status | Migration Notes |
|------------|------------|--------|-----------------|
| - | - | - | - |

### 🔄 In Progress

| Old Service | New Service | Target Date | Blockers |
|------------|------------|-------------|----------|
| `KpiCacheService` | `AdvancedCacheService` | 2025-12 | Need to migrate all usages |
| `ApiResponseCacheService` | `AdvancedCacheService` | 2025-12 | Need to migrate all usages |

### 📋 Planned

| Old Service | New Service | Priority | Notes |
|------------|------------|----------|-------|
| `SecurityService` | `UnifiedSecurityMiddleware` | Medium | Security logic should be in middleware |
| `RateLimitService` | `UnifiedRateLimitMiddleware` | Medium | Rate limiting should be in middleware |

## Deprecation Annotations

Services marked with `@deprecated` should be migrated by the target date.

### Example

```php
/**
 * @deprecated since 2025-11-18
 * Use AdvancedCacheService instead
 * Will be removed in 2026-01
 */
class KpiCacheService
{
    // ...
}
```

## Migration Checklist

For each deprecated service:
- [ ] Find all usages (grep/search)
- [ ] Update to use new service
- [ ] Update tests
- [ ] Remove deprecated service
- [ ] Update documentation

## Usage Statistics

Run `php artisan service:usage-stats` to see current usage of deprecated services.

