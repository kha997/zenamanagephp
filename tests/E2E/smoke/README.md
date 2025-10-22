# E2E Smoke Tests Documentation

## Overview

This directory contains End-to-End (E2E) smoke tests for the ZenaManage application. Smoke tests are lightweight, fast tests that verify the core functionality of the application works correctly.

## Test Structure

```
tests/e2e/
├── smoke/                    # Smoke tests (@smoke)
│   ├── auth.spec.ts         # Authentication tests
│   ├── admindashboard.spec.ts # Admin dashboard tests
│   ├── project_create.spec.ts # Project creation tests
│   └── alerts_preferences.spec.ts # Alerts & preferences tests
├── helpers/                  # Test helpers and utilities
│   ├── smoke-helpers.ts     # Smoke test helpers
│   ├── data.ts              # Test data
│   └── selectors.ts         # Element selectors
└── setup/                   # Test setup
    └── global-setup.ts      # Global test setup
```

## Smoke Test Categories

### 1. Authentication Tests (`auth.spec.ts`)
- **S1**: User registration flow
- **S2**: User login with i18n and theme toggle
- **S10**: User logout flow
- Password visibility toggle
- Form validation
- Forgot password navigation
- Multi-tenant login isolation
- Responsive authentication
- Authentication error handling

### 2. Admin Dashboard Tests (`admindashboard.spec.ts`)
- **S3**: Admin dashboard statistics verification
- Dashboard data loading
- Quick actions functionality
- Responsive design
- Theme persistence
- Navigation
- Error handling
- Performance

### 3. Project Creation Tests (`project_create.spec.ts`)
- **S4**: Project creation flow
- **S6**: Task status change
- Form validation
- Minimal data creation
- Permission-based creation
- List functionality
- Error handling
- Responsive design

### 4. Alerts & Preferences Tests (`alerts_preferences.spec.ts`)
- **S8**: Alert management functionality
- **S9**: User preferences management
- Alert filtering and search
- Preferences persistence
- Alert notifications
- Responsive design
- Error handling

## Test Data

The smoke tests use the following test data:

### Users
- **ZENA Company**: owner@zena.local, admin@zena.local, pm@zena.local, dev@zena.local, guest@zena.local
- **TTF Company**: owner@ttf.local, admin@ttf.local, pm@ttf.local, dev@ttf.local, guest@ttf.local
- **Password**: `password` (for all users)

### Roles
- **Owner**: Full system access
- **Admin**: System administration
- **Project Manager**: Project and task management
- **Developer**: Task and document access
- **Guest**: Read-only access

### Projects
- **E2E-001**: E2E Test Project 1 (Active)
- **E2E-002**: E2E Test Project 2 (Planning)

## Running Smoke Tests

### Prerequisites
1. E2E database seeded with test data
2. Laravel application running on `http://127.0.0.1:8000`
3. Playwright installed (`npm install`)

### Commands

```bash
# Run all smoke tests
npm run test:smoke

# Run smoke tests on mobile
npm run test:smoke:mobile

# Run smoke tests on all platforms
npm run test:smoke:all

# Run smoke tests with UI
npm run test:smoke:ui

# Run smoke tests in headed mode
npm run test:smoke:headed

# Debug smoke tests
npm run test:smoke:debug

# View test report
npm run test:e2e:report
```

### Individual Test Files

```bash
# Run specific test file
npx playwright test tests/e2e/smoke/auth.spec.ts

# Run specific test
npx playwright test tests/e2e/smoke/auth.spec.ts -g "User login"

# Run tests with specific tag
npx playwright test --grep "@smoke"
```

## Test Configuration

### Playwright Configuration
- **Base URL**: `http://127.0.0.1:8000`
- **Timeout**: 5-15 seconds (smoke tests are fast)
- **Retries**: 2 on CI, 0 locally
- **Workers**: 1 on CI, parallel locally

### Environment Setup
- **Database**: `zenamanage_e2e`
- **Mailer**: Log driver for testing
- **Storage**: Test upload directory
- **Cache**: Array driver for speed

## Test Helpers

### AuthHelper
- `login(email, password)`: Login with credentials
- `logout()`: Logout from application
- `isLoggedIn()`: Check login status
- `testPasswordToggle()`: Test password visibility

### DashboardHelper
- `navigateToDashboard()`: Go to dashboard
- `verifyDashboardLoads()`: Verify dashboard elements
- `testThemeToggle()`: Test theme switching

### ProjectHelper
- `navigateToProjects()`: Go to projects list
- `verifyProjectsListLoads()`: Verify projects page
- `createProject(name, description)`: Create new project
- `verifyProjectExists(name)`: Check project exists

### TestUtils
- `generateUniqueName(prefix)`: Generate unique test data
- `waitForElement(page, selector, timeout)`: Wait for element
- `takeScreenshot(page, name)`: Take debug screenshot
- `checkConsoleErrors(page)`: Check for console errors

## Selectors

The tests use centralized selectors defined in `helpers/selectors.ts`:

```typescript
// Authentication
selectors.auth.emailInput
selectors.auth.passwordInput
selectors.auth.loginButton

// Dashboard
selectors.dashboard.title
selectors.dashboard.kpiCards
selectors.dashboard.quickActions

// Projects
selectors.projects.list
selectors.projects.createButton
selectors.projects.projectCard

// Forms
selectors.forms.submitButton
selectors.forms.errorMessage
```

## Best Practices

### 1. Test Isolation
- Each test is independent
- No shared state between tests
- Clean database state for each run

### 2. Error Handling
- Graceful handling of missing elements
- Fallback selectors for robustness
- Console error checking

### 3. Performance
- Fast execution (< 5 seconds per test)
- Minimal data setup
- Efficient selectors

### 4. Maintainability
- Centralized selectors
- Reusable helpers
- Clear test names and descriptions

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   ```bash
   # Ensure E2E database exists
   mysql -u root -p -e "CREATE DATABASE zenamanage_e2e;"
   ```

2. **Test Data Missing**
   ```bash
   # Re-seed test data
   php artisan migrate:fresh --seed --env=e2e --seeder=E2EDatabaseSeeder
   ```

3. **Element Not Found**
   - Check if application is running
   - Verify selectors in `helpers/selectors.ts`
   - Use `test:smoke:headed` to debug visually

4. **Timeout Issues**
   - Increase timeout in `playwright.config.ts`
   - Check application performance
   - Verify database performance

### Debug Mode

```bash
# Run with debug mode
npm run test:smoke:debug

# Run with UI mode
npm run test:smoke:ui

# Run with headed mode
npm run test:smoke:headed
```

## CI/CD Integration

### GitHub Actions
```yaml
- name: Run Smoke Tests
  run: npm run test:smoke:all
```

### Pre-commit Hook
```bash
# Add to pre-commit
npm run test:smoke
```

## Reporting

### HTML Report
```bash
npm run test:e2e:report
```

### JSON Report
```bash
npx playwright test --reporter=json
```

### JUnit Report
```bash
npx playwright test --reporter=junit
```

## Contributing

### Adding New Smoke Tests

1. Create test file in `tests/e2e/smoke/`
2. Use existing helpers and selectors
3. Follow naming convention: `feature.spec.ts`
4. Add `@smoke` tag to tests
5. Update documentation

### Test Naming Convention

```typescript
test('@smoke S1: User registration flow', async ({ page }) => {
  // Test implementation
});
```

### Helper Functions

```typescript
// Add new helpers to smoke-helpers.ts
export class NewFeatureHelper {
  constructor(private page: Page) {}
  
  async newMethod(): Promise<void> {
    // Implementation
  }
}
```

## Support

For issues with smoke tests:
1. Check this documentation
2. Review test logs
3. Use debug mode
4. Check application logs
5. Contact development team
