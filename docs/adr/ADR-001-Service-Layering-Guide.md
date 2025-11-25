# ADR-001: Service Layering Guide

**Status**: Accepted  
**Date**: 2025-11-18  
**Context**: Multiple services and middleware have been created over time, leading to sprawl and unclear boundaries. We need clear guidelines for service organization and dependencies.

## Decision

Establish a clear service layering architecture with defined boundaries and dependency rules.

## Service Layers

### 1. Domain Services (Business Logic)
**Location**: `app/Services/`
**Purpose**: Core business logic, domain rules, orchestration
**Examples**:
- `ProjectManagementService`
- `TaskManagementService`
- `DocumentManagementService`
- `PermissionService`

**Rules**:
- Can call Repository layer
- Can call other Domain Services
- CANNOT call Infrastructure Services directly (use interfaces)
- CANNOT call Cross-cutting Services directly (use dependency injection)

### 2. Infrastructure Services (External Dependencies)
**Location**: `app/Services/Infrastructure/` or `app/Services/`
**Purpose**: External systems, file storage, email, queues
**Examples**:
- `MediaService` (file uploads)
- `EmailService` (email sending)
- `QueueService` (job queuing)
- `CacheService` (caching)

**Rules**:
- Can be called by Domain Services via interfaces
- Should implement interfaces defined in Domain layer
- Can use external libraries/packages

### 3. Cross-cutting Services (Shared Utilities)
**Location**: `app/Services/`
**Purpose**: Shared utilities, logging, tracing, error handling
**Examples**:
- `ErrorEnvelopeService`
- `TracingService`
- `ObservabilityService`
- `CacheKeyService`

**Rules**:
- Can be used by any layer
- Should be stateless or have minimal state
- Should not contain business logic

### 4. Application Services (Orchestration)
**Location**: `app/Services/`
**Purpose**: Coordinate multiple domain services for complex workflows
**Examples**:
- `DashboardService` (aggregates multiple data sources)
- `ReportGenerationService` (coordinates data collection)

**Rules**:
- Can call multiple Domain Services
- Should not contain business logic (delegate to Domain Services)
- Can use Cross-cutting Services

## Middleware Organization

### 1. Authentication & Authorization
- `Authenticate`
- `AbilityMiddleware`
- `EnsureAdminAccess`

### 2. Security
- `SecurityHeadersMiddleware`
- `UnifiedSecurityMiddleware`
- `BruteForceProtectionMiddleware`

### 3. Performance & Observability
- `TracingMiddleware`
- `PerformanceLoggingMiddleware`
- `QueryBudgetMiddleware`
- `MetricsMiddleware`

### 4. Request Processing
- `ErrorEnvelopeMiddleware`
- `RequestCorrelationMiddleware`
- `TenantIsolationMiddleware`

### 5. Validation & Idempotency
- `IdempotencyMiddleware`
- `UnifiedValidationMiddleware`

## Dependency Rules

1. **Domain Services** → Repository (via Eloquent models)
2. **Domain Services** → Other Domain Services (allowed)
3. **Domain Services** → Infrastructure Services (via interfaces only)
4. **Domain Services** → Cross-cutting Services (allowed)
5. **Application Services** → Domain Services (allowed)
6. **Controllers** → Domain/Application Services (allowed)
7. **Controllers** → Infrastructure Services (discouraged, use Domain Services)

## Consolidation Map

### Services to Consolidate

| Current Service | Target Service | Reason |
|----------------|---------------|---------|
| `KpiCacheService` | `AdvancedCacheService` | Duplicate caching logic |
| `ApiResponseCacheService` | `AdvancedCacheService` | Duplicate caching logic |
| `SecurityService` | `UnifiedSecurityMiddleware` | Security logic should be in middleware |
| `RateLimitService` | `UnifiedRateLimitMiddleware` | Rate limiting should be in middleware |
| `LoggingService` | `TracingService` / Laravel Log | Use Laravel's built-in logging |

### Deprecation Timeline

- **Phase 1 (2025-11)**: Mark services as deprecated, add `@deprecated` annotations
- **Phase 2 (2025-12)**: Update all usages to new services
- **Phase 3 (2026-01)**: Remove deprecated services

## Module Boundaries

### Linting Rules

Services should not:
- Import from `app/Infrastructure/` directly (use interfaces)
- Call other services in circular dependencies
- Contain business logic in Infrastructure layer

### Enforcement

- PHPStan/Psalm rules to detect violations
- CI checks for deprecated service usage
- Code review checklist

## Examples

### ✅ Good: Domain Service calling Repository
```php
class ProjectManagementService
{
    public function __construct(
        private ProjectRepository $repository
    ) {}
    
    public function createProject(array $data): Project
    {
        // Business logic here
        return $this->repository->create($data);
    }
}
```

### ❌ Bad: Domain Service calling Infrastructure directly
```php
class ProjectManagementService
{
    public function createProject(array $data): Project
    {
        // BAD: Direct call to infrastructure
        $file = Storage::disk('s3')->put(...);
    }
}
```

### ✅ Good: Using interface
```php
interface FileStorageInterface
{
    public function store(string $path, $content): string;
}

class ProjectManagementService
{
    public function __construct(
        private FileStorageInterface $storage
    ) {}
}
```

## Consequences

**Positive**:
- Clear boundaries and responsibilities
- Easier to test and maintain
- Reduced coupling
- Better code organization

**Negative**:
- Initial refactoring effort
- Need to update existing code
- Learning curve for team

## References

- Clean Architecture principles
- Domain-Driven Design
- Laravel Service Container best practices

