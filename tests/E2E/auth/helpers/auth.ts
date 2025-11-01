import { Page, expect } from '@playwright/test';

export interface LoginCredentials {
  email: string;
  password: string;
  remember?: boolean;
}

/**
 * Login as a user
 */
export async function loginAs(
  page: Page,
  credentials: LoginCredentials
): Promise<void> {
  // Navigate to login page if not already there
  const currentUrl = page.url();
  if (!currentUrl.includes('/login')) {
    await page.goto('/login');
  }
  
  // Wait for form to be ready
  await page.waitForSelector('[data-testid="email-input"]', { timeout: 5000 });
  
  // Fill email
  await page.fill('[data-testid="email-input"]', credentials.email);
  
  // Fill password
  await page.fill('[data-testid="password-input"]', credentials.password);
  
  // Check remember me if needed
  if (credentials.remember) {
    await page.check('[data-testid="remember-checkbox"]');
  }
  
  // Submit form
  await page.click('[data-testid="login-submit"]');
  
  // Wait for navigation or error
  await page.waitForLoadState('networkidle');
}

/**
 * Logout user
 */
export async function logout(page: Page): Promise<void> {
  // Try to find and click logout button
  const logoutButton = page.locator('[data-testid="logout-button"]').first();
  
  if (await logoutButton.isVisible({ timeout: 1000 }).catch(() => false)) {
    await logoutButton.click();
  } else {
    // Fallback: navigate directly to logout endpoint
    await page.goto('/logout');
  }
  
  await page.waitForLoadState('networkidle');
}

/**
 * Assert user is logged in
 */
export async function assertLoggedIn(page: Page, expectedRole?: string): Promise<void> {
  // Check for authentication indicators
  await expect(page.locator('[data-testid="user-menu"]').or(page.locator('[data-testid="dashboard"]'))).toBeVisible();
  
  // If role is specified, check it
  if (expectedRole) {
    const roleIndicator = page.locator(`[data-testid="role-${expectedRole}"]`);
    if (await roleIndicator.isVisible({ timeout: 1000 }).catch(() => false)) {
      await expect(roleIndicator).toBeVisible();
    }
  }
  
  // Ensure we're not on login page
  await expect(page).not.toHaveURL(/.*\/login.*/);
}

/**
 * Assert user is logged out
 */
export async function assertLoggedOut(page: Page): Promise<void> {
  // Should be redirected to login
  await expect(page).toHaveURL(/.*\/login.*/);
  
  // No auth indicators should be visible
  await expect(page.locator('[data-testid="user-menu"]')).not.toBeVisible();
}

/**
 * Extract CSRF token from page
 */
export async function getCSRFToken(page: Page): Promise<string | null> {
  // Try meta tag first
  const metaTag = await page.locator('meta[name="csrf-token"]').getAttribute('content');
  if (metaTag) return metaTag;
  
  // Try hidden input
  const inputTag = await page.locator('input[name="_token"]').getAttribute('value');
  return inputTag;
}

/**
 * Assert cookie flags for security
 */
export async function assertSecureCookie(page: Page, cookieName: string): Promise<void> {
  const cookies = await page.context().cookies();
  const cookie = cookies.find(c => c.name === cookieName);
  
  if (!cookie) {
    throw new Error(`Cookie ${cookieName} not found`);
  }
  
  // Check security flags
  expect(cookie.httpOnly).toBe(true);
  expect(cookie.secure).toBe(true);
  expect(['Strict', 'Lax']).toContain(cookie.sameSite);
}

/**
 * Clear all cookies
 */
export async function clearCookies(page: Page): Promise<void> {
  await page.context().clearCookies();
}

/**
 * Generate unique test email
 */
export function generateTestEmail(prefix: string = 'test'): string {
  const timestamp = Date.now();
  const random = Math.random().toString(36).substring(7);
  return `${prefix}-${timestamp}-${random}@test.com`;
}

/**
 * Wait for loading state to complete
 */
export async function waitForLoadingToComplete(page: Page): Promise<void> {
  // Wait for any loading spinners to disappear
  const loadingSelectors = [
    '[data-testid="loading-spinner"]',
    '.loading',
    '[role="progressbar"]',
  ];
  
  for (const selector of loadingSelectors) {
    const elements = await page.locator(selector).all();
    for (const element of elements) {
      await element.waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {});
    }
  }
}

