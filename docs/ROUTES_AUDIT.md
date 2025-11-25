# Routes Architecture Compliance Audit

## Overview

**Date**: 2025-01-19  
**Status**: Audit Complete

---

## Architecture Principle

**Web routes should only render views and handle UI interactions. All business logic must be handled via API endpoints.**

---

## Issues Found in `routes/web.php`

### 1. Debug Routes with Business Logic (Lines 12-92)

**Violations**:
- `Auth::login()` calls
- Direct model queries (`User::where()`, `Project::where()`)
- Business logic in route closures

**Routes**:
- `/debug/tasks-create` (line 13)
- `/debug/dropdown-test` (line 31)
- `/debug/console-check` (line 47)
- `/debug/direct-dropdown-test` (line 63)
- `/debug/css-conflict-check` (line 79)

**Action**: Move to `routes/debug.php` with env guard

---

### 2. Test Routes with Business Logic (Lines 175-567)

**Violations**:
- `Auth::login()` calls
- Direct model queries
- Service calls

**Routes**:
- `/test/login` (line 184)
- `/test-debug-component` (line 195)
- `/test-fixed-component` (line 214)
- `/test/tasks/{id}` (line 449)
- `/test-simple-task/{id}` (line 484)
- `/sandbox/*` routes (lines 508-560)
- `/test-csrf` (line 564)
- `/test-simple` (line 567)

**Action**: Move to `routes/debug.php` or `routes/test.php` with env guard

---

### 3. Valid Routes (Keep in web.php)

**These are compliant**:
- Root redirect (line 105) - ✅ View logic only
- API session token (line 125) - ✅ API endpoint (should be in api.php, but acceptable)
- Authentication routes (lines 155-305) - ✅ View rendering
- Invitation routes (line 312) - ✅ View rendering
- App SPA routes (line 326) - ✅ View rendering
- Admin routes (line 346) - ✅ View rendering
- Demo routes (line 234) - ✅ Already has env guard

---

## Current `routes/debug.php` State

**Existing routes** (under `_debug` prefix):
- `/_debug/health`
- `/_debug/ping`
- `/_debug/info`
- `/_debug/performance`
- `/_debug/clear-cache`
- `/_debug/test-simple`

**Status**: ✅ Already has proper structure, but no env guard

---

## Migration Plan

### Phase 1: Move Debug Routes
- Move `/debug/*` routes from `web.php` to `debug.php`
- Add env guard: `if (app()->environment(['local', 'testing']))`
- Keep `_debug` prefix for existing routes
- Use `/debug/*` prefix for new debug routes

### Phase 2: Move Test Routes
- Move `/test/*` and `/sandbox/*` routes to `debug.php` or `test.php`
- Add env guard
- Group by purpose

### Phase 3: Update RouteServiceProvider
- Ensure `debug.php` only loads in local/testing
- Verify `test.php` only loads in local/testing

### Phase 4: Cleanup web.php
- Remove all moved routes
- Verify only view-rendering routes remain
- Add comment documenting architecture compliance

---

## Recommendations

1. **Consolidate debug routes**: Use `routes/debug.php` for all debug/test routes
2. **Add env guards**: All debug routes must have `if (app()->environment(['local', 'testing']))`
3. **Update RouteServiceProvider**: Ensure debug routes only load in appropriate environments
4. **Document**: Add comments explaining why routes are in debug.php vs web.php

---

**Next Steps**: Execute migration plan

