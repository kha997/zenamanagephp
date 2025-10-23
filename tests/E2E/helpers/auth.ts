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
    await this.page.waitForSelector('h1, h2, [data-testid="dashboard"], .dashboard', { timeout: 10000 });
    
    // Try deterministic selectors for user menu
    const userMenuSelectors = [
      '.header-user-menu button',
      '[data-testid="user-menu"] button',
      '[data-testid="user-menu-toggle"]',
      'button[\\@click="open = !open"]'
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
    await this.page.waitForSelector('.header-dropdown', { state: 'visible' });
    
    // Click logout link
    await this.page.click('button[type="submit"]');
    
    // Wait for redirect to login
    await this.page.waitForURL(/\/login/);
  }

  async isLoggedIn(): Promise<boolean> {
    try {
      // Wait for dashboard to be fully loaded
      await this.page.waitForURL(/\/app\/dashboard/, { timeout: 5000 });
      return true;
    } catch {
      return false;
    }
  }
}
