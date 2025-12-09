# Authentication E2E Test Suite

Comprehensive Playwright end-to-end test suite for hardening the authentication surface of the ZenaManage multi-tenant application.

## Overview

This test suite provides **zero-skip** coverage of all authentication flows:
- Registration (with email verification)
- Login (with various account states)
- Two-Factor Authentication (TOTP)
- Password Reset
- Password Change
- Security hardening
- Accessibility compliance
- Performance budgets

## Running Tests

### Prerequisites

```bash
# Install dependencies
npm install
composer install

# Install Playwright browsers
npx playwright install
```

### Run All Auth Tests

```bash
npm run test:auth
```

### Run in UI Mode

```bash
npm run test:auth:ui
```

### Run with Head (headed browser)

```bash
npm run test:auth:headed
```

### View Test Report

```bash
npm run test:auth:report
```

### Run Specific Test File

```bash
npx playwright test tests/E2E/auth/login.spec.ts --config=playwright.auth.config.ts
```

### Run on Specific Browser

```bash
# Chromium
npx playwright test --config=playwright.auth.config.ts --project=auth-desktop-chromium

# Firefox
npx playwright test --config=playwright.auth.config.ts --project=auth-desktop-firefox

# WebKit
npx playwright test --config=playwright.auth.config.ts --project=auth-desktop-webkit

# Mobile Chrome
npx playwright test --config=playwright.auth.config.ts --project=auth-mobile-chrome

# Mobile Safari
npx playwright test --config=playwright.auth.config.ts --project=auth-mobile-safari
```

## Configuration

### Environment Variables

Set these in your `.env` or environment:

```bash
# Base URL for the application
BASE_URL=http://127.0.0.1:8000

# API base URL (if separate from BASE_URL)
API_BASE_URL=http://127.0.0.1:8000

# Database mode (sqlite recommended for speed)
DB_MODE=sqlite

# Mailbox UI for email testing (MailHog or Mailpit)
MAILBOX_UI=http://localhost:8025

# Feature flags
FEATURES_TWO_FA=true
FEATURES_INVITE_ONLY=false
FEATURES_SSO=false
```

### Test Data

Test users are automatically seeded via the test API endpoints. Canonical users:

- `admin@zena.test` - Admin user with 2FA enabled
- `manager@zena.test` - Manager user
- `member@zena.test` - Member user
- `locked@zena.test` - Locked account
- `unverified@zena.test` - Unverified account

Default password for all test users: `password`

## Test Structure

### Helper Files

- `helpers/auth.ts` - Authentication helpers (login, logout, assertions)
- `helpers/mailbox.ts` - Email testing utilities
- `helpers/seeds.ts` - Test data seeding
- `helpers/assertions.ts` - Security & accessibility assertions

### Test Files

- `registration.spec.ts` - Registration flow tests
- `login.spec.ts` - Login flow tests  
- `2fa.spec.ts` - Two-factor authentication tests
- `reset-password.spec.ts` - Password reset flow
- `change-password.spec.ts` - In-app password change
- `hardening.spec.ts` - Security hardening tests
- `a11y-visual.spec.ts` - Accessibility & visual regression
- `perf-smoke.spec.ts` - Performance budgets

## Test Coverage

### Registration (`registration.spec.ts`)

- ✅ Form validation (required fields, email format, password policy)
- ✅ Password confirmation match
- ✅ Toggle password visibility
- ✅ Duplicate email prevention (case-insensitive)
- ✅ Terms acceptance requirement
- ✅ Email verification flow
- ✅ Verification link extraction and usage
- ✅ Token expiration and reuse prevention
- ✅ Email resend throttling
- ✅ Invite-only registration handling

### Login (`login.spec.ts`)

- ✅ Successful login for verified users
- ✅ Neutral error messages (no PII leakage)
- ✅ Wrong password handling
- ✅ Unverified account prompt
- ✅ Locked account handling
- ✅ Rate limiting enforcement
- ✅ Remember me cookie (secure flags)
- ✅ CSRF protection
- ✅ Session management
- ✅ Session expiry handling
- ✅ Locale persistence
- ✅ Mobile responsiveness

### Two-Factor Authentication (`2fa.spec.ts`)

- ✅ TOTP QR code display
- ✅ Secret display for manual entry
- ✅ TOTP code confirmation
- ✅ Recovery codes display and download
- ✅ Login with TOTP code
- ✅ Invalid code rejection
- ✅ Recovery code login
- ✅ Recovery code one-time use
- ✅ Old code invalidation on regeneration
- ✅ Password requirement for 2FA disable

### Password Reset (`reset-password.spec.ts`)

- ✅ Email validation
- ✅ Neutral success message
- ✅ Rate limiting
- ✅ Email sending
- ✅ Reset link extraction
- ✅ Password policy enforcement
- ✅ Password confirmation requirement
- ✅ Session invalidation on reset
- ✅ Token reuse prevention
- ✅ Expired token handling
- ✅ Tampered token rejection

### Password Change (`change-password.spec.ts`)

- ✅ Current password requirement
- ✅ Current password validation
- ✅ New password policy enforcement
- ✅ Password confirmation match
- ✅ Successful password change
- ✅ Session invalidation after change
- ✅ Old password reuse prevention
- ✅ CSRF protection

### Security Hardening (`hardening.spec.ts`)

- ✅ CSRF protection enforcement
- ✅ XSS input sanitization
- ✅ Open redirect protection
- ✅ Secure cookie flags (HttpOnly, Secure, SameSite)
- ✅ Security headers (X-Frame-Options, X-Content-Type-Options, CSP)
- ✅ Clickjacking prevention
- ✅ Cache-control for auth pages
- ✅ SQL injection attempt handling
- ✅ Timing attack prevention
- ✅ Account enumeration prevention

### Accessibility (`a11y-visual.spec.ts`)

- ✅ Visible focus indicators
- ✅ Keyboard-only navigation
- ✅ Screen reader announcements
- ✅ ARIA labels and roles
- ✅ Form semantics
- ✅ Skip to main content
- ✅ Color contrast compliance
- ✅ Reduced motion support
- ✅ Visual snapshots

### Performance (`perf-smoke.spec.ts`)

- ✅ Page load within budget (< 2s)
- ✅ TTFB measurement (< 500ms)
- ✅ First Contentful Paint (< 2s)
- ✅ Login flow completion (< 3s)
- ✅ Cold start efficiency
- ✅ Warm reload efficiency
- ✅ Resource size optimization
- ✅ Render-blocking minimization
- ✅ Lazy loading for images

## Data Test IDs

Tests use `data-testid` attributes for reliable selectors. If missing in views, add them:

```php
// Example for login form
<input data-testid="email-input" ... />
<input data-testid="password-input" ... />
<button data-testid="login-submit">Sign in</button>
<label data-testid="remember-checkbox">Remember me</label>
```

## Seeding Test Data

Test data is seeded automatically during test setup via API endpoints:

```typescript
// Create a test user
await createUser({
  email: 'test@example.com',
  password: 'TestPassword123!',
  name: 'Test User',
  tenant: 'zena',
  role: 'member',
  verified: true,
  locked: false,
  twoFA: false,
});
```

## Email Testing

For email verification testing, the suite can use:
- **MailHog** (default on `http://localhost:8025`)
- **Mailpit** (alternative mailbox)

Install and run one:

```bash
# MailHog
docker run -d -p 8025:8025 -p 1025:1025 mailhog/mailhog

# Mailpit  
docker run -d -p 8025:8025 -p 1025:1025 axllent/mailpit
```

## Debugging Failed Tests

### Run in Headed Mode

```bash
npm run test:auth:headed
```

### Run in Debug Mode

```bash
npx playwright test tests/E2E/auth/login.spec.ts --config=playwright.auth.config.ts --debug
```

### View Traces

Traces are captured on first retry. View with:

```bash
npx playwright show-trace test-results/auth/trace.zip
```

### Check Screenshots

Screenshots are saved on failure:

```bash
open test-results/auth/**/*.png
```

## CI Integration

Tests run automatically on push/PR via GitHub Actions workflow (`.github/workflows/e2e-auth.yml`):

- ✅ Multiple browser matrix (Chromium, Firefox, WebKit)
- ✅ Mobile viewport testing
- ✅ Test artifacts (screenshots, traces, reports)
- ✅ Retry on failure (2x)

## Writing New Tests

Follow the patterns in existing test files:

```typescript
import { test, expect } from '@playwright/test';

test('describes what the test does', async ({ page }) => {
  // Arrange: setup test data
  // Act: perform the action
  // Assert: verify the result
});
```

### Best Practices

1. ✅ Use `data-testid` selectors
2. ✅ Don't use `.skip` or `.only` (forbidOnly: true)
3. ✅ No `waitForTimeout` - use proper awaits
4. ✅ Seed and cleanup test data per test
5. ✅ Keep tests deterministic and idempotent
6. ✅ Assert security and accessibility

## Troubleshooting

### Database Issues

```bash
# Reset test database
touch database/database.sqlite
php artisan migrate:fresh --env=testing
php artisan db:seed --class=AuthE2ESeeder --env=testing
```

### Port Conflicts

If port 8000 is in use:

```bash
# Use different port
BASE_URL=http://127.0.0.1:8001 npm run test:auth
```

### Browser Not Found

```bash
npx playwright install chromium firefox webkit
```

## Contributing

When adding new auth features:

1. Add corresponding tests to relevant spec files
2. Add `data-testid` attributes to views if needed
3. Update this README with new test coverage
4. Ensure all tests pass before submitting PR

## References

- [Playwright Documentation](https://playwright.dev)
- [Testing Best Practices](https://playwright.dev/docs/best-practices)
- [Project Rules](PROJECT_RULES.md)
- [Documentation Index](../DOCUMENTATION_INDEX.md)

