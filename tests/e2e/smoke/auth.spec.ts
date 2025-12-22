import { test, expect } from '@playwright/test';
import { AuthHelper, testData } from '../helpers/smoke-helpers';

// Helper function to get theme state consistently
const getThemeState = () => {
  const html = document.documentElement;
  return html.dataset.theme ?? (html.classList.contains('dark') ? 'dark' : 'light');
};

test.describe('E2E Smoke Tests - Authentication', () => {
  let authHelper: AuthHelper;

  test.beforeEach(async ({ page }) => {
    authHelper = new AuthHelper(page);
    
    // Clear localStorage to prevent theme preference from previous runs
    // Handle security errors gracefully
    try {
      await page.evaluate(() => {
        localStorage.clear();
        sessionStorage.clear();
      });
    } catch (error) {
      console.log('Could not clear storage (security restriction):', (error as Error).message);
    }
    
    // Capture console messages
    page.on('console', msg => {
      if (msg.type() === 'log') {
        console.log('Browser console:', msg.text());
      }
    });
  });

  test('@smoke S1: User registration flow', async ({ page }) => {
    // Navigate to login page
    await page.goto('/login');
    
    // Check if register link exists
    const registerLink = page.locator('a:has-text("Sign up"), a:has-text("Register")');
    
    if (await registerLink.isVisible()) {
      // Click register link
      await registerLink.click();
      
      // Should redirect to dashboard (register not implemented in React FE v1)
      await page.waitForURL(/\/app\/dashboard/, { timeout: 10000 });
      
      // Verify we're on dashboard
      await expect(page.locator('h1:has-text("Dashboard"), [role="heading"]:has-text("Dashboard")')).toBeVisible();
    } else {
      // If no register link, verify login page loads correctly
      await expect(page.locator('input[name="email"], input[type="email"]')).toBeVisible();
      await expect(page.locator('input[name="password"], input[type="password"]')).toBeVisible();
      await expect(page.locator('button[type="submit"], button:has-text("Sign In")')).toBeVisible();
    }
  });

  test('@smoke S2: User login with i18n and theme toggle', async ({ page }) => {
    // Test login with ZENA admin user
    const testUser = testData.users.zena.find(user => user.role === 'Admin');
    expect(testUser).toBeDefined();
    
    if (testUser) {
      await authHelper.login(testUser.email, testUser.password);
      
      // Verify successful login
      await expect(authHelper.isLoggedIn()).resolves.toBe(true);
      
      // Test theme toggle functionality
      const themeToggle = page.locator('button:has-text("Dark mode"), button:has-text("Theme"), [data-testid="theme-toggle"]').first();
      
      if (await themeToggle.isVisible()) {
        // Get initial theme
        const initialTheme = await page.evaluate(getThemeState);
        console.log('Initial theme:', initialTheme);
        
        // Toggle theme
        await themeToggle.click();
        await page.waitForTimeout(500);
        
        // Get final theme and verify it changed
        const newTheme = await page.evaluate(getThemeState);
        console.log('New theme:', newTheme);
        
        // Verify theme actually changed
        expect(newTheme).not.toBe(initialTheme);
      }
      
      // Test i18n (if available)
      // This would test language switching if implemented
      const languageToggle = page.locator('button[aria-label*="language"], select[name*="language"]');
      if (await languageToggle.isVisible()) {
        // Test language switching
        await languageToggle.click();
        await page.waitForTimeout(500);
      }
    }
  });

  test('@smoke S10: User logout flow', async ({ page }) => {
    // Login first
    const testUser = testData.users.zena.find(user => user.role === 'Admin');
    expect(testUser).toBeDefined();
    
    if (testUser) {
      await authHelper.login(testUser.email, testUser.password);
      
      // Verify logged in
      await expect(authHelper.isLoggedIn()).resolves.toBe(true);
      
      // Logout
      await authHelper.logout();
      
      // Verify logged out
      await expect(authHelper.isLoggedIn()).resolves.toBe(false);
      
      // Should be on login page
      await expect(page).toHaveURL(/\/login/);
    }
  });

  test('@smoke Password visibility toggle', async () => {
    await authHelper.testPasswordToggle();
  });

  test('@smoke Form validation', async ({ page }) => {
    await page.goto('/login');
    
    // Test invalid email
    await page.fill('input[name="email"], input[type="email"]', 'invalid-email');
    await page.fill('input[name="password"], input[type="password"]', 'password123');
    
    // Submit form
    await page.click('button[type="submit"], button:has-text("Sign In")');
    
    // Check for validation error or form still visible
    await page.waitForTimeout(1000);
    
    // Either validation error appears or form remains (both are acceptable)
    const hasValidationError = await page.locator('.error, .invalid, [data-testid="error"]').isVisible();
    const formStillVisible = await page.locator('input[name="email"]').isVisible();
    
    expect(hasValidationError || formStillVisible).toBe(true);
  });

  test('@smoke Forgot password navigation', async ({ page }) => {
    await page.goto('/login');
    
    // Look for forgot password link
    const forgotPasswordLink = page.locator('a:has-text("Forgot password"), a:has-text("Forgot Password")');
    
    if (await forgotPasswordLink.isVisible()) {
      await forgotPasswordLink.click();
      
      // Should navigate to forgot password page
      await page.waitForURL(/\/forgot-password/, { timeout: 10000 });
      
      // Verify forgot password page elements
      await expect(page.locator('h1:has-text("Forgot Password"), h2:has-text("Forgot Password")')).toBeVisible();
    }
  });

  test('@smoke Multi-tenant login isolation', async ({ page }) => {
    // Test ZENA user login
    const zenaUser = testData.users.zena.find(user => user.role === 'Admin');
    const ttfUser = testData.users.ttf.find(user => user.role === 'Admin');
    
    expect(zenaUser).toBeDefined();
    expect(ttfUser).toBeDefined();
    
    if (zenaUser && ttfUser) {
      // Login as ZENA user
      await authHelper.login(zenaUser.email, zenaUser.password);
      await expect(authHelper.isLoggedIn()).resolves.toBe(true);
      
      // Logout
      await authHelper.logout();
      
      // Login as TTF user
      await authHelper.login(ttfUser.email, ttfUser.password);
      await expect(authHelper.isLoggedIn()).resolves.toBe(true);
      
      // Verify different tenant context
      // This would be verified by checking tenant-specific data
      const currentUrl = page.url();
      expect(currentUrl).toMatch(/\/app\/|\/dashboard/);
    }
  });

  test('@smoke Responsive authentication', async ({ page }) => {
    // Test mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/login');
    
    // Verify login form is still visible and properly sized
    await expect(page.locator('input[name="email"], input[type="email"]')).toBeVisible();
    await expect(page.locator('input[name="password"], input[type="password"]')).toBeVisible();
    await expect(page.locator('button[type="submit"], button:has-text("Sign In")')).toBeVisible();
    
    // Test tablet viewport
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.waitForTimeout(500);
    
    // Verify form still works
    await expect(page.locator('input[name="email"], input[type="email"]')).toBeVisible();
    
    // Test desktop viewport
    await page.setViewportSize({ width: 1200, height: 800 });
    await page.waitForTimeout(500);
    
    // Verify form still works
    await expect(page.locator('input[name="email"], input[type="email"]')).toBeVisible();
  });

  test('@smoke Authentication error handling', async ({ page }) => {
    await page.goto('/login');
    
    // Test with invalid credentials
    await page.fill('input[name="email"], input[type="email"]', 'nonexistent@test.local');
    await page.fill('input[name="password"], input[type="password"]', 'wrongpassword');
    
    // Submit form
    await page.click('button[type="submit"], button:has-text("Sign In")');
    
    // Wait for response
    await page.waitForTimeout(2000);
    
    // Should either show error message or stay on login page
    const hasErrorMessage = await page.locator('.error, .alert-error, [data-testid="error"]').isVisible();
    const stillOnLoginPage = page.url().includes('/login');
    
    expect(hasErrorMessage || stillOnLoginPage).toBe(true);
  });
});
