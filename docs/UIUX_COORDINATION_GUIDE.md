# UI/UX Coordination Guide

**Last Updated:** 2025-11-08 12:00  
**Purpose:** Quick reference for UI/UX file ownership and coordination between Cursor and Codex  
**For:** Cursor (UI/UX Implementation) and Codex (Frontend E2E Organization)

---

## Quick Reference

### File Ownership

**Cursor (UI/UX Implementation):**
- ✅ `frontend/src/shared/tokens/**` - All token files
- ✅ `frontend/src/components/**` - All UI components
- ✅ `frontend/src/app/layouts/**` - App layouts
- ✅ `frontend/src/features/**/components/**` - Feature components
- ✅ `frontend/src/features/**/pages/**` - Feature pages
- ✅ `frontend/src/index.css` - Global styles
- ✅ `frontend/tailwind.config.ts` - Tailwind config
- ✅ `frontend/vite.config.ts` - Vite config (build)

**Codex (Frontend E2E Organization):**
- ✅ `frontend/playwright.config.ts` - Playwright config
- ✅ `frontend/e2e/**` - E2E test files
- ✅ `frontend/tests/**` - Test utilities

**Shared (Coordinate):**
- ⚠️ `frontend/package.json` - Cursor (dependencies), Codex (scripts)

---

## Coordination Procedures

### Before Starting Work

**Cursor:**
1. Check `docs/AGENT_COORDINATION_HUB.md` for file locks
2. Lock `frontend/src/shared/tokens/**` in coordination hub
3. Lock `frontend/src/components/**` in coordination hub
4. Update `docs/AGENT_STATUS_REPORTS.md` with status
5. Add entry to Communication Log

**Codex:**
1. Check `docs/AGENT_COORDINATION_HUB.md` for file locks
2. Lock `frontend/playwright.config.ts` in coordination hub
3. Lock `frontend/e2e/**` in coordination hub
4. Update `docs/AGENT_STATUS_REPORTS.md` with status
5. Add entry to Communication Log

### During Work

**For Independent Files:**
- Work freely on owned files
- Update progress every 30 minutes
- Report blockers immediately

**For Shared Files (`frontend/package.json`):**
- Lock file before modification
- Update Communication Log with changes
- Notify other agent via Communication Log
- Unlock after modification
- Other agent reviews changes before proceeding

### After Completing Work

**Cursor:**
1. Unlock all locked files
2. Update `docs/AGENT_TASK_BOARD.md` (move to "Ready for Review")
3. Update `docs/AGENT_HANDOFF.md` with "Next for Codex: Review UI/UX implementation"
4. Update `docs/AGENT_STATUS_REPORTS.md` with completion status
5. Add entry to Communication Log

**Codex:**
1. Unlock all locked files
2. Update `docs/AGENT_TASK_BOARD.md` (move to "Ready for Review")
3. Update `docs/AGENT_HANDOFF.md` with "Next for Cursor: Review E2E organization"
4. Update `docs/AGENT_STATUS_REPORTS.md` with completion status
5. Add entry to Communication Log

---

## File Lock Examples

### Example 1: Cursor Locking UI/UX Files

```
| File | Locked By | Reason | Locked At | Until | Status |
|------|-----------|--------|-----------|-------|--------|
| frontend/src/shared/tokens/** | Cursor | UI/UX Implementation | 2025-11-08 12:00 | 2025-11-08 16:00 | 🔒 Locked |
| frontend/src/components/** | Cursor | UI/UX Implementation | 2025-11-08 12:00 | 2025-11-08 16:00 | 🔒 Locked |
```

### Example 2: Codex Locking E2E Files

```
| File | Locked By | Reason | Locked At | Until | Status |
|------|-----------|--------|-----------|-------|--------|
| frontend/playwright.config.ts | Codex | Frontend E2E Organization | 2025-11-08 12:00 | 2025-11-08 14:00 | 🔒 Locked |
| frontend/e2e/** | Codex | Frontend E2E Organization | 2025-11-08 12:00 | 2025-11-08 14:00 | 🔒 Locked |
```

### Example 3: Shared File with Multiple Locks

```
| File | Locked By | Reason | Locked At | Until | Status |
|------|-----------|--------|-----------|-------|--------|
| frontend/package.json | Cursor | Adding UI dependencies | 2025-11-08 12:00 | 2025-11-08 13:00 | 🔒 Locked |
| frontend/package.json | Codex | Adding test scripts | 2025-11-08 12:30 | 2025-11-08 13:30 | 🔒 Locked |
```

**Note:** Same file can have multiple locks if different sections are being modified. Coordinate via Communication Log.

---

## Conflict Resolution

### Scenario 1: Both Need frontend/package.json

**Resolution:**
1. Cursor locks for `dependencies` section
2. Codex locks for `scripts` section (different sections)
3. Both coordinate via Communication Log
4. Codex reviews Cursor's dependency changes
5. Merge coordination

### Scenario 2: Codex Needs to Update E2E Tests for Header

**Resolution:**
1. Cursor implements `HeaderShell.tsx` first (lock file)
2. Cursor notifies Codex when header is ready for testing
3. Codex updates E2E tests after implementation
4. Coordination via Communication Log

---

## Key Principles

1. **Lock Before Modify:** Always lock files before modifying
2. **Coordinate Shared Files:** Use Communication Log for shared files
3. **Update Progress:** Update progress every 30 minutes
4. **Review Each Other:** Review each other's work when complete
5. **No Conflicts:** Work on independent files in parallel

---

## Related Documentation

- **[AGENT_CONFLICT_MATRIX.md](AGENT_CONFLICT_MATRIX.md)** - Complete file ownership rules
- **[AGENT_COORDINATION_HUB.md](AGENT_COORDINATION_HUB.md)** - Central coordination point
- **[AGENT_WORKFLOW.md](AGENT_WORKFLOW.md)** - Standard workflow procedures
- **[UIUX_APPLE_STYLE_SPEC.md](UIUX_APPLE_STYLE_SPEC.md)** - UI/UX implementation spec

---

## Quick Checklist

**Before Starting:**
- [ ] Check file locks in AGENT_COORDINATION_HUB.md
- [ ] Lock files you'll modify
- [ ] Update AGENT_STATUS_REPORTS.md
- [ ] Add entry to Communication Log

**During Work:**
- [ ] Update progress every 30 minutes
- [ ] Report blockers immediately
- [ ] Coordinate via Communication Log for shared files

**After Completing:**
- [ ] Unlock all locked files
- [ ] Update AGENT_TASK_BOARD.md
- [ ] Update AGENT_HANDOFF.md
- [ ] Update AGENT_STATUS_REPORTS.md
- [ ] Add entry to Communication Log

---

**Last Updated:** 2025-11-08 12:00  
**Maintainer:** Cursor (for Cursor and Codex coordination)

