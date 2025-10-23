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
    // Click user menu to open dropdown
    await this.page.click('button[\\@click="showUserMenu = !showUserMenu"]');
    // Click logout link
    await this.page.click('a:has-text("Logout")');
    // Wait for redirect to login
    await this.page.waitForURL(/\/login/);
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
