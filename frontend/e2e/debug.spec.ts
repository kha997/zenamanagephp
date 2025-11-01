import { test, expect } from '@playwright/test';

test.describe('Debug Staging Environment', () => {
  test('debug React app loading', async ({ page }) => {
    // Listen to console messages
    const consoleMessages: string[] = [];
    page.on('console', msg => {
      consoleMessages.push(`${msg.type()}: ${msg.text()}`);
    });

    // Listen to network requests
    const networkRequests: string[] = [];
    page.on('request', request => {
      networkRequests.push(`>> ${request.method()} ${request.url()}`);
    });
    page.on('response', response => {
      networkRequests.push(`<< ${response.status()} ${response.url()}`);
    });

    // Navigate to React app
    await page.goto('/');
    
    // Wait for page to load
    await page.waitForLoadState('networkidle');
    
    // Take screenshot for visual debugging
    await page.screenshot({ path: 'debug-staging.png', fullPage: true });
    
    // Log console messages
    console.log('=== CONSOLE MESSAGES ===');
    consoleMessages.forEach(msg => console.log(msg));
    
    // Log network requests
    console.log('=== NETWORK REQUESTS ===');
    networkRequests.forEach(req => console.log(req));
    
    // Check if root div exists
    const rootDiv = await page.locator('#root');
    const rootExists = await rootDiv.count() > 0;
    console.log('Root div exists:', rootExists);
    
    // Check if React has mounted anything
    const rootContent = await rootDiv.textContent();
    console.log('Root content:', rootContent);
    
    // Check page title
    const title = await page.title();
    console.log('Page title:', title);
    
    // Check if any React components are rendered
    const hasReactContent = await page.locator('text=Welcome back').count() > 0;
    console.log('Has React content:', hasReactContent);
    
    // Basic assertion to keep test passing
    expect(title).toContain('ZENA Manage');
  });
});
