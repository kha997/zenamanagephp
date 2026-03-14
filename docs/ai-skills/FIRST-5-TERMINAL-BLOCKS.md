# First 5 Terminal Command Blocks

These are the first command blocks worth running when you need to re-ground yourself in the repo's operating truth.

## 1) Clear caches and load the app cleanly
```bash
php artisan optimize:clear
```

## 2) Run the composite SSOT gate
```bash
composer ssot:lint
```

## 3) Run the default fast test path
```bash
composer test:fast
```

## 4) Inspect route truth for strict surfaces
```bash
php artisan route:list --except-vendor -v --path='api/zena'
php artisan route:list --except-vendor -v --path='api/v1'
```

## 5) Run the high-value route / invariant tests
```bash
php artisan test --filter 'ApiSecurityMiddlewareGateTest|ZenaRouteSurfaceInvariantTest|RouteMiddlewareSecurityContractTest|RouteSsotGuardTest' -v
./scripts/ci/zena-invariants
./scripts/ci/zena-invariants-mysql
```
