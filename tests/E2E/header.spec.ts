import { test, expect } from '@playwright/test';

test.describe('Header Component', () => {
  test.beforeEach(async ({ page }) => {
    // Mock authentication
    await page.addInitScript(() => {
      window.localStorage.setItem('auth_token', 'mock-token');
      window.localStorage.setItem('user', JSON.stringify({
        id: 'user1',
        name: 'Test User',
        email: 'test@example.com',
        roles: ['pm'],
        tenant_id: 'tenant1'
      }));
    });

    await page.goto('/app/dashboard');
  });

  test.describe('Responsive Behavior', () => {
    test('should show full navigation on desktop', async ({ page }) => {
      await page.setViewportSize({ width: 1024, height: 768 });
      
      // Check that desktop navigation is visible
      await expect(page.locator('.header-nav')).toBeVisible();
      await expect(page.locator('.hamburger')).not.toBeVisible();
      
      // Check navigation items
      await expect(page.locator('text=Dashboard')).toBeVisible();
      await expect(page.locator('text=Projects')).toBeVisible();
      await expect(page.locator('text=Tasks')).toBeVisible();
    });

    test('should show hamburger menu on mobile', async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 667 });
      
      // Check that mobile hamburger is visible
      await expect(page.locator('.hamburger')).toBeVisible();
      await expect(page.locator('.header-nav')).not.toBeVisible();
      
      // Check that mobile sheet is initially closed
      await expect(page.locator('.mobile-sheet')).toHaveClass(/closed/);
    });

    test('should open mobile menu when hamburger is clicked', async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 667 });
      
      // Click hamburger
      await page.click('.hamburger');
      
      // Check that mobile sheet opens
      await expect(page.locator('.mobile-sheet')).toHaveClass(/open/);
      await expect(page.locator('.mobile-overlay')).toBeVisible();
      
      // Check that navigation items are visible in mobile menu
      await expect(page.locator('.mobile-sheet text=Dashboard')).toBeVisible();
      await expect(page.locator('.mobile-sheet text=Projects')).toBeVisible();
    });

    test('should close mobile menu when overlay is clicked', async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 667 });
      
      // Open mobile menu
      await page.click('.hamburger');
      await expect(page.locator('.mobile-sheet')).toHaveClass(/open/);
      
      // Click overlay to close
      await page.click('.mobile-overlay');
      await expect(page.locator('.mobile-sheet')).toHaveClass(/closed/);
    });
  });

  test.describe('Sticky and Condensed Behavior', () => {
    test('should be sticky at top of page', async ({ page }) => {
      await page.setViewportSize({ width: 1024, height: 768 });
      
      const header = page.locator('.header-shell');
      await expect(header).toHaveClass(/sticky/);
      
      // Scroll down and check header is still visible
      await page.evaluate(() => window.scrollTo(0, 500));
      await expect(header).toBeVisible();
    });

    test('should condense when scrolling down', async ({ page }) => {
      await page.setViewportSize({ width: 1024, height: 768 });
      
      const header = page.locator('.header-shell');
      
      // Initially not condensed
      await expect(header).not.toHaveClass(/condensed/);
      
      // Scroll down beyond threshold
      await page.evaluate(() => window.scrollTo(0, 150));
      await page.waitForTimeout(300); // Wait for animation
      
      // Should be condensed
      await expect(header).toHaveClass(/condensed/);
    });

    test('should uncondense when scrolling back to top', async ({ page }) => {
      await page.setViewportSize({ width: 1024, height: 768 });
      
      const header = page.locator('.header-shell');
      
      // Scroll down to condense
      await page.evaluate(() => window.scrollTo(0, 150));
      await page.waitForTimeout(300);
      await expect(header).toHaveClass(/condensed/);
      
      // Scroll back to top
      await page.evaluate(() => window.scrollTo(0, 0));
      await page.waitForTimeout(300);
      
      // Should not be condensed
      await expect(header).not.toHaveClass(/condensed/);
    });
  });

  test.describe('Keyboard Navigation', () => {
    test('should navigate through menu items with Tab', async ({ page }) => {
      await page.setViewportSize({ width: 1024, height: 768 });
      
      // Focus on first navigation item
      await page.keyboard.press('Tab');
      await page.keyboard.press('Tab');
      
      // Should be able to navigate through menu items
      const dashboardLink = page.locator('a[href="/app/dashboard"]');
      await expect(dashboardLink).toBeFocused();
      
      await page.keyboard.press('Tab');
      const projectsLink = page.locator('a[href="/app/projects"]');
      await expect(projectsLink).toBeFocused();
    });

    test('should open user menu with Enter and navigate with Arrow keys', async ({ page }) => {
      await page.setViewportSize({ width: 1024, height: 768 });
      
      // Focus and activate user menu button
      const userMenuButton = page.locator('.header-user-avatar');
      await userMenuButton.focus();
      await page.keyboard.press('Enter');
      
      // Menu should be open
      await expect(page.locator('.header-dropdown')).toBeVisible();
      
      // Navigate with arrow keys
      await page.keyboard.press('ArrowDown');
      const firstMenuItem = page.locator('.header-dropdown-item').first();
      await expect(firstMenuItem).toBeFocused();
      
      await page.keyboard.press('ArrowDown');
      const secondMenuItem = page.locator('.header-dropdown-item').nth(1);
      await expect(secondMenuItem).toBeFocused();
    });

    test('should close user menu with Escape', async ({ page }) => {
      await page.setViewportSize({ width: 1024, height: 768 });
      
      // Open user menu
      await page.click('.header-user-avatar');
      await expect(page.locator('.header-dropdown')).toBeVisible();
      
      // Press Escape to close
      await page.keyboard.press('Escape');
      await expect(page.locator('.header-dropdown')).not.toBeVisible();
      
      // Focus should return to user menu button
      await expect(page.locator('.header-user-avatar')).toBeFocused();
    });

    test('should close mobile menu with Escape', async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 667 });
      
      // Open mobile menu
      await page.click('.hamburger');
      await expect(page.locator('.mobile-sheet')).toHaveClass(/open/);
      
      // Press Escape to close
      await page.keyboard.press('Escape');
      await expect(page.locator('.mobile-sheet')).toHaveClass(/closed/);
    });
  });

  test.describe('RBAC Visibility', () => {
    test('should hide admin-only menu items for non-admin users', async ({ page }) => {
      // Mock non-admin user
      await page.addInitScript(() => {
        window.localStorage.setItem('user', JSON.stringify({
          id: 'user1',
          name: 'Test User',
          email: 'test@example.com',
          roles: ['member'], // Only member role
          tenant_id: 'tenant1'
        }));
      });

      await page.reload();
      
      // Admin menu items should not be visible
      await expect(page.locator('text=Settings')).not.toBeVisible();
      await expect(page.locator('text=Reports')).not.toBeVisible();
      
      // Regular menu items should be visible
      await expect(page.locator('text=Dashboard')).toBeVisible();
      await expect(page.locator('text=Projects')).toBeVisible();
    });

    test('should show all menu items for admin users', async ({ page }) => {
      // Mock admin user
      await page.addInitScript(() => {
        window.localStorage.setItem('user', JSON.stringify({
          id: 'user1',
          name: 'Test User',
          email: 'test@example.com',
          roles: ['admin'], // Admin role
          tenant_id: 'tenant1'
        }));
      });

      await page.reload();
      
      // All menu items should be visible
      await expect(page.locator('text=Dashboard')).toBeVisible();
      await expect(page.locator('text=Projects')).toBeVisible();
      await expect(page.locator('text=Settings')).toBeVisible();
      await expect(page.locator('text=Reports')).toBeVisible();
    });
  });

  test.describe('Theme Toggle', () => {
    test('should toggle between light and dark themes', async ({ page }) => {
      await page.setViewportSize({ width: 1024, height: 768 });
      
      // Initially should be light theme
      const html = page.locator('html');
      await expect(html).toHaveAttribute('data-theme', 'light');
      
      // Find and click theme toggle (assuming it exists in secondary actions)
      const themeToggle = page.locator('[aria-label*="theme"], [title*="theme"]').first();
      if (await themeToggle.isVisible()) {
        await themeToggle.click();
        
        // Should switch to dark theme
        await expect(html).toHaveAttribute('data-theme', 'dark');
        
        // Click again to switch back
        await themeToggle.click();
        await expect(html).toHaveAttribute('data-theme', 'light');
      }
    });
  });

  test.describe('Search Functionality', () => {
    test('should open search overlay when search button is clicked', async ({ page }) => {
      await page.setViewportSize({ width: 1024, height: 768 });
      
      // Click search button
      const searchButton = page.locator('[aria-label="Search"]');
      await searchButton.click();
      
      // Search overlay should be visible
      await expect(page.locator('[role="dialog"][aria-label="Search"]')).toBeVisible();
    });

    test('should open search with Ctrl+K shortcut', async ({ page }) => {
      await page.setViewportSize({ width: 1024, height: 768 });
      
      // Press Ctrl+K
      await page.keyboard.press('Control+k');
      
      // Search overlay should be visible
      await expect(page.locator('[role="dialog"][aria-label="Search"]')).toBeVisible();
    });
  });

  test.describe('Notifications', () => {
    test('should show unread count badge', async ({ page }) => {
      await page.setViewportSize({ width: 1024, height: 768 });
      
      // Mock notifications with unread count
      await page.addInitScript(() => {
        window.localStorage.setItem('notifications', JSON.stringify([
          { id: '1', title: 'Test', message: 'Test message', read: false }
        ]));
      });

      await page.reload();
      
      // Should show unread badge
      const notificationBell = page.locator('[aria-label*="Notifications"]');
      await expect(notificationBell.locator('.bg-red-500')).toBeVisible();
    });

    test('should open notifications overlay when clicked', async ({ page }) => {
      await page.setViewportSize({ width: 1024, height: 768 });
      
      // Click notifications bell
      const notificationBell = page.locator('[aria-label*="Notifications"]');
      await notificationBell.click();
      
      // Notifications overlay should be visible
      await expect(page.locator('[role="menu"][aria-label="Notifications"]')).toBeVisible();
    });
  });
});
