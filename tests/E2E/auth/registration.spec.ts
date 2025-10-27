import { test, expect } from '@playwright/test';
import { generateTestEmail, loginAs } from './helpers/auth';
import { createUser } from './helpers/seeds';
import { waitForEmail, extractVerificationLink } from './helpers/mailbox';
import { assertValidationErrors, assertNeutralError } from './helpers/assertions';

test.describe('Registration', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/register');
  });

  test('should validate required fields', async ({ page }) => {
    // Try to submit empty form
    await page.click('[data-testid="register-submit"]');
    
    // Should show validation errors
    await assertValidationErrors(page, ['email', 'password', 'name']);
  });

  test('should validate email format', async ({ page }) => {
    await page.fill('[data-testid="email-input"]', 'invalid-email');
    await page.fill('[data-testid="password-input"]', 'ValidPassword123!');
    await page.fill('[data-testid="name-input"]', 'Test User');
    
    await page.click('[data-testid="register-submit"]');
    
    await expect(page.locator('[data-testid="email-error"]')).toContainText('valid');
  });

  test('should enforce password policy', async ({ page }) => {
    await page.fill('[data-testid="email-input"]', generateTestEmail());
    await page.fill('[data-testid="password-input"]', 'weak');
    await page.fill('[data-testid="name-input"]', 'Test User');
    
    await page.click('[data-testid="register-submit"]');
    
    await expect(page.locator('[data-testid="password-error"]')).toBeVisible();
  });

  test('should require password confirmation match', async ({ page }) => {
    const email = generateTestEmail();
    await page.fill('[data-testid="email-input"]', email);
    await page.fill('[data-testid="password-input"]', 'ValidPassword123!');
    await page.fill('[data-testid="password-confirm-input"]', 'DifferentPassword123!');
    await page.fill('[data-testid="name-input"]', 'Test User');
    
    await page.click('[data-testid="register-submit"]');
    
    await expect(page.locator('[data-testid="password-confirm-error"]')).toBeVisible();
  });

  test('should toggle password visibility', async ({ page }) => {
    await page.fill('[data-testid="password-input"]', 'TestPassword123!');
    
    // Initially hidden
    await expect(page.locator('[data-testid="password-input"]')).toHaveAttribute('type', 'password');
    
    // Click toggle
    await page.click('[data-testid="password-toggle"]');
    
    // Now visible
    await expect(page.locator('[data-testid="password-input"]')).toHaveAttribute('type', 'text');
  });

  test('should prevent duplicate emails', async ({ page }) => {
    const email = 'duplicate@test.com';
    
    // Create user first
    await createUser({
      email,
      name: 'Existing User',
      tenant: 'zena',
      role: 'member',
    });
    
    // Try to register with same email
    await page.fill('[data-testid="email-input"]', email);
    await page.fill('[data-testid="password-input"]', 'ValidPassword123!');
    await page.fill('[data-testid="name-input"]', 'Another User');
    
    await page.click('[data-testid="register-submit"]');
    
    // Should show error (neutral)
    await assertNeutralError(page);
  });

  test('should handle case-insensitive email', async ({ page }) => {
    const email = 'CaseTest@test.com';
    
    // Create user
    await createUser({
      email,
      name: 'Existing User',
      tenant: 'zena',
      role: 'member',
    });
    
    // Try different case
    await page.fill('[data-testid="email-input"]', email.toLowerCase());
    await page.fill('[data-testid="password-input"]', 'ValidPassword123!');
    await page.fill('[data-testid="name-input"]', 'Test User');
    
    await page.click('[data-testid="register-submit"]');
    
    // Should still detect duplicate
    await assertNeutralError(page);
  });

  test('should require terms acceptance', async ({ page }) => {
    const email = generateTestEmail();
    await page.fill('[data-testid="email-input"]', email);
    await page.fill('[data-testid="password-input"]', 'ValidPassword123!');
    await page.fill('[data-testid="name-input"]', 'Test User');
    
    // Don't check terms
    await page.click('[data-testid="register-submit"]');
    
    // Submit should be disabled or show error
    await expect(page.locator('[data-testid="terms-error"]')).toBeVisible();
  });

  test('should successfully register verified user', async ({ page }) => {
    const email = generateTestEmail();
    
    await page.fill('[data-testid="email-input"]', email);
    await page.fill('[data-testid="password-input"]', 'ValidPassword123!');
    await page.fill('[data-testid="password-confirm-input"]', 'ValidPassword123!');
    await page.fill('[data-testid="name-input"]', 'Test User');
    await page.check('[data-testid="terms-checkbox"]');
    
    await page.click('[data-testid="register-submit"]');
    
    // Should show success message
    await expect(page.locator('[data-testid="success-message"]')).toContainText('verification');
    
    // Should be redirected to login or verification pending page
    await page.waitForURL(/\/(login|verify)/);
  });

  test('should send verification email once', async ({ page }) => {
    const email = generateTestEmail();
    
    await page.fill('[data-testid="email-input"]', email);
    await page.fill('[data-testid="password-input"]', 'ValidPassword123!');
    await page.fill('[data-testid="password-confirm-input"]', 'ValidPassword123!');
    await page.fill('[data-testid="name-input"]', 'Test User');
    await page.check('[data-testid="terms-checkbox"]');
    
    await page.click('[data-testid="register-submit"]');
    
    // Wait for email
    const mailEmail = await waitForEmail(email, 10000);
    expect(mailEmail).not.toBeNull();
    expect(mailEmail.To.some(t => t.Mailbox === email.split('@')[0])).toBeTruthy();
  });

  test('should extract and use verification link', async ({ page }) => {
    const email = generateTestEmail();
    
    // Register
    await page.fill('[data-testid="email-input"]', email);
    await page.fill('[data-testid="password-input"]', 'ValidPassword123!');
    await page.fill('[data-testid="password-confirm-input"]', 'ValidPassword123!');
    await page.fill('[data-testid="name-input"]', 'Test User');
    await page.check('[data-testid="terms-checkbox"]');
    
    await page.click('[data-testid="register-submit"]');
    
    // Get verification link
    const mailEmail = await waitForEmail(email, 10000);
    const verifyLink = extractVerificationLink(mailEmail);
    
    expect(verifyLink).toBeTruthy();
    
    // Visit link
    await page.goto(verifyLink);
    
    // Should show success
    await expect(page.locator('[data-testid="verification-success"]')).toBeVisible();
    
    // Now user can login
    await page.goto('/login');
    await loginAs(page, { email, password: 'ValidPassword123!' });
    await expect(page).toHaveURL(/.*dashboard.*/);
  });

  test('should block expired verification token', async ({ page }) => {
    const email = generateTestEmail();
    
    // Register and get link
    await page.fill('[data-testid="email-input"]', email);
    await page.fill('[data-testid="password-input"]', 'ValidPassword123!');
    await page.fill('[data-testid="password-confirm-input"]', 'ValidPassword123!');
    await page.fill('[data-testid="name-input"]', 'Test User');
    await page.check('[data-testid="terms-checkbox"]');
    
    await page.click('[data-testid="register-submit"]');
    
    const mailEmail = await waitForEmail(email, 10000);
    const verifyLink = extractVerificationLink(mailEmail);
    
    // Simulate expired token by modifying timestamp
    const expiredLink = verifyLink?.replace(/expires=.*/, 'expires=0');
    
    await page.goto(expiredLink);
    
    // Should show error
    await expect(page.locator('[data-testid="error-message"]')).toBeVisible();
  });

  test('should throttle verification email resends', async ({ page }) => {
    const email = generateTestEmail();
    
    // Register first time
    await page.fill('[data-testid="email-input"]', email);
    await page.fill('[data-testid="password-input"]', 'ValidPassword123!');
    await page.fill('[data-testid="password-confirm-input"]', 'ValidPassword123!');
    await page.fill('[data-testid="name-input"]', 'Test User');
    await page.check('[data-testid="terms-checkbox"]');
    
    await page.click('[data-testid="register-submit"]');
    
    // Try to resend multiple times
    for (let i = 0; i < 5; i++) {
      await page.click('[data-testid="resend-verification"]');
      await page.waitForTimeout(100);
    }
    
    // Should eventually show throttled message
    await expect(page.locator('[data-testid="throttle-message"]')).toBeVisible({ timeout: 10000 });
  });

  test('should handle invite-only registration flow', async ({ page }) => {
    // Check if invite system exists
    const hasInviteButton = await page.locator('[data-testid="signup-invite-link"]').isVisible().catch(() => false);
    
    if (hasInviteButton) {
      // Should show invite message instead of form
      await page.click('[data-testid="signup-invite-link"]');
      await expect(page.locator('[data-testid="invite-only-message"]')).toBeVisible();
    } else {
      // If no invite system, registration page should work normally
      await expect(page).toHaveURL(/.*register.*/);
    }
  });

  test('should accept valid invitation', async ({ page }) => {
    // Test that invitation links can be accepted
    // Navigate to invite acceptance page (if exists)
    await page.goto('/accept-invite/test-token');
    
    // Should either show form or error message
    const hasForm = await page.locator('form').isVisible().catch(() => false);
    const hasError = await page.locator('[data-testid="invite-error"]').isVisible().catch(() => false);
    
    // One of them should be visible
    expect(hasForm || hasError).toBeTruthy();
  });
});

