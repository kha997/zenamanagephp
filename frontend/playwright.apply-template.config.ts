import { defineConfig, devices } from '@playwright/test';

/**
 * Temporary config for running apply-template.spec.ts from root tests directory
 * Uses frontend preview server setup but looks for tests in root tests/e2e
 * 
 * Usage: npx playwright test --config=frontend/playwright.apply-template.config.ts
 */

// Use PLAYWRIGHT_PREVIEW_PORT to override the preview port if 4173 is blocked.
const previewPort = Number(process.env.PLAYWRIGHT_PREVIEW_PORT ?? '4173');
const validPreviewPort = Number.isNaN(previewPort) ? 4173 : previewPort;

export default defineConfig({
  // Look for tests in root tests directory
  testDir: '../tests/e2e',
  // Only run the apply-template spec
  testMatch: '**/projects/apply-template.spec.ts',
  /* Run tests in files in parallel */
  fullyParallel: true,
  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,
  /* Retry on CI only */
  retries: process.env.CI ? 2 : 0,
  /* Opt out of parallel tests on CI. */
  workers: process.env.CI ? 1 : undefined,
  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
  reporter: 'html',
  /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
  use: {
    /* Base URL to use in actions like `await page.goto('/')`. */
    baseURL: `http://127.0.0.1:${validPreviewPort}`,

    /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
    trace: 'on-first-retry',
  },

  /* Configure projects for major browsers */
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],

  /* Run your local dev server before starting the tests */
  webServer: {
    command: `npm run preview -- --host 127.0.0.1 --port ${validPreviewPort}`,
    url: `http://127.0.0.1:${validPreviewPort}`,
    reuseExistingServer: !process.env.CI,
    timeout: 120 * 1000, // 2 minutes timeout
  },
});

