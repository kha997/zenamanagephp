import { Page, expect } from '@playwright/test';

export class MinimalAuthHelper {
  constructor(private page: Page) {}

  async login(email: string, password: string): Promise<void> {
    await this.page.goto('/login');
    await this.page.fill('#email', email);
    await this.page.fill('#password', password);
    await this.page.click('#loginForm button[type="submit"]');
    await this.page.waitForTimeout(2000); // Wait for redirect
    // Check if we're redirected to app or still on login (indicating failure)
    const currentUrl = this.page.url();
    if (currentUrl.includes('/login')) {
      throw new Error('Login failed - still on login page');
    }
  }

  async logout(): Promise<void> {
    await this.page.click('button:has-text("Logout")');
    await expect(this.page).toHaveURL(/\/login/);
  }

  async isLoggedIn(): Promise<boolean> {
    try {
      await this.page.waitForURL(/\/app/, { timeout: 2000 });
      return true;
    } catch {
      return false;
    }
  }
}
