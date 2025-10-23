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
    // Wait for page to load and user menu to be available
    await this.page.waitForLoadState('networkidle');
    
    // Try multiple selectors for user menu
    const userMenuSelectors = [
      '[data-testid="user-menu-toggle"]',
      'button[\\@click="showUserMenu = !showUserMenu"]',
      'button:has-text("Super Admin")',
      '.flex.items-center.space-x-2'
    ];
    
    let userMenuFound = false;
    for (const selector of userMenuSelectors) {
      try {
        await this.page.waitForSelector(selector, { timeout: 2000 });
        await this.page.click(selector);
        userMenuFound = true;
        break;
      } catch (e) {
        console.log(`Selector ${selector} not found, trying next...`);
      }
    }
    
    if (!userMenuFound) {
      throw new Error('User menu button not found with any selector');
    }
    
    // Wait for dropdown to be visible
    await this.page.waitForSelector('[data-testid="user-menu-dropdown"]', { state: 'visible' });
    
    // Click logout link
    await this.page.click('[data-testid="logout-link"]');
    
    // Wait for redirect to login
    await this.page.waitForURL(/\/login/);
  }

  async isLoggedIn(): Promise<boolean> {
    try {
      const currentUrl = this.page.url();
      console.log('Current URL for isLoggedIn check:', currentUrl);
      return currentUrl.includes('/app') || currentUrl.includes('/dashboard');
    } catch {
      return false;
    }
  }
}
