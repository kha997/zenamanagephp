import { defineConfig, devices } from '@playwright/test';

/**
 * Phase 3 E2E Test Configuration
 * 
 * Specific configuration for Phase 3 feature testing:
 * - Frontend comment UI integration
 * - Kanban React board with ULID schema
 * - File attachments system
 * - Real-time updates
 */
export default defineConfig({
  testDir: './tests/E2E/phase3',
  
  /* Run tests in files in parallel */
  fullyParallel: true,
  
  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,
  
  /* Retry on CI only */
  retries: process.env.CI ? 2 : 0,
  
  /* Opt out of parallel tests on CI. */
  workers: process.env.CI ? 1 : undefined,
  
  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
  reporter: process.env.CI ? [
    ['html'],
    ['json', { outputFile: 'test-results/phase3-results.json' }],
    ['junit', { outputFile: 'test-results/phase3-results.xml' }]
  ] : [
    ['list'],
    ['json', { outputFile: 'test-results/phase3-results.json' }]
  ],
  
  /* Shared settings for all the projects below. */
  use: {
    /* Base URL to use in actions like `await page.goto('/')`. */
    baseURL: 'http://127.0.0.1:8000',

    /* Collect trace when retrying the failed test. */
    trace: 'on-first-retry',
    
    /* Take screenshot on failure */
    screenshot: 'only-on-failure',
    
    /* Record video on failure */
    video: 'retain-on-failure',
    
    /* Timeout for each action */
    actionTimeout: 15000,
    
    /* Timeout for navigation */
    navigationTimeout: 30000,
    
    /* Timeout for each test */
    timeout: 60000,
  },

  /* Global setup for Phase 3 tests */
  globalSetup: require.resolve('./tests/E2E/phase3/setup/phase3-global-setup.ts'),

  /* Configure projects for major browsers */
  projects: [
    {
      name: 'chromium',
      use: { 
        ...devices['Desktop Chrome'],
        // Additional Chrome-specific settings for Phase 3
        launchOptions: {
          args: [
            '--disable-web-security',
            '--disable-features=VizDisplayCompositor',
            '--enable-features=NetworkService,NetworkServiceLogging',
          ]
        }
      },
    },

    {
      name: 'firefox',
      use: { 
        ...devices['Desktop Firefox'],
        // Additional Firefox-specific settings
        launchOptions: {
          firefoxUserPrefs: {
            'media.navigator.streams.fake': true,
            'media.navigator.permission.disabled': true,
          }
        }
      },
    },

    {
      name: 'webkit',
      use: { 
        ...devices['Desktop Safari'],
        // Additional Safari-specific settings
        launchOptions: {
          args: ['--disable-web-security']
        }
      },
    },

    /* Test against mobile viewports. */
    {
      name: 'Mobile Chrome',
      use: { 
        ...devices['Pixel 5'],
        // Mobile-specific settings for Phase 3
        viewport: { width: 393, height: 851 },
      },
    },
    {
      name: 'Mobile Safari',
      use: { 
        ...devices['iPhone 12'],
        // Mobile Safari settings
        viewport: { width: 390, height: 844 },
      },
    },

    /* Test against branded browsers. */
    {
      name: 'Microsoft Edge',
      use: { 
        ...devices['Desktop Edge'],
        channel: 'msedge',
      },
    },
    {
      name: 'Google Chrome',
      use: { 
        ...devices['Desktop Chrome'],
        channel: 'chrome',
      },
    },
  ],

  /* Run your local dev server before starting the tests */
  webServer: {
    command: 'php artisan serve --host=127.0.0.1 --port=8000',
    url: 'http://127.0.0.1:8000',
    reuseExistingServer: !process.env.CI,
    timeout: 120000,
  },

  /* Test timeout for Phase 3 features */
  timeout: 60000,

  /* Expect timeout for assertions */
  expect: {
    timeout: 10000,
  },

  /* Output directory for test artifacts */
  outputDir: 'test-results/phase3-artifacts/',

  /* Test match patterns */
  testMatch: [
    '**/phase3/**/*.spec.ts',
    '**/phase3/**/*.test.ts',
  ],

  /* Test ignore patterns */
  testIgnore: [
    '**/node_modules/**',
    '**/dist/**',
    '**/build/**',
  ],

  /* Global test timeout */
  globalTimeout: 600000, // 10 minutes for entire test suite

  /* Maximum failures before stopping */
  maxFailures: process.env.CI ? 10 : undefined,
});
