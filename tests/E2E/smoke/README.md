# E2E Smoke Tests

## Overview
Minimal smoke test suite for critical user flows. Designed for fast execution and CI integration.

## Test Scope
- **Authentication**: Login/logout flow verification
- **Project Creation**: Form load and list visibility checks

## Files
- `tests/e2e/helpers/auth.ts` - Minimal authentication helper
- `tests/e2e/smoke/auth-minimal.spec.ts` - Authentication smoke tests
- `tests/e2e/smoke/project-minimal.spec.ts` - Project creation smoke tests

## Environment Variables
Configure these secrets in GitHub:
- `SMOKE_ADMIN_EMAIL` - Admin user email for testing
- `SMOKE_ADMIN_PASSWORD` - Admin user password for testing

## Running Tests
```bash
# Run all smoke tests
npx playwright test tests/e2e/smoke --grep @smoke

# Run specific test file
npx playwright test tests/e2e/smoke/auth-minimal.spec.ts
```

## CI Integration
Tests run automatically on:
- Push to main/develop branches
- Pull requests to main/develop
- Manual workflow dispatch

Artifacts (traces, videos, screenshots) are automatically uploaded on test completion.