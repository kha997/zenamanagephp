import { test, expect } from '@playwright/test';
import { MinimalAuthHelper } from '../helpers/auth';
import { testData } from '../helpers/data';

test.describe('Smoke Tests - Project Creation', () => {
  test('@smoke project creation form loads', async ({ page }) => {
    const auth = new MinimalAuthHelper(page);
    const adminEmail = process.env.SMOKE_ADMIN_EMAIL || testData.adminUser.email;
    const adminPassword = process.env.SMOKE_ADMIN_PASSWORD || testData.adminUser.password;
    
    await auth.login(adminEmail, adminPassword);
    
    await page.goto('/app/projects');
    await page.click('[data-testid="create-project"]');
    await expect(page.locator('form[action*="projects"]')).toBeVisible();
  });

  test('@smoke project list loads', async ({ page }) => {
    const auth = new MinimalAuthHelper(page);
    const adminEmail = process.env.SMOKE_ADMIN_EMAIL || testData.adminUser.email;
    const adminPassword = process.env.SMOKE_ADMIN_PASSWORD || testData.adminUser.password;
    
    await auth.login(adminEmail, adminPassword);
    
    await page.goto('/app/projects');
    await expect(page.locator('h1:has-text("Projects")')).toBeVisible();
  });
});
