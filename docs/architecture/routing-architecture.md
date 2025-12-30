# Routing Architecture Rules

This document defines the routing architecture as law for every contributor.
Any deviation introduces security risk or duplicated behavior and must be approved before merging.

## Single Source of Truth
- `routes/api.php` is the only place where API routes are composed; no other file may auto-mount or duplicate those routes.
- ServiceProviders must not call `loadRoutesFrom` or otherwise mount routes; the service container must remain agnostic about HTTP composition.

## Module Routes Policy
- Every module owns `src/<Module>/routes/api.php`.
- Module route files define their own prefixes (for example `v1` or `api/v1`) and any middleware they need within that file.
- `routes/api.php` mounts module routes explicitly:

```php
// routes/api.php
require base_path('src/Accounting/routes/api.php');
require base_path('src/Projects/routes/api.php');
```

## Prefix Rules
- Module routes are never wrapped inside another `v1` or `api/v1` group when mounted from `routes/api.php`.
- No duplicate prefixes (`api/v1/api/v1`, `v1/v1`, etc.) are permitted; each module owns its single prefix level.

## Middleware Rules
- The global security stack is always `auth:sanctum`, `tenant.isolation`, `rbac`.
- Business routes inherit middleware from their parent groups; modules may add finer-grained RBAC but must not bypass tenant isolation.
- Middleware applied inside a module must be additive, never subtractive relative to the global stack.

## Forbidden Practices
- `loadRoutesFrom` in ServiceProviders is explicitly forbidden.
- Dynamically mutating routes in `app()->booted()` or any runtime hook is disallowed.
- Routes may not be defined simultaneously in ServiceProviders and `routes/api.php`; the sole composition point is `routes/api.php`.

## Validation & Safety Nets
- `RouteHygieneTest` ensures security middleware consistency across every route.
- `TenantIsolationProjectsTest` guarantees tenant boundary safety.
- CI fails if:
  - duplicated METHOD+URI routes exist,
  - double prefixes such as `api/v1/api/v1` or `v1/v1` are detected.

## Quick Checklist for New Modules
1. Put routing definitions in `src/<Module>/routes/api.php`.
2. Define the module prefix and its middleware there.
3. Mount the module from `routes/api.php` using `require base_path(...)`.
4. Do not wrap module routes with another prefix group.
5. Do not use `loadRoutesFrom`, runtime hooks, or duplicate route definitions in a ServiceProvider.
