# E2E-SMOKE-MIN API Documentation

## 📋 **Overview**

This document provides API documentation for the E2E-SMOKE-MIN implementation, including test helpers, configuration, and CI integration.

## 🔧 **MinimalAuthHelper API**

### **Class: MinimalAuthHelper**

**Location**: `tests/e2e/helpers/auth.ts`

#### **Constructor**
```typescript
constructor(page: Page)
```
- **Parameters**: `page` - Playwright Page instance
- **Returns**: MinimalAuthHelper instance

#### **Methods**

##### **login(email: string, password: string): Promise<void>**
Authenticates user and waits for universal logged-in marker.

**Parameters**:
- `email` (string): User email address
- `password` (string): User password

**Behavior**:
1. Navigates to `/login`
2. Fills email and password fields
3. Clicks login button
4. Waits for `[data-testid="user-menu"]` to appear
5. Throws error if timeout exceeded

**Example**:
```typescript
const auth = new MinimalAuthHelper(page);
await auth.login("admin@zena.local", "password");
```

##### **logout(): Promise<void>**
Logs out user and waits for redirect to login page.

**Parameters**: None

**Behavior**:
1. Waits for `[data-testid="user-menu"]` to be visible
2. Clicks user menu toggle button
3. Waits for dropdown to be visible
4. Clicks logout link
5. Waits for redirect to `/login`

**Example**:
```typescript
const auth = new MinimalAuthHelper(page);
await auth.logout();
```

##### **isLoggedIn(): Promise<boolean>**
Checks if user is currently authenticated.

**Parameters**: None

**Returns**: `Promise<boolean>` - true if logged in, false otherwise

**Behavior**:
1. Waits for `[data-testid="user-menu"]` to appear
2. Returns true if found within timeout
3. Returns false if timeout exceeded

**Example**:
```typescript
const auth = new MinimalAuthHelper(page);
const loggedIn = await auth.isLoggedIn();
expect(loggedIn).toBe(true);
```

## 🧪 **Test Specifications API**

### **Authentication Tests**

#### **auth-minimal.spec.ts**

##### **@smoke admin login succeeds**
```typescript
test('@smoke admin login succeeds', async ({ page }) => {
  const auth = new MinimalAuthHelper(page);
  await auth.login(process.env.SMOKE_ADMIN_EMAIL!, process.env.SMOKE_ADMIN_PASSWORD!);
  expect(await auth.isLoggedIn()).toBe(true);
});
```

**Purpose**: Validates successful admin login
**Duration**: ~11.1s
**Dependencies**: Environment variables `SMOKE_ADMIN_EMAIL`, `SMOKE_ADMIN_PASSWORD`

##### **@smoke admin logout succeeds**
```typescript
test('@smoke admin logout succeeds', async ({ page }) => {
  const auth = new MinimalAuthHelper(page);
  await auth.login(process.env.SMOKE_ADMIN_EMAIL!, process.env.SMOKE_ADMIN_PASSWORD!);
  await auth.logout();
  await expect(page).toHaveURL(/\/login/);
});
```

**Purpose**: Validates successful admin logout
**Duration**: ~9.1s
**Dependencies**: Environment variables `SMOKE_ADMIN_EMAIL`, `SMOKE_ADMIN_PASSWORD`

### **Project Tests**

#### **project-minimal.spec.ts**

##### **@smoke project creation form loads**
```typescript
test('@smoke project creation form loads', async ({ page }) => {
  const auth = new MinimalAuthHelper(page);
  await auth.login(process.env.SMOKE_ADMIN_EMAIL!, process.env.SMOKE_ADMIN_PASSWORD!);
  
  await page.goto('/app/projects');
  await page.click('[data-testid="create-project"]');
  await expect(page.locator('form[action*="projects"]')).toBeVisible();
});
```

**Purpose**: Validates project creation form loads
**Duration**: ~9.9s
**Dependencies**: Environment variables, `data-testid="create-project"` on UI

##### **@smoke project list loads**
```typescript
test('@smoke project list loads', async ({ page }) => {
  const auth = new MinimalAuthHelper(page);
  await auth.login(process.env.SMOKE_ADMIN_EMAIL!, process.env.SMOKE_ADMIN_PASSWORD!);

  await page.goto('/app/projects');
  await expect(page.locator('h1:has-text("Projects")')).toBeVisible();
});
```

**Purpose**: Validates project list page loads
**Duration**: ~6.7s
**Dependencies**: Environment variables

## ⚙️ **Configuration API**

### **Playwright Configuration**

#### **smoke-chromium Project**
```typescript
{
  name: 'smoke-chromium',
  testMatch: '**/smoke/*-minimal.spec.ts',
  testIgnore: '**/smoke/api-*.spec.ts',
  use: { 
    ...devices['Desktop Chrome'],
    actionTimeout: 5000,
    navigationTimeout: 15000,
  },
  fullyParallel: false,
  workers: 1,
}
```

**Key Settings**:
- `testMatch`: Only runs `*-minimal.spec.ts` files
- `fullyParallel: false`: Prevents race conditions
- `workers: 1`: Sequential execution
- `actionTimeout: 5000ms`: Fast timeout for smoke tests
- `navigationTimeout: 15000ms`: Reasonable navigation timeout

### **NPM Scripts**

#### **test:e2e:smoke**
```json
{
  "scripts": {
    "test:e2e:smoke": "playwright test --project=smoke-chromium"
  }
}
```

**Purpose**: Run minimal smoke tests headless
**Usage**: `npm run test:e2e:smoke`

#### **test:e2e:smoke:headed**
```json
{
  "scripts": {
    "test:e2e:smoke:headed": "playwright test --project=smoke-chromium --headed"
  }
}
```

**Purpose**: Run minimal smoke tests with browser visible
**Usage**: `npm run test:e2e:smoke:headed`

## 🔄 **CI/CD API**

### **GitHub Actions Workflow**

#### **Workflow File**: `.github/workflows/e2e-smoke.yml`

##### **Triggers**
```yaml
on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main, develop]
```

##### **Environment Variables**
```yaml
env:
  SMOKE_ADMIN_EMAIL: ${{ secrets.SMOKE_ADMIN_EMAIL }}
  SMOKE_ADMIN_PASSWORD: ${{ secrets.SMOKE_ADMIN_PASSWORD }}
```

**Required GitHub Secrets**:
- `SMOKE_ADMIN_EMAIL`: Admin user email for testing
- `SMOKE_ADMIN_PASSWORD`: Admin user password for testing

##### **Test Execution**
```yaml
- name: Run smoke tests
  run: npm run test:e2e:smoke
  env:
    SMOKE_ADMIN_EMAIL: ${{ secrets.SMOKE_ADMIN_EMAIL }}
    SMOKE_ADMIN_PASSWORD: ${{ secrets.SMOKE_ADMIN_PASSWORD }}
```

##### **Artifact Collection**
```yaml
- name: Upload test results
  uses: actions/upload-artifact@v4
  if: always()
  with:
    name: playwright-report
    path: test-results/
    retention-days: 30
```

**Artifacts Collected**:
- Test traces (`.zip` files)
- Test videos (`.webm` files)
- Screenshots (`.png` files)
- Error context (`.md` files)

## 📊 **Results API**

### **Test Results JSON**

#### **Location**: `test-results/results.json`

#### **Schema**
```typescript
interface TestResults {
  startTime: string;        // ISO timestamp
  duration: number;         // Duration in milliseconds
  expected: number;         // Number of passing tests
  skipped: number;          // Number of skipped tests
  unexpected: number;       // Number of failing tests
  flaky: number;           // Number of flaky tests
}
```

#### **Example Results**
```json
{
  "startTime": "2025-10-23T06:55:00.354Z",
  "duration": 75140.99799999999,
  "expected": 4,
  "skipped": 0,
  "unexpected": 0,
  "flaky": 0
}
```

### **Performance Metrics**

#### **Individual Test Performance**
- `@smoke admin login succeeds`: ~11.1s
- `@smoke admin logout succeeds`: ~9.1s
- `@smoke project creation form loads`: ~9.9s
- `@smoke project list loads`: ~6.7s
- **Total Duration**: ~1.3 minutes

#### **Success Rate**
- **Expected**: 4 tests (100% pass rate)
- **Unexpected**: 0 tests (0% failure rate)
- **Flaky**: 0 tests (0% flaky rate)

## 🛠️ **UI Elements API**

### **Required Data-testid Attributes**

#### **Authentication Elements**
```html
<!-- User Menu Container -->
<div data-testid="user-menu">
  <!-- User Menu Toggle Button -->
  <button data-testid="user-menu-toggle">...</button>
  
  <!-- User Menu Dropdown -->
  <div data-testid="user-menu-dropdown">
    <!-- Logout Link -->
    <a data-testid="logout-link">Logout</a>
  </div>
</div>
```

#### **Project Elements**
```html
<!-- New Project Link -->
<a href="/app/projects/create" data-testid="create-project">
  <i class="fas fa-plus mr-2"></i>New Project
</a>

<!-- Dashboard Container -->
<div data-testid="dashboard">...</div>
```

### **Form Selectors**

#### **Project Creation Form**
```typescript
// Specific form selector to avoid strict mode violations
page.locator('form[action*="projects"]')
```

#### **Login Form**
```typescript
// Login form elements
page.locator('#email')
page.locator('#password')
page.locator('#loginButton')
```

## 🚨 **Error Handling API**

### **Common Error Types**

#### **TimeoutError**
```typescript
// Login timeout
TimeoutError: page.waitForSelector: Timeout 10000ms exceeded.
Call log:
  - waiting for locator('[data-testid="user-menu"]') to be visible
```

**Resolution**: Check environment variables and universal marker

#### **StrictModeViolationError**
```typescript
// Multiple form elements
Error: strict mode violation: locator('form') resolved to 2 elements
```

**Resolution**: Use specific form selector `form[action*="projects"]`

#### **ElementNotFoundError**
```typescript
// Button not found
TimeoutError: page.click: Timeout 5000ms exceeded.
Call log:
  - waiting for locator('button:has-text("New Project")')
```

**Resolution**: Use data-testid selector `[data-testid="create-project"]`

## 📚 **Best Practices**

### **Selector Guidelines**
1. **Use data-testid**: Prefer `data-testid` over text-based selectors
2. **Avoid generic selectors**: Use specific selectors (e.g., `form[action*="projects"]`)
3. **Universal markers**: Use elements that exist across all pages
4. **Stable selectors**: Avoid selectors that change with content

### **Test Design**
1. **Minimal scope**: Test only critical user flows
2. **Sequential execution**: Prevent race conditions
3. **Fast timeouts**: Use appropriate timeout values
4. **Clear assertions**: Use specific, meaningful assertions

### **CI Integration**
1. **Environment variables**: Use GitHub secrets for credentials
2. **Artifact collection**: Collect traces, videos, and screenshots
3. **Error reporting**: Include detailed error context
4. **Performance monitoring**: Track execution duration

---

**Last Updated**: 2025-01-21  
**Version**: 1.0  
**Status**: Complete ✅
