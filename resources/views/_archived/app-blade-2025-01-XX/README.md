# Archived Blade Templates - /app/* Routes

**Archive Date**: 2025-01-XX  
**Reason**: Migration to React + TypeScript SPA  
**Status**: Archived (do not use for new development)

## What Was Archived

All Blade templates for `/app/*` routes have been archived to prepare for React migration.

## Migration Status

- ✅ All Blade templates archived
- ✅ React SPA entry point created: `resources/views/app/spa.blade.php`
- ✅ Routes configured to use React SPA

## Important Notes

- ❌ **DO NOT** create new Blade templates for `/app/*` routes
- ✅ Use React + TypeScript for all new `/app/*` development
- 📖 See [FRONTEND_GUIDELINES.md](../../FRONTEND_GUIDELINES.md) for development standards
- 📖 See [ADR-007](../../docs/adr/ADR-007-frontend-technology-split.md) for architecture decision

## Rollback Plan

If rollback is needed (within 3 months), restore templates from this archive:

```bash
cp -r resources/views/_archived/app-blade-2025-01-XX/* resources/views/app/
```

## Related Documentation

- [Frontend Migration Plan](../../frontend-migration-to-react.plan.md)
- [ADR-007: Frontend Technology Split](../../docs/adr/ADR-007-frontend-technology-split.md)
- [Frontend Guidelines](../../FRONTEND_GUIDELINES.md)

