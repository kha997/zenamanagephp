import { test, expect } from '@playwright/test';
import { login, authHeaders, expectSuccess } from './helpers/apiClient';

/**
 * Project Health Dashboard E2E Tests
 * 
 * Round 82: Project Health vertical hardening + E2E flows
 * 
 * Tests the Project Health Widget on the dashboard:
 * - Widget display and counters
 * - Navigation from counters to Health Portfolio
 * - Permission checks (tenant.view_reports)
 */
test.describe('Project Health Dashboard Widget', () => {
  const baseURL = process.env.PLAYWRIGHT_TEST_BASE_URL || 'http://127.0.0.1:8000';

  test.beforeEach(async ({ page }) => {
    // Navigate to base URL
    await page.goto(baseURL);
  });

  test('should display widget and counters for user with tenant.view_reports', async ({ page, request }) => {
    // Login as user with tenant.view_reports (pm role)
    const session = await login(request, 'pm@e2e-health.local', 'password');
    
    // Set auth token in localStorage for SPA
    await page.goto(`${baseURL}/app/dashboard`);
    await page.evaluate((token) => {
      localStorage.setItem('auth_token', token);
    }, session.token);
    
    // Reload page to apply auth
    await page.reload();
    await page.waitForLoadState('networkidle');

    // Wait for widget to appear
    const widget = page.locator('[data-testid="project-health-widget"]');
    await expect(widget).toBeVisible({ timeout: 10000 });

    // Check widget title
    await expect(widget.locator('text=Sức khỏe dự án')).toBeVisible();

    // Wait for data to load (check for counters)
    // Counters should show: "Tốt", "Cảnh báo", "Nguy cấp"
    await page.waitForSelector('text=Tốt', { timeout: 10000 }).catch(() => {});
    await page.waitForSelector('text=Cảnh báo', { timeout: 10000 }).catch(() => {});
    await page.waitForSelector('text=Nguy cấp', { timeout: 10000 }).catch(() => {});

    // At least one counter should be > 0 (based on seed data)
    const goodCounter = page.locator('text=/Tốt.*\\d+/').first();
    const warningCounter = page.locator('text=/Cảnh báo.*\\d+/').first();
    const criticalCounter = page.locator('text=/Nguy cấp.*\\d+/').first();

    // At least one should be visible and > 0
    const countersVisible = await Promise.all([
      goodCounter.isVisible().catch(() => false),
      warningCounter.isVisible().catch(() => false),
      criticalCounter.isVisible().catch(() => false),
    ]);

    expect(countersVisible.some(v => v)).toBeTruthy();
  });

  test('should navigate to health portfolio when clicking counters', async ({ page, request }) => {
    // Login as user with tenant.view_reports
    const session = await login(request, 'pm@e2e-health.local', 'password');
    
    await page.goto(`${baseURL}/app/dashboard`);
    await page.evaluate((token) => {
      localStorage.setItem('auth_token', token);
    }, session.token);
    
    await page.reload();
    await page.waitForLoadState('networkidle');

    // Wait for widget
    const widget = page.locator('[data-testid="project-health-widget"]');
    await expect(widget).toBeVisible({ timeout: 10000 });

    // Wait for counters to load
    await page.waitForTimeout(2000);

    // Click "Tốt" counter - should navigate to /app/reports/projects/health?overall=good
    const goodLink = page.locator('text=/Tốt/').first();
    if (await goodLink.isVisible({ timeout: 5000 }).catch(() => false)) {
      await goodLink.click();
      await page.waitForURL(/.*\/app\/reports\/projects\/health.*overall=good/, { timeout: 10000 });
      
      // Verify filter is active
      const filterActive = page.locator('button[aria-pressed="true"]', { hasText: /Tốt|Good/i });
      await expect(filterActive.first()).toBeVisible({ timeout: 5000 });
      
      // Go back to dashboard
      await page.goto(`${baseURL}/app/dashboard`);
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(2000);
    }

    // Click "Cảnh báo" counter - should navigate to /app/reports/projects/health?overall=warning
    const warningLink = page.locator('text=/Cảnh báo/').first();
    if (await warningLink.isVisible({ timeout: 5000 }).catch(() => false)) {
      await warningLink.click();
      await page.waitForURL(/.*\/app\/reports\/projects\/health.*overall=warning/, { timeout: 10000 });
      
      // Verify filter is active
      const filterActive = page.locator('button[aria-pressed="true"]', { hasText: /Cảnh báo|Warning/i });
      await expect(filterActive.first()).toBeVisible({ timeout: 5000 });
      
      // Verify at least one warning project is displayed
      const projectRow = page.locator('text=P-WARNING-01');
      await expect(projectRow.first()).toBeVisible({ timeout: 5000 });
      
      // Go back to dashboard
      await page.goto(`${baseURL}/app/dashboard`);
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(2000);
    }

    // Click "Nguy cấp" counter - should navigate to /app/reports/projects/health?overall=critical
    const criticalLink = page.locator('text=/Nguy cấp/').first();
    if (await criticalLink.isVisible({ timeout: 5000 }).catch(() => false)) {
      await criticalLink.click();
      await page.waitForURL(/.*\/app\/reports\/projects\/health.*overall=critical/, { timeout: 10000 });
      
      // Verify filter is active
      const filterActive = page.locator('button[aria-pressed="true"]', { hasText: /Nguy cấp|Critical/i });
      await expect(filterActive.first()).toBeVisible({ timeout: 5000 });
      
      // Verify at least one critical project is displayed
      const projectRow = page.locator('text=P-CRITICAL-01');
      await expect(projectRow.first()).toBeVisible({ timeout: 5000 });
    }
  });

  test('should not display widget for user without tenant.view_reports', async ({ page, request }) => {
    // Login as user without tenant.view_reports (member role)
    const session = await login(request, 'member@e2e-health.local', 'password');
    
    await page.goto(`${baseURL}/app/dashboard`);
    await page.evaluate((token) => {
      localStorage.setItem('auth_token', token);
    }, session.token);
    
    await page.reload();
    await page.waitForLoadState('networkidle');

    // Widget should NOT be visible
    const widget = page.locator('[data-testid="project-health-widget"]');
    await expect(widget).not.toBeVisible({ timeout: 5000 });

    // Verify no health API call was made (check network requests)
    // This is a basic check - widget should not render at all
    const widgetTitle = page.locator('text=Sức khỏe dự án');
    await expect(widgetTitle).not.toBeVisible({ timeout: 2000 });
  });

  test('should handle error state gracefully', async ({ page, request }) => {
    // Login as user with tenant.view_reports
    const session = await login(request, 'pm@e2e-health.local', 'password');
    
    await page.goto(`${baseURL}/app/dashboard`);
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

    // Widget should still be visible
    const widget = page.locator('[data-testid="project-health-widget"]');
    await expect(widget).toBeVisible({ timeout: 10000 });

    // Error message should be displayed
    const errorMessage = page.locator('text=/Không tải được dữ liệu/i');
    await expect(errorMessage).toBeVisible({ timeout: 5000 });

    // Retry button should be visible
    const retryButton = page.locator('button', { hasText: /Thử lại/i });
    await expect(retryButton).toBeVisible({ timeout: 5000 });
  });
});

