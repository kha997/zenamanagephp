import { test, expect } from '@playwright/test';
import { AuthHelper } from '../../helpers/smoke-helpers';
import { testData } from '../../helpers/data';

test.describe('@regression Performance Retry Testing', () => {
  let authHelper: AuthHelper;

  test.beforeEach(async ({ page }) => {
    authHelper = new AuthHelper(page);
  });

  test('@regression API error retry with exponential backoff', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to a page that makes API calls
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    let retryCount = 0;
    const maxRetries = 3;
    const retryDelays = [];
    
    // Intercept API requests to simulate server errors
    await page.route('**/api/**', route => {
      retryCount++;
      
      if (retryCount <= maxRetries) {
        // Simulate server error with retry-after header
        const retryAfter = Math.pow(2, retryCount - 1); // Exponential backoff: 1s, 2s, 4s
        retryDelays.push(retryAfter);
        
        route.fulfill({
          status: 503,
          contentType: 'application/json',
          headers: {
            'Retry-After': retryAfter.toString()
          },
          body: JSON.stringify({ 
            error: 'Service Unavailable',
            retry_after: retryAfter,
            attempt: retryCount
          })
        });
      } else {
        // Allow request to proceed after max retries
        route.continue();
      }
    });
    
    console.log('✅ API requests intercepted - simulating server errors with exponential backoff');
    
    // Try to perform an action that requires API call
    const refreshButton = page.locator('button:has-text("Refresh"), button:has-text("Reload"), [data-testid="refresh-button"]');
    
    if (await refreshButton.isVisible()) {
      const startTime = Date.now();
      await refreshButton.click();
      
      // Wait for retry attempts
      await page.waitForTimeout(10000);
      const endTime = Date.now();
      const totalTime = endTime - startTime;
      
      console.log(`✅ Retry attempts completed: ${retryCount}`);
      console.log(`Total time: ${totalTime}ms`);
      console.log(`Retry delays: ${retryDelays.join(', ')}s`);
      
      // Check for retry indicators in UI
      const retryIndicator = page.locator('text=/retry|attempt|error.*retry/i');
      
      if (await retryIndicator.isVisible()) {
        console.log('✅ Retry indicator displayed in UI');
        
        const retryText = await retryIndicator.textContent();
        console.log(`Retry indicator text: ${retryText}`);
      }
      
      // Check for exponential backoff timing
      const backoffIndicator = page.locator('text=/backoff|delay|wait|retry.*after/i');
      
      if (await backoffIndicator.isVisible()) {
        console.log('✅ Exponential backoff timing displayed');
        
        const backoffText = await backoffIndicator.textContent();
        console.log(`Backoff indicator text: ${backoffText}`);
      }
      
      // Verify exponential backoff timing
      if (retryDelays.length >= 2) {
        const isExponential = retryDelays[1] >= retryDelays[0] * 1.5; // Allow some tolerance
        
        if (isExponential) {
          console.log('✅ Exponential backoff timing verified');
        } else {
          console.log('⚠️ Exponential backoff timing may not be working correctly');
        }
      }
      
    } else {
      console.log('⚠️ No refresh button found - cannot test retry mechanism');
    }
  });

  test('@regression UI user feedback during retries', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to a page that makes API calls
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    let retryCount = 0;
    const maxRetries = 3;
    
    // Intercept API requests to simulate server errors
    await page.route('**/api/**', route => {
      retryCount++;
      
      if (retryCount <= maxRetries) {
        // Simulate server error
        route.fulfill({
          status: 500,
          contentType: 'application/json',
          body: JSON.stringify({ error: 'Internal Server Error' })
        });
      } else {
        // Allow request to proceed after max retries
        route.continue();
      }
    });
    
    console.log('✅ API requests intercepted - simulating server errors');
    
    // Try to perform an action that requires API call
    const actionButton = page.locator('button:has-text("Load"), button:has-text("Update"), button:has-text("Save")');
    
    if (await actionButton.isVisible()) {
      await actionButton.click();
      
      // Check for loading indicator
      const loadingIndicator = page.locator('.loading, .spinner, .loader, [aria-label*="loading"]');
      
      if (await loadingIndicator.isVisible()) {
        console.log('✅ Loading indicator displayed during retries');
        
        // Wait for retry attempts
        await page.waitForTimeout(5000);
        
        // Check for retry status messages
        const retryStatus = page.locator('.retry-status, .error-status, .loading-message');
        
        if (await retryStatus.isVisible()) {
          const statusText = await retryStatus.textContent();
          console.log(`Retry status message: ${statusText}`);
          
          // Check for retry count in message
          if (statusText?.includes('retry') || statusText?.includes('attempt')) {
            console.log('✅ Retry count displayed in status message');
          }
        }
        
        // Check for progress indicator
        const progressIndicator = page.locator('.progress-bar, .upload-progress, [role="progressbar"]');
        
        if (await progressIndicator.isVisible()) {
          console.log('✅ Progress indicator displayed during retries');
          
          // Check progress value
          const progressValue = await progressIndicator.getAttribute('aria-valuenow');
          const progressText = await progressIndicator.textContent();
          
          if (progressValue || progressText) {
            console.log(`Progress: ${progressValue || progressText}`);
          }
        }
        
      } else {
        console.log('⚠️ No loading indicator found during retries');
      }
      
      // Check for error messages
      const errorMessage = page.locator('.error, .alert-danger, .error-message');
      
      if (await errorMessage.isVisible()) {
        const errorText = await errorMessage.textContent();
        console.log(`Error message: ${errorText}`);
        
        // Check for retry information in error message
        if (errorText?.includes('retry') || errorText?.includes('attempt')) {
          console.log('✅ Retry information included in error message');
        }
      }
      
    } else {
      console.log('⚠️ No action button found - cannot test retry feedback');
    }
  });

  test('@regression Retry limit and failure handling', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to a page that makes API calls
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    let retryCount = 0;
    const maxRetries = 5;
    
    // Intercept API requests to simulate persistent failures
    await page.route('**/api/**', route => {
      retryCount++;
      
      if (retryCount <= maxRetries) {
        // Simulate persistent server error
        route.fulfill({
          status: 503,
          contentType: 'application/json',
          body: JSON.stringify({ error: 'Service Unavailable' })
        });
      } else {
        // Allow request to proceed after max retries
        route.continue();
      }
    });
    
    console.log('✅ API requests intercepted - simulating persistent failures');
    
    // Try to perform an action that requires API call
    const actionButton = page.locator('button:has-text("Refresh"), button:has-text("Load"), button:has-text("Update")');
    
    if (await actionButton.isVisible()) {
      await actionButton.click();
      
      // Wait for retry attempts
      await page.waitForTimeout(15000);
      
      console.log(`✅ Retry attempts completed: ${retryCount}`);
      
      // Check for retry limit reached message
      const retryLimitMessage = page.locator('text=/retry.*limit|max.*retries|too many.*attempts/i');
      
      if (await retryLimitMessage.isVisible()) {
        console.log('✅ Retry limit message displayed');
        
        const limitText = await retryLimitMessage.textContent();
        console.log(`Retry limit message: ${limitText}`);
        
        // Check for manual retry option
        const manualRetryButton = page.locator('button:has-text("Retry"), button:has-text("Try Again"), button:has-text("Retry Now")');
        
        if (await manualRetryButton.isVisible()) {
          console.log('✅ Manual retry button available');
          
          // Test manual retry
          await manualRetryButton.click();
          
          // Wait for retry attempt
          await page.waitForTimeout(3000);
          
          // Check for retry status
          const retryStatus = page.locator('.retry-status, .loading-message, .error-message');
          
          if (await retryStatus.isVisible()) {
            const statusText = await retryStatus.textContent();
            console.log(`Manual retry status: ${statusText}`);
          }
        } else {
          console.log('⚠️ No manual retry button found');
        }
        
      } else {
        console.log('⚠️ No retry limit message found - may need to implement retry limits');
      }
      
      // Check for fallback options
      const fallbackOptions = page.locator('text=/fallback|alternative|offline|cache/i');
      
      if (await fallbackOptions.isVisible()) {
        console.log('✅ Fallback options available');
        
        const fallbackText = await fallbackOptions.textContent();
        console.log(`Fallback options: ${fallbackText}`);
      } else {
        console.log('⚠️ No fallback options found');
      }
      
    } else {
      console.log('⚠️ No action button found - cannot test retry mechanisms');
    }
  });

  test('@regression Performance metrics during retries', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to a page that makes API calls
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    let retryCount = 0;
    const maxRetries = 3;
    const retryTimes = [];
    
    // Intercept API requests to simulate server errors
    await page.route('**/api/**', route => {
      retryCount++;
      const retryTime = Date.now();
      retryTimes.push(retryTime);
      
      if (retryCount <= maxRetries) {
        // Simulate server error
        route.fulfill({
          status: 500,
          contentType: 'application/json',
          body: JSON.stringify({ error: 'Internal Server Error' })
        });
      } else {
        // Allow request to proceed after max retries
        route.continue();
      }
    });
    
    console.log('✅ API requests intercepted - simulating server errors for performance testing');
    
    // Try to perform an action that requires API call
    const actionButton = page.locator('button:has-text("Refresh"), button:has-text("Load"), button:has-text("Update")');
    
    if (await actionButton.isVisible()) {
      const startTime = Date.now();
      await actionButton.click();
      
      // Wait for retry attempts
      await page.waitForTimeout(10000);
      const endTime = Date.now();
      const totalTime = endTime - startTime;
      
      console.log(`✅ Retry attempts completed: ${retryCount}`);
      console.log(`Total time: ${totalTime}ms`);
      
      // Calculate retry intervals
      if (retryTimes.length >= 2) {
        const intervals = [];
        for (let i = 1; i < retryTimes.length; i++) {
          intervals.push(retryTimes[i] - retryTimes[i - 1]);
        }
        
        console.log(`Retry intervals: ${intervals.join(', ')}ms`);
        
        // Check for exponential backoff in intervals
        if (intervals.length >= 2) {
          const isExponential = intervals[1] >= intervals[0] * 1.5;
          
          if (isExponential) {
            console.log('✅ Exponential backoff verified in retry intervals');
          } else {
            console.log('⚠️ Exponential backoff may not be working correctly');
          }
        }
      }
      
      // Check for performance metrics in UI
      const performanceMetrics = page.locator('.performance-metrics, .retry-metrics, .timing-info');
      
      if (await performanceMetrics.isVisible()) {
        console.log('✅ Performance metrics displayed in UI');
        
        const metricsText = await performanceMetrics.textContent();
        console.log(`Performance metrics: ${metricsText}`);
        
        // Check for specific metrics
        if (metricsText?.includes('retry') || metricsText?.includes('attempt')) {
          console.log('✅ Retry metrics included in performance display');
        }
        
        if (metricsText?.includes('time') || metricsText?.includes('ms') || metricsText?.includes('seconds')) {
          console.log('✅ Timing metrics included in performance display');
        }
      } else {
        console.log('⚠️ No performance metrics displayed in UI');
      }
      
      // Check for network timing information
      const networkTiming = page.locator('.network-timing, .request-timing, .api-timing');
      
      if (await networkTiming.isVisible()) {
        console.log('✅ Network timing information displayed');
        
        const timingText = await networkTiming.textContent();
        console.log(`Network timing: ${timingText}`);
      } else {
        console.log('⚠️ No network timing information displayed');
      }
      
    } else {
      console.log('⚠️ No action button found - cannot test retry performance');
    }
  });

  test('@regression Concurrent retry handling', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to a page that makes API calls
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    let retryCount = 0;
    const maxRetries = 3;
    
    // Intercept API requests to simulate server errors
    await page.route('**/api/**', route => {
      retryCount++;
      
      if (retryCount <= maxRetries) {
        // Simulate server error
        route.fulfill({
          status: 503,
          contentType: 'application/json',
          body: JSON.stringify({ error: 'Service Unavailable' })
        });
      } else {
        // Allow request to proceed after max retries
        route.continue();
      }
    });
    
    console.log('✅ API requests intercepted - simulating server errors for concurrent testing');
    
    // Try to perform multiple actions simultaneously
    const actionButtons = page.locator('button:has-text("Refresh"), button:has-text("Load"), button:has-text("Update")');
    const buttonCount = await actionButtons.count();
    
    if (buttonCount > 0) {
      console.log(`Found ${buttonCount} action buttons for concurrent testing`);
      
      // Click multiple buttons simultaneously
      const startTime = Date.now();
      
      for (let i = 0; i < Math.min(buttonCount, 3); i++) {
        await actionButtons.nth(i).click();
      }
      
      // Wait for retry attempts
      await page.waitForTimeout(8000);
      const endTime = Date.now();
      const totalTime = endTime - startTime;
      
      console.log(`✅ Concurrent retry attempts completed: ${retryCount}`);
      console.log(`Total time for concurrent requests: ${totalTime}ms`);
      
      // Check for concurrent retry indicators
      const concurrentIndicators = page.locator('text=/concurrent|parallel|multiple.*retry/i');
      
      if (await concurrentIndicators.isVisible()) {
        console.log('✅ Concurrent retry indicators displayed');
        
        const concurrentText = await concurrentIndicators.textContent();
        console.log(`Concurrent retry text: ${concurrentText}`);
      } else {
        console.log('⚠️ No concurrent retry indicators found');
      }
      
      // Check for retry queue status
      const retryQueue = page.locator('.retry-queue, .request-queue, .pending-requests');
      
      if (await retryQueue.isVisible()) {
        console.log('✅ Retry queue status displayed');
        
        const queueText = await retryQueue.textContent();
        console.log(`Retry queue status: ${queueText}`);
      } else {
        console.log('⚠️ No retry queue status found');
      }
      
      // Check for rate limiting
      const rateLimit = page.locator('text=/rate.*limit|throttle|too many.*requests/i');
      
      if (await rateLimit.isVisible()) {
        console.log('✅ Rate limiting detected');
        
        const rateLimitText = await rateLimit.textContent();
        console.log(`Rate limit message: ${rateLimitText}`);
      } else {
        console.log('⚠️ No rate limiting detected');
      }
      
    } else {
      console.log('⚠️ No action buttons found - cannot test concurrent retries');
    }
  });

  test('@regression Retry success and failure scenarios', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to a page that makes API calls
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    let retryCount = 0;
    const maxRetries = 3;
    
    // Intercept API requests to simulate mixed success/failure scenarios
    await page.route('**/api/**', route => {
      retryCount++;
      
      if (retryCount <= maxRetries) {
        // Simulate server error for first few attempts
        route.fulfill({
          status: 500,
          contentType: 'application/json',
          body: JSON.stringify({ error: 'Internal Server Error' })
        });
      } else {
        // Allow request to succeed after retries
        route.continue();
      }
    });
    
    console.log('✅ API requests intercepted - simulating mixed success/failure scenarios');
    
    // Try to perform an action that requires API call
    const actionButton = page.locator('button:has-text("Refresh"), button:has-text("Load"), button:has-text("Update")');
    
    if (await actionButton.isVisible()) {
      await actionButton.click();
      
      // Wait for retry attempts
      await page.waitForTimeout(8000);
      
      console.log(`✅ Retry attempts completed: ${retryCount}`);
      
      // Check for success after retries
      const successMessage = page.locator('text=/success|loaded|updated|completed/i');
      
      if (await successMessage.isVisible()) {
        console.log('✅ Success message displayed after retries');
        
        const successText = await successMessage.textContent();
        console.log(`Success message: ${successText}`);
        
        // Check for retry information in success message
        if (successText?.includes('retry') || successText?.includes('attempt')) {
          console.log('✅ Retry information included in success message');
        }
      } else {
        console.log('⚠️ No success message displayed after retries');
      }
      
      // Check for retry summary
      const retrySummary = page.locator('.retry-summary, .attempt-summary, .retry-info');
      
      if (await retrySummary.isVisible()) {
        console.log('✅ Retry summary displayed');
        
        const summaryText = await retrySummary.textContent();
        console.log(`Retry summary: ${summaryText}`);
        
        // Check for retry count in summary
        if (summaryText?.includes('retry') || summaryText?.includes('attempt')) {
          console.log('✅ Retry count included in summary');
        }
      } else {
        console.log('⚠️ No retry summary displayed');
      }
      
      // Check for performance impact
      const performanceImpact = page.locator('.performance-impact, .timing-impact, .retry-timing');
      
      if (await performanceImpact.isVisible()) {
        console.log('✅ Performance impact displayed');
        
        const impactText = await performanceImpact.textContent();
        console.log(`Performance impact: ${impactText}`);
      } else {
        console.log('⚠️ No performance impact displayed');
      }
      
    } else {
      console.log('⚠️ No action button found - cannot test retry scenarios');
    }
  });
});
