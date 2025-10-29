import { Page, expect } from '@playwright/test';

export class MinimalAuthHelper {
  constructor(private page: Page) {}

  async login(email: string, password: string): Promise<void> {
    try {
      // Navigate to login page and wait for it to be ready
      await this.page.goto('/login', { waitUntil: 'domcontentloaded', timeout: 30000 });
      
      // Log page URL and title for debugging
      const url = this.page.url();
      const title = await this.page.title();
      console.log(`[Auth Helper] Navigated to: ${url}, Title: ${title}`);
      
      // Wait for email input to be visible (with longer timeout for CI)
      await this.page.waitForSelector('#email', { state: 'visible', timeout: 20000 }).catch(async (error) => {
        // Debug: take screenshot and log page content if selector not found
        console.error(`[Auth Helper] #email selector not found on page: ${url}`);
        console.error(`[Auth Helper] Page title: ${title}`);
        
        // Try to find alternative selectors
        const pageContent = await this.page.content();
        const hasEmailInput = pageContent.includes('email') || pageContent.includes('Email');
        console.log(`[Auth Helper] Page contains 'email': ${hasEmailInput}`);
        
        // List all input fields on page
        const inputs = await this.page.$$eval('input', (elements) => 
          elements.map(el => ({
            id: el.id,
            name: el.getAttribute('name'),
            type: el.type,
            placeholder: el.getAttribute('placeholder')
          }))
        ).catch(() => []);
        console.log(`[Auth Helper] Input fields found:`, JSON.stringify(inputs, null, 2));
        
        throw error;
      });
      
      // Fill in credentials
      await this.page.fill('#email', email);
      await this.page.fill('#password', password);
      
      // Wait for login button to be ready
      await this.page.waitForSelector('#loginButton', { state: 'visible', timeout: 10000 });
      
      // Click submit and wait for universal logged-in marker
      await Promise.all([
        this.page.waitForSelector('[data-testid="user-menu"]', { timeout: 20000 }),
        this.page.click('#loginButton')
      ]);
    } catch (error) {
      // Take screenshot on error for debugging
      await this.page.screenshot({ path: `test-results/login-error-${Date.now()}.png`, fullPage: true }).catch(() => {});
      throw error;
    }
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
