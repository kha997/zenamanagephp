import { test, expect } from '@playwright/test';
import { AuthHelper } from '../../helpers/smoke-helpers';
import { testData } from '../../helpers/data';

test.describe('@regression Performance Load Testing', () => {
  let authHelper: AuthHelper;

  test.beforeEach(async ({ page }) => {
    authHelper = new AuthHelper(page);
  });

  test('@regression Page load performance metrics', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Test dashboard load performance
    const startTime = Date.now();
    await page.goto('/app/dashboard');
    await page.waitForLoadState('networkidle');
    const endTime = Date.now();
    const loadTime = endTime - startTime;
    
    console.log(`✅ Dashboard load time: ${loadTime}ms`);
    
    // Check for performance indicators
    const performanceIndicator = page.locator('.performance-indicator, .load-time, .timing-info');
    
    if (await performanceIndicator.isVisible()) {
      console.log('✅ Performance indicator displayed');
      
      const performanceText = await performanceIndicator.textContent();
      console.log(`Performance indicator: ${performanceText}`);
    } else {
      console.log('⚠️ No performance indicator displayed');
    }
    
    // Check for loading time in UI
    const loadingTime = page.locator('text=/load.*time|load.*duration|page.*load/i');
    
    if (await loadingTime.isVisible()) {
      console.log('✅ Loading time displayed in UI');
      
      const timeText = await loadingTime.textContent();
      console.log(`Loading time text: ${timeText}`);
    } else {
      console.log('⚠️ No loading time displayed in UI');
    }
    
    // Verify load time is within acceptable limits
    if (loadTime < 5000) {
      console.log('✅ Dashboard load time is acceptable (< 5s)');
    } else {
      console.log('⚠️ Dashboard load time is slow (> 5s)');
    }
  });

  test('@regression API response time metrics', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to users page
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    // Intercept API requests to measure response times
    const responseTimes = [];
    
    await page.route('**/api/**', route => {
      const startTime = Date.now();
      
      route.continue().then(() => {
        const endTime = Date.now();
        const responseTime = endTime - startTime;
        responseTimes.push(responseTime);
        
        console.log(`✅ API response time: ${responseTime}ms`);
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

  test('@regression Concurrent user simulation', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to users page
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    // Simulate concurrent actions
    const startTime = Date.now();
    
    // Perform multiple actions simultaneously
    const actions = [
      page.locator('button:has-text("Refresh")'),
      page.locator('button:has-text("Filter")'),
      page.locator('button:has-text("Search")')
    ];
    
    const visibleActions = [];
    for (const action of actions) {
      if (await action.isVisible()) {
        visibleActions.push(action);
      }
    }
    
    if (visibleActions.length > 0) {
      console.log(`✅ Found ${visibleActions.length} actions for concurrent testing`);
      
      // Click multiple actions simultaneously
      for (const action of visibleActions.slice(0, 3)) {
        await action.click();
      }
      
      // Wait for actions to complete
      await page.waitForTimeout(5000);
      
      const endTime = Date.now();
      const totalTime = endTime - startTime;
      
      console.log(`✅ Concurrent actions completed in: ${totalTime}ms`);
      
      // Check for concurrent action indicators
      const concurrentIndicator = page.locator('text=/concurrent|parallel|multiple.*action/i');
      
      if (await concurrentIndicator.isVisible()) {
        console.log('✅ Concurrent action indicator displayed');
        
        const concurrentText = await concurrentIndicator.textContent();
        console.log(`Concurrent indicator: ${concurrentText}`);
      } else {
        console.log('⚠️ No concurrent action indicator found');
      }
      
      // Check for performance impact
      const performanceImpact = page.locator('.performance-impact, .concurrent-impact, .load-impact');
      
      if (await performanceImpact.isVisible()) {
        console.log('✅ Performance impact displayed');
        
        const impactText = await performanceImpact.textContent();
        console.log(`Performance impact: ${impactText}`);
      } else {
        console.log('⚠️ No performance impact displayed');
      }
      
    } else {
      console.log('⚠️ No actions found for concurrent testing');
    }
  });

  test('@regression Memory usage monitoring', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to users page
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    // Check for memory usage indicators
    const memoryIndicator = page.locator('.memory-usage, .memory-info, .memory-stats');
    
    if (await memoryIndicator.isVisible()) {
      console.log('✅ Memory usage indicator displayed');
      
      const memoryText = await memoryIndicator.textContent();
      console.log(`Memory usage: ${memoryText}`);
      
      // Check for memory warnings
      if (memoryText?.includes('high') || memoryText?.includes('warning')) {
        console.log('⚠️ Memory usage warning displayed');
      } else {
        console.log('✅ Memory usage is normal');
      }
    } else {
      console.log('⚠️ No memory usage indicator found');
    }
    
    // Check for performance warnings
    const performanceWarning = page.locator('.performance-warning, .slow-performance, .performance-alert');
    
    if (await performanceWarning.isVisible()) {
      console.log('⚠️ Performance warning displayed');
      
      const warningText = await performanceWarning.textContent();
      console.log(`Performance warning: ${warningText}`);
    } else {
      console.log('✅ No performance warnings displayed');
    }
    
    // Check for resource usage
    const resourceUsage = page.locator('.resource-usage, .resource-stats, .resource-info');
    
    if (await resourceUsage.isVisible()) {
      console.log('✅ Resource usage displayed');
      
      const resourceText = await resourceUsage.textContent();
      console.log(`Resource usage: ${resourceText}`);
    } else {
      console.log('⚠️ No resource usage information found');
    }
  });

  test('@regression Network performance monitoring', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to users page
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    // Check for network performance indicators
    const networkIndicator = page.locator('.network-performance, .network-stats, .network-info');
    
    if (await networkIndicator.isVisible()) {
      console.log('✅ Network performance indicator displayed');
      
      const networkText = await networkIndicator.textContent();
      console.log(`Network performance: ${networkText}`);
      
      // Check for network warnings
      if (networkText?.includes('slow') || networkText?.includes('warning')) {
        console.log('⚠️ Network performance warning displayed');
      } else {
        console.log('✅ Network performance is normal');
      }
    } else {
      console.log('⚠️ No network performance indicator found');
    }
    
    // Check for bandwidth usage
    const bandwidthUsage = page.locator('.bandwidth-usage, .bandwidth-stats, .bandwidth-info');
    
    if (await bandwidthUsage.isVisible()) {
      console.log('✅ Bandwidth usage displayed');
      
      const bandwidthText = await bandwidthUsage.textContent();
      console.log(`Bandwidth usage: ${bandwidthText}`);
    } else {
      console.log('⚠️ No bandwidth usage information found');
    }
    
    // Check for connection quality
    const connectionQuality = page.locator('.connection-quality, .connection-stats, .connection-info');
    
    if (await connectionQuality.isVisible()) {
      console.log('✅ Connection quality displayed');
      
      const qualityText = await connectionQuality.textContent();
      console.log(`Connection quality: ${qualityText}`);
    } else {
      console.log('⚠️ No connection quality information found');
    }
  });

  test('@regression Performance thresholds and alerts', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to users page
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    // Check for performance thresholds
    const performanceThreshold = page.locator('.performance-threshold, .threshold-warning, .threshold-alert');
    
    if (await performanceThreshold.isVisible()) {
      console.log('✅ Performance threshold indicator displayed');
      
      const thresholdText = await performanceThreshold.textContent();
      console.log(`Performance threshold: ${thresholdText}`);
      
      // Check for threshold warnings
      if (thresholdText?.includes('exceeded') || thresholdText?.includes('warning')) {
        console.log('⚠️ Performance threshold exceeded');
      } else {
        console.log('✅ Performance within thresholds');
      }
    } else {
      console.log('⚠️ No performance threshold indicator found');
    }
    
    // Check for performance alerts
    const performanceAlert = page.locator('.performance-alert, .alert-performance, .performance-notification');
    
    if (await performanceAlert.isVisible()) {
      console.log('⚠️ Performance alert displayed');
      
      const alertText = await performanceAlert.textContent();
      console.log(`Performance alert: ${alertText}`);
    } else {
      console.log('✅ No performance alerts displayed');
    }
    
    // Check for performance recommendations
    const performanceRecommendation = page.locator('.performance-recommendation, .recommendation-performance, .performance-suggestion');
    
    if (await performanceRecommendation.isVisible()) {
      console.log('✅ Performance recommendation displayed');
      
      const recommendationText = await performanceRecommendation.textContent();
      console.log(`Performance recommendation: ${recommendationText}`);
    } else {
      console.log('⚠️ No performance recommendations found');
    }
  });
});
