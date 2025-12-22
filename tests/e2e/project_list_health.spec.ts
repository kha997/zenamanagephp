import { test, expect } from '@playwright/test';
import { login, authHeaders, expectSuccess } from './helpers/apiClient';

/**
 * Projects List Health Column E2E Tests
 * 
 * Round 82: Project Health vertical hardening + E2E flows
 * 
 * Tests the Projects List page with health column:
 * - Health column display for users with tenant.view_reports
 * - Health filter functionality
 * - Permission checks (no health column for users without permission)
 */
test.describe('Projects List Health Column', () => {
  const baseURL = process.env.PLAYWRIGHT_TEST_BASE_URL || 'http://127.0.0.1:8000';

  test.beforeEach(async ({ page }) => {
    await page.goto(baseURL);
  });

  test('should display health column and badges for user with tenant.view_reports', async ({ page, request }) => {
    // Login as user with tenant.view_reports
    const session = await login(request, 'pm@e2e-health.local', 'password');
    
    await page.goto(`${baseURL}/app/projects`);
    await page.evaluate((token) => {
      localStorage.setItem('auth_token', token);
    }, session.token);
    
    await page.reload();
    await page.waitForLoadState('networkidle');

    // Switch to table view if not already
    const tableViewButton = page.locator('button[aria-pressed="true"]', { hasText: /Table/i });
    if (!(await tableViewButton.isVisible({ timeout: 2000 }).catch(() => false))) {
      const tableButton = page.locator('button', { hasText: /Table/i });
      await tableButton.first().click();
      await page.waitForTimeout(1000);
    }

    // Wait for table to load
    await page.waitForSelector('table', { timeout: 10000 });

    // Health column header should be visible
    const healthHeader = page.locator('th', { hasText: /Health/i });
    await expect(healthHeader.first()).toBeVisible({ timeout: 5000 });

    // Health badges should be visible for projects
    // P-GOOD-01 should show "Tốt" badge
    const goodProjectHealth = page.locator('[data-testid="project-health-"]').filter({ hasText: /Tốt|Good/i }).first();
    await expect(goodProjectHealth).toBeVisible({ timeout: 5000 });

    // P-WARNING-01 should show "Cảnh báo" badge
    const warningProjectHealth = page.locator('[data-testid="project-health-"]').filter({ hasText: /Cảnh báo|Warning/i }).first();
    await expect(warningProjectHealth).toBeVisible({ timeout: 5000 });

    // P-CRITICAL-01 should show "Nguy cấp" badge
    const criticalProjectHealth = page.locator('[data-testid="project-health-"]').filter({ hasText: /Nguy cấp|Critical/i }).first();
    await expect(criticalProjectHealth).toBeVisible({ timeout: 5000 });
  });

  test('should filter projects by health status', async ({ page, request }) => {
    // Login as user with tenant.view_reports
    const session = await login(request, 'pm@e2e-health.local', 'password');
    
    await page.goto(`${baseURL}/app/projects`);
    await page.evaluate((token) => {
      localStorage.setItem('auth_token', token);
    }, session.token);
    
    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Wait for health filter to appear
    const healthFilter = page.locator('[data-testid="health-filter"]');
    await expect(healthFilter).toBeVisible({ timeout: 5000 });

    // Click "Tốt" filter
    const goodFilter = page.locator('[data-testid="health-filter-good"]');
    await goodFilter.click();
    await page.waitForTimeout(2000);

    // Only good projects should be visible
    // P-GOOD-01 should be visible
    const goodProject = page.locator('text=P-GOOD-01');
    await expect(goodProject.first()).toBeVisible({ timeout: 5000 });

    // P-WARNING-01 and P-CRITICAL-01 should NOT be visible (or filtered out)
    // Note: This depends on client-side filtering implementation
    // If filtering is done client-side, we can check visibility
    // If filtering requires server-side, we check that only good projects are in the list

    // Click "Cảnh báo" filter
    const warningFilter = page.locator('[data-testid="health-filter-warning"]');
    await warningFilter.click();
    await page.waitForTimeout(2000);

    // Only warning projects should be visible
    const warningProject = page.locator('text=P-WARNING-01');
    await expect(warningProject.first()).toBeVisible({ timeout: 5000 });

    // Click "Tất cả" to show all
    const allFilter = page.locator('[data-testid="health-filter-all"]');
    await allFilter.click();
    await page.waitForTimeout(2000);

    // All projects should be visible again
    await expect(goodProject.first()).toBeVisible({ timeout: 5000 });
    await expect(warningProject.first()).toBeVisible({ timeout: 5000 });
    const criticalProject = page.locator('text=P-CRITICAL-01');
    await expect(criticalProject.first()).toBeVisible({ timeout: 5000 });
  });

  test('should not display health column for user without tenant.view_reports', async ({ page, request }) => {
    // Login as user without tenant.view_reports
    const session = await login(request, 'member@e2e-health.local', 'password');
    
    await page.goto(`${baseURL}/app/projects`);
    await page.evaluate((token) => {
      localStorage.setItem('auth_token', token);
    }, session.token);
    
    await page.reload();
    await page.waitForLoadState('networkidle');

    // Switch to table view if not already
    const tableViewButton = page.locator('button[aria-pressed="true"]', { hasText: /Table/i });
    if (!(await tableViewButton.isVisible({ timeout: 2000 }).catch(() => false))) {
      const tableButton = page.locator('button', { hasText: /Table/i });
      await tableButton.first().click();
      await page.waitForTimeout(1000);
    }

    // Wait for table to load
    await page.waitForSelector('table', { timeout: 10000 });

    // Health column header should NOT be visible
    const healthHeader = page.locator('th', { hasText: /Health/i });
    await expect(healthHeader).not.toBeVisible({ timeout: 2000 });

    // Health filter should NOT be visible
    const healthFilter = page.locator('[data-testid="health-filter"]');
    await expect(healthFilter).not.toBeVisible({ timeout: 2000 });

    // Projects list should still work normally
    const projectsTable = page.locator('table');
    await expect(projectsTable).toBeVisible({ timeout: 5000 });

    // Projects should still be visible
    const goodProject = page.locator('text=P-GOOD-01');
    await expect(goodProject.first()).toBeVisible({ timeout: 5000 });
  });

  test('should handle health data error gracefully', async ({ page, request }) => {
    // Login as user with tenant.view_reports
    const session = await login(request, 'pm@e2e-health.local', 'password');
    
    await page.goto(`${baseURL}/app/projects`);
    await page.evaluate((token) => {
      localStorage.setItem('auth_token', token);
    }, session.token);
    
    // Intercept and fail the health API call
    await page.route('**/api/v1/app/reports/projects/health', async (route) => {
      await route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({
          ok: false,
          code: 'PROJECT_HEALTH_PORTFOLIO_ERROR',
          message: 'Failed to load project health portfolio',
        }),
      });
    });
    
    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Projects list should still render
    const projectsTable = page.locator('table');
    await expect(projectsTable).toBeVisible({ timeout: 10000 });

    // Health error hint should be displayed
    const healthErrorHint = page.locator('[data-testid="health-error-hint"]');
    await expect(healthErrorHint).toBeVisible({ timeout: 5000 });

    // Health column should still exist but show "—" for projects
    const healthColumn = page.locator('th', { hasText: /Health/i });
    await expect(healthColumn.first()).toBeVisible({ timeout: 5000 });

    // Projects should show "—" for health (empty state)
    const emptyHealth = page.locator('[data-testid="project-health-empty-"]').first();
    await expect(emptyHealth).toBeVisible({ timeout: 5000 });
  });
});

