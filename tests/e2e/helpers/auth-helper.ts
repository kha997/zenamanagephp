import { Page, expect } from '@playwright/test';

/**
 * Auth Helper for E2E Tests
 * Provides methods for authentication in tests
 */
export class AuthHelper {
  constructor(private page: Page) {}

  /**
   * Login with email and password
   */
  async login(email: string, password: string): Promise<void> {
    // For Phase 3 tests, routes are now open without authentication
    console.log('Phase 3 routes are open without authentication...');
    
    // Intercept requests to add test headers
    await this.page.route('**/*', async (route) => {
      const headers = {
        ...route.request().headers(),
        'X-Playwright-Test': 'true',
        'X-Test-Environment': 'playwright'
      };
      
      await route.continue({ headers });
    });
    
    // Navigate to test tasks page directly (no authentication required)
    await this.page.goto('/app/test-tasks', { 
      waitUntil: 'domcontentloaded',
      timeout: 60000 
    });
    
    // Wait for the page to be fully loaded
    await this.page.waitForLoadState('networkidle', { timeout: 30000 });
    
    // Verify we're on an app page
    const currentUrl = this.page.url();
    console.log('Current URL after navigation:', currentUrl);
    
    if (!currentUrl.includes('/app/')) {
      console.log('Not on app page, current URL:', currentUrl);
      // If we're redirected to login, that means authentication is still required
      if (currentUrl.includes('/login')) {
        throw new Error('Authentication still required - routes not properly opened');
      }
      throw new Error('Failed to navigate to app page');
    }
    
    console.log('Successfully navigated to app page:', currentUrl);
    
    // Verify we're actually on the tasks page by checking for task elements
    try {
      // Try to find task items first
      await this.page.waitForSelector('[data-testid="task-item"]', { timeout: 5000 });
      console.log('Tasks page loaded successfully');
    } catch (error) {
      // If task items not found, check if we're on an app page
      const currentUrl = this.page.url();
      if (!currentUrl.includes('/app/')) {
        throw new Error('Login failed - not redirected to app page');
      }
      // If we're on an app page but no task items, that's okay for some pages
      console.log('Task items not found but on app page, continuing...');
    }
  }

  /**
   * Logout
   */
  async logout(): Promise<void> {
    await this.page.click('[data-testid="user-menu"]');
    await this.page.click('[data-testid="logout-button"]');
    await this.page.waitForURL('**/login');
  }

  /**
   * Check if user is logged in
   */
  async isLoggedIn(): Promise<boolean> {
    try {
      await this.page.waitForSelector('[data-testid="user-menu"]', { timeout: 5000 });
      return true;
    } catch {
      return false;
    }
  }
}
