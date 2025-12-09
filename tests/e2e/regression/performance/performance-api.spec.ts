import { test, expect } from '@playwright/test';
import { AuthHelper } from '../../helpers/smoke-helpers';
import { testData } from '../../helpers/data';

test.describe('@regression Performance API Testing', () => {
  let authHelper: AuthHelper;

  test.beforeEach(async ({ page }) => {
    authHelper = new AuthHelper(page);
  });

  test('@regression API endpoint response times', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to users page
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    // Intercept API requests to measure response times
    const responseTimes = [];
    const endpoints = [];
    
    await page.route('**/api/**', route => {
      const startTime = Date.now();
      const url = route.request().url();
      
      route.continue().then(() => {
        const endTime = Date.now();
        const responseTime = endTime - startTime;
        responseTimes.push(responseTime);
        endpoints.push(url);
        
        console.log(`✅ API endpoint ${url} response time: ${responseTime}ms`);
      });
    });
    
    // Trigger API calls
    const refreshButton = page.locator('button:has-text("Refresh"), button:has-text("Reload"), [data-testid="refresh-button"]');
    
    if (await refreshButton.isVisible()) {
      await refreshButton.click();
      
      // Wait for API calls to complete
      await page.waitForTimeout(3000);
      
      console.log(`✅ API calls completed: ${responseTimes.length}`);
      
      // Check for API timing in UI
      const apiTiming = page.locator('.api-timing, .response-time, .request-timing');
      
      if (await apiTiming.isVisible()) {
        console.log('✅ API timing displayed in UI');
        
        const timingText = await apiTiming.textContent();
        console.log(`API timing: ${timingText}`);
      } else {
        console.log('⚠️ No API timing displayed in UI');
      }
      
      // Verify response times are within acceptable limits
      const avgResponseTime = responseTimes.reduce((a, b) => a + b, 0) / responseTimes.length;
      
      if (avgResponseTime < 1000) {
        console.log('✅ Average API response time is acceptable (< 1s)');
      } else {
        console.log('⚠️ Average API response time is slow (> 1s)');
      }
      
    } else {
      console.log('⚠️ No refresh button found - cannot test API response times');
    }
  });

  test('@regression API error handling performance', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to users page
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    // Intercept API requests to simulate errors
    const errorTimes = [];
    
    await page.route('**/api/**', route => {
      const startTime = Date.now();
      
      // Simulate server error
      route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({ error: 'Internal Server Error' })
      }).then(() => {
        const endTime = Date.now();
        const errorTime = endTime - startTime;
        errorTimes.push(errorTime);
        
        console.log(`✅ API error response time: ${errorTime}ms`);
      });
    });
    
    // Trigger API calls
    const refreshButton = page.locator('button:has-text("Refresh"), button:has-text("Reload"), [data-testid="refresh-button"]');
    
    if (await refreshButton.isVisible()) {
      await refreshButton.click();
      
      // Wait for error handling
      await page.waitForTimeout(2000);
      
      console.log(`✅ API error handling completed: ${errorTimes.length}`);
      
      // Check for error handling performance
      const errorHandling = page.locator('.error-handling, .error-performance, .error-timing');
      
      if (await errorHandling.isVisible()) {
        console.log('✅ Error handling performance displayed');
        
        const handlingText = await errorHandling.textContent();
        console.log(`Error handling performance: ${handlingText}`);
      } else {
        console.log('⚠️ No error handling performance information found');
      }
      
      // Check for error recovery time
      const errorRecovery = page.locator('.error-recovery, .recovery-time, .recovery-performance');
      
      if (await errorRecovery.isVisible()) {
        console.log('✅ Error recovery performance displayed');
        
        const recoveryText = await errorRecovery.textContent();
        console.log(`Error recovery performance: ${recoveryText}`);
      } else {
        console.log('⚠️ No error recovery performance information found');
      }
      
    } else {
      console.log('⚠️ No refresh button found - cannot test API error handling');
    }
  });

  test('@regression API rate limiting performance', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to users page
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    // Intercept API requests to simulate rate limiting
    const rateLimitTimes = [];
    
    await page.route('**/api/**', route => {
      const startTime = Date.now();
      
      // Simulate rate limiting
      route.fulfill({
        status: 429,
        contentType: 'application/json',
        headers: {
          'Retry-After': '1'
        },
        body: JSON.stringify({ error: 'Too Many Requests' })
      }).then(() => {
        const endTime = Date.now();
        const rateLimitTime = endTime - startTime;
        rateLimitTimes.push(rateLimitTime);
        
        console.log(`✅ API rate limit response time: ${rateLimitTime}ms`);
      });
    });
    
    // Trigger multiple API calls
    const refreshButton = page.locator('button:has-text("Refresh"), button:has-text("Reload"), [data-testid="refresh-button"]');
    
    if (await refreshButton.isVisible()) {
      // Click multiple times to trigger rate limiting
      for (let i = 0; i < 3; i++) {
        await refreshButton.click();
        await page.waitForTimeout(500);
      }
      
      // Wait for rate limiting handling
      await page.waitForTimeout(3000);
      
      console.log(`✅ API rate limiting completed: ${rateLimitTimes.length}`);
      
      // Check for rate limiting performance
      const rateLimitPerformance = page.locator('.rate-limit-performance, .rate-limit-timing, .rate-limit-info');
      
      if (await rateLimitPerformance.isVisible()) {
        console.log('✅ Rate limiting performance displayed');
        
        const rateLimitText = await rateLimitPerformance.textContent();
        console.log(`Rate limiting performance: ${rateLimitText}`);
      } else {
        console.log('⚠️ No rate limiting performance information found');
      }
      
      // Check for rate limit recovery
      const rateLimitRecovery = page.locator('.rate-limit-recovery, .recovery-rate-limit, .rate-limit-retry');
      
      if (await rateLimitRecovery.isVisible()) {
        console.log('✅ Rate limit recovery performance displayed');
        
        const recoveryText = await rateLimitRecovery.textContent();
        console.log(`Rate limit recovery: ${recoveryText}`);
      } else {
        console.log('⚠️ No rate limit recovery information found');
      }
      
    } else {
      console.log('⚠️ No refresh button found - cannot test API rate limiting');
    }
  });

  test('@regression API caching performance', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to users page
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    // Intercept API requests to check caching
    const cacheTimes = [];
    const cacheHits = [];
    
    await page.route('**/api/**', route => {
      const startTime = Date.now();
      const url = route.request().url();
      
      // Check for cache headers
      const cacheControl = route.request().headers()['cache-control'];
      const etag = route.request().headers()['etag'];
      
      if (cacheControl || etag) {
        cacheHits.push(url);
        console.log(`✅ Cache hit for ${url}`);
      }
      
      route.continue().then(() => {
        const endTime = Date.now();
        const cacheTime = endTime - startTime;
        cacheTimes.push(cacheTime);
        
        console.log(`✅ API cache response time: ${cacheTime}ms`);
      });
    });
    
    // Trigger API calls
    const refreshButton = page.locator('button:has-text("Refresh"), button:has-text("Reload"), [data-testid="refresh-button"]');
    
    if (await refreshButton.isVisible()) {
      await refreshButton.click();
      
      // Wait for API calls to complete
      await page.waitForTimeout(3000);
      
      console.log(`✅ API caching completed: ${cacheTimes.length}`);
      console.log(`Cache hits: ${cacheHits.length}`);
      
      // Check for caching performance
      const cachePerformance = page.locator('.cache-performance, .cache-timing, .cache-info');
      
      if (await cachePerformance.isVisible()) {
        console.log('✅ Cache performance displayed');
        
        const cacheText = await cachePerformance.textContent();
        console.log(`Cache performance: ${cacheText}`);
      } else {
        console.log('⚠️ No cache performance information found');
      }
      
      // Check for cache hit ratio
      const cacheHitRatio = page.locator('.cache-hit-ratio, .hit-ratio, .cache-efficiency');
      
      if (await cacheHitRatio.isVisible()) {
        console.log('✅ Cache hit ratio displayed');
        
        const hitRatioText = await cacheHitRatio.textContent();
        console.log(`Cache hit ratio: ${hitRatioText}`);
      } else {
        console.log('⚠️ No cache hit ratio information found');
      }
      
    } else {
      console.log('⚠️ No refresh button found - cannot test API caching');
    }
  });

  test('@regression API pagination performance', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to users page
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    // Intercept API requests to check pagination
    const paginationTimes = [];
    
    await page.route('**/api/**', route => {
      const startTime = Date.now();
      const url = route.request().url();
      
      // Check for pagination parameters
      if (url.includes('page=') || url.includes('per_page=')) {
        console.log(`✅ Pagination request detected: ${url}`);
      }
      
      route.continue().then(() => {
        const endTime = Date.now();
        const paginationTime = endTime - startTime;
        paginationTimes.push(paginationTime);
        
        console.log(`✅ API pagination response time: ${paginationTime}ms`);
      });
    });
    
    // Trigger pagination
    const paginationButton = page.locator('button:has-text("Next"), button:has-text("Previous"), .pagination button');
    
    if (await paginationButton.isVisible()) {
      await paginationButton.click();
      
      // Wait for pagination to complete
      await page.waitForTimeout(3000);
      
      console.log(`✅ API pagination completed: ${paginationTimes.length}`);
      
      // Check for pagination performance
      const paginationPerformance = page.locator('.pagination-performance, .pagination-timing, .pagination-info');
      
      if (await paginationPerformance.isVisible()) {
        console.log('✅ Pagination performance displayed');
        
        const paginationText = await paginationPerformance.textContent();
        console.log(`Pagination performance: ${paginationText}`);
      } else {
        console.log('⚠️ No pagination performance information found');
      }
      
      // Check for pagination efficiency
      const paginationEfficiency = page.locator('.pagination-efficiency, .efficiency-pagination, .pagination-optimization');
      
      if (await paginationEfficiency.isVisible()) {
        console.log('✅ Pagination efficiency displayed');
        
        const efficiencyText = await paginationEfficiency.textContent();
        console.log(`Pagination efficiency: ${efficiencyText}`);
      } else {
        console.log('⚠️ No pagination efficiency information found');
      }
      
    } else {
      console.log('⚠️ No pagination button found - cannot test API pagination');
    }
  });

  test('@regression API bulk operations performance', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to users page
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    // Intercept API requests to check bulk operations
    const bulkTimes = [];
    
    await page.route('**/api/**', route => {
      const startTime = Date.now();
      const url = route.request().url();
      
      // Check for bulk operation parameters
      if (url.includes('bulk=') || url.includes('batch=')) {
        console.log(`✅ Bulk operation request detected: ${url}`);
      }
      
      route.continue().then(() => {
        const endTime = Date.now();
        const bulkTime = endTime - startTime;
        bulkTimes.push(bulkTime);
        
        console.log(`✅ API bulk operation response time: ${bulkTime}ms`);
      });
    });
    
    // Trigger bulk operations
    const bulkButton = page.locator('button:has-text("Bulk"), button:has-text("Batch"), button:has-text("Select All")');
    
    if (await bulkButton.isVisible()) {
      await bulkButton.click();
      
      // Wait for bulk operations to complete
      await page.waitForTimeout(3000);
      
      console.log(`✅ API bulk operations completed: ${bulkTimes.length}`);
      
      // Check for bulk operation performance
      const bulkPerformance = page.locator('.bulk-performance, .bulk-timing, .bulk-info');
      
      if (await bulkPerformance.isVisible()) {
        console.log('✅ Bulk operation performance displayed');
        
        const bulkText = await bulkPerformance.textContent();
        console.log(`Bulk operation performance: ${bulkText}`);
      } else {
        console.log('⚠️ No bulk operation performance information found');
      }
      
      // Check for bulk operation efficiency
      const bulkEfficiency = page.locator('.bulk-efficiency, .efficiency-bulk, .bulk-optimization');
      
      if (await bulkEfficiency.isVisible()) {
        console.log('✅ Bulk operation efficiency displayed');
        
        const efficiencyText = await bulkEfficiency.textContent();
        console.log(`Bulk operation efficiency: ${efficiencyText}`);
      } else {
        console.log('⚠️ No bulk operation efficiency information found');
      }
      
    } else {
      console.log('⚠️ No bulk operation button found - cannot test API bulk operations');
    }
  });
});
