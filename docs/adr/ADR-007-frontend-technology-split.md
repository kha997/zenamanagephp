# ADR-007: Frontend Technology Split

## Status
Accepted

## Date
2025-01-XX

## Context

The ZenaManage frontend currently has two conflicting implementations for `/app/*` routes:

1. **Blade + Alpine.js** templates (`resources/views/app/*`) - Legacy implementation with numerous bugs that are difficult to fix
2. **React + TypeScript** SPA (`frontend/`) - Modern implementation already exists but not fully integrated

This dual implementation causes:
- Inconsistent user experience (different UIs on different ports)
- Code duplication and maintenance overhead
- Architecture violations (same route handled by multiple technologies)
- Developer confusion about which technology to use

The Blade/Alpine.js frontend has accumulated too many bugs (Alpine.js initialization errors, Chart.js conflicts, authentication issues, etc.) that make it impractical to maintain.

## Decision

Implement a **clear technology split** based on route patterns:

1. **`/app/*` routes** → **React + TypeScript SPA** (Official, no new Blade development)
   - All tenant-scoped application routes
   - Modern SPA architecture
   - Type-safe with TypeScript
   - Component-based development

2. **`/admin/*` routes** → **Blade + Alpine.js** (Legacy, keep as-is)
   - System-wide administration routes
   - Simple CRUD operations
   - Minimal JavaScript complexity
   - Server-side rendering

3. **Auth/public pages** → **Blade** (Simple, server-rendered)
   - Login, registration, password reset
   - Public-facing pages
   - SEO-friendly

4. **Blade entry point** → **React SPA wrapper** (`resources/views/app/spa.blade.php`)
   - Single entry point for React app
   - Vite asset integration
   - Server-side data hydration (if needed later)

## Rules

### For `/app/*` Routes:
- ✅ **React + TypeScript is the official technology**
- ❌ **No new Blade templates** for `/app/*` routes
- ❌ **No new Alpine.js components** for `/app/*` routes
- ✅ **All business logic** goes through API layer
- ✅ **React Router** handles client-side routing

### For `/admin/*` Routes:
- ✅ **Blade templates** remain the standard
- ✅ **Simple CRUD operations** only
- ❌ **No complex JavaScript** features
- ❌ **No heavy interactivity** (if needed, consider Phase 2 migration)
- ⚠️ **If admin grows complex** → Consider Phase 2 migration to React

### Migration Strategy:
- **Phase 1 (Current)**: Migrate `/app/*` to React
- **Phase 2 (Future)**: Migrate `/admin/*` to React if complexity increases
- **Rollback Plan**: Keep archived Blade templates for 3 months

## Consequences

### Positive

1. **Unified Technology Stack**
   - Single source of truth for `/app/*` routes
   - Consistent developer experience
   - Clear technology boundaries

2. **Maintainability**
   - TypeScript provides type safety
   - Component-based architecture is easier to maintain
   - Modern tooling (Vite, React DevTools)

3. **Scalability**
   - React component library can grow organically
   - Code splitting and lazy loading built-in
   - Better performance optimization opportunities

4. **Developer Experience**
   - Clear guidelines on which technology to use
   - No confusion about route handling
   - Better debugging tools (React DevTools)

5. **Backend Stability**
   - API layer remains unchanged
   - No breaking changes to backend
   - Gradual migration reduces risk

### Negative

1. **Migration Effort**
   - Need to migrate existing Blade templates
   - Consolidate multiple API clients
   - Update routing configuration

2. **Learning Curve**
   - Team needs React/TypeScript knowledge
   - New patterns and conventions to learn
   - Different development workflow

3. **Build Complexity**
   - Requires Vite build step
   - More complex deployment process
   - Asset management complexity

4. **Dual Technology Maintenance**
   - Still need to maintain Blade for `/admin/*`
   - Two different development workflows
   - Different testing approaches

## Implementation

### Phase 1: Foundation (Week 1)
- Create ADR-007 document
- Create FRONTEND_GUIDELINES.md
- Archive old Blade templates
- Create React SPA entry point

### Phase 2: API & Design System (Week 1-2)
- Consolidate API clients
- Setup shared design tokens
- Sync Tailwind configurations

### Phase 3: SPA Setup (Week 2)
- Configure routes
- Setup Vite for production
- Create SPA entry point

### Phase 4: Component Migration (Week 3-4)
- Migrate core components
- Build feature modules
- Implement RBAC integration

### Phase 5: Testing & Integration (Week 5)
- Setup testing suite
- Integration testing
- Performance testing

### Phase 6: Documentation & Cleanup (Week 6)
- Update documentation
- Remove obsolete files
- Update CI/CD

## Alternatives Considered

### Option A: Pure React for Everything
- **Pros**: Single technology, consistent
- **Cons**: Too much migration effort, admin doesn't need complexity
- **Decision**: Rejected - admin routes are simple, Blade is sufficient

### Option B: Pure Blade for Everything
- **Pros**: Simple, server-rendered, SEO-friendly
- **Cons**: Current Blade implementation has too many bugs, less maintainable
- **Decision**: Rejected - bugs are too difficult to fix, React provides better DX

### Option C: Hybrid Approach (Current Decision)
- **Pros**: Balanced, pragmatic, reduces migration scope
- **Cons**: Dual technology maintenance
- **Decision**: Accepted - best balance of effort and benefit

## Success Criteria

1. ✅ All `/app/*` routes serve React SPA
2. ✅ No Blade templates for `/app/*` (except entry point)
3. ✅ Single unified API client
4. ✅ Shared design tokens between Blade and React
5. ✅ All tests passing
6. ✅ Performance: p95 < 500ms page load
7. ✅ Accessibility: WCAG 2.1 AA compliance
8. ✅ Documentation complete

## References

- [Frontend Migration Plan](../frontend-migration-to-react.plan.md)
- [Dashboard Architecture Decision](../DASHBOARD_ARCHITECTURE_DECISION.md)
- [Frontend Guidelines](../FRONTEND_GUIDELINES.md)
- [Project Rules](../../PROJECT_RULES.md)

---

**Last Updated**: 2025-01-XX  
**Maintained By**: Development Team

