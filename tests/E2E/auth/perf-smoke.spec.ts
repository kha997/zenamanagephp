import { test, expect } from '@playwright/test';
import { assertPerformanceBudget } from './helpers/assertions';

test.describe('Performance Smoke', () => {
  test('should load login page within performance budget', async ({ page }) => {
    const startTime = Date.now();
    
    await page.goto('/login');
    
    // Wait for page to be interactive
    await page.waitForLoadState('networkidle');
    
    const endTime = Date.now();
    const loadTime = endTime - startTime;
    
    // Should load within 2 seconds
    expect(loadTime).toBeLessThan(2000);
  });

  test('should measure TTFB for login page', async ({ page }) => {
    const response = await page.goto('/login');
    
    if (!response) return;
    
    const timing = response.timing();
    
    if (timing.requestStart > 0 && timing.responseStart > 0) {
      const ttfb = timing.responseStart - timing.requestStart;
      
      // TTFB should be under 500ms
      expect(ttfb).toBeLessThan(500);
    }
  });

  test('should measure first contentful paint', async ({ page }) => {
    await page.goto('/login');
    
    const fcp = await page.evaluate(() => {
      return new Promise((resolve) => {
        new PerformanceObserver((list) => {
          const entries = list.getEntries();
          const fcpEntry = entries.find(entry => entry.name === 'first-contentful-paint');
          if (fcpEntry) {
            resolve(fcpEntry.startTime);
          }
        }).observe({ entryTypes: ['paint'] });
        
        // Timeout after 5s
        setTimeout(() => resolve(null), 5000);
      });
    });
    
    if (fcp && typeof fcp === 'number') {
      // FCP should be under 2 seconds
      expect(fcp).toBeLessThan(2000);
    }
  });

  test('should load registration page within budget', async ({ page }) => {
    const startTime = Date.now();
    
    await page.goto('/register');
    await page.waitForLoadState('networkidle');
    
    const loadTime = Date.now() - startTime;
    
    // Should load within 2 seconds
    expect(loadTime).toBeLessThan(2000);
  });

  test('should complete login flow within budget', async ({ page }) => {
    const startTime = Date.now();
    
    // Navigate to login
    await page.goto('/login');
    
    // Fill form
    await page.fill('[data-testid="email-input"]', 'admin@zena.test');
    await page.fill('[data-testid="password-input"]', 'password');
    
    // Submit and wait for redirect
    const navigationPromise = page.waitForNavigation();
    await page.click('[data-testid="login-submit"]');
    await navigationPromise;
    
    const endTime = Date.now();
    const loginTime = endTime - startTime;
    
    // Login should complete within 3 seconds
    expect(loginTime).toBeLessThan(3000);
  });

  test('should handle cold start efficiently', async ({ page }) => {
    // Clear cache
    await page.context().clearCookies();
    await page.evaluate(() => localStorage.clear());
    
    const startTime = Date.now();
    
    await page.goto('/login');
    await page.waitForLoadState('networkidle');
    
    const coldStartTime = Date.now() - startTime;
    
    // Cold start should still be reasonable
    expect(coldStartTime).toBeLessThan(3000);
  });

  test('should handle warm reload efficiently', async ({ page }) => {
    // First load (cold)
    await page.goto('/login');
    await page.waitForLoadState('networkidle');
    
    const coldStart = Date.now();
    
    // Second load (warm)
    await page.reload();
    await page.waitForLoadState('networkidle');
    
    const warmStart = Date.now() - coldStart;
    
    // Warm load should be faster
    expect(warmStart).toBeLessThan(1000);
  });

  test('should have acceptable resource sizes', async ({ page }) => {
    const response = await page.goto('/login');
    
    if (!response) return;
    
    const body = await response.body();
    const sizeKB = body.length / 1024;
    
    // Initial HTML should be under 100KB
    expect(sizeKB).toBeLessThan(100);
  });

  test('should minimize render-blocking resources', async ({ page }) => {
    await page.goto('/login');
    
    // Get all resources
    const resources = await page.evaluate(() => {
      return performance.getEntriesByType('resource') as PerformanceResourceTiming[];
    });
    
    // Count blocking resources
    const blockingResources = resources.filter(r => 
      r.initiatorType === 'link' || 
      r.initiatorType === 'script' ||
      r.renderBlockingStatus === 'blocking'
    ).length;
    
    // Should have minimal blocking resources
    expect(blockingResources).toBeLessThan(5);
  });

  test('should lazy load images', async ({ page }) => {
    await page.goto('/login');
    
    const images = await page.locator('img').all();
    
    for (const img of images) {
      const loading = await img.getAttribute('loading');
      
      // Images should have lazy loading attribute
      expect(loading).toBe('lazy');
    }
  });
});

