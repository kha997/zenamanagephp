import { test, expect } from '@playwright/test';
import { MinimalAuthHelper } from '../helpers/auth';

test.describe('Smoke Tests - Authentication', () => {
  test('@smoke admin login succeeds', async ({ page }) => {
    const auth = new MinimalAuthHelper(page);
    await auth.login(process.env.SMOKE_ADMIN_EMAIL!, process.env.SMOKE_ADMIN_PASSWORD!);
    expect(await auth.isLoggedIn()).toBe(true);
  });

  test('@smoke admin logout succeeds', async ({ page }) => {
    const auth = new MinimalAuthHelper(page);
    await auth.login(process.env.SMOKE_ADMIN_EMAIL!, process.env.SMOKE_ADMIN_PASSWORD!);
    await auth.logout();
    expect(await auth.isLoggedIn()).toBe(false);
  });
});
