# Service Catalog

**Version**: 1.0  
**Last Updated**: January 2025  
**Status**: Active  
**Purpose**: Single source of truth for approved services, deprecated services, and service consolidation guidelines

---

## Overview

This catalog lists all services in the ZenaManage system, categorized by domain and status. It serves as a reference for developers to understand which services are approved for use and which should be avoided or migrated.

**Rule**: Do not create a new service if an equivalent service already exists. Check this catalog first.

---

## Service Categories

### 1. Core/Facade Services (Approved)

These are the main entry points for each domain. Use these services for domain operations.

#### Projects Domain
- ✅ **`ProjectManagementService`** - Facade for project operations
- ✅ **`ProjectService`** - Core project operations
- ✅ **`ProjectAnalyticsService`** - Project analytics and metrics
- ✅ **`ProjectAuditService`** - Project audit trail

#### Tasks Domain
- ✅ **`TaskManagementService`** - Facade for task operations
- ✅ **`TaskService`** - Core task operations (⚠️ Check for deprecation)
- ✅ **`TaskStatusTransitionService`** - Task status workflow management
- ✅ **`TaskAssignmentService`** - Task assignment logic
- ✅ **`TaskDependencyService`** - Task dependency management
- ✅ **`TaskCommentManagementService`** - Task comments

#### Documents Domain
- ✅ **`DocumentService`** - Facade for document operations
- ✅ **`FileManagementService`** - File storage operations
- ✅ **`SecureFileUploadService`** - Secure file upload handling
- ✅ **`MediaService`** - Media file operations (images, videos)

#### Tenants Domain
- ✅ **`TenantProvisioningService`** - Tenant creation and setup
- ✅ **`TenantContext`** - Tenant context management
- ✅ **`TenantCacheService`** - Tenant-specific caching

#### RBAC Domain
- ✅ **`PermissionService`** - Facade for permission checking
- ✅ **`RBACManager`** - RBAC operations manager
- ✅ **`RBACSyncService`** - Syncs permissions from config to database
- ✅ **`PermissionMatrixService`** - Permission matrix calculations (internal helper)
- ✅ **`AbilityMatrixService`** - Ability matrix calculations (internal helper)

#### Dashboard Domain (Application Service)
- ✅ **`DashboardService`** - Facade for dashboard operations
- ✅ **`DashboardDataAggregationService`** - Aggregates data from multiple sources
- ✅ **`RealTimeDashboardService`** - Real-time dashboard updates
- ✅ **`DashboardRoleBasedService`** - Role-based dashboard customization
- ✅ **`KpiService`** - KPI calculations

#### Core/Cross-Cutting Services
- ✅ **`ErrorEnvelopeService`** - Standardized error response format
- ✅ **`TracingService`** - Request tracing and correlation IDs
- ✅ **`ObservabilityService`** - Metrics and observability
- ✅ **`CacheKeyService`** - Cache key generation
- ✅ **`RequestCorrelationService`** - Request ID correlation
- ✅ **`ComprehensiveLoggingService`** - Structured logging

#### Infrastructure Services
- ✅ **`EmailService`** - Email sending
- ✅ **`MediaService`** - File storage operations
- ✅ **`CacheService`** - Basic caching operations
- ✅ **`AdvancedCacheService`** - Advanced caching features (use instead of deprecated cache services)

---

## Deprecated Services

These services are deprecated and should not be used in new code. Migrate existing code to the replacement service.

### Deprecated with Replacement

| Deprecated Service | Replacement | Deprecated Since | Removal Date | Notes |
|-------------------|-------------|------------------|--------------|-------|
| `KpiCacheService` | `AdvancedCacheService` + `CacheInvalidationService` | 2025-11-18 | 2026-01 | Duplicate caching logic |
| `ApiResponseCacheService` | `AdvancedCacheService` | Planned | TBD | Duplicate caching logic |
| `ErrorHandlingService` | `ErrorEnvelopeService` | TBD | TBD | Use ErrorEnvelopeService for error formatting |
| `ErrorHandlerService` | `ErrorEnvelopeService` | TBD | TBD | Use ErrorEnvelopeService |

### Deprecated (No Direct Replacement)

| Deprecated Service | Status | Notes |
|-------------------|--------|-------|
| `AdvancedSearchService` | Disabled | File: `.disabled` - Not currently used |
| `WorkflowAutomationService` | Disabled | File: `.disabled` - Not currently used |
| `BackupService` | Disabled | File: `.disabled` - Not currently used |
| `TemplateImportExportService` | Disabled | File: `.disabled` - Not currently used |
| `ThirdPartyIntegrationService` | Disabled | File: `.disabled` - Not currently used |
| `AdvancedAuthzService` | Disabled | File: `.disabled` - Not currently used |
| `PasswordPolicyService` | Disabled | File: `.disabled` - Not currently used |

---

## Services to Consolidate

These services have overlapping functionality and should be consolidated.

### Cache Services

**Current State**:
- `CacheService` - Basic caching
- `AdvancedCacheService` - Advanced caching ✅ **Use this**
- `KpiCacheService` - KPI-specific caching ⚠️ **Deprecated**
- `ApiResponseCacheService` - API response caching ⚠️ **To be deprecated**
- `TenantCacheService` - Tenant-specific caching ✅ **Keep (domain-specific)**
- `RedisCachingService` - Redis-specific caching ⚠️ **Consider merging into AdvancedCacheService**

**Consolidation Plan**:
- Keep: `AdvancedCacheService` (main caching service)
- Keep: `TenantCacheService` (domain-specific, uses AdvancedCacheService internally)
- Deprecate: `KpiCacheService`, `ApiResponseCacheService`
- Merge: `RedisCachingService` functionality into `AdvancedCacheService`

### Security Services

**Current State**:
- `SecurityService` - Basic security operations
- `AdvancedSecurityService` - Advanced threat detection ✅ **Use for threat detection**
- `SecurityHeadersService` - Security headers management
- `SecurityMonitoringService` - Security monitoring
- `SecurityAuditService` - Security audit trail
- `SecureAuditService` - Secure audit operations

**Consolidation Plan**:
- Keep: `AdvancedSecurityService` (for threat detection)
- Keep: `SecurityAuditService` (for audit trail)
- Review: `SecurityService`, `SecurityHeadersService`, `SecurityMonitoringService` - may be redundant

### Error Handling Services

**Current State**:
- `ErrorEnvelopeService` ✅ **Use this** - Standardized error format
- `ErrorHandlingService` ⚠️ **Deprecated** - Enhanced error handling (uses ErrorEnvelopeService internally)
- `ErrorHandlerService` ⚠️ **Disabled** - Old error handler

**Consolidation Plan**:
- Use: `ErrorEnvelopeService` for all error formatting
- Remove: `ErrorHandlingService` (migrate to ErrorEnvelopeService)
- Remove: `ErrorHandlerService` (already disabled)

### Logging Services

**Current State**:
- `ComprehensiveLoggingService` ✅ **Use this** - Structured logging
- `StructuredLoggingService` - Structured logging ⚠️ **Check if duplicate**
- `QueryLoggingService` - Query-specific logging ✅ **Keep (specialized)**

**Consolidation Plan**:
- Keep: `ComprehensiveLoggingService` (main logging service)
- Keep: `QueryLoggingService` (specialized for queries)
- Review: `StructuredLoggingService` - may be duplicate of ComprehensiveLoggingService

---

## Service Creation Rules

### Before Creating a New Service

1. **Check this catalog** - Does an equivalent service already exist?
2. **Check domain** - Does it belong to an existing domain or is it a new domain?
3. **Check dependencies** - Can it be a helper method in an existing service?
4. **Check scope** - Is it domain-specific or cross-cutting?

### Naming Conventions

- **Facade Services**: `{Domain}ManagementService` or `{Domain}Service`
- **Internal Services**: `{Domain}{SpecificPurpose}Service`
- **Infrastructure Services**: `{Purpose}Service`
- **Cross-cutting Services**: `{Purpose}Service` (e.g., `ErrorEnvelopeService`)

### Service Organization

- **Domain Services**: `app/Services/` (flat structure for now)
- **Future**: `app/Domains/{Domain}/Services/` (planned migration)
- **Infrastructure Services**: `app/Services/Infrastructure/` or `app/Services/`
- **Cross-cutting Services**: `app/Services/`

---

## Migration Guidelines

### Migrating from Deprecated Services

1. **Identify usage**: Use grep/search to find all usages
   ```bash
   grep -r "KpiCacheService" app/ tests/
   ```

2. **Update imports**: Change import statements
   ```php
   // Old
   use App\Services\KpiCacheService;
   
   // New
   use App\Services\AdvancedCacheService;
   use App\Services\CacheInvalidationService;
   ```

3. **Update method calls**: Adapt to new service API
   ```php
   // Old
   $kpiCache->getKpiData($tenantId);
   
   // New
   $cacheKey = CacheKeyService::kpi($tenantId);
   $kpiData = AdvancedCacheService::remember($cacheKey, function() {
       return KpiService::calculate($tenantId);
   });
   ```

4. **Update tests**: Ensure tests pass with new service
5. **Remove deprecated service**: After all usages migrated

### Adding Deprecation Annotations

When deprecating a service, add this annotation:

```php
/**
 * @deprecated since 2025-XX-XX
 * Use {ReplacementService} instead
 * Migration: {brief migration instructions}
 * Will be removed in {removal date}
 */
class DeprecatedService
{
    // Log warning when used
    public function __construct()
    {
        Log::warning('DeprecatedService is deprecated. Use ReplacementService instead.');
    }
}
```

---

## Service Usage Statistics

To check usage of deprecated services, run:

```bash
# Check for deprecated service usage
grep -r "KpiCacheService" app/ tests/ routes/

# Check for disabled service usage (should be zero)
grep -r "AdvancedSearchService\|WorkflowAutomationService\|BackupService" app/ tests/ routes/
```

---

## Service Dependency Rules

1. **Domain Services** → Can call Repository layer, other Domain Services, Cross-cutting Services
2. **Domain Services** → Cannot call Infrastructure Services directly (use interfaces)
3. **Application Services** → Can call multiple Domain Services
4. **Infrastructure Services** → Can be called via interfaces only
5. **Cross-cutting Services** → Can be used by any layer

---

## References

- [Architecture Overview](ARCHITECTURE_OVERVIEW.md)
- [ADR-001: Service Layering Guide](adr/ADR-001-Service-Layering-Guide.md)
- [Service Consolidation Map](adr/SERVICE_CONSOLIDATION_MAP.md)

---

## Update Process

When adding, deprecating, or consolidating services:

1. Update this catalog
2. Add deprecation annotations to code
3. Update migration guide if needed
4. Notify team via PR description
5. Update architecture documentation

---

*This catalog should be updated whenever services are added, deprecated, or consolidated.*

