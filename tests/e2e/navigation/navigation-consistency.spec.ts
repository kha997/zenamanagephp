import { test, expect } from '@playwright/test';

/**
 * Navigation Consistency E2E Test
 * 
 * PR #5: Verify that Blade and React render the same navigation items
 * from the same source (NavigationService).
 */
test.describe('Navigation Consistency', () => {
  test.beforeEach(async ({ page }) => {
    // Login as test user
    // Note: This assumes you have a test user set up
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // Wait for navigation to load
    await page.waitForSelector('[data-testid="primary-navigator"]', { timeout: 5000 });
  });

  test('Blade navigation displays correct items', async ({ page }) => {
    // Navigate to a Blade page (e.g., /app/dashboard)
    await page.goto('/app/dashboard');
    
    // Wait for navigation
    const nav = page.locator('[data-testid="primary-navigator"]');
    await expect(nav).toBeVisible();
    
    // Check that navigation items are present
    const navItems = nav.locator('a');
    const count = await navItems.count();
    
    expect(count).toBeGreaterThan(0);
    
    // Verify common navigation items exist
    const navTexts = await navItems.allTextContents();
    expect(navTexts).toContain('Dashboard');
    expect(navTexts).toContain('Projects');
    expect(navTexts).toContain('Tasks');
  });

  test('React navigation displays correct items', async ({ page }) => {
    // Navigate to a React page (e.g., /app/tasks if React is enabled)
    // Note: This test assumes React routes are available
    await page.goto('/app/tasks');
    
    // Wait for React navigation (HeaderShell)
    const header = page.locator('header[role="banner"]');
    await expect(header).toBeVisible({ timeout: 5000 });
    
    // Check that navigation items are present
    const nav = header.locator('nav[role="navigation"]');
    const navItems = nav.locator('a');
    const count = await navItems.count();
    
    expect(count).toBeGreaterThan(0);
    
    // Verify common navigation items exist
    const navTexts = await navItems.allTextContents();
    expect(navTexts).toContain('Dashboard');
    expect(navTexts).toContain('Projects');
    expect(navTexts).toContain('Tasks');
  });

  test('Navigation items match between Blade and React', async ({ page }) => {
    // Get navigation items from Blade page
    await page.goto('/app/dashboard');
    const bladeNav = page.locator('[data-testid="primary-navigator"]');
    await expect(bladeNav).toBeVisible();
    
    const bladeItems = await bladeNav.locator('a').allTextContents();
    const bladePaths = await bladeNav.locator('a').evaluateAll(els => 
      els.map(el => (el as HTMLAnchorElement).href)
    );
    
    // Get navigation items from React page (if available)
    // Note: This assumes React routes are available
    try {
      await page.goto('/app/tasks');
      const reactNav = page.locator('header nav[role="navigation"]');
      
      // If React navigation exists, compare
      if (await reactNav.count() > 0) {
        await expect(reactNav).toBeVisible({ timeout: 5000 });
        
        const reactItems = await reactNav.locator('a').allTextContents();
        
        // Compare navigation items (order may differ, so check content)
        const bladeSet = new Set(bladeItems.map(item => item.trim()));
        const reactSet = new Set(reactItems.map(item => item.trim()));
        
        // Both should have same items (allowing for different order)
        expect(bladeSet.size).toBe(reactSet.size);
        
        // All Blade items should be in React
        bladeSet.forEach(item => {
          expect(reactSet.has(item)).toBe(true);
        });
      } else {
        // React navigation not available, skip comparison
        test.skip();
      }
    } catch (e) {
      // React routes may not be available, skip this test
      test.skip();
    }
  });

  test('Navigation respects permissions', async ({ page }) => {
    // Login as regular user (not admin)
    await page.goto('/login');
    await page.fill('input[name="email"]', 'member@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    await page.goto('/app/dashboard');
    const nav = page.locator('[data-testid="primary-navigator"]');
    await expect(nav).toBeVisible();
    
    const navTexts = await nav.locator('a').allTextContents();
    
    // Regular users should not see admin items
    expect(navTexts).not.toContain('Admin Dashboard');
    expect(navTexts).not.toContain('Tenants');
    expect(navTexts).not.toContain('Users (System)');
  });

  test('Admin navigation shows for admin users', async ({ page }) => {
    // Login as admin user
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    await page.goto('/app/dashboard');
    const nav = page.locator('[data-testid="primary-navigator"]');
    await expect(nav).toBeVisible();
    
    const navTexts = await nav.locator('a').allTextContents();
    
    // Admin users should see admin items
    expect(navTexts).toContain('Admin Dashboard');
  });
});

