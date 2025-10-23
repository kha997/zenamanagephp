import { Page, expect } from '@playwright/test';

export class MinimalAuthHelper {
  constructor(private page: Page) {}

  async login(email: string, password: string): Promise<void> {
    await this.page.goto('/login');
    await this.page.fill('#email', email);
    await this.page.fill('#password', password);
    
    // Click submit and wait for navigation
    await Promise.all([
      this.page.waitForURL(/\/app/),
      this.page.click('#loginButton')
    ]);
  }

  async logout(): Promise<void> {
    // Wait for dashboard to be fully loaded by checking for a known element
    await this.page.waitForSelector('[data-testid="dashboard-nav"], [data-testid="dashboard"], .dashboard-content', { timeout: 10000 });
    
    // Try deterministic selectors for user menu
    const userMenuSelectors = [
      '[data-testid="user-menu"] button',
      'button[data-testid="user-menu-toggle"]'
    ];
    
    let userMenuFound = false;
    for (const selector of userMenuSelectors) {
      try {
        await this.page.waitForSelector(selector, { timeout: 3000 });
        await this.page.click(selector);
        userMenuFound = true;
        break;
      } catch (e) {
        // Try next selector
      }
    }
    
    if (!userMenuFound) {
      throw new Error('User menu button not found with deterministic selectors');
    }
    
    // Wait for dropdown to be visible
    await this.page.waitForSelector('[data-testid="user-menu-dropdown"]', { state: 'visible' });
    
    // Click logout form submit button specifically
    await this.page.click('[data-testid="logout-link"]');
    
    // Wait for redirect to login
    await this.page.waitForURL(/\/login/);
  }

  async isLoggedIn(): Promise<boolean> {
    try {
      // Wait for any /app/* destination with retry and debug
      const currentUrl = this.page.url();
      console.log('Current URL before waitForURL:', currentUrl);
      
      await this.page.waitForURL(/\/app\//, { timeout: 5000 });
      
      const finalUrl = this.page.url();
      console.log('Final URL after waitForURL:', finalUrl);
      
      return true;
    } catch (error) {
      console.log('isLoggedIn failed:', error.message);
      return false;
    }
  }
}
