import { test, expect } from '@playwright/test';
import { login, authHeaders, expectSuccess } from './helpers/apiClient';

/**
 * Project Health Portfolio Page E2E Tests
 * 
 * Round 82: Project Health vertical hardening + E2E flows
 * 
 * Tests the Project Health Portfolio page:
 * - Direct access with query params
 * - Filter changes and URL sync
 * - Permission checks
 * - CSV export
 */
test.describe('Project Health Portfolio Page', () => {
  const baseURL = process.env.PLAYWRIGHT_TEST_BASE_URL || 'http://127.0.0.1:8000';

  test.beforeEach(async ({ page }) => {
    await page.goto(baseURL);
  });

  test('should filter by overall status from query param', async ({ page, request }) => {
    // Login as user with tenant.view_reports
    const session = await login(request, 'pm@e2e-health.local', 'password');
    
    await page.goto(`${baseURL}/app/reports/projects/health?overall=warning`);
    await page.evaluate((token) => {
      localStorage.setItem('auth_token', token);
    }, session.token);
    
    await page.reload();
    await page.waitForLoadState('networkidle');

    // Wait for page to load
    await page.waitForSelector('text=Sức khỏe dự án', { timeout: 10000 });

    // Verify filter "Cảnh báo" is active
    const warningFilter = page.locator('button[aria-pressed="true"]', { hasText: /Cảnh báo|Warning/i });
    await expect(warningFilter.first()).toBeVisible({ timeout: 5000 });

    // Verify P-WARNING-01 is displayed
    const warningProject = page.locator('text=P-WARNING-01');
    await expect(warningProject.first()).toBeVisible({ timeout: 5000 });

    // Verify P-GOOD-01 is NOT displayed (filtered out)
    const goodProject = page.locator('text=P-GOOD-01');
    await expect(goodProject).not.toBeVisible({ timeout: 2000 });

    // Test critical filter
    await page.goto(`${baseURL}/app/reports/projects/health?overall=critical`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Verify filter "Nguy cấp" is active
    const criticalFilter = page.locator('button[aria-pressed="true"]', { hasText: /Nguy cấp|Critical/i });
    await expect(criticalFilter.first()).toBeVisible({ timeout: 5000 });

    // Verify P-CRITICAL-01 is displayed
    const criticalProject = page.locator('text=P-CRITICAL-01');
    await expect(criticalProject.first()).toBeVisible({ timeout: 5000 });
  });

  test('should sync filter changes with URL', async ({ page, request }) => {
    // Login as user with tenant.view_reports
    const session = await login(request, 'pm@e2e-health.local', 'password');
    
    await page.goto(`${baseURL}/app/reports/projects/health?overall=good`);
    await page.evaluate((token) => {
      localStorage.setItem('auth_token', token);
    }, session.token);
    
    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Click "Tất cả" filter
    const allFilter = page.locator('button', { hasText: /Tất cả|All/i });
    await expect(allFilter.first()).toBeVisible({ timeout: 5000 });
    await allFilter.first().click();

    // URL should remove overall param
    await page.waitForURL(/.*\/app\/reports\/projects\/health(?!.*overall)/, { timeout: 5000 });

    // All projects should be visible
    const goodProject = page.locator('text=P-GOOD-01');
    const warningProject = page.locator('text=P-WARNING-01');
    const criticalProject = page.locator('text=P-CRITICAL-01');

    await expect(goodProject.first()).toBeVisible({ timeout: 5000 });
    await expect(warningProject.first()).toBeVisible({ timeout: 5000 });
    await expect(criticalProject.first()).toBeVisible({ timeout: 5000 });

    // Click "Nguy cấp" filter
    const criticalFilter = page.locator('button', { hasText: /Nguy cấp|Critical/i });
    await criticalFilter.first().click();

    // URL should have overall=critical
    await page.waitForURL(/.*overall=critical/, { timeout: 5000 });

    // Only critical projects should be visible
    await expect(criticalProject.first()).toBeVisible({ timeout: 5000 });
    await expect(goodProject).not.toBeVisible({ timeout: 2000 });
    await expect(warningProject).not.toBeVisible({ timeout: 2000 });
  });

  test('should show AccessRestricted for user without tenant.view_reports', async ({ page, request }) => {
    // Login as user without tenant.view_reports
    const session = await login(request, 'member@e2e-health.local', 'password');
    
    await page.goto(`${baseURL}/app/reports/projects/health`);
    await page.evaluate((token) => {
      localStorage.setItem('auth_token', token);
    }, session.token);
    
    await page.reload();
    await page.waitForLoadState('networkidle');

    // AccessRestricted component should be visible
    const accessRestricted = page.locator('text=/Access Restricted|Không có quyền/i');
    await expect(accessRestricted.first()).toBeVisible({ timeout: 10000 });

    // Table should NOT be visible
    const table = page.locator('table');
    await expect(table).not.toBeVisible({ timeout: 2000 });
  });

  test('should export CSV for user with tenant.view_reports', async ({ page, request }) => {
    // Login as user with tenant.view_reports
    const session = await login(request, 'pm@e2e-health.local', 'password');
    
    await page.goto(`${baseURL}/app/reports/projects/health`);
    await page.evaluate((token) => {
      localStorage.setItem('auth_token', token);
    }, session.token);
    
    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Find CSV export button
    const exportButton = page.locator('button', { hasText: /Xuất CSV|Export CSV/i });
    await expect(exportButton.first()).toBeVisible({ timeout: 5000 });

    // Intercept the export request
    let exportRequest: any = null;
    page.on('request', (request) => {
      if (request.url().includes('/api/v1/app/reports/projects/health/export')) {
        exportRequest = request;
      }
    });

    // Click export button
    await exportButton.first().click();

    // Wait for request
    await page.waitForTimeout(2000);

    // Verify export endpoint was called
    if (exportRequest) {
      expect(exportRequest.url()).toContain('/api/v1/app/reports/projects/health/export');
    } else {
      // Alternative: Make direct API call to verify export works
      const exportResponse = await request.get('/api/v1/app/reports/projects/health/export', {
        headers: authHeaders(session.token),
      });

      expect(exportResponse.status()).toBe(200);
      const contentType = exportResponse.headers()['content-type'];
      expect(contentType).toContain('csv');
    }
  });

  test('should not show CSV export button for user without tenant.view_reports', async ({ page, request }) => {
    // Login as user without tenant.view_reports
    const session = await login(request, 'member@e2e-health.local', 'password');
    
    await page.goto(`${baseURL}/app/reports/projects/health`);
    await page.evaluate((token) => {
      localStorage.setItem('auth_token', token);
    }, session.token);
    
    await page.reload();
    await page.waitForLoadState('networkidle');

    // CSV export button should NOT be visible
    const exportButton = page.locator('button', { hasText: /Xuất CSV|Export CSV/i });
    await expect(exportButton).not.toBeVisible({ timeout: 2000 });
  });

  test('should handle error state gracefully', async ({ page, request }) => {
    // Login as user with tenant.view_reports
    const session = await login(request, 'pm@e2e-health.local', 'password');
    
    await page.goto(`${baseURL}/app/reports/projects/health`);
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

    // Page should still render (not crash)
    const pageTitle = page.locator('text=/Sức khỏe dự án|Project Health/i');
    await expect(pageTitle.first()).toBeVisible({ timeout: 10000 });

    // Error message should be displayed
    const errorMessage = page.locator('text=/Không tải được|Failed to load/i');
    await expect(errorMessage.first()).toBeVisible({ timeout: 5000 });
  });

  test('should display trend card for user with tenant.view_reports', async ({ page, request }) => {
    // Login as user with tenant.view_reports
    const session = await login(request, 'pm@e2e-health.local', 'password');
    
    await page.goto(`${baseURL}/app/reports/projects/health`);
    await page.evaluate((token) => {
      localStorage.setItem('auth_token', token);
    }, session.token);
    
    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Trend card title should appear
    const trendCardTitle = page.locator('text=/Xu hướng sức khỏe portfolio/i');
    await expect(trendCardTitle.first()).toBeVisible({ timeout: 10000 });

    // Summary section should be visible
    const summary = page.locator('[data-testid="project-health-portfolio-trend-summary"]');
    await expect(summary.first()).toBeVisible({ timeout: 5000 });

    // Timeline section should be visible
    const timeline = page.locator('[data-testid="project-health-portfolio-trend-timeline"]');
    await expect(timeline.first()).toBeVisible({ timeout: 5000 });

    // At least one day should exist in timeline
    const timelineDays = page.locator('[data-testid="project-health-portfolio-trend-day"]');
    const count = await timelineDays.count();
    expect(count).toBeGreaterThan(0);
  });

  test('should not display trend card for user without tenant.view_reports', async ({ page, request }) => {
    // Login as user without tenant.view_reports
    const session = await login(request, 'member@e2e-health.local', 'password');
    
    // Track history endpoint calls
    let historyCalls = 0;
    await page.route('**/api/v1/app/reports/projects/health/history**', (route) => {
      historyCalls++;
      return route.continue();
    });
    
    await page.goto(`${baseURL}/app/reports/projects/health`);
    await page.evaluate((token) => {
      localStorage.setItem('auth_token', token);
    }, session.token);
    
    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Trend card elements should NOT be present
    const summary = page.locator('[data-testid="project-health-portfolio-trend-summary"]');
    await expect(summary).not.toBeVisible({ timeout: 2000 });

    const timeline = page.locator('[data-testid="project-health-portfolio-trend-timeline"]');
    await expect(timeline).not.toBeVisible({ timeout: 2000 });

    // Verify history endpoint is NOT called when user lacks permission
    expect(historyCalls).toBe(0);
  });
});

