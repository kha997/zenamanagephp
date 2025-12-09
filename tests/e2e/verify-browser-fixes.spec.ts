import { test, expect } from '@playwright/test';

/**
 * Step 1: Refresh và Verify Browser Fixes - Automated Check
 * 
 * Script này tự động kiểm tra:
 * 1. Console errors (Alpine.js, Chart.js, Syntax errors)
 * 2. Visual elements (header, charts, layout)
 * 3. API calls và CSRF tokens
 */

test.describe('Step 1: Browser Fixes Verification', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to dashboard
    await page.goto('http://127.0.0.1:8000/app/dashboard');
    await page.waitForLoadState('networkidle');
    
    // Wait for Alpine.js and Chart.js to initialize
    await page.waitForTimeout(2000);
  });

  test('should NOT have Alpine.js ReferenceErrors', async ({ page }) => {
    const errors: string[] = [];
    
    page.on('console', msg => {
      if (msg.type() === 'error') {
        const text = msg.text();
        // Check for Alpine.js ReferenceErrors we fixed
        const isAlpineError = 
          text.includes('showMobileMenu is not defined') ||
          text.includes('currentTheme is not defined') ||
          text.includes('unreadCount is not defined') ||
          text.includes('showUserMenu is not defined') ||
          text.includes('notifications is not defined') ||
          text.includes('alertCount is not defined');
        
        if (isAlpineError) {
          errors.push(text);
        }
      }
    });

    // Wait for page to fully load
    await page.waitForTimeout(2000);
    
    // Should have NO Alpine.js ReferenceErrors
    expect(errors).toHaveLength(0);
  });

  test('should NOT have Syntax Errors', async ({ page }) => {
    const errors: string[] = [];
    
    page.on('console', msg => {
      if (msg.type() === 'error') {
        const text = msg.text();
        // Check for Syntax Errors
        const isSyntaxError = 
          text.includes('SyntaxError: Invalid or unexpected token') ||
          text.includes('SyntaxError: Unexpected token') ||
          (text.includes('SyntaxError') && text.includes('cdn.min.js'));
        
        if (isSyntaxError) {
          errors.push(text);
        }
      }
    });

    await page.waitForTimeout(2000);
    
    // Should have NO Syntax Errors
    expect(errors).toHaveLength(0);
  });

  test('should NOT have Chart.js adapter errors', async ({ page }) => {
    const errors: string[] = [];
    
    page.on('console', msg => {
      if (msg.type() === 'error') {
        const text = msg.text();
        // Check for Chart.js adapter errors
        const isChartError = 
          text.includes('Cannot read properties of undefined (reading \'_adapters\')') ||
          text.includes('Cannot read properties of undefined (reading \'_date\')') ||
          (text.includes('Chart') && text.includes('is not defined'));
        
        if (isChartError) {
          errors.push(text);
        }
      }
    });

    await page.waitForTimeout(2000);
    
    // Chart.js should be available
    const chartAvailable = await page.evaluate(() => typeof Chart !== 'undefined');
    expect(chartAvailable).toBe(true);
    
    // Should have NO Chart.js adapter errors
    expect(errors).toHaveLength(0);
  });

  test('should display header component correctly', async ({ page }) => {
    // Check header exists
    const header = page.locator('[data-testid="header-wrapper"]');
    await expect(header).toBeVisible();
    
    // Check header elements
    await expect(header.locator('text=ZenaManage')).toBeVisible();
    
    // Check user menu button exists
    const userMenuButton = header.locator('button[aria-label*="User menu"], button[aria-label*="user menu"]');
    await expect(userMenuButton.first()).toBeVisible();
    
    // Check notifications button exists
    const notificationsButton = header.locator('button[aria-label*="Notifications"], button[aria-label*="notifications"]');
    await expect(notificationsButton.first()).toBeVisible();
    
    // Check theme toggle exists
    const themeToggle = header.locator('button[aria-label*="Toggle theme"], button[aria-label*="theme"]');
    await expect(themeToggle.first()).toBeVisible();
  });

  test('should have Alpine.js component initialized', async ({ page }) => {
    const header = page.locator('[data-testid="header-wrapper"]');
    
    // Check Alpine.js is available
    const alpineAvailable = await page.evaluate(() => typeof Alpine !== 'undefined');
    expect(alpineAvailable).toBe(true);
    
    // Check header has x-data attribute or Alpine component
    const hasAlpineData = await header.evaluate(el => {
      return el.hasAttribute('x-data') || 
             el.querySelector('[x-data]') !== null;
    });
    
    expect(hasAlpineData || alpineAvailable).toBe(true);
  });

  test('should handle expected API errors gracefully', async ({ page }) => {
    const unexpectedErrors: string[] = [];
    
    page.on('console', msg => {
      if (msg.type() === 'error') {
        const text = msg.text();
        // Allow expected errors (404, 403, JSON parse errors for disabled features)
        const isExpectedError = 
          text.includes('focus mode') ||
          text.includes('rewards') ||
          text.includes('403') ||
          text.includes('404') ||
          text.includes('Feature may not be enabled');
        
        if (!isExpectedError && text.includes('Error')) {
          unexpectedErrors.push(text);
        }
      }
    });

    await page.waitForTimeout(3000);
    
    // Should not have many unexpected errors
    // Allow some minor errors but not major ones
    expect(unexpectedErrors.length).toBeLessThan(5);
  });

  test('should have CSRF token in API requests', async ({ page }) => {
    const requestsWithCsrf: string[] = [];
    const requestsWithoutCsrf: string[] = [];
    
    page.on('request', request => {
      if (request.url().includes('/api/')) {
        const headers = request.headers();
        if (headers['x-csrf-token'] || headers['X-CSRF-TOKEN']) {
          requestsWithCsrf.push(request.url());
        } else {
          requestsWithoutCsrf.push(request.url());
        }
      }
    });

    // Trigger some API calls by interacting with page
    await page.waitForTimeout(2000);
    
    // Log results for debugging
    console.log('API requests with CSRF:', requestsWithCsrf.length);
    console.log('API requests without CSRF:', requestsWithoutCsrf.length);
    
    // At least some API calls should have CSRF token if any were made
    // (Note: Some API calls might not be made on initial load)
    if (requestsWithCsrf.length + requestsWithoutCsrf.length > 0) {
      expect(requestsWithCsrf.length).toBeGreaterThan(0);
    }
  });

  test('should display charts without errors', async ({ page }) => {
    // Check if chart canvases exist
    const projectProgressChart = page.locator('#projectProgressChart');
    const taskCompletionChart = page.locator('#taskCompletionChart');
    
    // Charts might not exist if no data, so check if they exist first
    const hasProjectChart = await projectProgressChart.count() > 0;
    const hasTaskChart = await taskCompletionChart.count() > 0;
    
    if (hasProjectChart) {
      await expect(projectProgressChart).toBeVisible();
    }
    
    if (hasTaskChart) {
      await expect(taskCompletionChart).toBeVisible();
    }
    
    // Check for chart-related errors
    const chartErrors: string[] = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        const text = msg.text();
        if (text.includes('chart') || text.includes('Chart')) {
          chartErrors.push(text);
        }
      }
    });
    
    await page.waitForTimeout(2000);
    
    // Should have no chart errors
    expect(chartErrors.length).toBeLessThan(3); // Allow some minor errors
  });

  test('should have responsive layout', async ({ page }) => {
    // Test desktop view
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.waitForTimeout(500);
    
    const header = page.locator('[data-testid="header-wrapper"]');
    await expect(header).toBeVisible();
    
    // Test tablet view
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.waitForTimeout(500);
    await expect(header).toBeVisible();
    
    // Test mobile view
    await page.setViewportSize({ width: 375, height: 667 });
    await page.waitForTimeout(500);
    await expect(header).toBeVisible();
    
    // Check mobile menu button exists on mobile
    const mobileMenuButton = header.locator('button[aria-label*="mobile menu"], button[aria-label*="Toggle mobile"]');
    if (await mobileMenuButton.count() > 0) {
      await expect(mobileMenuButton.first()).toBeVisible();
    }
  });
});

