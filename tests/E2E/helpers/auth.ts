import { Page, expect } from '@playwright/test';

export class MinimalAuthHelper {
  constructor(private page: Page) {}

  async login(email: string, password: string): Promise<void> {
    // Navigate to login page and wait for it to be ready
    await this.page.goto('/login', { waitUntil: 'networkidle', timeout: 30000 });
    
    // Wait for email input to be visible (with longer timeout for CI)
    await this.page.waitForSelector('#email', { state: 'visible', timeout: 15000 });
    
    // Fill in credentials
    await this.page.fill('#email', email);
    await this.page.fill('#password', password);
    
    // Click submit and wait for universal logged-in marker
    await Promise.all([
      this.page.waitForSelector('[data-testid="user-menu"]', { timeout: 15000 }),
      this.page.click('#loginButton')
    ]);
  }

  async logout(): Promise<void> {
    // Wait for universal logged-in marker
    await this.page.waitForSelector('[data-testid="user-menu"]', { timeout: 10000 });
    
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
      // Wait for universal logged-in marker
      await this.page.waitForSelector('[data-testid="user-menu"]', { timeout: 5000 });
      return true;
    } catch (error) {
      return false;
    }
  }
}
