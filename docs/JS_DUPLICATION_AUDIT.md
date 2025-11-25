# JS Duplication Audit - focus-mode.js & rewards.js

## Overview

**Date**: 2025-01-19  
**Status**: Audit Complete

---

## Current State

### Source Files (Canonical)
- `resources/js/focus-mode.js` - Source file (443 lines)
- `resources/js/rewards.js` - Source file

**Usage**: Imported in `resources/js/app.js`:
```javascript
import './focus-mode';
import './rewards';
```

**Build Process**: Vite builds `resources/js/app.js` → `public/build/` (via `@vite` directive)

---

### Duplicate Files (Suspected)
- `public/js/focus-mode.js` - Duplicate (331 lines, different version)
- `public/js/rewards.js` - Duplicate

**Status**: These appear to be either:
1. Old build artifacts (manual copy)
2. Legacy files from before Vite migration
3. Unused duplicates

---

## Analysis

### 1. How Scripts Are Loaded

**Current Implementation** (Correct):
```blade
{{-- resources/views/layouts/app.blade.php --}}
@vite(['resources/css/app.css', 'resources/js/app.js'])
```

This loads:
- `resources/js/app.js` (via Vite)
- Which imports `focus-mode.js` and `rewards.js` (via ES6 imports)
- Vite bundles everything and outputs to `public/build/`

**No Direct References Found**:
- No `<script src="/js/focus-mode.js">` found in layouts
- No `<script src="/js/rewards.js">` found in layouts
- No `@vite(['resources/js/focus-mode.js'])` found

---

### 2. File Comparison

**focus-mode.js**:
- `resources/js/focus-mode.js`: 443 lines, more complete
- `public/js/focus-mode.js`: 331 lines, appears to be older version

**rewards.js**:
- Both files exist but content comparison needed

---

## Recommendations

### Option A: Remove `public/js/` Duplicates (Recommended)

**Rationale**:
1. Source of truth is `resources/js/` (imported via Vite)
2. No direct references to `public/js/focus-mode.js` or `public/js/rewards.js` found
3. Vite handles bundling and output to `public/build/`
4. `public/js/` files are likely legacy/manual copies

**Action Items**:
1. Verify no direct script tags reference `public/js/focus-mode.js` or `public/js/rewards.js`
2. Check `.gitignore` - ensure `public/js/` is ignored if it's build output
3. Delete `public/js/focus-mode.js` and `public/js/rewards.js`
4. Update documentation if needed

**Risks**:
- Low risk if no direct references found
- Can restore from git if needed

---

### Option B: Keep Both (Not Recommended)

**Rationale**: Only if `public/js/` files are actively used

**Action Items**:
1. Document why both are needed
2. Ensure they stay in sync
3. Add build process to copy from `resources/js/` to `public/js/`

**Risks**:
- High maintenance overhead
- Risk of divergence
- Confusion about which is canonical

---

## Verification Steps

Before cleanup:
1. ✅ Check layouts for direct script tags - **None found**
2. ✅ Check `app.js` imports - **Found: imports from resources/js/**
3. ⚠️ Check if `public/js/` files are in `.gitignore` - **Need to verify**
4. ⚠️ Check build process - **Vite outputs to public/build/, not public/js/**
5. ⚠️ Search codebase for any references to `public/js/focus-mode.js` - **Need to verify**

---

## Cleanup Plan

### Phase 1: Verification
- [ ] Search for all references to `public/js/focus-mode.js`
- [ ] Search for all references to `public/js/rewards.js`
- [ ] Check `.gitignore` for `public/js/`
- [ ] Verify Vite build output location

### Phase 2: Cleanup
- [ ] Delete `public/js/focus-mode.js` (if unused)
- [ ] Delete `public/js/rewards.js` (if unused)
- [ ] Update `.gitignore` if needed
- [ ] Update documentation

### Phase 3: Verification
- [ ] Test that focus-mode still works
- [ ] Test that rewards still works
- [ ] Verify no broken references

---

## Conclusion

**Recommendation**: **Remove `public/js/` duplicates**

**Reasoning**:
- Source of truth is `resources/js/` (via Vite)
- No direct references found
- Vite handles bundling correctly
- `public/js/` files appear to be legacy

**Next Steps**: Execute cleanup plan after verification

