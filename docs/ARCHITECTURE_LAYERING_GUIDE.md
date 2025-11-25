# Architecture Layering Guide

**Version**: 1.0  
**Last Updated**: 2025-01-XX  
**Status**: Active  
**Purpose**: Define clear layer boundaries and dependency rules for ZenaManage codebase

---

## Overview

This guide establishes the architectural layers and their contracts to ensure clean separation of concerns, reduce coupling, and improve maintainability. All code must adhere to these layer boundaries.

---

## Layer Definitions

### 1. Domain Layer

**Location**: `app/Services/` (domain-specific services), `app/Models/`, `app/Repositories/`

**Purpose**: Core business logic, domain rules, and data access

**Components**:
- **Domain Services**: `TaskService`, `ProjectService`, `DocumentService`, etc.
- **Models**: `Task`, `Project`, `Document`, `User`, etc.
- **Repositories**: `TaskRepository`, `ProjectRepository`, etc.
- **Domain Events**: `TaskUpdated`, `ProjectCreated`, etc.

**Rules**:
- ✅ Can call other domain services
- ✅ Can call repository layer
- ✅ Can fire domain events
- ❌ Cannot call cross-cutting services directly (use dependency injection via interfaces)
- ❌ Cannot call HTTP/WebSocket clients directly
- ❌ Cannot call infrastructure services directly (use interfaces)

**Example from ZenaManage**:
```php
// ✅ Good: Domain service calling repository (app/Services/TaskService.php)
class TaskService
{
    public function __construct(
        TaskRepository $taskRepository, 
        AuditService $auditService, 
        PermissionService $permissionService,
        TaskStatusTransitionService $transitionService
    ) {
        $this->taskRepository = $taskRepository;
        $this->auditService = $auditService;
        $this->permissionService = $permissionService;
        $this->transitionService = $transitionService;
    }
    
    public function createTask(array $data, string $userId, string $tenantId): Task
    {
        // Business logic validation
        // Permission checks
        // Create via repository
        $task = $this->taskRepository->create($data);
        // Fire domain events
        event(new TaskCreated($task));
        return $task;
    }
}
```

---

### 2. Cross-Cutting Layer

**Location**: `app/Services/` (cross-cutting services), `app/Http/Middleware/`

**Purpose**: Shared utilities, logging, tracing, security, rate limiting

**Components**:
- **Cross-Cutting Services**: `LoggingService`, `TracingService`, `CacheService`, `RateLimitService`
- **Middleware**: `SecurityHeadersMiddleware`, `RateLimitMiddleware`, `TracingMiddleware`
- **Interfaces**: `LoggingInterface`, `RateLimiterInterface`, `CacheInterface`

**Rules**:
- ✅ Can be used by any layer
- ✅ Should be stateless or have minimal state
- ✅ Should not contain business logic
- ❌ Cannot depend on domain services
- ❌ Cannot depend on controllers

**Example from ZenaManage**:
```php
// ✅ Good: Cross-cutting service via dependency injection
// (app/Services/CacheInvalidationService.php)
class CacheInvalidationService
{
    public function __construct(
        AdvancedCacheService $cacheService,  // Cross-cutting service
        CacheKeyService $keyService          // Cross-cutting service
    ) {
        $this->cacheService = $cacheService;
        $this->keyService = $keyService;
    }
}

// Domain service uses cross-cutting service
class TaskService
{
    public function __construct(
        TaskRepository $repository,
        CacheInvalidationService $cacheInvalidation  // Via dependency injection
    ) {}
}
```

---

### 3. Controller Layer

**Location**: `app/Http/Controllers/`

**Purpose**: HTTP request handling, validation, response formatting

**Components**:
- **API Controllers**: `Api\V1\App\TasksController`, `Api\V1\App\ProjectsController`
- **Web Controllers**: `Web\TasksController`, `Web\ProjectsController`
- **Admin Controllers**: `Admin\*`

**Rules**:
- ✅ Can call domain services
- ✅ Can call application services
- ✅ Can validate input
- ✅ Can format responses
- ❌ Cannot call repository directly (must go through service)
- ❌ Cannot contain business logic
- ❌ Cannot call HTTP/WebSocket clients directly (use integration layer)

**Example from ZenaManage**:
```php
// ✅ Good: Controller calling service (app/Http/Controllers/Api/V1/App/TasksController.php)
class TasksController extends BaseApiV1Controller
{
    public function __construct(
        private TaskManagementService $taskService
    ) {}
    
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([...]);
        $tenantId = $this->getTenantId();
        $task = $this->taskService->createTask($validated, $tenantId);
        return $this->successResponse($task, 'Task created successfully', 201);
    }
}

// ❌ Bad: Controller calling repository directly
class TasksController extends Controller
{
    public function store(Request $request)
    {
        // BAD: Bypassing service layer
        $task = Task::create($request->all());
        return response()->json($task);
    }
}
```

---

### 4. Service Layer

**Location**: `app/Services/`

**Purpose**: Business logic orchestration and coordination

**Components**:
- **Domain Services**: `TaskService`, `ProjectService` (business logic)
- **Application Services**: `DashboardService`, `ReportGenerationService` (orchestration)

**Rules**:
- ✅ Can call other domain services
- ✅ Can call repository layer
- ✅ Can call cross-cutting services via interfaces
- ✅ Can fire domain events
- ❌ Cannot call HTTP/WebSocket clients directly (use integration layer)
- ❌ Cannot call external APIs directly (use integration layer)

**Example**:
```php
// ✅ Good: Service using integration layer
interface HttpClientInterface
{
    public function get(string $url, array $headers = []): array;
}

class ExternalApiService
{
    public function __construct(
        private HttpClientInterface $httpClient  // Via interface
    ) {}
}

class TaskService
{
    public function __construct(
        private ExternalApiService $externalApi  // Integration layer
    ) {}
}
```

---

### 5. Integration Layer

**Location**: `app/Services/Integration/` or `app/Http/Client/`

**Purpose**: External system integration, HTTP clients, WebSocket clients, external APIs

**Components**:
- **HTTP Clients**: `HttpClient`, `ApiClient`
- **WebSocket Clients**: `WebSocketClient`
- **External API Services**: `ThirdPartyApiService`

**Rules**:
- ✅ Can use external libraries/packages
- ✅ Can make HTTP requests
- ✅ Can establish WebSocket connections
- ✅ Should implement interfaces defined in domain layer
- ❌ Cannot contain business logic
- ❌ Cannot call domain services directly

**Example**:
```php
// ✅ Good: Integration service implementing interface
interface NotificationServiceInterface
{
    public function send(string $recipient, string $message): void;
}

class EmailNotificationService implements NotificationServiceInterface
{
    public function send(string $recipient, string $message): void
    {
        // External API call
        Mail::to($recipient)->send(new NotificationMail($message));
    }
}

// Domain service uses interface
class TaskService
{
    public function __construct(
        private NotificationServiceInterface $notification
    ) {}
}
```

---

## Dependency Rules

### Allowed Dependencies

| From Layer | To Layer | Allowed? | Notes |
|------------|----------|----------|-------|
| Controller | Domain Service | ✅ Yes | Primary interaction |
| Controller | Application Service | ✅ Yes | For orchestration |
| Controller | Cross-Cutting | ✅ Yes | Via dependency injection |
| Domain Service | Repository | ✅ Yes | Data access |
| Domain Service | Other Domain Service | ✅ Yes | Service composition |
| Domain Service | Cross-Cutting | ✅ Yes | Via interface |
| Domain Service | Integration | ✅ Yes | Via interface |
| Application Service | Domain Service | ✅ Yes | Orchestration |
| Integration | External APIs | ✅ Yes | External systems |

### Prohibited Dependencies

| From Layer | To Layer | Prohibited? | Reason |
|------------|----------|-------------|--------|
| Controller | Repository | ❌ No | Must go through service |
| Domain Service | Controller | ❌ No | Circular dependency |
| Domain Service | HTTP Client (direct) | ❌ No | Use integration layer |
| Domain Service | WebSocket Client (direct) | ❌ No | Use integration layer |
| Cross-Cutting | Domain Service | ❌ No | Cross-cutting should be independent |
| Integration | Domain Service | ❌ No | Integration should be independent |

---

## Enforcement

### Static Analysis

**PHPStan/Larastan**:
- Level 8+ recommended
- Custom rules to detect layer violations
- Check for direct repository calls from controllers
- Check for direct HTTP client calls from services

**Configuration** (`phpstan.neon`):
```yaml
parameters:
    level: 8
    paths:
        - app
    excludePaths:
        - app/Console/Commands
    customRules:
        - App\Rules\LayerBoundaryRule
```

### Deptrac (Optional)

**Configuration** (`deptrac.yaml`):
```yaml
layers:
  - name: Controller
    collectors:
      - type: className
        regex: App\\Http\\Controllers\\.*
  
  - name: Domain
    collectors:
      - type: className
        regex: App\\Services\\.*Service
  
  - name: Repository
    collectors:
      - type: className
        regex: App\\Repositories\\.*

ruleset:
  Controller:
    - Domain
    - Repository: ❌  # Prohibited
```

### CI Workflow

**File**: `.github/workflows/architecture-lint.yml` (to be created)

```yaml
name: Architecture Lint

on: [push, pull_request]

jobs:
  layer-check:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist
      - name: Run PHPStan
        run: composer run phpstan || true  # Don't fail if not configured
      - name: Check Deprecated Usage
        run: php scripts/check-deprecated-usage.php --strict
      - name: Audit Blade Views
        run: php scripts/audit-blade-views.php
```

**Note**: This workflow should be created to enforce layer boundaries in CI.

---

## Common Violations & Fixes

### Violation 1: Controller Calling Repository Directly

**❌ Bad**:
```php
class TasksController extends Controller
{
    public function index()
    {
        $tasks = Task::where('tenant_id', auth()->user()->tenant_id)->get();
        return response()->json($tasks);
    }
}
```

**✅ Good**:
```php
class TasksController extends Controller
{
    public function __construct(
        private TaskService $taskService
    ) {}
    
    public function index()
    {
        $tasks = $this->taskService->getTasksForTenant(auth()->user()->tenant_id);
        return response()->json($tasks);
    }
}
```

### Violation 2: Service Calling HTTP Client Directly

**❌ Bad**:
```php
class TaskService
{
    public function syncWithExternalSystem()
    {
        $response = Http::get('https://api.external.com/tasks');
        // Process response
    }
}
```

**✅ Good**:
```php
interface ExternalApiInterface
{
    public function fetchTasks(): array;
}

class ExternalApiService implements ExternalApiInterface
{
    public function fetchTasks(): array
    {
        return Http::get('https://api.external.com/tasks')->json();
    }
}

class TaskService
{
    public function __construct(
        private ExternalApiInterface $externalApi
    ) {}
    
    public function syncWithExternalSystem()
    {
        $tasks = $this->externalApi->fetchTasks();
        // Process tasks
    }
}
```

### Violation 3: Cross-Cutting Service Depending on Domain

**❌ Bad**:
```php
class LoggingService
{
    public function logTaskCreation(Task $task)
    {
        // BAD: Cross-cutting depends on domain
        Log::info('Task created', ['task_id' => $task->id]);
    }
}
```

**✅ Good**:
```php
class LoggingService
{
    public function log(string $level, string $message, array $context = []): void
    {
        // Good: Generic logging, no domain dependency
        Log::log($level, $message, $context);
    }
}

class TaskService
{
    public function __construct(
        private LoggingInterface $logger
    ) {}
    
    public function createTask(array $data): Task
    {
        $task = $this->repository->create($data);
        $this->logger->log('info', 'Task created', ['task_id' => $task->id]);
        return $task;
    }
}
```

---

## Migration Guide

### Step 1: Identify Violations

Run static analysis to identify layer violations:
```bash
# Check for deprecated usage
php scripts/check-deprecated-usage.php

# Audit Blade views for service calls
php scripts/audit-blade-views.php

# Run PHPStan if configured
composer run phpstan
```

### Step 2: Create Interfaces

For cross-cutting and integration services, create interfaces:
```php
// app/Contracts/LoggingInterface.php
interface LoggingInterface
{
    public function log(string $level, string $message, array $context = []): void;
}
```

### Step 3: Refactor Gradually

1. Start with controllers → services
2. Move HTTP client calls to integration layer
3. Extract interfaces for cross-cutting services
4. Update dependency injection

### Step 4: Enforce in CI

Add architecture linting to CI pipeline to prevent new violations.

---

## ZenaManage-Specific Examples

### Real Examples from Codebase

#### ✅ Good: TaskService (Domain Layer)
```php
// app/Services/TaskService.php
class TaskService
{
    public function __construct(
        TaskRepository $taskRepository,        // ✅ Repository layer
        AuditService $auditService,            // ✅ Other domain service
        PermissionService $permissionService,  // ✅ Other domain service
        TaskStatusTransitionService $transitionService  // ✅ Domain service
    ) {}
}
```

#### ✅ Good: TasksController (Controller Layer)
```php
// app/Http/Controllers/Api/V1/App/TasksController.php
class TasksController extends BaseApiV1Controller
{
    public function __construct(
        private TaskManagementService $taskService  // ✅ Domain service
    ) {}
    
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([...]);
        $task = $this->taskService->createTask($validated, $tenantId);
        return $this->successResponse($task);
    }
}
```

#### ✅ Good: CacheInvalidationService (Cross-Cutting)
```php
// app/Services/CacheInvalidationService.php
class CacheInvalidationService
{
    public function __construct(
        AdvancedCacheService $cacheService,  // ✅ Cross-cutting service
        CacheKeyService $keyService          // ✅ Cross-cutting service
    ) {}
    
    // Used by domain services via dependency injection
    public function forTaskUpdate($task): void { ... }
}
```

#### ❌ Bad: Controller Calling Repository Directly
```php
// ❌ This violates layer boundaries
class TasksController extends Controller
{
    public function index()
    {
        // BAD: Bypassing service layer
        $tasks = Task::where('tenant_id', auth()->user()->tenant_id)->get();
        return response()->json($tasks);
    }
}
```

---

## References

- [ADR-001: Service Layering Guide](adr/ADR-001-Service-Layering-Guide.md)
- [Middleware Consolidation](MIDDLEWARE_CONSOLIDATION.md)
- [Clean Architecture Principles](https://blog.cleancoder.com/uncle-bob/2012/08/13/the-clean-architecture.html)
- [Domain-Driven Design](https://martinfowler.com/bliki/DomainDrivenDesign.html)
- [Laravel Service Container](https://laravel.com/docs/container)

---

## Questions & Support

For questions about layer boundaries or to report violations, please:
1. Check this guide first
2. Review ADR-001
3. Consult with architecture team
4. Create an issue if clarification is needed

