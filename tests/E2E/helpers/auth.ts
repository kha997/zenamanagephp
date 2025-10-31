import { Page, expect } from '@playwright/test';

export class MinimalAuthHelper {
  constructor(private page: Page) {}

  async login(email: string, password: string): Promise<void> {
    try {
      // First, verify base URL is accessible
      console.log(`[Auth Helper] Base URL: ${this.page.url()}`);
      
      // Navigate to login page and wait for it to be ready
      await this.page.goto('/login', { waitUntil: 'domcontentloaded', timeout: 30000 });
      
      // Log page URL and title for debugging
      const url = this.page.url();
      const title = await this.page.title();
      console.log(`[Auth Helper] Navigated to: ${url}, Title: ${title}`);
      
      // Check if we got redirected (should not happen for /login)
      if (!url.includes('/login')) {
        console.warn(`[Auth Helper] WARNING: Expected /login but got: ${url}`);
        console.warn(`[Auth Helper] This might indicate a redirect issue`);
      }
      
      // Wait a bit for page to fully render
      await this.page.waitForTimeout(1000);
      
      // Wait for email input to be visible (with longer timeout for CI)
      let emailSelector = '#email';
      try {
        await this.page.waitForSelector('#email', { state: 'visible', timeout: 20000 });
      } catch (error) {
        // Debug: take screenshot and log page content if selector not found
        console.error(`[Auth Helper] #email selector not found on page: ${url}`);
        console.error(`[Auth Helper] Page title: ${title}`);
        console.error(`[Auth Helper] Page HTML (first 1000 chars):`);
        const htmlContent = await this.page.content();
        console.error(htmlContent.substring(0, 1000));
        
        // Try to find alternative selectors
        const hasEmailInput = htmlContent.includes('id="email"') || htmlContent.includes('name="email"');
        console.log(`[Auth Helper] Page contains id/name="email": ${hasEmailInput}`);
        
        // List all input fields on page
        const inputs = await this.page.$$eval('input', (elements) => 
          elements.map(el => ({
            id: el.id,
            name: el.getAttribute('name'),
            type: el.type,
            placeholder: el.getAttribute('placeholder'),
            className: el.className
          }))
        ).catch(() => []);
        console.log(`[Auth Helper] Input fields found:`, JSON.stringify(inputs, null, 2));
        
        // Try alternative selectors
        console.log(`[Auth Helper] Trying alternative selectors...`);
        const altSelectors = ['input[name="email"]', 'input[type="email"]', '[data-testid="email-input"]'];
        let foundAlternative = false;
        for (const selector of altSelectors) {
          try {
            await this.page.waitForSelector(selector, { state: 'visible', timeout: 5000 });
            console.log(`[Auth Helper] Found alternative selector: ${selector}`);
            emailSelector = selector;
            foundAlternative = true;
            break;
          } catch (e) {
            // Try next selector
          }
        }
        
        if (!foundAlternative) {
          throw error;
        }
      }
      
      // Fill in credentials
      await this.page.fill(emailSelector, email);
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
      const timestamp = Date.now();
      await this.page.screenshot({ 
        path: `test-results/login-error-${timestamp}.png`, 
        fullPage: true 
      }).catch(() => {});
      
      // Also save page HTML for analysis
      try {
        const html = await this.page.content();
        const fs = require('fs');
        const path = require('path');
        fs.writeFileSync(
          `test-results/login-error-${timestamp}.html`, 
          html
        );
        console.log(`[Auth Helper] Saved page HTML to test-results/login-error-${timestamp}.html`);
      } catch (e) {
        // Ignore if fs not available
      }
      
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
