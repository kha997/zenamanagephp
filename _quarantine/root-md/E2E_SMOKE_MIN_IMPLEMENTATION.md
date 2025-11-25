# E2E-SMOKE-MIN Implementation Documentation

## 📋 **Overview**

E2E-SMOKE-MIN is a minimal, fast-running end-to-end smoke test suite designed to validate critical user flows in the ZenaManage application. This implementation provides a reliable foundation for CI/CD integration and rapid feedback on application health.

## 🎯 **Objectives**

- **Minimal Scope**: Test only critical user flows (login, logout, project creation, project list)
- **Fast Execution**: Complete test suite runs in ~1.3 minutes
- **Reliable Results**: Sequential execution prevents race conditions
- **CI Integration**: GitHub Actions workflow with artifact collection
- **Stable Selectors**: Use data-testid attributes for robust element targeting

## 🏗️ **Architecture**

### **Test Structure**
```
tests/e2e/
├── helpers/
│   └── auth.ts                    # MinimalAuthHelper class
├── smoke/
│   ├── auth-minimal.spec.ts       # Authentication smoke tests
│   ├── project-minimal.spec.ts    # Project creation smoke tests
│   └── README.md                  # Smoke test documentation
└── setup/
    └── global-setup.ts            # Global test setup
```

### **CI Workflow**
```
.github/workflows/
└── e2e-smoke.yml                  # GitHub Actions workflow
```

## 🔧 **Implementation Details**

### **1. MinimalAuthHelper Class**

**Location**: `tests/e2e/helpers/auth.ts`

**Purpose**: Provides minimal authentication functionality for smoke tests

**Key Methods**:
- `login(email, password)`: Authenticates user and waits for universal logged-in marker
- `logout()`: Logs out user and waits for redirect to login page
- `isLoggedIn()`: Checks if user is currently authenticated

**Key Features**:
- Uses `[data-testid="user-menu"]` as universal logged-in marker
- Works across all pages (dashboard, projects, etc.)
- Robust selector fallbacks for user menu interaction
- Deterministic navigation waits

### **2. Smoke Test Specs**

**Authentication Tests** (`auth-minimal.spec.ts`):
- `@smoke admin login succeeds`: Validates successful admin login
- `@smoke admin logout succeeds`: Validates successful admin logout

**Project Tests** (`project-minimal.spec.ts`):
- `@smoke project creation form loads`: Validates project creation form loads
- `@smoke project list loads`: Validates project list page loads

### **3. Playwright Configuration**

**Key Settings**:
- `fullyParallel: false`: Prevents race conditions
- `workers: 1`: Sequential execution
- `testMatch: '**/smoke/*-minimal.spec.ts'`: Only runs minimal specs
- `actionTimeout: 5000ms`: Fast timeout for smoke tests
- `navigationTimeout: 15000ms`: Reasonable navigation timeout

### **4. UI Enhancements**

**Data-testid Attributes Added**:
- `data-testid="user-menu"`: Universal logged-in marker
- `data-testid="user-menu-toggle"`: User menu toggle button
- `data-testid="user-menu-dropdown"`: User menu dropdown
- `data-testid="logout-link"`: Logout link
- `data-testid="create-project"`: New Project link
- `data-testid="dashboard"`: Dashboard container

## 🚀 **Usage**

### **Local Development**

```bash
# Set environment variables
export SMOKE_ADMIN_EMAIL="admin@zena.local"
export SMOKE_ADMIN_PASSWORD="password"

# Run minimal smoke tests
npm run test:e2e:smoke

# Run with browser visible
npm run test:e2e:smoke:headed
```

### **CI Integration**

**GitHub Secrets Required**:
- `SMOKE_ADMIN_EMAIL`: Admin user email for testing
- `SMOKE_ADMIN_PASSWORD`: Admin user password for testing

**Workflow Triggers**:
- Push to main/develop branches
- Pull requests to main/develop
- Manual workflow dispatch

## 📊 **Performance Metrics**

### **Test Execution Results**
```json
{
  "expected": 4,           // All tests pass
  "unexpected": 0,         // No failures
  "duration": 75141,       // ~1.3 minutes
  "flaky": 0               // Stable execution
}
```

### **Individual Test Performance**
- `@smoke admin login succeeds`: ~11.1s
- `@smoke admin logout succeeds`: ~9.1s
- `@smoke project creation form loads`: ~9.9s
- `@smoke project list loads`: ~6.7s

## 🔍 **Technical Solutions**

### **1. Race Condition Resolution**
**Problem**: Parallel execution caused timing conflicts between login attempts
**Solution**: Sequential execution with `fullyParallel: false, workers: 1`
**Result**: Consistent, reliable test execution

### **2. Universal Login Detection**
**Problem**: Dashboard-specific selectors didn't work on all pages
**Solution**: Used `[data-testid="user-menu"]` as universal marker
**Result**: Works across dashboard, projects, and other pages

### **3. Button Selector Issues**
**Problem**: `button:has-text("New Project")` not found (was actually a link)
**Solution**: Added `data-testid="create-project"` to link element
**Result**: Stable, reliable selector

### **4. Form Detection Ambiguity**
**Problem**: `locator('form')` resolved to 2 elements (strict mode violation)
**Solution**: Used `form[action*="projects"]` for specificity
**Result**: Precise form targeting

## 🛠️ **Maintenance**

### **Adding New Smoke Tests**
1. Create new spec file in `tests/e2e/smoke/`
2. Use `*-minimal.spec.ts` naming convention
3. Add `@smoke` tag to test descriptions
4. Use `MinimalAuthHelper` for authentication
5. Add appropriate `data-testid` attributes to UI elements

### **Updating Selectors**
1. Add `data-testid` attributes to UI elements
2. Update test selectors to use data-testid
3. Avoid text-based selectors for stability
4. Test selectors across different pages

### **Performance Optimization**
1. Keep test scope minimal
2. Use sequential execution for stability
3. Set appropriate timeouts
4. Monitor execution duration

## 🚨 **Troubleshooting**

### **Common Issues**

**Login Timeout**:
- Check environment variables are set
- Verify admin user exists in database
- Check universal marker `[data-testid="user-menu"]` exists

**Button Not Found**:
- Verify `data-testid` attributes are present
- Check element is visible and clickable
- Use browser dev tools to inspect selectors

**Form Detection Issues**:
- Use specific form selectors (e.g., `form[action*="projects"]`)
- Avoid generic `form` selectors
- Check for multiple forms on page

**Race Conditions**:
- Ensure sequential execution is enabled
- Check for proper wait conditions
- Use universal markers instead of page-specific elements

## 📈 **Future Enhancements**

### **Potential Expansions**
- Add more critical user flows (user management, document upload)
- Implement mobile smoke tests
- Add performance monitoring
- Create smoke test health dashboard

### **CI/CD Improvements**
- Add smoke test badges to README
- Implement automatic retry on failure
- Add smoke test metrics to monitoring
- Create smoke test reports

## 📚 **References**

- [Playwright Documentation](https://playwright.dev/)
- [GitHub Actions Documentation](https://docs.github.com/en/actions)
- [Laravel Testing Documentation](https://laravel.com/docs/testing)
- [E2E Testing Best Practices](https://playwright.dev/docs/best-practices)

---

**Last Updated**: 2025-01-21  
**Version**: 1.0  
**Status**: Complete ✅
