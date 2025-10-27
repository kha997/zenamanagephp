import { test } from '@playwright/test';

test.describe('UI Screenshots for Review', () => {
  test('capture login page screenshot', async ({ page }) => {
    await page.goto('/login');
    await page.waitForLoadState('networkidle');
    
    // Take full page screenshot
    await page.screenshot({ 
      path: 'ui-screenshots/login-page.png', 
      fullPage: true 
    });
    
    // Take viewport screenshot
    await page.screenshot({ 
      path: 'ui-screenshots/login-page-viewport.png' 
    });
  });

  test('capture dashboard page screenshot', async ({ page }) => {
    await page.goto('/app/dashboard');
    await page.waitForLoadState('networkidle');
    
    // Take full page screenshot
    await page.screenshot({ 
      path: 'ui-screenshots/dashboard-page.png', 
      fullPage: true 
    });
    
    // Take viewport screenshot
    await page.screenshot({ 
      path: 'ui-screenshots/dashboard-page-viewport.png' 
    });
  });

  test('capture preferences page screenshot', async ({ page }) => {
    await page.goto('/app/preferences');
    await page.waitForLoadState('networkidle');
    
    // Take full page screenshot
    await page.screenshot({ 
      path: 'ui-screenshots/preferences-page.png', 
      fullPage: true 
    });
    
    // Take viewport screenshot
    await page.screenshot({ 
      path: 'ui-screenshots/preferences-page-viewport.png' 
    });
  });

  test('capture alerts page screenshot', async ({ page }) => {
    await page.goto('/app/alerts');
    await page.waitForLoadState('networkidle');
    
    // Take full page screenshot
    await page.screenshot({ 
      path: 'ui-screenshots/alerts-page.png', 
      fullPage: true 
    });
    
    // Take viewport screenshot
    await page.screenshot({ 
      path: 'ui-screenshots/alerts-page-viewport.png' 
    });
  });

  test('capture mobile view screenshot', async ({ page }) => {
    // Set mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    
    await page.goto('/login');
    await page.waitForLoadState('networkidle');
    
    // Take mobile screenshot
    await page.screenshot({ 
      path: 'ui-screenshots/login-mobile.png' 
    });
    
    // Navigate to dashboard on mobile
    await page.goto('/app/dashboard');
    await page.waitForLoadState('networkidle');
    
    await page.screenshot({ 
      path: 'ui-screenshots/dashboard-mobile.png' 
    });
  });

  test('capture dark mode screenshots', async ({ page }) => {
    // Navigate to dashboard first
    await page.goto('/app/dashboard');
    await page.waitForLoadState('networkidle');
    
    // Toggle to dark mode (assuming there's a theme toggle)
    const themeToggle = page.locator('[data-testid="theme-toggle"]').or(page.locator('button:has-text("Dark")')).or(page.locator('button:has-text("🌙")'));
    if (await themeToggle.isVisible()) {
      await themeToggle.click();
      await page.waitForTimeout(1000); // Wait for theme change
    }
    
    // Take dark mode screenshots
    await page.screenshot({ 
      path: 'ui-screenshots/dashboard-dark-mode.png', 
      fullPage: true 
    });
    
    await page.screenshot({ 
      path: 'ui-screenshots/dashboard-dark-mode-viewport.png' 
    });
  });
});
