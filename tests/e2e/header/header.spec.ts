import { test, expect, Page } from '@playwright/test';

const adminUser = {
  email: 'admin@zena.local',
  password: 'password',
};

const loginViaForm = async (page: Page, email: string, password: string) => {
  await page.goto('/login');
  await page.waitForSelector('#loginForm', { state: 'visible', timeout: 10000 });

  await page.fill('input[name="email"], input[type="email"]', email);
  await page.fill('input[name="password"], input[type="password"]', password);

  await Promise.all([
    page.waitForNavigation({ url: /\/app\//, timeout: 20000 }).catch(() => undefined),
    page.click('#loginButton, button[type="submit"], button:has-text("Login"), button:has-text("Sign In")'),
  ]);

  await page.waitForLoadState('networkidle');

  if (!page.url().includes('/app/')) {
    throw new Error(`UI login failed, current URL: ${page.url()}`);
  }
};

const login = async (page: Page, email: string, password: string) => {
  const redirect = encodeURIComponent('/app/dashboard');
  const encodedEmail = encodeURIComponent(email);
  const response = await page.goto(`/test/login?email=${encodedEmail}&redirect=${redirect}`);

  if (!response || response.status() >= 400) {
    await loginViaForm(page, email, password);
    return;
  }

  await page.waitForLoadState('networkidle');

  if (!page.url().includes('/app/')) {
    await loginViaForm(page, email, password);
  }
};

/**
 * Header E2E Tests
 * 
 * Tests for the HeaderShell component including:
 * - RBAC navigation filtering
 * - Theme toggle functionality
 * - Mobile hamburger menu
 * - Breadcrumbs
 * - Notifications
 * - User menu
 * - Search functionality
 * - Keyboard navigation
 * - Accessibility
 */

test.describe('Header Functionality', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, adminUser.email, adminUser.password);
    await page.goto('/app/dashboard');
    await page.waitForLoadState('networkidle');

    await page
      .waitForSelector('[data-testid="header-shell"]', { state: 'visible', timeout: 15000 })
      .catch(async () => {
        await page.waitForSelector('header', { state: 'visible', timeout: 15000 });
      });
  });

  test('should display header with basic elements', async ({ page }) => {
    // Check logo
    await expect(page.locator('text=ZenaManage')).toBeVisible();
    
    // Check hamburger button
    await expect(page.locator('[aria-label="Toggle navigation menu"]')).toBeVisible();
    
    // Check theme toggle
    await expect(page.locator('[aria-label*="Current theme"]')).toBeVisible();
    
    // Check notifications
    await expect(page.locator('[aria-label*="Notifications"]')).toBeVisible();
    
    // Check user menu
    await expect(page.locator('[aria-label*="User menu"]')).toBeVisible();
  });

  test('should show only allowed nav for role @header', async ({ page }) => {
    // This test assumes the user has specific permissions
    // The navigation should be filtered based on RBAC
    
    // Click hamburger to open mobile menu
    await page.click('[aria-label="Toggle navigation menu"]');
    
    // Wait for mobile menu to appear
    await expect(page.locator('[role="navigation"][aria-label="Mobile navigation"]')).toBeVisible();
    
    // Check that only appropriate navigation items are shown
    // This depends on the user's role and permissions
    
    // Example: If user is PM, they should see Projects but not Users
    const navItems = await page.locator('[role="navigation"][aria-label="Mobile navigation"] a').all();
    
    expect(navItems.length).toBeGreaterThan(0);
  });

  test('should persist theme preference', async ({ page }) => {
    // Get initial theme
    const initialTheme = await page.evaluate(() => {
      return localStorage.getItem('theme') || localStorage.getItem('zenamanage.theme');
    });
    
    // Click theme toggle
    await page.click('[aria-label*="Current theme"]');
    
    // Wait for theme to change
    await page.waitForTimeout(500);
    
    // Check if theme persisted
    const newTheme = await page.evaluate(() => {
      return localStorage.getItem('theme') || localStorage.getItem('zenamanage.theme');
    });
    
    // Theme should be different
    expect(newTheme).not.toBe(initialTheme);
    
    // Reload page
    await page.reload();
    
    // Wait for header to load
    await page.waitForSelector('header', { state: 'visible' });
    
    // Check if theme is still persisted
    const persistedTheme = await page.evaluate(() => {
      return localStorage.getItem('theme') || localStorage.getItem('zenamanage.theme');
    });
    
    expect(persistedTheme).toBe(newTheme);
  });

  test('should work with mobile drawer', async ({ page }) => {
    // Set mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    
    // Click hamburger
    await page.click('[aria-label="Toggle navigation menu"]');
    
    // Wait for mobile menu
    await expect(page.locator('[role="navigation"][aria-label="Mobile navigation"]')).toBeVisible();
    
    // Check if menu items are visible
    const navItems = await page.locator('[role="navigation"][aria-label="Mobile navigation"] a');
    const count = await navItems.count();
    
    expect(count).toBeGreaterThan(0);
    
    // Close mobile menu
    await page.click('[aria-label="Toggle navigation menu"]');
    
    // Menu should be hidden
    await expect(page.locator('[role="navigation"][aria-label="Mobile navigation"]')).not.toBeVisible();
  });

  test('should reflect breadcrumbs in route', async ({ page }) => {
    // Navigate to a page with breadcrumbs
    await page.goto('/app/projects/123');
    
    // Wait for page to load
    await page.waitForSelector('header', { state: 'visible' });
    
    // Check if breadcrumbs are visible (might need to open mobile menu on mobile)
    const breadcrumbs = page.locator('[aria-label="Breadcrumb"]');
    
    // On desktop, breadcrumbs should be visible
    // On mobile, they might be in the mobile menu
    await expect(breadcrumbs.or(page.locator('[role="navigation"][aria-label="Mobile navigation"]'))).toBeVisible();
  });

  test('should have keyboard navigation support', async ({ page }) => {
    // Focus hamburger button
    await page.keyboard.press('Tab');
    
    // Press Enter to open menu
    await page.keyboard.press('Enter');
    
    // Menu should be open
    await expect(page.locator('[role="navigation"][aria-label="Mobile navigation"]')).toBeVisible();
    
    // Tab through menu items
    await page.keyboard.press('Tab');
    
    // Press Escape to close menu
    await page.keyboard.press('Escape');
    
    // Menu should be closed
    await expect(page.locator('[role="navigation"][aria-label="Mobile navigation"]')).not.toBeVisible();
  });

  test('should have proper ARIA attributes', async ({ page }) => {
    // Check header has role
    await expect(page.locator('header[role="banner"]')).toBeVisible();
    
    // Check hamburger button has aria-label
    await expect(page.locator('[aria-label="Toggle navigation menu"]')).toBeVisible();
    
    // Check theme button has aria-label
    await expect(page.locator('[aria-label*="Current theme"]')).toBeVisible();
    
    // Check notifications button has aria-label
    await expect(page.locator('[aria-label*="Notifications"]')).toBeVisible();
    
    // Check user menu button has aria-label
    await expect(page.locator('[aria-label*="User menu"]')).toBeVisible();
  });

  test('should filter notifications by unread count', async ({ page }) => {
    // Click notifications button
    await page.click('[aria-label*="Notifications"]');
    
    // Wait for notifications dropdown
    await page.waitForTimeout(500);
    
    // Check if unread count badge is visible
    const unreadBadge = page.locator('span:has-text("1")');
    
    // If there are notifications, the badge or content should be visible
    const hasNotifications = await page.locator('text=No notifications').count() === 0;
    
    if (hasNotifications) {
      await expect(unreadBadge.or(page.locator('[aria-label*="Notifications"] >> span'))).toBeVisible();
    }
  });

  test('should handle logout action', async ({ page }) => {
    // Click user menu
    await page.click('[aria-label*="User menu"]');
    
    // Wait for dropdown
    await page.waitForTimeout(300);
    
    // Click sign out
    await page.click('text=Sign out');
    
    // Should redirect to login page or show logout success
    // This depends on the logout implementation
    await page.waitForTimeout(1000);
    
    // Check if logged out (this depends on your auth flow)
    const currentUrl = page.url();
    
    // Might redirect to login or dashboard
    expect(currentUrl).toMatch(/\/(login|register|auth)/);
  });

  test('should have search functionality', async ({ page }) => {
    // Check if search bar is visible (on desktop)
    const isMobile = await page.evaluate(() => window.innerWidth < 768);
    
    if (!isMobile) {
      const searchInput = page.locator('[aria-label="Search"]');
      await expect(searchInput).toBeVisible();
      
      // Type search query
      await searchInput.fill('test query');
      
      // Wait for debounce
      await page.waitForTimeout(400);
      
      // Check if query is in input
      await expect(searchInput).toHaveValue('test query');
    }
  });

  test('should close dropdowns when clicking outside', async ({ page }) => {
    // Click notifications
    await page.click('[aria-label*="Notifications"]');
    
    // Wait for dropdown
    await page.waitForTimeout(300);
    
    // Check if dropdown is visible
    const dropdown = page.locator('text=Test Notification').or(page.locator('text=No notifications'));
    await expect(dropdown).toBeVisible();
    
    // Click outside
    await page.click('body');
    
    // Wait for dropdown to close
    await page.waitForTimeout(300);
    
    // Dropdown should not be visible
    await expect(page.locator('text=No notifications')).not.toBeVisible();
  });

  test('should work on mobile viewport', async ({ page }) => {
    // Set mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    
    // Check that header is visible
    await expect(page.locator('header')).toBeVisible();
    
    // Check hamburger is visible
    await expect(page.locator('[aria-label="Toggle navigation menu"]')).toBeVisible();
    
    // Click hamburger
    await page.click('[aria-label="Toggle navigation menu"]');
    
    // Mobile menu should open
    await expect(page.locator('[role="navigation"][aria-label="Mobile navigation"]')).toBeVisible();
  });

  test('should work on tablet viewport', async ({ page }) => {
    // Set tablet viewport
    await page.setViewportSize({ width: 768, height: 1024 });
    
    // Check that header is visible
    await expect(page.locator('header')).toBeVisible();
    
    // Check hamburger is visible
    await expect(page.locator('[aria-label="Toggle navigation menu"]')).toBeVisible();
  });

  test('should work on desktop viewport', async ({ page }) => {
    // Set desktop viewport
    await page.setViewportSize({ width: 1920, height: 1080 });
    
    // Check that header is visible
    await expect(page.locator('header')).toBeVisible();
    
    // Check all elements are visible
    await expect(page.locator('[aria-label="Toggle navigation menu"]')).toBeVisible();
    await expect(page.locator('[aria-label*="Notifications"]')).toBeVisible();
    await expect(page.locator('[aria-label*="User menu"]')).toBeVisible();
  });
});
