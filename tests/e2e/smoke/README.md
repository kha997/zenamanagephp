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

**Note**: Ensure your database is seeded with an admin user matching these credentials. See [Database Seeding Guide](../../../database/seeders/README.md) for setup instructions.

## Running Tests

### Local Development
```bash
# Set environment variables
export SMOKE_ADMIN_EMAIL="admin@zena.local"
export SMOKE_ADMIN_PASSWORD="password"

# Run minimal smoke tests (recommended)
npm run test:e2e:smoke:headed

# Alternative: headless execution
npm run test:e2e:smoke
```

**Prerequisites**: Ensure your database is seeded with admin users. See [Database Seeding Guide](../../../database/seeders/README.md) for setup instructions.

### Important Notes
- **Use npm scripts**: Always use `npm run test:e2e:smoke*` to ensure minimal scope execution
- **Avoid legacy tests**: Do NOT run `npx playwright test tests/e2e/smoke` directly as this executes heavy legacy specs
- **Minimal scope**: Only runs `*-minimal.spec.ts` files (4 tests total)
- **Fast execution**: Designed for quick CI feedback, not comprehensive testing

## CI Integration
Tests run automatically on:
- Push to main/develop branches
- Pull requests to main/develop
- Manual workflow dispatch

Artifacts (traces, videos, screenshots) are automatically uploaded on test completion.