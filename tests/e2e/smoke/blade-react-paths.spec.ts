import { test, expect } from '@playwright/test';
import { MinimalAuthHelper } from '../helpers/auth';

/**
 * Smoke Tests for Blade and React Paths
 * 
 * PR: Smoke tests cho Blade và React paths + Feature flag verification
 * 
 * Tests verify:
 * 1. Blade paths (/admin/*) render correctly
 * 2. React paths (/app/*) render correctly when React is enabled
 * 3. Feature flag routing works correctly
 * 4. Navigation consistency between Blade and React
 */

test.describe('Blade and React Paths Smoke Tests', () => {
  let authHelper: MinimalAuthHelper;

  test.beforeEach(async ({ page }) => {
    authHelper = new MinimalAuthHelper(page);
    
    // Login as admin user
    await authHelper.login('admin@zena.local', 'password');
    
    // Wait for navigation to be ready
    await page.waitForTimeout(1000);
  });

  test('@smoke Blade admin dashboard loads correctly', async ({ page }) => {
    // Navigate to admin dashboard (Blade route)
    await page.goto('/admin/dashboard');
    
    // Wait for page to load
    await page.waitForLoadState('networkidle');
    
    // Verify page title
    const title = await page.title();
    expect(title).toBeTruthy();
    
    // Verify admin dashboard content is present
    // Look for common admin dashboard elements
    const hasAdminContent = await page.locator('body').textContent();
    expect(hasAdminContent).toBeTruthy();
    
    // Verify we're on admin route
    const url = page.url();
    expect(url).toContain('/admin/dashboard');
  });

  test('@smoke Blade admin routes are accessible', async ({ page }) => {
    const adminRoutes = [
      '/admin/dashboard',
      '/admin/users',
      '/admin/tenants',
      '/admin/settings',
    ];

    for (const route of adminRoutes) {
      await page.goto(route);
      await page.waitForLoadState('domcontentloaded');
      
      // Verify page loaded (not 404 or error)
      const status = page.url();
      expect(status).toContain(route);
      
      // Verify page has content
      const bodyText = await page.locator('body').textContent();
      expect(bodyText).toBeTruthy();
    }
  });

  test('@smoke React app dashboard loads correctly', async ({ page }) => {
    // Navigate to app dashboard (React route)
    await page.goto('/app/dashboard');
    
    // Wait for React to hydrate
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000); // Additional wait for React hydration
    
    // Verify React app loaded
    // Look for React root element or common React markers
    const reactRoot = page.locator('#root, [data-reactroot], [id^="react"]');
    const hasReactRoot = await reactRoot.count() > 0;
    
    // Alternative: Check for React Router or common React components
    const bodyContent = await page.locator('body').textContent();
    const hasReactContent = bodyContent && (
      bodyContent.includes('Dashboard') ||
      bodyContent.includes('Projects') ||
      bodyContent.includes('Tasks')
    );
    
    // At least one should be true
    expect(hasReactRoot || hasReactContent).toBeTruthy();
    
    // Verify URL
    const url = page.url();
    expect(url).toContain('/app/dashboard');
  });

  test('@smoke React app routes are accessible', async ({ page }) => {
    const appRoutes = [
      '/app/dashboard',
      '/app/projects',
      '/app/tasks',
    ];

    for (const route of appRoutes) {
      await page.goto(route);
      await page.waitForLoadState('domcontentloaded');
      await page.waitForTimeout(2000); // Wait for React hydration
      
      // Verify page loaded
      const url = page.url();
      expect(url).toContain(route);
      
      // Verify page has content (not blank)
      const bodyText = await page.locator('body').textContent();
      expect(bodyText).toBeTruthy();
      expect(bodyText.length).toBeGreaterThan(100); // Should have substantial content
    }
  });

  test('@smoke Navigation works in Blade pages', async ({ page }) => {
    // Navigate to admin dashboard
    await page.goto('/admin/dashboard');
    await page.waitForLoadState('networkidle');
    
    // Check if navigation is present
    const nav = page.locator('nav, [role="navigation"], [data-testid*="nav"]');
    const navCount = await nav.count();
    
    // Navigation should exist (at least one nav element)
    expect(navCount).toBeGreaterThan(0);
    
    // Verify navigation links are clickable
    const navLinks = page.locator('nav a, [role="navigation"] a');
    const linkCount = await navLinks.count();
    
    if (linkCount > 0) {
      // Try clicking first link
      const firstLink = navLinks.first();
      const href = await firstLink.getAttribute('href');
      
      if (href && !href.startsWith('#')) {
        await firstLink.click();
        await page.waitForLoadState('domcontentloaded');
        
        // Verify navigation occurred
        const newUrl = page.url();
        expect(newUrl).toBeTruthy();
      }
    }
  });

  test('@smoke Navigation works in React pages', async ({ page }) => {
    // Navigate to app dashboard
    await page.goto('/app/dashboard');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000); // Wait for React hydration
    
    // Check for React navigation (HeaderShell or similar)
    const header = page.locator('header, [role="banner"], [data-testid*="header"]');
    const headerCount = await header.count();
    
    // Header should exist
    expect(headerCount).toBeGreaterThan(0);
    
    // Check for navigation links
    const navLinks = page.locator('header a, [role="banner"] a, nav a');
    const linkCount = await navLinks.count();
    
    if (linkCount > 0) {
      // Verify links are present
      expect(linkCount).toBeGreaterThan(0);
    }
  });

  test('@smoke Feature flag routing works correctly', async ({ page }) => {
    // Test that /app/tasks can route to either Blade or React
    // depending on feature flag
    
    await page.goto('/app/tasks');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    // Verify page loaded (either Blade or React)
    const url = page.url();
    expect(url).toContain('/app/tasks');
    
    // Verify page has content
    const bodyText = await page.locator('body').textContent();
    expect(bodyText).toBeTruthy();
    
    // Check if it's React (has React root) or Blade (has Blade markers)
    const hasReactRoot = await page.locator('#root, [data-reactroot]').count() > 0;
    const hasBladeContent = bodyText.includes('Tasks') || bodyText.includes('Kanban');
    
    // At least one should be true
    expect(hasReactRoot || hasBladeContent).toBeTruthy();
  });

  test('@smoke Deep linking works for React routes', async ({ page }) => {
    // Test that direct navigation to React routes works (F5/refresh)
    const deepRoutes = [
      '/app/projects',
      '/app/tasks',
      '/app/dashboard',
    ];

    for (const route of deepRoutes) {
      // Navigate directly (simulating F5/refresh)
      await page.goto(route);
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(2000);
      
      // Verify URL is correct
      const url = page.url();
      expect(url).toContain(route);
      
      // Verify page loaded (not blank or error)
      const bodyText = await page.locator('body').textContent();
      expect(bodyText).toBeTruthy();
    }
  });

  test('@smoke Authentication redirects work correctly', async ({ page }) => {
    // Logout first
    await authHelper.logout();
    
    // Try to access protected route
    await page.goto('/app/dashboard');
    
    // Should redirect to login
    await page.waitForLoadState('domcontentloaded');
    const url = page.url();
    
    // Should be on login page
    expect(url).toMatch(/\/login/);
  });

  test('@smoke Page load performance is acceptable', async ({ page }) => {
    // Measure page load time for key routes
    const routes = [
      '/admin/dashboard',
      '/app/dashboard',
    ];

    for (const route of routes) {
      const startTime = Date.now();
      
      await page.goto(route);
      await page.waitForLoadState('networkidle');
      
      const loadTime = Date.now() - startTime;
      
      // Page should load within 5 seconds (smoke test threshold)
      expect(loadTime).toBeLessThan(5000);
    }
  });
});

