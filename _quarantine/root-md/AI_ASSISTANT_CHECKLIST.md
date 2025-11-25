# AI Assistant Checklist - Single Source of Truth

## ⚠️ MANDATORY CHECKS BEFORE ANY CODE CHANGE

### Before Making Changes

1. **Check Active Frontend System**
   ```bash
   # Read config/frontend.php
   # Verify which system is active (react/blade)
   ```

2. **Check Route Conflicts**
   ```bash
   php artisan frontend:validate
   ```

3. **Check Documentation**
   - Read `docs/SINGLE_SOURCE_OF_TRUTH.md`
   - Read `REACT_FRONTEND_CHOSEN.md` (if React is active)
   - Read `FRONTEND_CONFLICT_SUMMARY.md` (for context)

### When Adding/Modifying Routes

1. **Check if route already exists**
   ```bash
   # Check React routes
   grep -r "path: '/login'" frontend/src/
   
   # Check Blade routes
   grep -r "Route::get('/login'" routes/
   ```

2. **Verify active system**
   - If React is active → Add route to React Router
   - If Blade is active → Add route to routes/web.php
   - **NEVER add to both**

3. **Update config if needed**
   - Update `config/frontend.php` if changing active system
   - Run validation after changes

### When Working on Login/Auth

**CRITICAL:** Login route is handled by React (Port 5173)

- ✅ Edit: `frontend/src/features/auth/components/LoginForm.tsx`
- ❌ DO NOT edit: `resources/views/auth/login.blade.php` (disabled)
- ❌ DO NOT enable: Blade login route in `routes/web.php`

### Red Flags - STOP IMMEDIATELY

If you see any of these, STOP and check configuration:

1. **Same route in both systems**
   - React has `/login` AND Blade has `/login`
   - → Check `config/frontend.php`

2. **Both systems enabled**
   - `config/frontend.php` shows both enabled
   - → Only ONE can be enabled

3. **Port conflict**
   - React and Blade use same port
   - → Ports must be different (5173 vs 8000)

4. **User reports different UI**
   - Same URL shows different content
   - → Route conflict detected

### Validation Commands

```bash
# Validate frontend config
php artisan frontend:validate

# Check for duplicate routes
grep -r "Route::get('/login'" routes/
grep -r "path: '/login'" frontend/src/

# Check active system
grep "active" config/frontend.php
```

### Documentation to Update

When changing frontend system:

1. ✅ `config/frontend.php` - Update active system
2. ✅ `docs/SINGLE_SOURCE_OF_TRUTH.md` - Update current state
3. ✅ `REACT_FRONTEND_CHOSEN.md` - Update if switching to React
4. ✅ `routes/web.php` - Comment/disable conflicting routes

### Example: Adding Login Feature

**WRONG:**
```php
// routes/web.php
Route::get('/login', [LoginController::class, 'showLoginForm']); // ❌ CONFLICT
```

```tsx
// frontend/src/app/router.tsx
{ path: '/login', element: <LoginPage /> } // ❌ CONFLICT
```

**CORRECT:**
```tsx
// frontend/src/app/router.tsx (React is active)
{ path: '/login', element: <LoginPage /> } // ✅ CORRECT
```

```php
// routes/web.php
// ⚠️ SINGLE SOURCE OF TRUTH: Login handled by React
// Route::get('/login', ...) // ✅ DISABLED - React handles it
```

## Summary

**Single Source of Truth = `config/frontend.php`**

- ✅ Always check this file first
- ✅ Only ONE system active at a time
- ✅ Run validation before committing
- ✅ Update documentation when changing

**Remember:** If user reports "same URL, different results" → Route conflict → Check config!

