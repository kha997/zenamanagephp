import { test, expect } from '@playwright/test';
import { MinimalAuthHelper } from '../helpers/auth';
import { testData } from '../helpers/data';

test.describe('Smoke Tests - Authentication', () => {
  test('@smoke admin login succeeds', async ({ page }) => {
    const auth = new MinimalAuthHelper(page);
    const adminEmail = process.env.SMOKE_ADMIN_EMAIL || testData.adminUser.email;
    const adminPassword = process.env.SMOKE_ADMIN_PASSWORD || testData.adminUser.password;
    
    await auth.login(adminEmail, adminPassword);
    expect(await auth.isLoggedIn()).toBe(true);
  });

  test('@smoke admin logout succeeds', async ({ page }) => {
    const auth = new MinimalAuthHelper(page);
    const adminEmail = process.env.SMOKE_ADMIN_EMAIL || testData.adminUser.email;
    const adminPassword = process.env.SMOKE_ADMIN_PASSWORD || testData.adminUser.password;
    
    await auth.login(adminEmail, adminPassword);
    await auth.logout();
    expect(await auth.isLoggedIn()).toBe(false);
  });
});
