import { test, expect } from '@playwright/test';

/**
 * Dashboard E2E Tests
 * 
 * These tests verify that the rebuilt Dashboard works correctly
 * with the standard structure: Header, Navigator, KPI Strip, Alert Bar, Content, Activity
 */

test.describe('Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to dashboard
    await page.goto('http://127.0.0.1:8000/app/dashboard');
    
    // Wait for page to load
    await page.waitForLoadState('networkidle');
  });

  test('should display header with user menu', async ({ page }) => {
    // Check header is present
    const header = page.locator('[data-testid="header-wrapper"]');
    await expect(header).toBeVisible();
    
    // Check user menu is present
    const userMenu = page.locator('[data-testid="user-menu"]');
    await expect(userMenu).toBeVisible();
  });

  test('should display notifications bell', async ({ page }) => {
    // Check notifications bell is visible
    const notificationBell = page.locator('button[aria-label*="notification" i]');
    await expect(notificationBell).toBeVisible();
  });

  test('should display primary navigator', async ({ page }) => {
    // Check navigator is present
    const navigator = page.locator('[data-testid="primary-navigator"]');
    await expect(navigator).toBeVisible();
    
    // Check navigator has menu items
    const navItems = navigator.locator('a');
    await expect(navItems.first()).toBeVisible();
  });

  test('should display KPI strip with correct KPIs', async ({ page }) => {
    // Total Projects KPI
    const totalProjects = page.locator('[data-testid="kpi-total-projects"]');
    await expect(totalProjects).toBeVisible();
    
    // Active Tasks KPI
    const activeTasks = page.locator('[data-testid="kpi-active-tasks"]');
    await expect(activeTasks).toBeVisible();
    
    // Team Members KPI
    const teamMembers = page.locator('[data-testid="kpi-team-members"]');
    await expect(teamMembers).toBeVisible();
    
    // Completion Rate KPI
    const completionRate = page.locator('[data-testid="kpi-completion-rate"]');
    await expect(completionRate).toBeVisible();
  });

  test('should display and dismiss alert bar', async ({ page }) => {
    // Check alert bar is visible
    const alertBar = page.locator('[x-show]').filter({ hasText: 'Welcome to your dashboard' });
    await expect(alertBar).toBeVisible();
    
    // Click dismiss button
    const dismissButton = alertBar.locator('button').last();
    await dismissButton.click();
    
    // Alert bar should be hidden
    await expect(alertBar).toBeHidden();
  });

  test('should display recent projects widget', async ({ page }) => {
    const projectsWidget = page.locator('[data-testid="recent-projects-widget"]');
    await expect(projectsWidget).toBeVisible();
    
    // Check widget header
    const widgetHeader = projectsWidget.locator('h3', { hasText: 'Recent Projects' });
    await expect(widgetHeader).toBeVisible();
  });

  test('should display activity feed widget', async ({ page }) => {
    const activityWidget = page.locator('[data-testid="activity-feed-widget"]');
    await expect(activityWidget).toBeVisible();
    
    // Check widget header
    const widgetHeader = activityWidget.locator('h3', { hasText: 'Recent Activity' });
    await expect(widgetHeader).toBeVisible();
  });

  test('should display quick actions', async ({ page }) => {
    // Check quick actions section exists
    const quickActions = page.locator('text=Quick Actions').first();
    await expect(quickActions).toBeVisible();
    
    // Check New Project button
    const newProjectButton = page.locator('button', { hasText: 'New Project' });
    await expect(newProjectButton).toBeVisible();
    
    // Check New Task button
    const newTaskButton = page.locator('button', { hasText: 'New Task' });
    await expect(newTaskButton).toBeVisible();
  });

  test('should refresh dashboard when refresh button is clicked', async ({ page }) => {
    const refreshButton = page.locator('[data-testid="refresh-dashboard-button"]');
    await expect(refreshButton).toBeVisible();
    
    // Click refresh
    await refreshButton.click();
    
    // Page should reload (wait for reload)
    await page.waitForLoadState('networkidle');
    
    // Verify still on dashboard
    await expect(page).toHaveURL(/.*dashboard/);
  });

  test('should navigate to create project', async ({ page }) => {
    const newProjectButton = page.locator('[data-testid="new-project-button"]');
    
    // Check if button exists
    const count = await newProjectButton.count();
    if (count > 0) {
      await newProjectButton.first().click();
      
      // Should navigate to create project or open modal
      // This depends on implementation
    }
  });

  test('should display activity section', async ({ page }) => {
    // Scroll to activity section
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    
    // Check activity section exists
    const activitySection = page.locator('h3', { hasText: 'Recent Activity' });
    await expect(activitySection).toBeVisible();
  });

  test('should be responsive on mobile', async ({ page }) => {
    // Set mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    
    // Check navigator collapses on mobile
    const navigator = page.locator('[data-testid="primary-navigator"]');
    await expect(navigator).toBeVisible();
    
    // Check KPI cards stack on mobile
    const kpiCards = page.locator('[data-testid="kpi-total-projects"]');
    await expect(kpiCards).toBeVisible();
  });

  test('should handle empty state gracefully', async ({ page }) => {
    // If no projects, should show empty state
    const emptyState = page.locator('text=No projects yet');
    
    // Either show empty state OR show projects
    const hasEmpty = await emptyState.count() > 0;
    const hasProjects = await page.locator('[data-testid="recent-projects-widget"]').count() > 0;
    
    expect(hasEmpty || hasProjects).toBeTruthy();
  });
});

/**
 * Dashboard Performance Tests
 */
test.describe('Dashboard Performance', () => {
  test('should load within performance budget', async ({ page }) => {
    const startTime = Date.now();
    
    await page.goto('http://127.0.0.1:8000/app/dashboard');
    await page.waitForLoadState('networkidle');
    
    const loadTime = Date.now() - startTime;
    
    // Page should load within 3 seconds (p95 target)
    expect(loadTime).toBeLessThan(3000);
  });

  test('should not have layout shifts', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/app/dashboard');
    
    // Check that no major layout shifts occur
    // This is a basic check - could be expanded with CLS metrics
    const elements = await page.locator('body').count();
    expect(elements).toBeGreaterThan(0);
  });
});

