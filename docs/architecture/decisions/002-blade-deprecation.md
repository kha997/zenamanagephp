# ADR-002: Blade Deprecation Plan

## Status
**Proposed** - 2025-01-18

## Context

ZenaManage currently has two UI layers:
- **Blade views** (`resources/views/app/*`) - Legacy server-rendered views
- **React SPA** (`frontend/src/pages/*`) - Modern client-side application

This dual-layer approach creates several risks:
1. **Feature drift**: Business logic and UI can diverge between Blade and React
2. **Maintenance burden**: Changes must be implemented in two places
3. **Inconsistency**: User experience differs between Blade and React views
4. **Security risk**: Blade views may bypass API validation if they call services directly

## Decision

**"Freeze" Blade views** - Blade will become view-only shells that:
- Render only static content or mount React components
- **Prohibit** direct service calls (all business logic via API)
- Serve as fallback/legacy views for `/admin/*` routes only
- All `/app/*` routes migrate to React SPA

## Rules

### ✅ Allowed in Blade Views
- Rendering static HTML/CSS
- Mounting React components via `<div id="react-root"></div>`
- API calls via JavaScript/fetch
- Displaying data passed from controllers (read-only)
- Including shared components (`@include('components.shared.*')`)

### ❌ Prohibited in Blade Views
- Direct service calls: `App\Services\*`
- Direct model queries: `App\Models\*::query()`
- Business logic: calculations, validations, transformations
- Database writes: `Model::create()`, `Model::update()`, etc.
- Event dispatching: `event()`, `Event::dispatch()`

### Route Migration
- `/app/*` routes → React SPA (via `AppController::handle`)
- `/admin/*` routes → Blade views (legacy, view-only)
- Banner "Legacy - View-Only" displayed on all Blade views

## Implementation

### Phase 1: Documentation & Banner (Current)
- [x] ADR document created
- [ ] Legacy banner component created
- [ ] Banner included in all Blade layouts

### Phase 2: Linting & Enforcement
- [ ] Script to check Blade views for service calls
- [ ] CI workflow to fail on service calls in Blade
- [ ] Deprecation warnings in Blade views

### Phase 3: Migration
- [ ] All `/app/*` routes serve React SPA
- [ ] Blade views become shells only
- [ ] Remove business logic from Blade controllers

## Consequences

### Positive
- Single source of truth for business logic (API)
- Consistent user experience (React SPA)
- Reduced maintenance burden
- Better security (all writes via API)

### Negative
- Initial migration effort
- Some Blade views may need refactoring
- Developers must learn to avoid service calls in Blade

## Compliance

All Blade views must:
1. Display the legacy banner
2. Pass lint checks (no service calls)
3. Use API endpoints for all data operations
4. Mount React components for interactive features

## References

- [Production Hardening Plan - Gói 8](../production-hardening-plan-6d5745.plan.md#gói-8-deprecation-plan-cho-blade)
- [Architecture Documentation](../../architecture/ARCHITECTURE_DOCUMENTATION.md)
- [Route Architecture](../../ROUTE_ARCHITECTURE.md)

