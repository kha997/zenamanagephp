import { test, expect } from '@playwright/test';
import { AuthHelper } from '../helpers/smoke-helpers';

test.describe('Theme Debug Final', () => {
  test('Debug theme toggle DOM state', async ({ page }) => {
    const authHelper = new AuthHelper(page);
    
    // Clear storage
    await page.evaluate(() => {
      try {
        localStorage.clear();
        sessionStorage.clear();
      } catch (e) {
        console.log('Storage clear failed:', e);
      }
    });
    
    // Login
    await authHelper.login();
    
    // Get initial theme state
    const initialState = await page.evaluate(() => {
      const html = document.documentElement;
      return {
        datasetTheme: html.dataset.theme,
        classList: [...html.classList],
        localStorage: (() => {
          try { return localStorage.getItem('theme'); } catch { return 'access denied'; }
        })(),
        computedStyle: getComputedStyle(html).getPropertyValue('color-scheme')
      };
    });
    
    console.log('Initial state:', initialState);
    
    // Take screenshot before toggle
    await page.screenshot({ path: 'theme-before.png' });
    
    // Find and click theme toggle
    const themeToggle = page.locator('button:has-text("Dark mode"), button:has-text("Theme"), [data-testid="theme-toggle"]').first();
    
    if (await themeToggle.isVisible()) {
      console.log('Theme toggle found, clicking...');
      await themeToggle.click();
      
      // Wait a bit
      await page.waitForTimeout(1000);
      
      // Get state after toggle
      const afterState = await page.evaluate(() => {
        const html = document.documentElement;
        return {
          datasetTheme: html.dataset.theme,
          classList: [...html.classList],
          localStorage: (() => {
            try { return localStorage.getItem('theme'); } catch { return 'access denied'; }
          })(),
          computedStyle: getComputedStyle(html).getPropertyValue('color-scheme')
        };
      });
      
      console.log('After toggle state:', afterState);
      
      // Take screenshot after toggle
      await page.screenshot({ path: 'theme-after.png' });
      
      // Check if theme actually changed
      const themeChanged = initialState.datasetTheme !== afterState.datasetTheme || 
                          initialState.classList.includes('dark') !== afterState.classList.includes('dark');
      
      console.log('Theme changed:', themeChanged);
      
      if (!themeChanged) {
        // Log all possible theme buttons
        const allButtons = await page.evaluate(() => {
          const buttons = Array.from(document.querySelectorAll('button'));
          return buttons.map(btn => ({
            text: btn.textContent?.trim(),
            classes: btn.className,
            dataset: btn.dataset,
            id: btn.id
          }));
        });
        
        console.log('All buttons found:', allButtons);
      }
    } else {
      console.log('Theme toggle not found');
      
      // Log all buttons
      const allButtons = await page.evaluate(() => {
        const buttons = Array.from(document.querySelectorAll('button'));
        return buttons.map(btn => ({
          text: btn.textContent?.trim(),
          classes: btn.className,
          dataset: btn.dataset,
          id: btn.id
        }));
      });
      
      console.log('All buttons found:', allButtons);
    }
  });
});