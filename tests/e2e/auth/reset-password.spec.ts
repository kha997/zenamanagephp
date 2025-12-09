import { test, expect } from '@playwright/test';
import { createUser } from './helpers/seeds';
import { waitForEmail, extractVerificationLink } from './helpers/mailbox';
import { assertNeutralError } from './helpers/assertions';

test.describe('Password Reset', () => {
  test('should validate email on reset request', async ({ page }) => {
    await page.goto('/forgot-password');
    
    // Try invalid email
    await page.fill('[data-testid="email-input"]', 'invalid-email');
    await page.click('[data-testid="submit-reset"]');
    
    await expect(page.locator('[data-testid="email-error"]')).toBeVisible();
  });

  test('should show neutral success message', async ({ page }) => {
    await page.goto('/forgot-password');
    
    await page.fill('[data-testid="email-input"]', 'test-reset@test.com');
    await page.click('[data-testid="submit-reset"]');
    
    // Should show success (regardless of email existence)
    await expect(page.locator('[data-testid="reset-success"]')).toBeVisible();
    
    // Message should be neutral
    await assertNeutralError(page);
  });

  test('should rate limit reset requests', async ({ page }) => {
    await page.goto('/forgot-password');
    
    // Request multiple times
    for (let i = 0; i < 6; i++) {
      await page.fill('[data-testid="email-input"]', 'test-rate@test.com');
      await page.click('[data-testid="submit-reset"]');
      await page.waitForTimeout(500);
      
      // Clear form
      await page.fill('[data-testid="email-input"]', '');
    }
    
    // Should show throttled message
    await expect(page.locator('[data-testid="throttle-message"]')).toBeVisible({ timeout: 10000 });
  });

  test('should send reset email', async ({ page }) => {
    const email = 'test-reset-email@test.com';
    
    await createUser({
      email,
      password: 'OldPassword123!',
      name: 'Test User',
      tenant: 'zena',
      role: 'member',
      verified: true,
    });
    
    await page.goto('/forgot-password');
    await page.fill('[data-testid="email-input"]', email);
    await page.click('[data-testid="submit-reset"]');
    
    // Wait for email
    const mailEmail = await waitForEmail(email, 10000);
    expect(mailEmail).not.toBeNull();
  });

  test('should extract reset link from email', async ({ page }) => {
    const email = 'test-reset-link@test.com';
    
    await createUser({
      email,
      password: 'OldPassword123!',
      name: 'Test User',
      tenant: 'zena',
      role: 'member',
      verified: true,
    });
    
    await page.goto('/forgot-password');
    await page.fill('[data-testid="email-input"]', email);
    await page.click('[data-testid="submit-reset"]');
    
    const mailEmail = await waitForEmail(email, 10000);
    const resetLink = extractVerificationLink(mailEmail);
    
    expect(resetLink).toBeTruthy();
    expect(resetLink).toContain('reset');
  });

  test('should enforce password policy on reset', async ({ page }) => {
    const email = 'test-reset-policy@test.com';
    
    await createUser({
      email,
      password: 'OldPassword123!',
      name: 'Test User',
      tenant: 'zena',
      role: 'member',
      verified: true,
    });
    
    // Get reset link
    await page.goto('/forgot-password');
    await page.fill('[data-testid="email-input"]', email);
    await page.click('[data-testid="submit-reset"]');
    
    const mailEmail = await waitForEmail(email, 10000);
    const resetLink = extractVerificationLink(mailEmail);
    
    await page.goto(resetLink);
    
    // Try weak password
    await page.fill('[data-testid="password-input"]', 'weak');
    await page.fill('[data-testid="password-confirm-input"]', 'weak');
    await page.click('[data-testid="submit-new-password"]');
    
    await expect(page.locator('[data-testid="password-error"]')).toBeVisible();
  });

  test('should require password confirmation match', async ({ page }) => {
    const email = 'test-reset-match@test.com';
    
    await createUser({
      email,
      password: 'OldPassword123!',
      name: 'Test User',
      tenant: 'zena',
      role: 'member',
      verified: true,
    });
    
    // Get reset link
    await page.goto('/forgot-password');
    await page.fill('[data-testid="email-input"]', email);
    await page.click('[data-testid="submit-reset"]');
    
    const mailEmail = await waitForEmail(email, 10000);
    const resetLink = extractVerificationLink(mailEmail);
    
    await page.goto(resetLink);
    
    await page.fill('[data-testid="password-input"]', 'NewPassword123!');
    await page.fill('[data-testid="password-confirm-input"]', 'DifferentPassword123!');
    await page.click('[data-testid="submit-new-password"]');
    
    await expect(page.locator('[data-testid="password-confirm-error"]')).toBeVisible();
  });

  test('should successfully reset password', async ({ page }) => {
    const email = 'test-reset-success@test.com';
    const newPassword = 'NewPassword123!';
    
    await createUser({
      email,
      password: 'OldPassword123!',
      name: 'Test User',
      tenant: 'zena',
      role: 'member',
      verified: true,
    });
    
    // Get reset link
    await page.goto('/forgot-password');
    await page.fill('[data-testid="email-input"]', email);
    await page.click('[data-testid="submit-reset"]');
    
    const mailEmail = await waitForEmail(email, 10000);
    const resetLink = extractVerificationLink(mailEmail);
    
    await page.goto(resetLink);
    
    await page.fill('[data-testid="password-input"]', newPassword);
    await page.fill('[data-testid="password-confirm-input"]', newPassword);
    await page.click('[data-testid="submit-new-password"]');
    
    // Should show success
    await expect(page.locator('[data-testid="reset-success"]')).toBeVisible();
    
    // Should redirect to login
    await page.waitForURL(/.*login.*/);
    
    // Can login with new password
    await page.fill('[data-testid="email-input"]', email);
    await page.fill('[data-testid="password-input"]', newPassword);
    await page.click('[data-testid="login-submit"]');
    
    await expect(page).toHaveURL(/.*dashboard.*/);
  });

  test('should invalidate all sessions on reset', async ({ page, context }) => {
    const email = 'test-session-invalidate@test.com';
    const newPassword = 'NewPassword123!';
    
    await createUser({
      email,
      password: 'OldPassword123!',
      name: 'Test User',
      tenant: 'zena',
      role: 'member',
      verified: true,
    });
    
    // Login in one session
    const page1 = await context.newPage();
    await page1.goto('/login');
    await page1.fill('[data-testid="email-input"]', email);
    await page1.fill('[data-testid="password-input"]', 'OldPassword123!');
    await page1.click('[data-testid="login-submit"]');
    await page1.waitForURL(/.*dashboard.*/);
    
    // Reset password in another session
    await page.goto('/forgot-password');
    await page.fill('[data-testid="email-input"]', email);
    await page.click('[data-testid="submit-reset"]');
    
    const mailEmail = await waitForEmail(email, 10000);
    const resetLink = extractVerificationLink(mailEmail);
    
    await page.goto(resetLink);
    await page.fill('[data-testid="password-input"]', newPassword);
    await page.fill('[data-testid="password-confirm-input"]', newPassword);
    await page.click('[data-testid="submit-new-password"]');
    
    // Old session should be logged out
    await page1.waitForTimeout(1000);
    await page1.goto('/app/dashboard');
    
    // Should redirect to login
    await expect(page1).toHaveURL(/.*login.*/);
  });

  test('should block reset token reuse', async ({ page }) => {
    const email = 'test-reuse-token@test.com';
    
    await createUser({
      email,
      password: 'OldPassword123!',
      name: 'Test User',
      tenant: 'zena',
      role: 'member',
      verified: true,
    });
    
    await page.goto('/forgot-password');
    await page.fill('[data-testid="email-input"]', email);
    await page.click('[data-testid="submit-reset"]');
    
    const mailEmail = await waitForEmail(email, 10000);
    const resetLink = extractVerificationLink(mailEmail);
    
    // Use link once
    await page.goto(resetLink);
    await page.fill('[data-testid="password-input"]', 'NewPassword123!');
    await page.fill('[data-testid="password-confirm-input"]', 'NewPassword123!');
    await page.click('[data-testid="submit-new-password"]');
    
    await page.waitForURL(/.*login.*/);
    
    // Try to use same link again
    await page.goto(resetLink);
    
    // Should show error
    await expect(page.locator('[data-testid="reset-error"]')).toContainText('expired');
  });

  test('should reject expired reset token', async ({ page }) => {
    // Navigate to reset password page with an expired token
    const expiredToken = 'expired_' + Date.now() + '000';
    
    await page.goto(`/reset-password/${expiredToken}`);
    
    // Should show error or redirect
    const hasError = await page.locator('[data-testid="reset-error"]').isVisible().catch(() => false);
    const isRedirected = await page.url().includes('/forgot-password').catch(() => false);
    const hasForm = await page.locator('[data-testid="password-input"]').isVisible().catch(() => false);
    
    // Should NOT show the password reset form
    expect(hasForm).toBeFalsy();
    // Should have error or redirect
    expect(hasError || isRedirected).toBeTruthy();
  });

  test('should reject tampered reset token', async ({ page }) => {
    const email = 'test-tampered@test.com';
    
    await createUser({
      email,
      password: 'OldPassword123!',
      name: 'Test User',
      tenant: 'zena',
      role: 'member',
      verified: true,
    });
    
    await page.goto('/forgot-password');
    await page.fill('[data-testid="email-input"]', email);
    await page.click('[data-testid="submit-reset"]');
    
    const mailEmail = await waitForEmail(email, 10000);
    let resetLink = extractVerificationLink(mailEmail);
    
    // Tamper with the token
    resetLink = resetLink?.replace(/token=[^&]+/, 'token=tampered-token') || '';
    
    await page.goto(resetLink);
    
    // Should show error
    await expect(page.locator('[data-testid="reset-error"]')).toContainText('invalid');
  });
});

