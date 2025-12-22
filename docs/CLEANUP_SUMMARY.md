# Cleanup Summary - Frontend Migration

**Date**: 2025-01-XX  
**Status**: Verification Required

## Files to Review Before Removal

### Alpine.js Files (Check Usage)
These files may still be used by `/admin/*` routes or other parts of the system:

- `resources/js/alpine-data-functions.js` - Check if used by admin
- `resources/js/alpine-missing-components.js` - Check if used by admin
- `resources/js/bootstrap.js` - Check if used by admin
- `resources/js/focus-mode.js` - Check if used by admin
- `resources/js/rewards.js` - Check if used by admin
- `resources/js/task-comments.js` - Check if used by admin
- `resources/js/pages/users.js` - Check if used by admin
- `resources/js/pages/tenants.js` - Check if used by admin

**Action**: Verify these are only used by `/admin/*` before removing. If used by `/app/*`, migrate to React.

### Deprecated API Clients
These files now re-export the unified client and can be removed after migration:

- `frontend/src/lib/api-client.ts` ✅ Deprecated (re-exports unified client)
- `frontend/src/services/api.ts` ✅ Deprecated (re-exports unified client)
- `frontend/src/lib/api/client.ts` ✅ Deprecated (re-exports unified client)

**Action**: After verifying all imports are updated, remove these files.

### Archived Blade Templates
- `resources/views/_archived/app-blade-2025-01-XX/` ✅ Archived

**Action**: Keep for 3 months for rollback, then remove.

## Cleanup Checklist

- [ ] Verify Alpine.js files are only used by `/admin/*`
- [ ] Update all imports to use unified API client
- [ ] Remove deprecated API client files
- [ ] Remove archived Blade templates after 3 months
- [ ] Update `.gitignore` if needed

## Notes

- Do not remove Alpine.js files that are used by `/admin/*` routes
- Keep deprecated API clients until all imports are updated
- Archive is kept for rollback safety

