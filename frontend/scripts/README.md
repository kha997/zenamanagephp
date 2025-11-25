# Navigation Feature Flag Scripts

This directory contains helper scripts for managing the navigation feature flag.

## Scripts

### `toggle-nav-flag.sh`
Toggle the navigation feature flag on/off.

**Usage:**
```bash
# Show current status
./scripts/toggle-nav-flag.sh status
# or
npm run nav:toggle

# Enable new navigation (AppNavigator)
./scripts/toggle-nav-flag.sh on
# or
npm run nav:toggle on

# Disable new navigation (use PrimaryNavigator)
./scripts/toggle-nav-flag.sh off
# or
npm run nav:toggle off
```

### `rebuild-nav.sh`
Rebuild frontend with current feature flag settings.

**Usage:**
```bash
# Normal rebuild
./scripts/rebuild-nav.sh
# or
npm run nav:rebuild

# Clean rebuild (removes cache and build artifacts)
./scripts/rebuild-nav.sh clean
# or
npm run nav:rebuild clean
```

### `toggle-rebuild-nav.sh`
Toggle feature flag and rebuild in one command.

**Usage:**
```bash
# Enable new navigation and rebuild
./scripts/toggle-rebuild-nav.sh on
# or
npm run nav:toggle-rebuild on

# Disable new navigation and rebuild
./scripts/toggle-rebuild-nav.sh off
# or
npm run nav:toggle-rebuild off
```

## Quick Reference

```bash
# Enable new navigation
npm run nav:toggle on

# Rebuild
npm run nav:rebuild

# Or do both at once
npm run nav:toggle-rebuild on

# Check status
npm run nav:toggle status
```

## Feature Flag Values

- **false** (default): Uses `PrimaryNavigator` (legacy component)
- **true**: Uses `AppNavigator` (new component, text-only, full dark mode support)

## Environment Files

The scripts modify `.env.local` (preferred) or `.env` file:
- `.env.local` - Local overrides (not committed to git)
- `.env` - Default environment file

## After Rebuild

Always:
1. Clear browser cache
2. Hard refresh: `Ctrl+Shift+R` (Windows/Linux) or `Cmd+Shift+R` (Mac)
3. Check DevTools to verify the correct component is loaded

## Verification

To verify which component is active, check DevTools:
- Legacy: `data-source="react"` in `<nav>` element
- New: `data-source="react-new"` in `<nav>` element

