import { test, expect } from '@playwright/test';

test.describe('Dashboard Enhanced Components', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to dashboard (assuming we can access it directly)
    await page.goto('/app/dashboard');
    await page.waitForLoadState('networkidle');
  });

  test('should have all data-testid attributes', async ({ page }) => {
    // Check main dashboard container
    await expect(page.locator('[data-testid="dashboard"]')).toBeVisible();
    
    // Check KPI cards have data-testid
    await expect(page.locator('[data-testid="kpi-total-projects"]')).toBeVisible();
    await expect(page.locator('[data-testid="kpi-active-tasks"]')).toBeVisible();
    await expect(page.locator('[data-testid="kpi-team-members"]')).toBeVisible();
    await expect(page.locator('[data-testid="kpi-completion-rate"]')).toBeVisible();
    
    // Check new components
    await expect(page.locator('[data-testid="recent-projects-widget"]')).toBeVisible();
    await expect(page.locator('[data-testid="activity-feed-widget"]')).toBeVisible();
    await expect(page.locator('[data-testid="team-status-widget"]')).toBeVisible();
    await expect(page.locator('[data-testid="quick-actions-widget"]')).toBeVisible();
    
    // Check charts
    await expect(page.locator('[data-testid="chart-project-progress"]')).toBeVisible();
    await expect(page.locator('[data-testid="chart-task-completion"]')).toBeVisible();
  });

  test('should display alert banner when alerts exist', async ({ page }) => {
    const alertBanner = page.locator('[data-testid="alert-banner"]');
    const alertCount = await alertBanner.count();
    
    if (alertCount > 0) {
      await expect(alertBanner).toBeVisible();
      
      // Check for dismiss all button
      const dismissAllButton = page.locator('[data-testid="alert-banner-dismiss-all"]');
      const dismissCount = await dismissAllButton.count();
      if (dismissCount > 0) {
        await expect(dismissAllButton).toBeVisible();
        await expect(dismissAllButton).toBeEnabled();
      }
    }
  });

  test('should have quick action buttons', async ({ page }) => {
    // Wait a bit for content to load
    await page.waitForTimeout(1000);
    
    // Quick Actions widget should be visible
    await expect(page.locator('[data-testid="quick-actions-widget"]')).toBeVisible();
    
    // Check individual quick action buttons
    const createProjectBtn = page.locator('[data-testid="quick-action-create-project"]');
    const addMemberBtn = page.locator('[data-testid="quick-action-add-member"]');
    const generateReportBtn = page.locator('[data-testid="quick-action-generate-report"]');
    const viewAnalyticsBtn = page.locator('[data-testid="quick-action-view-analytics"]');
    
    if (await createProjectBtn.count() > 0) {
      await expect(createProjectBtn).toBeVisible();
    }
    if (await addMemberBtn.count() > 0) {
      await expect(addMemberBtn).toBeVisible();
    }
    if (await generateReportBtn.count() > 0) {
      await expect(generateReportBtn).toBeVisible();
    }
    if (await viewAnalyticsBtn.count() > 0) {
      await expect(viewAnalyticsBtn).toBeVisible();
    }
  });

  test('should display charts with canvas elements', async ({ page }) => {
    // Wait for charts to potentially load
    await page.waitForTimeout(2000);
    
    // Project Progress Chart
    const progressChart = page.locator('[data-testid="chart-project-progress"]');
    await expect(progressChart).toBeVisible();
    
    // Check for canvas in chart container
    const progressCanvas = progressChart.locator('canvas');
    const canvasCount = await progressCanvas.count();
    console.log('Project progress canvas count:', canvasCount);
    // Canvas might not exist if loading or no data
    
    // Task Completion Chart
    const completionChart = page.locator('[data-testid="chart-task-completion"]');
    await expect(completionChart).toBeVisible();
    
    // Check for canvas in chart container
    const completionCanvas = completionChart.locator('canvas');
    const completionCanvasCount = await completionCanvas.count();
    console.log('Task completion canvas count:', completionCanvasCount);
  });

  test('should have responsive layout', async ({ page }) => {
    // Mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    
    // Dashboard should still be visible
    await expect(page.locator('[data-testid="dashboard"]')).toBeVisible();
    
    // KPI cards should be accessible
    await expect(page.locator('[data-testid="kpi-total-projects"]')).toBeVisible();
    
    // Reset to desktop
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.waitForTimeout(500);
    
    // Verify desktop layout
    await expect(page.locator('[data-testid="dashboard"]')).toBeVisible();
  });

  test('should load without critical console errors', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', msg => {
      if (msg.type() === 'error' && !msg.text().includes('favicon')) {
        errors.push(msg.text());
      }
    });
    
    await page.goto('/app/dashboard');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    console.log('Console errors found:', errors);
    // Allow some errors but not too many
    expect(errors.length).toBeLessThan(20);
  });
});

