# Single Source of Truth Violation - CRITICAL

## Problem Analysis

### Current State: VIOLATION
- **3 controllers** handling projects:
  1. `Web\ProjectController` - Web routes only
  2. `Unified\ProjectManagementController` - Both web and API
  3. `ProjectShellController` (legacy, partially used)

### Violated Rules
From PROJECT_RULES.md:
- ❌ "NEVER create duplicate functionality"
- ❌ "Single source of truth"
- ❌ "Clear separation: /admin/* (system-wide) ≠ /app/* (tenant-scoped)"

## Root Cause
Routes are registered in multiple places:
- `routes/web.php` - uses `Web\ProjectController`
- `routes/api.php` - uses `Unified\ProjectManagementController`

## Solution Required

### Option 1: Use Unified Controller (RECOMMENDED)
- Remove `Web\ProjectController`
- Use only `Unified\ProjectManagementController` for ALL operations
- Separate via `expectsJson()` or middleware

### Option 2: Separate Web/API Completely
- Keep `Web\ProjectController` for web only
- Keep `Unified\ProjectManagementController` for API only  
- Ensure NO overlap

## Action Items
1. Choose ONE controller for `/app/projects`
2. Remove other implementations
3. Update routes to point to single controller
4. Test both web and API requests
5. Document the decision in ADR

