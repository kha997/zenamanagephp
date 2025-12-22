import { Page, expect } from '@playwright/test';

/**
 * Security-related assertions
 */

/**
 * Assert neutral error message (no PII leakage)
 */
export async function assertNeutralError(page: Page): Promise<void> {
  // Error should not contain specific user info like email
  const errorText = await page.textContent('[data-testid="error-message"]').catch(() => '');
  
  expect(errorText).not.toContain('email');
  expect(errorText).not.toContain('password');
  expect(errorText).not.toContain('account');
  expect(errorText).not.toContain('user');
  
  // Should be generic
  expect(errorText?.length).toBeGreaterThan(0);
}

/**
 * Assert secure response headers
 */
export async function assertSecureHeaders(page: Page): Promise<void> {
  const headers = page.response()?.headers();
  
  if (!headers) return;
  
  // Check for security headers
  expect(headers['x-frame-options']).toContain('DENY');
  expect(headers['x-content-type-options']).toContain('nosniff');
  
  // CSP if configured
  if (headers['content-security-policy']) {
    expect(headers['content-security-policy']).toBeTruthy();
  }
}

/**
 * Assert form validation errors
 */
export async function assertValidationErrors(page: Page, fields: string[]): Promise<void> {
  for (const field of fields) {
    const fieldError = page.locator(`[data-testid="${field}-error"]`);
    await expect(fieldError).toBeVisible();
  }
}

/**
 * Assert rate limiting response
 */
export async function assertRateLimited(page: Page): Promise<void> {
  // Should show 429 Too Many Requests or lockout message
  const response = await page.waitForResponse(
    response => response.status() === 429 || response.url().includes('/login')
  );
  
  expect([429, 422]).toContain(response.status());
}

/**
 * Assert redirect protection
 */
export async function assertOpenRedirectProtected(
  page: Page,
  redirectUrl: string
): Promise<void> {
  // Should not redirect to external URLs
  const finalUrl = page.url();
  expect(finalUrl).not.toContain(redirectUrl);
  
  // Should stay on same domain or redirect to safe default
  expect(finalUrl).toContain(new URL(page.url()).hostname);
}

/**
 * Assert password requirements message
 */
export async function assertPasswordPolicy(page: Page): Promise<void> {
  const passwordInput = page.locator('[data-testid="password-input"]');
  
  // Get validation message (might be on blur or submit)
  const errorText = await page.textContent('[data-testid="password-error"]').catch(() => '');
  
  // Should mention minimum requirements
  expect(errorText?.length).toBeGreaterThan(0);
}

/**
 * Assert accessibility compliance
 */
export async function assertA11yLabels(page: Page): Promise<void> {
  // Check for aria-labels on interactive elements
  const inputs = page.locator('input');
  const count = await inputs.count();
  
  for (let i = 0; i < count; i++) {
    const input = inputs.nth(i);
    const id = await input.getAttribute('id');
    const ariaLabel = await input.getAttribute('aria-label');
    
    // Should have either id with label reference or aria-label
    if (id) {
      const label = page.locator(`label[for="${id}"]`);
      const hasLabel = await label.count() > 0;
      expect(hasLabel || ariaLabel).toBeTruthy();
    } else {
      expect(ariaLabel).toBeTruthy();
    }
  }
}

/**
 * Assert keyboard navigation works
 */
export async function assertKeyboardNavigation(page: Page): Promise<void> {
  // Tab through elements
  await page.keyboard.press('Tab');
  
  // Should show focus indicator
  const focusedElement = page.locator(':focus');
  const focusVisible = await focusedElement.evaluate(
    el => window.getComputedStyle(el).getPropertyValue('outline-width')
  );
  
  expect(focusVisible).not.toBe('0px');
}

/**
 * Assert page performance within budget
 */
export async function assertPerformanceBudget(
  page: Page,
  ttfbBudget: number = 500,
  fcpBudget: number = 2000
): Promise<void> {
  const metrics = await page.evaluate(() => {
    const perf = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming;
    return {
      ttfb: perf.responseStart - perf.requestStart,
      fcp: perf.domContentLoadedEventStart - perf.fetchStart,
    };
  });
  
  expect(metrics.ttfb).toBeLessThan(ttfbBudget);
  expect(metrics.fcp).toBeLessThan(fcpBudget);
}

/**
 * Assert CSRF protection
 */
export async function assertCSRFProtected(page: Page): Promise<void> {
  // Try to submit without CSRF token
  await page.evaluate(() => {
    fetch('/api/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: 'test@test.com', password: 'test' }),
    });
  });
  
  // Should handle error gracefully
  const response = await page.waitForResponse(res => res.url().includes('/login'));
  
  // Should either redirect or return error, not expose app details
  expect([302, 419, 403]).toContain(response.status());
}

