# E2E Handoff Report

This document summarizes the automated end-to-end coverage that maps to the requested delivery cards and provides a fast reference for execution.

## E2E-SMOKE-001 – Critical Smoke Coverage

- **Scenarios**: authentication bootstrap, dashboard KPIs availability, REST project creation, dashboard preference persistence.
- **Location**: `tests/e2e/smoke/smoke.spec.ts`
- **Command**: `npx playwright test --project=chromium --grep @smoke`
- **CI**: `e2e-smoke` job in `.github/workflows/ci-cd.yml`
- **Notes**: Uses seeded admin credentials (`admin@zena.local` / `password`). Database and storage reset handled by `tests/e2e/setup/global-setup.ts`.

## E2E-CORE-010 – Core Delivery Flows

- **Scenarios**: project CRUD lifecycle, task lifecycle within tenant context.
- **Location**:
  - Projects: `tests/e2e/core/projects-core.spec.ts`
  - Tasks: `tests/e2e/core/tasks-core.spec.ts`
- **Command**: `npx playwright test --project=chromium --grep @core`
- **CI**: `e2e-core` job (Playwright Core Suite)
- **Data Prep**: Each test provisions isolated resources and performs clean-up (project / task deletion).

## E2E-CORE-020 – Operational Core Extensions

- **Scenarios**: document upload + metadata management, admin user management, multi-tenant search isolation.
- **Location**:
  - Documents: `tests/e2e/core/documents-core.spec.ts`
  - Admin users & roles: `tests/e2e/core/admin-core.spec.ts`
  - Multi-tenant search: `tests/e2e/core/search-core.spec.ts`
- **Command**: included in the `@core` suite.
- **Highlights**: Validates cross-tenant boundaries, RBAC-driven mutations, and artifact clean-up.

## E2E-REG-030 – Regression Shield

- **Scenarios**: authentication hardening, tenant boundary enforcement, user preference regressions, user removal, dashboard import/export, metrics availability.
- **Location**:
  - Security: `tests/e2e/regression/security-regression.spec.ts`
  - Preferences: `tests/e2e/regression/preferences-regression.spec.ts`
  - Data integrity: `tests/e2e/regression/data-integrity-regression.spec.ts`
- **Command**: `npx playwright test --project=chromium --grep @regression`
- **CI**: `e2e-regression` job (Playwright Regression Suite)
- **Output**: Reports uploaded as artifacts per suite run.

## Execution Environment

1. **Global setup**: `tests/e2e/setup/global-setup.ts` resets the database (`migrate:fresh` + `DatabaseSeeder` + `E2EDatabaseSeeder`), primes storage/mail, and applies crypto polyfills.
2. **Environment file**: `.env.e2e` is copied to `.env` during CI jobs; adjust locally if credentials differ.
3. **Browsers**: CI executes `chromium` for deterministic runtime. Additional projects (`firefox`, `webkit`) remain available for local execution.

## Quick Start

```bash
# Install dependencies
npm ci
composer install

# Copy environment (if not already configured)
cp .env.e2e .env
php artisan key:generate

# Run suites
npx playwright test --project=chromium --grep @smoke
npx playwright test --project=chromium --grep @core
npx playwright test --project=chromium --grep @regression
```

All suites rely on Playwright's default HTML reports (`playwright-report/`). CI uploads each suite's report for later review.
