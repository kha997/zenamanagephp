import { test, expect } from '@playwright/test';

test.describe('UI Primitives', () => {
  test('buttons and card snapshot (light)', async ({ page }) => {
    await page.addInitScript(() => document.documentElement.setAttribute('data-theme', 'light'));
    await page.goto('/');
    const demo = page.locator('[data-testid="ui-demo"]');
    await expect(demo).toBeVisible();
    expect(await demo.screenshot()).toMatchSnapshot('primitives-light.png');
  });

  test('buttons and card snapshot (dark)', async ({ page }) => {
    await page.addInitScript(() => document.documentElement.setAttribute('data-theme', 'dark'));
    await page.goto('/');
    const demo = page.locator('[data-testid="ui-demo"]');
    await expect(demo).toBeVisible();
    expect(await demo.screenshot()).toMatchSnapshot('primitives-dark.png');
  });
});

