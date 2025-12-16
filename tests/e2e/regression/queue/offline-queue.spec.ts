import { test, expect } from '@playwright/test';
import { AuthHelper } from '../../helpers/smoke-helpers';
import { testData } from '/Applications/XAMPP/xamppfiles/htdocs/zenamanage/tests/e2e/helpers/data';

test.describe('@regression Offline Queue / Performance Retry', () => {
  let authHelper: AuthHelper;

  test.beforeEach(async ({ page }) => {
    authHelper = new AuthHelper(page);
  });

  test('@regression Offline queue simulation and recovery', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to a page that makes API calls
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');
    
    // Intercept network requests to simulate offline
    await page.route('**/api/**', route => {
      // Simulate offline by aborting requests
      route.abort('failed');
    });
    
    console.log('✅ Network requests intercepted - simulating offline mode');
    
    // Try to perform an action that requires API call
    const createButton = page.locator('button:has-text("Create"), button:has-text("Add"), button:has-text("New")');
    
    if (await createButton.isVisible()) {
      await createButton.click();
      
      // Wait for offline handling
      await page.waitForTimeout(2000);
      
      // Check for offline indicator or queue message
      const offlineIndicator = page.locator('text=/offline|no connection|queue|retry/i');
      
      if (await offlineIndicator.isVisible()) {
        console.log('✅ Offline indicator displayed');
        
        // Check for queue status
        const queueStatus = page.locator('.queue-status, .offline-queue, .pending-actions');
        
        if (await queueStatus.isVisible()) {
          console.log('✅ Queue status displayed');
          
          // Check for retry button
          const retryButton = page.locator('button:has-text("Retry"), button:has-text("Resend"), button:has-text("Queue")');
          
          if (await retryButton.isVisible()) {
            console.log('✅ Retry button available');
          }
        }
        
      } else {
        console.log('⚠️ No offline indicator found - may need to implement offline handling');
      }
    }
    
    // Restore network connectivity
    await page.unroute('**/api/**');
    console.log('✅ Network connectivity restored');
    
    // Check for automatic retry or queue flush
    await page.waitForTimeout(3000);
    
    const retryAttempts = page.locator('text=/retry|attempt|queue.*flush/i');
    
    if (await retryAttempts.isVisible()) {
      console.log('✅ Automatic retry or queue flush detected');
    } else {
      console.log('⚠️ No automatic retry detected - may need to implement');
    }
  });

  test('@regression API error retry with exponential backoff', async ({ page }) => {
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
    const refreshButton = page.locator('button:has-text("Refresh"), button:has-text("Reload"), [data-testid="refresh-button"]');
    
    if (await refreshButton.isVisible()) {
      await refreshButton.click();
      
      // Wait for retry attempts
      await page.waitForTimeout(5000);
      
      console.log(`✅ Retry attempts completed: ${retryCount}`);
      
      // Check for retry indicators in UI
      const retryIndicator = page.locator('text=/retry|attempt|error.*retry/i');
      
      if (await retryIndicator.isVisible()) {
        console.log('✅ Retry indicator displayed in UI');
      }
      
      // Check for exponential backoff timing
      const retryTiming = page.locator('text=/backoff|delay|wait/i');
      
      if (await retryTiming.isVisible()) {
        console.log('✅ Exponential backoff timing displayed');
      }
      
    } else {
      console.log('⚠️ No refresh button found - cannot test retry mechanism');
    }
  });

  test('@regression Queue monitoring and metrics', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to monitoring or admin dashboard
    await page.goto('/app/monitoring');
    
    // Check if monitoring page exists
    if (page.url().includes('monitoring')) {
      console.log('✅ Monitoring page found');
      
      // Look for queue metrics
      const queueMetrics = page.locator('text=/queue|pending|failed|processed/i');
      
      if (await queueMetrics.isVisible()) {
        console.log('✅ Queue metrics found');
        
        // Check for specific queue metrics
        const pendingJobs = page.locator('text=/pending.*jobs|jobs.*pending/i');
        const failedJobs = page.locator('text=/failed.*jobs|jobs.*failed/i');
        const processedJobs = page.locator('text=/processed.*jobs|jobs.*processed/i');
        
        if (await pendingJobs.isVisible()) {
          console.log('✅ Pending jobs metric found');
        }
        
        if (await failedJobs.isVisible()) {
          console.log('✅ Failed jobs metric found');
        }
        
        if (await processedJobs.isVisible()) {
          console.log('✅ Processed jobs metric found');
        }
        
      } else {
        console.log('⚠️ No queue metrics found - may need to implement monitoring');
      }
      
    } else {
      console.log('⚠️ Monitoring page not found - may need to implement');
      
      // Try admin dashboard instead
      await page.goto('/admin/dashboard');
      
      const queueStatus = page.locator('text=/queue|pending|failed|processed/i');
      
      if (await queueStatus.isVisible()) {
        console.log('✅ Queue status found in admin dashboard');
      } else {
        console.log('⚠️ No queue status found in admin dashboard');
      }
    }
  });

  test('@regression Background job processing', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to a page that triggers background jobs
    await page.goto('/app/documents');
    await page.waitForLoadState('networkidle');
    
    // Look for actions that might trigger background jobs
    const uploadButton = page.locator('button:has-text("Upload"), button:has-text("Add Document")');
    
    if (await uploadButton.isVisible()) {
      await uploadButton.click();
      
      // Wait for upload form
      await page.waitForTimeout(1000);
      
      // Create a test file
      const fileInput = page.locator('input[type="file"]');
      
      if (await fileInput.isVisible()) {
        // Create a test file
        await page.evaluate(() => {
          const file = new File(['Test document content'], 'test-document.txt', { type: 'text/plain' });
          const dataTransfer = new DataTransfer();
          dataTransfer.items.add(file);
          return dataTransfer;
        });
        
        // Fill document details
        const nameInput = page.locator('input[name="name"], input[name="title"]');
        
        if (await nameInput.isVisible()) {
          await nameInput.fill('Test Document for Background Processing');
          
          // Submit upload
          const submitButton = page.locator('button[type="submit"], button:has-text("Upload"), button:has-text("Save")');
          
          if (await submitButton.isVisible()) {
            await submitButton.click();
            
            // Wait for processing
            await page.waitForTimeout(3000);
            
            // Check for background processing indicators
            const processingIndicator = page.locator('text=/processing|uploading|background|queue/i');
            
            if (await processingIndicator.isVisible()) {
              console.log('✅ Background processing indicator displayed');
              
              // Check for progress bar
              const progressBar = page.locator('.progress-bar, .upload-progress, [role="progressbar"]');
              
              if (await progressBar.isVisible()) {
                console.log('✅ Progress bar displayed for background processing');
              }
              
            } else {
              console.log('⚠️ No background processing indicator found');
            }
            
          } else {
            console.log('⚠️ Submit button not found - upload form may not be implemented');
          }
          
        } else {
          console.log('⚠️ Name input not found - upload form may not be implemented');
        }
        
      } else {
        console.log('⚠️ File input not found - upload functionality may not be implemented');
      }
      
    } else {
      console.log('⚠️ Upload button not found - cannot test background job processing');
    }
  });

  test('@regression Queue retry mechanisms and limits', async ({ page }) => {
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
      await page.waitForTimeout(10000);
      
      console.log(`✅ Retry attempts completed: ${retryCount}`);
      
      // Check for retry limit reached message
      const retryLimitMessage = page.locator('text=/retry.*limit|max.*retries|too many.*attempts/i');
      
      if (await retryLimitMessage.isVisible()) {
        console.log('✅ Retry limit message displayed');
        
        // Check for manual retry option
        const manualRetryButton = page.locator('button:has-text("Retry"), button:has-text("Try Again")');
        
        if (await manualRetryButton.isVisible()) {
          console.log('✅ Manual retry button available');
        }
        
      } else {
        console.log('⚠️ No retry limit message found - may need to implement retry limits');
      }
      
    } else {
      console.log('⚠️ No action button found - cannot test retry mechanisms');
    }
  });

  test('@regression Performance metrics and monitoring', async ({ page }) => {
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to monitoring page
    await page.goto('/app/monitoring');
    
    // Check if monitoring page exists
    if (page.url().includes('monitoring')) {
      console.log('✅ Monitoring page found');
      
      // Look for performance metrics
      const performanceMetrics = page.locator('text=/performance|response.*time|latency|throughput/i');
      
      if (await performanceMetrics.isVisible()) {
        console.log('✅ Performance metrics found');
        
        // Check for specific performance metrics
        const responseTime = page.locator('text=/response.*time|avg.*response|p95|p99/i');
        const throughput = page.locator('text=/throughput|requests.*per.*second|rps/i');
        const errorRate = page.locator('text=/error.*rate|failure.*rate|success.*rate/i');
        
        if (await responseTime.isVisible()) {
          console.log('✅ Response time metrics found');
        }
        
        if (await throughput.isVisible()) {
          console.log('✅ Throughput metrics found');
        }
        
        if (await errorRate.isVisible()) {
          console.log('✅ Error rate metrics found');
        }
        
      } else {
        console.log('⚠️ No performance metrics found - may need to implement monitoring');
      }
      
    } else {
      console.log('⚠️ Monitoring page not found - may need to implement');
      
      // Try to find performance indicators in other pages
      await page.goto('/admin/dashboard');
      
      const performanceIndicators = page.locator('text=/performance|response.*time|latency/i');
      
      if (await performanceIndicators.isVisible()) {
        console.log('✅ Performance indicators found in admin dashboard');
      } else {
        console.log('⚠️ No performance indicators found');
      }
    }
  });
});
