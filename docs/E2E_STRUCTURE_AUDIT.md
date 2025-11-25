# E2E Test Structure Audit

## Overview

**Date**: 2025-01-19  
**Status**: Audit Complete

---

## Current State

### Directory Structure

**Current**: `tests/e2e/` (lowercase, renamed from `tests/E2E/`)
- Contains 230+ test files
- Subdirectories: `auth/`, `core/`, `smoke/`, `regression/`, `phase3/`, `helpers/`, etc.

---

## Configuration Files

### Playwright Configs

1. **`playwright.config.ts`**:
   - `testDir: './tests/e2e'`
   - `globalSetup: require.resolve('./tests/e2e/setup/global-setup.ts')`

2. **`playwright.phase3.config.ts`**:
   - `testDir: './tests/e2e/phase3'`

3. **`playwright.auth.config.ts`**:
   - Uses `./tests/e2e/auth`

---

## Standardization Plan

### Recommendation: Standardize to Lowercase `tests/e2e/`

**Rationale**:
- Convention: Most projects use lowercase for test directories
- Consistency: Matches other test directories (`tests/Feature/`, `tests/Unit/`)
- Cross-platform: Works better on case-sensitive filesystems

**Action Items (Status)**:
1. Update Playwright configs to use `./tests/e2e` (**done**)
2. Update all references in docs (**done for known references**)
3. Rename directory on all filesystems (**done: \`tests/E2E/\` → \`tests/e2e/\`**)
4. Update package.json scripts if needed
5. Verify all tests still work

---

## Files to Update

### Config Files
- `playwright.config.ts` - Update `testDir` and `globalSetup`
- `playwright.phase3.config.ts` - Update `testDir`
- `playwright.auth.config.ts` (if exists) - Update paths

### Documentation
- `docs/CURSOR_CONSISTENCY_FIXES.md` - Update references
- `TEST_SUITE_SUMMARY.md` - Update paths
- `E2E_TESTING_STRATEGY.md` - Update paths
- All other docs referencing `tests/E2E/`

### Scripts
- `package.json` - Check if any scripts reference the path (update to `tests/e2e` if needed)

---

## Migration Strategy

Directory has been renamed and configs/docs updated, so remaining work is limited to keeping new references consistent with `tests/e2e`.
