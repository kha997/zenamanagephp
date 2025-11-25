# Legacy Folders Documentation

This document describes legacy/deprecated folders in the frontend directory.

## Deprecated Folders (Moved to legacy/)

### `legacy/frontend/src.old/`
- **Status**: Deprecated, moved to legacy/
- **Purpose**: Old version of frontend code before migration
- **Location**: `legacy/frontend/src.old/`
- **Last Updated**: 2025-01-27

### `legacy/frontend/src.backup/`
- **Status**: Deprecated, moved to legacy/
- **Purpose**: Backup of frontend code
- **Location**: `legacy/frontend/src.backup/`
- **Last Updated**: 2025-01-27

### `legacy/frontend/src.new/`
- **Status**: Deprecated, moved to legacy/
- **Purpose**: Temporary new version
- **Location**: `legacy/frontend/src.new/`

## Current Active Folder

### `frontend/src/`
- **Status**: ✅ **CANONICAL** - This is the main React application
- **Entry Point**: `src/main.tsx` → `AppShell.tsx` → `router.tsx`
- **Build Output**: `public/build/` (via Vite)
- **Architecture Decision**: See [FRONTEND_ARCHITECTURE_DECISION.md](../docs/FRONTEND_ARCHITECTURE_DECISION.md)

## Verification

To verify no code references legacy folders:
```bash
# Check for imports from src.old
grep -r "from.*src.old" frontend/src/

# Check for imports from src.backup  
grep -r "from.*src.backup" frontend/src/

# Check for imports from src.new
grep -r "from.*src.new" frontend/src/
```

## Notes

- ✅ All active code should only import from `frontend/src/`
- ✅ Legacy folders have been moved to `legacy/frontend/`
- ✅ Legacy folders are kept for reference only
- ✅ See [FRONTEND_ARCHITECTURE_DECISION.md](../docs/FRONTEND_ARCHITECTURE_DECISION.md) for architecture details

