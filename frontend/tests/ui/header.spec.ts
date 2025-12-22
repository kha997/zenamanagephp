import { test, expect } from '@playwright/test';

test.describe('Header (light/dark)', () => {
  test('renders header in light mode', async ({ page }) => {
    await page.addInitScript(() => {
      document.documentElement.setAttribute('data-theme', 'light');
    });
    await page.goto('/');
    const header = page.locator('[data-debug="header-shell"]');
    await expect(header).toBeVisible();
    expect(await header.screenshot()).toMatchSnapshot('header-light.png');
  });

  test('renders header in dark mode', async ({ page }) => {
    await page.addInitScript(() => {
      document.documentElement.setAttribute('data-theme', 'dark');
    });
    await page.goto('/');
    const header = page.locator('[data-debug="header-shell"]');
    await expect(header).toBeVisible();
    expect(await header.screenshot()).toMatchSnapshot('header-dark.png');
  });
});

