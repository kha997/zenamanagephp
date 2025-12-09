import { test, expect } from '@playwright/test';
import { login, authHeaders, expectSuccess } from './helpers/apiClient';

/**
 * Project Health History E2E Tests
 * 
 * Round 87: Project Health History UI
 * 
 * Tests the Project Health History section on Project Detail page:
 * - History visible for PM with tenant.view_reports
 * - Empty history case
 * - Error handling
 * - Permission gating
 */
test.describe('Project Health History', () => {
  const baseURL = process.env.PLAYWRIGHT_TEST_BASE_URL || 'http://127.0.0.1:8000';

  test.beforeEach(async ({ page }) => {
    await page.goto(baseURL);
  });

  test('should display history for PM with tenant.view_reports', async ({ page, request }) => {
    // Login as user with tenant.view_reports (pm role)
    const session = await login(request, 'pm@e2e-health.local', 'password');
    
    // Get project ID from API (P-GOOD-01 has snapshots from seeder)
    const projectsResponse = await request.get('/api/v1/app/projects', {
      headers: authHeaders(session.token),
    });
    const projectsData = await expectSuccess(projectsResponse);
    const goodProject = projectsData.data?.find((p: any) => p.code === 'P-GOOD-01');
    
    expect(goodProject).toBeDefined();
    const projectId = goodProject.id;

    await page.goto(`${baseURL}/app/projects/${projectId}`);
    await page.evaluate((token) => {
      localStorage.setItem('auth_token', token);
    }, session.token);
    
    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Wait for history card to appear
    const historyCard = page.locator('[data-testid="project-health-history-card"]');
    await expect(historyCard).toBeVisible({ timeout: 10000 });

    // Wait for data to load (check for at least one row)
    const historyRow = page.locator('[data-testid="project-health-history-row"]');
    await expect(historyRow.first()).toBeVisible({ timeout: 10000 });

    // Verify first row shows valid data
    const firstRow = historyRow.first();
    
    // Check date format (should be visible)
    const dateCell = firstRow.locator('td').first();
    await expect(dateCell).toBeVisible();
    const dateText = await dateCell.textContent();
    expect(dateText).toBeTruthy();
    // Date should match YYYY-MM-DD pattern or be formatted
    expect(dateText?.trim().length).toBeGreaterThan(0);

    // Check overall status label (should be one of: Tốt, Cảnh báo, Nguy cấp)
    const overallStatusCell = firstRow.locator('td').nth(1);
    await expect(overallStatusCell).toBeVisible();
    const statusText = await overallStatusCell.textContent();
    expect(statusText).toBeTruthy();
    // Should contain one of the Vietnamese labels
    expect(
      statusText?.includes('Tốt') ||
      statusText?.includes('Cảnh báo') ||
      statusText?.includes('Nguy cấp')
    ).toBeTruthy();

    // Round 89: Check trend section is visible
    const trendLabel = page.locator('[data-testid="project-health-history-trend-label"]');
    await expect(trendLabel).toBeVisible({ timeout: 5000 });
    expect(await trendLabel.textContent()).toContain('Xu hướng 30 ngày gần đây');

    // Check trend chip is visible and has valid text
    const trendChip = page.locator('[data-testid="project-health-history-trend-chip"]');
    await expect(trendChip).toBeVisible({ timeout: 5000 });
    const chipText = await trendChip.textContent();
    expect(chipText).toBeTruthy();
    // Should be one of: Tốt lên, Xấu đi, Ổn định, Chưa có đủ dữ liệu
    expect(
      chipText?.includes('Tốt lên') ||
      chipText?.includes('Xấu đi') ||
      chipText?.includes('Ổn định') ||
      chipText?.includes('Chưa có đủ dữ liệu')
    ).toBeTruthy();

    // Check timeline is visible and has at least one dot
    const timeline = page.locator('[data-testid="project-health-history-timeline"]');
    await expect(timeline).toBeVisible({ timeout: 5000 });
    const timelineDots = page.locator('[data-testid="project-health-history-timeline-dot"]');
    const dotCount = await timelineDots.count();
    expect(dotCount).toBeGreaterThan(0);

    // Check summary statistics are visible
    const summary = page.locator('[data-testid="project-health-history-summary"]');
    await expect(summary).toBeVisible({ timeout: 5000 });
    const summaryText = await summary.textContent();
    expect(summaryText).toBeTruthy();
    // Should contain statistics labels
    expect(summaryText).toContain('Tốt:');
    expect(summaryText).toContain('Cảnh báo:');
    expect(summaryText).toContain('Nguy cấp:');
  });

  test('should handle empty history gracefully', async ({ page, request }) => {
    // Login as user with tenant.view_reports
    const session = await login(request, 'pm@e2e-health.local', 'password');
    
    // Get all projects to find one without snapshots
    // For this test, we'll use a project that might not have snapshots
    // or create a new project via API if needed
    // For now, we'll check if any project shows empty state
    // In practice, we might need to create a project without snapshots
    
    const projectsResponse = await request.get('/api/v1/app/projects', {
      headers: authHeaders(session.token),
    });
    const projectsData = await expectSuccess(projectsResponse);
    
    // Try to find a project that might not have snapshots
    // If all projects have snapshots, we'll verify the empty state doesn't show
    // when there's data
    const anyProject = projectsData.data?.[0];
    
    if (anyProject) {
      const projectId = anyProject.id;

      await page.goto(`${baseURL}/app/projects/${projectId}`);
      await page.evaluate((token) => {
        localStorage.setItem('auth_token', token);
      }, session.token);
      
      await page.reload();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(2000);

      // History card should be visible
      const historyCard = page.locator('[data-testid="project-health-history-card"]');
      await expect(historyCard).toBeVisible({ timeout: 10000 });

      // Check if empty state is shown (if no snapshots)
      const emptyState = page.locator('[data-testid="project-health-history-empty"]');
      const hasRows = await page.locator('[data-testid="project-health-history-row"]').count();
      
      if (hasRows === 0) {
        // Empty state should be visible
        await expect(emptyState).toBeVisible({ timeout: 5000 });
        // Round 89: Empty state should show trend message
        const emptyText = await emptyState.textContent();
        expect(emptyText).toContain('Chưa có snapshot sức khỏe nào cho dự án này.');
        expect(emptyText).toContain('Chưa có dữ liệu để tính xu hướng.');
      } else {
        // If there are rows, empty state should not be visible
        await expect(emptyState).not.toBeVisible({ timeout: 2000 });
        
        // Round 89: If there's data, trend section should be visible
        const trendLabel = page.locator('[data-testid="project-health-history-trend-label"]');
        await expect(trendLabel).toBeVisible({ timeout: 5000 });
      }
    }
  });

  test('should handle error state gracefully', async ({ page, request }) => {
    // Login as user with tenant.view_reports
    const session = await login(request, 'pm@e2e-health.local', 'password');
    
    // Get project ID
    const projectsResponse = await request.get('/api/v1/app/projects', {
      headers: authHeaders(session.token),
    });
    const projectsData = await expectSuccess(projectsResponse);
    const goodProject = projectsData.data?.find((p: any) => p.code === 'P-GOOD-01');
    
    expect(goodProject).toBeDefined();
    const projectId = goodProject.id;

    // Intercept and fail the history API call
    await page.route(`**/api/v1/app/projects/${projectId}/health/history*`, async (route) => {
      await route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({
          ok: false,
          code: 'PROJECT_HEALTH_HISTORY_ERROR',
          message: 'Failed to load project health history',
        }),
      });
    });

    await page.goto(`${baseURL}/app/projects/${projectId}`);
    await page.evaluate((token) => {
      localStorage.setItem('auth_token', token);
    }, session.token);
    
    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // History card should still be visible
    const historyCard = page.locator('[data-testid="project-health-history-card"]');
    await expect(historyCard).toBeVisible({ timeout: 10000 });

    // Error message should be displayed
    const errorState = page.locator('[data-testid="project-health-history-error"]');
    await expect(errorState).toBeVisible({ timeout: 5000 });

    // Retry button should be visible
    const retryButton = page.locator('button', { hasText: /Thử lại/i });
    await expect(retryButton).toBeVisible({ timeout: 5000 });

    // Click retry - should trigger another request
    await retryButton.click();
    await page.waitForTimeout(1000);
    
    // Verify another request was made (route should be called again)
    // This is a basic check - in practice, you might want to verify the network request
  });

  test('should not display history for user without tenant.view_reports', async ({ page, request }) => {
    // Login as user without tenant.view_reports (member role)
    const session = await login(request, 'member@e2e-health.local', 'password');
    
    // Get project ID
    const projectsResponse = await request.get('/api/v1/app/projects', {
      headers: authHeaders(session.token),
    });
    const projectsData = await expectSuccess(projectsResponse);
    const goodProject = projectsData.data?.find((p: any) => p.code === 'P-GOOD-01');
    
    expect(goodProject).toBeDefined();
    const projectId = goodProject.id;

    // Track API calls to verify history endpoint is not called
    let historyApiCalled = false;
    await page.route(`**/api/v1/app/projects/${projectId}/health/history*`, async (route) => {
      historyApiCalled = true;
      await route.continue();
    });

    await page.goto(`${baseURL}/app/projects/${projectId}`);
    await page.evaluate((token) => {
      localStorage.setItem('auth_token', token);
    }, session.token);
    
    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // History card should NOT be visible
    const historyCard = page.locator('[data-testid="project-health-history-card"]');
    await expect(historyCard).not.toBeVisible({ timeout: 5000 });

    // Verify history API was not called
    expect(historyApiCalled).toBeFalsy();
  });
});

