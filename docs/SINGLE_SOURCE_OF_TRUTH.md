# Single Source of Truth - Frontend Architecture

## ⚠️ CRITICAL RULE

**ONLY ONE FRONTEND SYSTEM CAN BE ACTIVE AT A TIME**

This document defines the **single source of truth** for frontend routing and prevents conflicts.

## Configuration File

**Location:** `config/frontend.php`

This file defines which frontend system is ACTIVE. It is the **single source of truth**.

```php
'active' => env('FRONTEND_ACTIVE', 'react'),
```

## Active Systems

### React Frontend (Current)
- **Status:** ✅ ACTIVE
- **Port:** 5173
- **Routes:** `/login`, `/register`, `/app/*`
- **Location:** `frontend/src/`
- **Access:** `http://localhost:5173`

### Blade Templates
- **Status:** ❌ DISABLED for app routes
- **Port:** 8000
- **Routes:** `/admin/*` (admin routes only)
- **Location:** `resources/views/`
- **Access:** `http://localhost:8000` (API only)

## Route Rules

### ✅ ALLOWED
- React handles: `/login`, `/register`, `/app/*`
- Blade handles: `/admin/*` (admin panel)

### ❌ FORBIDDEN
- **NEVER** define same route in both React and Blade
- **NEVER** enable both systems for same route
- **NEVER** modify routes without updating `config/frontend.php`

## Validation

Run validation before committing:

```bash
php artisan frontend:validate
```

This checks:
- ✅ Only one system is active
- ✅ No route conflicts
- ✅ Ports are different
- ✅ Configuration is consistent

## Changing Active System

If you need to switch frontend systems:

1. **Update config:**
   ```php
   // config/frontend.php
   'active' => 'blade', // or 'react'
   ```

2. **Update routes:**
   - Enable/disable routes in `routes/web.php`
   - Update React Router if switching to React

3. **Run validation:**
   ```bash
   php artisan frontend:validate
   ```

4. **Update documentation:**
   - Update this file
   - Update `REACT_FRONTEND_CHOSEN.md` if switching

5. **Test:**
   - Verify routes work
   - Check no conflicts

## Pre-Commit Checks

Before committing code that touches routes:

```bash
# 1. Validate frontend config
php artisan frontend:validate

# 2. Check for duplicate routes
grep -r "Route::get('/login'" routes/

# 3. Verify active system matches routes
```

## Current State

**Active:** React Frontend (Port 5173)
- ✅ Login: React (`/login`)
- ✅ App routes: React (`/app/*`)
- ✅ Admin routes: Blade (`/admin/*`)
- ✅ API: Laravel (Port 8000)

## Troubleshooting

### Issue: Same route shows different UI
**Cause:** Both React and Blade handle same route
**Fix:** 
1. Check `config/frontend.php`
2. Disable one system
3. Run `php artisan frontend:validate`

### Issue: Route not found
**Cause:** Route disabled but still being accessed
**Fix:**
1. Check active system in `config/frontend.php`
2. Verify route exists in active system
3. Check port (5173 for React, 8000 for Blade)

### Issue: Validation fails
**Cause:** Configuration conflict
**Fix:**
1. Review `config/frontend.php`
2. Ensure only one system is enabled
3. Check route definitions don't conflict

## Enforcement

This rule is enforced by:
1. **Config file:** `config/frontend.php` (single source of truth)
2. **Validation command:** `php artisan frontend:validate`
3. **Documentation:** This file
4. **Code comments:** Routes marked with ⚠️ SINGLE SOURCE OF TRUTH

## History

- **2025-01-XX:** React Frontend chosen as active system
- **2025-01-XX:** Blade login route disabled
- **2025-01-XX:** Single source of truth system implemented

