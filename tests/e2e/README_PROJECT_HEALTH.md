# Project Health E2E Tests

Round 82: Project Health vertical hardening + E2E flows

## Setup

### 1. Seed E2E Health Demo Data

Run the seeder to create test data for E2E tests:

```bash
php artisan db:seed --class=Database\\Seeders\\E2E\\ProjectHealthDemoSeeder
```

This creates:
- **Tenant**: E2E Health Demo Tenant
- **Users**:
  - `pm@e2e-health.local` / `password` (has `tenant.view_reports` permission)
  - `member@e2e-health.local` / `password` (no `tenant.view_reports` permission)
- **Projects**:
  - `P-GOOD-01`: Good health (all tasks on time, cost on budget)
  - `P-WARNING-01`: Warning health (schedule at_risk: 2 overdue tasks)
  - `P-CRITICAL-01`: Critical health (delayed: 5 overdue tasks + over_budget: 15% overrun)

## Running Tests

### Run all Project Health E2E tests:

```bash
npx playwright test tests/e2e/project_health_dashboard.spec.ts
npx playwright test tests/e2e/project_health_portfolio.spec.ts
npx playwright test tests/e2e/project_list_health.spec.ts
npx playwright test tests/e2e/project_overview_health.spec.ts
npx playwright test tests/e2e/project_health_history.spec.ts
```

### Run all at once:

```bash
npx playwright test tests/e2e/project_health_*.spec.ts
```

## Test Files

1. **project_health_dashboard.spec.ts**
   - Widget display and counters
   - Navigation from counters to Health Portfolio
   - Permission checks

2. **project_health_portfolio.spec.ts**
   - Direct access with query params
   - Filter changes and URL sync
   - CSV export
   - Permission checks

3. **project_list_health.spec.ts**
   - Health column display
   - Health filter functionality
   - Permission checks

4. **project_overview_health.spec.ts**
   - Health card display
   - Deep-link navigation to tasks/reports
   - Different health statuses

5. **project_health_history.spec.ts**
   - History card display on Project Detail page
   - Viewing history for projects with snapshots
   - Empty history state
   - Error handling and retry
   - Permission gating (tenant.view_reports)

## Notes

- Tests require the E2E Health Demo data to be seeded first
- Tests use fixed project codes (P-GOOD-01, P-WARNING-01, P-CRITICAL-01)
- All tests verify permission `tenant.view_reports` is respected
- Round 86: E2E tenant includes health snapshots for demo projects (P-GOOD-01, P-WARNING-01, P-CRITICAL-01)

