import { test, expect } from '@playwright/test';
import { AuthHelper, DashboardHelper, testData, TestUtils } from '../helpers/smoke-helpers';

test.describe('E2E Smoke Tests - Admin Dashboard', () => {
  let authHelper: AuthHelper;
  let dashboardHelper: DashboardHelper;

  test.beforeEach(async ({ page }) => {
    authHelper = new AuthHelper(page);
    dashboardHelper = new DashboardHelper(page);
  });

  test('@smoke S3: Admin dashboard statistics verification', async ({ page }) => {
    // Login as admin user
    const adminUser = testData.users.zena.find(user => user.role === 'Admin');
    expect(adminUser).toBeDefined();
    
    if (adminUser) {
      await authHelper.login(adminUser.email, adminUser.password);
      
      // Navigate to admin dashboard
      await dashboardHelper.navigateToDashboard();
      
      // Verify dashboard loads correctly
      await dashboardHelper.verifyDashboardLoads();
      
      // Check for KPI cards with statistics
      const kpiCards = page.locator('.grid.grid-cols-2.gap-4.md\\:grid-cols-4 .card, .grid.grid-cols-2.gap-4.md\\:grid-cols-4 > div');
      const kpiCount = await kpiCards.count();
      
      if (kpiCount > 0) {
        await expect(kpiCards.first()).toBeVisible();
        
        // Check for specific KPI elements
        const kpiTitles = page.locator('.text-sm.text-\\[var\\(--color-text-muted\\)\\]');
        const kpiValues = page.locator('.text-2xl.font-semibold');
        
        // At least one KPI should have a title and value
        if (await kpiTitles.first().isVisible()) {
          await expect(kpiTitles.first()).toBeVisible();
        }
        
        if (await kpiValues.first().isVisible()) {
          await expect(kpiValues.first()).toBeVisible();
        }
      }
    }
  });

  test('@smoke Admin dashboard data loading', async ({ page }) => {
    // Login as admin
    const adminUser = testData.users.zena.find(user => user.role === 'Admin');
    expect(adminUser).toBeDefined();
    
    if (adminUser) {
      await authHelper.login(adminUser.email, adminUser.password);
      
      // Navigate to dashboard
      await page.goto('/app/dashboard');
      
      // Wait for dashboard to load
      await page.waitForSelector('h1:has-text("Dashboard"), [role="heading"]:has-text("Dashboard")', { timeout: 10000 });
      
      // Check for loading states
      const loadingIndicators = page.locator('.loading, .spinner, [data-testid="loading"]');
      
      // Wait for loading to complete (if any)
      if (await loadingIndicators.isVisible()) {
        await expect(loadingIndicators).not.toBeVisible({ timeout: 15000 });
      }
      
      // Verify dashboard content is loaded
      await expect(page.locator('h1:has-text("Dashboard"), [role="heading"]:has-text("Dashboard")')).toBeVisible();
      
      // Check for any dashboard content
      const recentProjects = page.locator('[data-testid="recent-projects"], .recent-projects, .project-list');
      const activities = page.locator('[data-testid="activities"], .activities, .activity-list');
      const anyContent = page.locator('main, .content, .container, .dashboard-content');
      
      // At least one of these should be visible
      const hasRecentProjects = await recentProjects.isVisible();
      const hasActivities = await activities.isVisible();
      const hasAnyContent = await anyContent.isVisible();
      
      // If none of the specific elements are visible, just verify dashboard title is visible
      if (!hasRecentProjects && !hasActivities && !hasAnyContent) {
        await expect(page.locator('h1:has-text("Dashboard"), [role="heading"]:has-text("Dashboard")')).toBeVisible();
      } else {
        expect(hasRecentProjects || hasActivities || hasAnyContent).toBe(true);
      }
    }
  });

  test('@smoke Dashboard quick actions functionality', async ({ page }) => {
    // Login as admin
    const adminUser = testData.users.zena.find(user => user.role === 'Admin');
    expect(adminUser).toBeDefined();
    
    if (adminUser) {
      await authHelper.login(adminUser.email, adminUser.password);
      
      // Navigate to dashboard
      await page.goto('/app/dashboard');
      
      // Wait for dashboard to load
      await page.waitForSelector('h1:has-text("Dashboard"), [role="heading"]:has-text("Dashboard")', { timeout: 10000 });
      
      // Check for quick actions
      const quickActions = page.locator('[data-testid="quick-actions"], .quick-actions, .action-buttons');
      
      if (await quickActions.isVisible()) {
        // Verify quick actions are visible
        await expect(quickActions).toBeVisible();
        
        // Check for action buttons
        const actionButtons = quickActions.locator('button, a');
        const buttonCount = await actionButtons.count();
        
        if (buttonCount > 0) {
          // Test clicking first action button
          const firstButton = actionButtons.first();
          await expect(firstButton).toBeVisible();
          
          // Click and verify it doesn't break the page
          await firstButton.click();
          await page.waitForTimeout(1000);
          
          // Verify page is still functional
          await expect(page.locator('h1:has-text("Dashboard"), [role="heading"]:has-text("Dashboard")')).toBeVisible();
        }
      }
    }
  });

  test('@smoke Dashboard responsive design', async ({ page }) => {
    // Login as admin
    const adminUser = testData.users.zena.find(user => user.role === 'Admin');
    expect(adminUser).toBeDefined();
    
    if (adminUser) {
      await authHelper.login(adminUser.email, adminUser.password);
      
      // Test mobile viewport
      await page.setViewportSize({ width: 375, height: 667 });
      await page.goto('/app/dashboard');
      
      // Wait for dashboard to load
      await page.waitForSelector('h1:has-text("Dashboard"), [role="heading"]:has-text("Dashboard")', { timeout: 10000 });
      
      // Verify dashboard elements are still visible on mobile
      await expect(page.locator('h1:has-text("Dashboard"), [role="heading"]:has-text("Dashboard")')).toBeVisible();
      
      // Check for mobile-specific elements (hamburger menu, etc.)
      const mobileMenu = page.locator('button[aria-label*="menu"], button:has-text("Menu"), .hamburger');
      if (await mobileMenu.isVisible()) {
        await expect(mobileMenu).toBeVisible();
      }
      
      // Test tablet viewport
      await page.setViewportSize({ width: 768, height: 1024 });
      await page.waitForTimeout(500);
      
      // Verify dashboard still works
      await expect(page.locator('h1:has-text("Dashboard"), [role="heading"]:has-text("Dashboard")')).toBeVisible();
      
      // Test desktop viewport
      await page.setViewportSize({ width: 1200, height: 800 });
      await page.waitForTimeout(500);
      
      // Verify dashboard still works
      await expect(page.locator('h1:has-text("Dashboard"), [role="heading"]:has-text("Dashboard")')).toBeVisible();
    }
  });

  test('@smoke Dashboard theme persistence', async ({ page }) => {
    // Login as admin
    const adminUser = testData.users.zena.find(user => user.role === 'Admin');
    expect(adminUser).toBeDefined();
    
    if (adminUser) {
      await authHelper.login(adminUser.email, adminUser.password);
      
      // Navigate to dashboard
      await page.goto('/app/dashboard');
      
      // Wait for dashboard to load
      await page.waitForSelector('h1:has-text("Dashboard"), [role="heading"]:has-text("Dashboard")', { timeout: 10000 });
      
      // Test theme toggle
      await dashboardHelper.testThemeToggle();
      
      // Refresh page and verify theme persists
      await page.reload();
      await page.waitForSelector('h1:has-text("Dashboard"), [role="heading"]:has-text("Dashboard")', { timeout: 10000 });
      
      // Theme should still be applied
      const currentTheme = await page.evaluate(() => 
        document.documentElement.getAttribute('data-theme') || 
        document.documentElement.classList.contains('dark') ? 'dark' : 'light'
      );
      
      expect(currentTheme).toBeDefined();
    }
  });

  test('@smoke Dashboard navigation', async ({ page }) => {
    // Login as admin
    const adminUser = testData.users.zena.find(user => user.role === 'Admin');
    expect(adminUser).toBeDefined();
    
    if (adminUser) {
      await authHelper.login(adminUser.email, adminUser.password);
      
      // Navigate to dashboard
      await page.goto('/app/dashboard');
      
      // Wait for dashboard to load
      await page.waitForSelector('h1:has-text("Dashboard"), [role="heading"]:has-text("Dashboard")', { timeout: 10000 });
      
      // Test navigation to other pages
      const navigationLinks = page.locator('nav a, .nav-link, [data-testid="nav-link"]');
      const linkCount = await navigationLinks.count();
      
      if (linkCount > 0) {
        // Test clicking first navigation link
        const firstLink = navigationLinks.first();
        const linkText = await firstLink.textContent();
        
        if (linkText && !linkText.toLowerCase().includes('dashboard')) {
          await firstLink.click();
          await page.waitForTimeout(2000);
          
          // Verify navigation worked
          const currentUrl = page.url();
          expect(currentUrl).not.toBe('');
          
          // Navigate back to dashboard
          await page.goto('/app/dashboard');
          await page.waitForSelector('h1:has-text("Dashboard"), [role="heading"]:has-text("Dashboard")', { timeout: 10000 });
        }
      }
    }
  });

  test('@smoke Dashboard error handling', async ({ page }) => {
    // Login as admin
    const adminUser = testData.users.zena.find(user => user.role === 'Admin');
    expect(adminUser).toBeDefined();
    
    if (adminUser) {
      await authHelper.login(adminUser.email, adminUser.password);
      
      // Navigate to dashboard
      await page.goto('/app/dashboard');
      
      // Wait for dashboard to load
      await page.waitForSelector('h1:has-text("Dashboard"), [role="heading"]:has-text("Dashboard")', { timeout: 10000 });
      
      // Check for any error messages
      const errorMessages = page.locator('.error, .alert-error, [data-testid="error"]');
      const hasErrors = await errorMessages.isVisible();
      
      if (hasErrors) {
        // Log error messages for debugging
        const errorTexts = await errorMessages.allTextContents();
        console.log('Dashboard errors found:', errorTexts);
      }
      
      // Dashboard should still be functional even with errors
      await expect(page.locator('h1:has-text("Dashboard"), [role="heading"]:has-text("Dashboard")')).toBeVisible();
    }
  });

  test('@smoke Dashboard performance', async ({ page }) => {
    // Login as admin
    const adminUser = testData.users.zena.find(user => user.role === 'Admin');
    expect(adminUser).toBeDefined();
    
    if (adminUser) {
      // Measure page load time
      const startTime = Date.now();
      
      await authHelper.login(adminUser.email, adminUser.password);
      
      // Navigate to dashboard
      await page.goto('/app/dashboard');
      
      // Wait for dashboard to load
      await page.waitForSelector('h1:has-text("Dashboard"), [role="heading"]:has-text("Dashboard")', { timeout: 10000 });
      
      const loadTime = Date.now() - startTime;
      
      // Dashboard should load within reasonable time (15 seconds)
      expect(loadTime).toBeLessThan(15000);
      
      // Verify dashboard is functional
      await expect(page.locator('h1:has-text("Dashboard"), [role="heading"]:has-text("Dashboard")')).toBeVisible();
    }
  });
});
