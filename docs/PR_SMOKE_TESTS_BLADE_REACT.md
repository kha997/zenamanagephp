# PR: Smoke Tests for Blade and React Paths

## Summary
Implemented comprehensive smoke tests to verify both Blade and React paths work correctly, including feature flag routing and navigation consistency.

## Changes

### New Files
1. **`tests/E2E/smoke/blade-react-paths.spec.ts`**
   - Smoke tests for Blade paths (`/admin/*`)
   - Smoke tests for React paths (`/app/*`)
   - Feature flag routing verification
   - Navigation consistency tests
   - Deep linking tests
   - Performance smoke tests

### Modified Files
1. **`playwright.config.ts`**
   - Updated `smoke-chromium` project to include new smoke tests
   - Configured base URL for Blade/React path tests (Laravel app URL)

## Test Coverage

### Blade Path Tests
- ✅ Admin dashboard loads correctly
- ✅ Admin routes are accessible (`/admin/dashboard`, `/admin/users`, etc.)
- ✅ Navigation works in Blade pages
- ✅ Page load performance is acceptable

### React Path Tests
- ✅ App dashboard loads correctly
- ✅ App routes are accessible (`/app/dashboard`, `/app/projects`, `/app/tasks`)
- ✅ Navigation works in React pages
- ✅ Deep linking works (F5/refresh support)
- ✅ Page load performance is acceptable

### Feature Flag Tests
- ✅ Feature flag routing works correctly
- ✅ Routes to correct implementation (Blade or React) based on flags

### Authentication Tests
- ✅ Authentication redirects work correctly
- ✅ Protected routes require authentication

## Running Tests

### Run All Smoke Tests
```bash
npm run test:e2e:smoke
```

### Run Specific Smoke Test File
```bash
npx playwright test tests/E2E/smoke/blade-react-paths.spec.ts
```

### Run with Tag
```bash
npx playwright test --grep @smoke
```

### Run in Headed Mode
```bash
npx playwright test tests/E2E/smoke/blade-react-paths.spec.ts --headed
```

## Test Structure

### Test Groups
1. **Blade Admin Routes** - Tests `/admin/*` routes
2. **React App Routes** - Tests `/app/*` routes
3. **Navigation** - Tests navigation in both Blade and React
4. **Feature Flags** - Tests routing based on feature flags
5. **Deep Linking** - Tests direct navigation (F5/refresh)
6. **Authentication** - Tests auth redirects
7. **Performance** - Tests page load times

### Test Helpers
- Uses `MinimalAuthHelper` for authentication
- Waits for React hydration (2s timeout)
- Checks for both React and Blade markers

## Configuration

### Environment Variables
- `BASE_URL` - Base URL for tests (default: `http://127.0.0.1:8000`)
- `APP_URL` - Laravel app URL (fallback)
- `CI` - CI environment flag

### Timeouts
- Action timeout: 5s (10s in CI)
- Navigation timeout: 15s (30s in CI)
- React hydration wait: 2s

## Success Criteria

### All Tests Must Pass
- ✅ All Blade routes load correctly
- ✅ All React routes load correctly
- ✅ Navigation works in both contexts
- ✅ Feature flags route correctly
- ✅ Deep linking works
- ✅ Authentication redirects work
- ✅ Page load times < 5s

## Future Improvements

1. **Feature Flag Testing**
   - Add tests to verify feature flag toggles work
   - Test rollback scenarios

2. **Performance Benchmarks**
   - Add performance budgets
   - Track page load times over time

3. **Visual Regression**
   - Add visual comparison tests
   - Screenshot comparison for Blade vs React

4. **Accessibility**
   - Add a11y checks for both Blade and React
   - Verify keyboard navigation

## CI Integration

### GitHub Actions
Add to `.github/workflows/ci-cd.yml`:
```yaml
- name: Run Blade/React Smoke Tests
  run: npm run test:e2e:smoke -- --project=smoke-chromium
```

## Notes

- Tests use `admin@zena.local` / `password` for authentication
- Tests wait for React hydration (2s) to ensure React is fully loaded
- Tests check for both React and Blade markers to handle feature flag routing
- Performance tests use 5s threshold for smoke tests (can be adjusted)

