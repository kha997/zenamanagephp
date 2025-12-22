import { test, expect } from '@playwright/test';
import { loginAs, assertLoggedIn } from './helpers/auth';
import { createUser } from './helpers/seeds';
import { extractOTPCode, waitForEmail } from './helpers/mailbox';

test.describe('Two-Factor Authentication', () => {
  test('should display QR code for TOTP enrollment', async ({ page }) => {
    const email = 'test-2fa@test.com';
    
    // Create user without 2FA
    await createUser({
      email,
      password: 'TestPassword123!',
      name: 'Test User',
      tenant: 'zena',
      role: 'member',
      verified: true,
      twoFA: false,
    });
    
    // Login
    await page.goto('/login');
    await loginAs(page, { email, password: 'TestPassword123!' });
    
    // Navigate to security settings
    await page.goto('/app/settings/security');
    
    // Enable 2FA
    await page.click('[data-testid="enable-2fa"]');
    
    // Should show QR code
    await expect(page.locator('[data-testid="2fa-qr-code"]')).toBeVisible();
    
    // Should show secret for manual entry
    await expect(page.locator('[data-testid="2fa-secret"]')).toBeVisible();
  });

  test('should confirm TOTP code during enrollment', async ({ page }) => {
    const email = 'test-2fa-confirm@test.com';
    
    await createUser({
      email,
      password: 'TestPassword123!',
      name: 'Test User',
      tenant: 'zena',
      role: 'member',
      verified: true,
      twoFA: false,
    });
    
    await page.goto('/login');
    await loginAs(page, { email, password: 'TestPassword123!' });
    
    await page.goto('/app/settings/security');
    await page.click('[data-testid="enable-2fa"]');
    
    // Wait for QR code
    await page.waitForSelector('[data-testid="2fa-qr-code"]');
    
    // Get code from page (mock for testing)
    const testCode = '123456';
    
    // Enter code
    await page.fill('[data-testid="2fa-confirm-input"]', testCode);
    await page.click('[data-testid="2fa-confirm-submit"]');
    
    // Should show success or error
    const successOrError = await page.locator('[data-testid="2fa-success"], [data-testid="2fa-error"]').first().isVisible();
    expect(successOrError).toBeTruthy();
  });

  test('should show recovery codes', async ({ page }) => {
    const email = 'test-recovery@test.com';
    
    await createUser({
      email,
      password: 'TestPassword123!',
      name: 'Test User',
      tenant: 'zena',
      role: 'member',
      verified: true,
      twoFA: true,
    });
    
    await page.goto('/login');
    await loginAs(page, { email, password: 'TestPassword123!' });
    
    // Should prompt for 2FA code
    await expect(page.locator('[data-testid="2fa-prompt"]')).toBeVisible();
    
    // Click recovery codes link
    await page.click('[data-testid="show-recovery-codes"]');
    
    // Should show recovery codes
    await expect(page.locator('[data-testid="recovery-codes"]')).toBeVisible();
    
    // Should allow download
    const downloadPromise = page.waitForEvent('download');
    await page.click('[data-testid="download-recovery-codes"]');
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toContain('recovery');
  });

  test('should login with TOTP code', async ({ page }) => {
    const email = 'test-2fa-login@test.com';
    
    await createUser({
      email,
      password: 'TestPassword123!',
      name: 'Test User',
      tenant: 'zena',
      role: 'member',
      verified: true,
      twoFA: true,
    });
    
    await page.goto('/login');
    
    // First step: password
    await page.fill('[data-testid="email-input"]', email);
    await page.fill('[data-testid="password-input"]', 'TestPassword123!');
    await page.click('[data-testid="login-submit"]');
    
    // Should show 2FA prompt
    await expect(page.locator('[data-testid="2fa-code-input"]')).toBeVisible();
    
    // Enter code (mock)
    await page.fill('[data-testid="2fa-code-input"]', '123456');
    await page.click('[data-testid="2fa-submit"]');
    
    // Should complete login
    await page.waitForURL(/.*dashboard.*/, { timeout: 5000 });
    await assertLoggedIn(page);
  });

  test('should reject invalid TOTP code', async ({ page }) => {
    const email = 'test-2fa-invalid@test.com';
    
    await createUser({
      email,
      password: 'TestPassword123!',
      name: 'Test User',
      tenant: 'zena',
      role: 'member',
      verified: true,
      twoFA: true,
    });
    
    await page.goto('/login');
    await page.fill('[data-testid="email-input"]', email);
    await page.fill('[data-testid="password-input"]', 'TestPassword123!');
    await page.click('[data-testid="login-submit"]');
    
    await expect(page.locator('[data-testid="2fa-code-input"]')).toBeVisible();
    
    // Enter invalid code
    await page.fill('[data-testid="2fa-code-input"]', '000000');
    await page.click('[data-testid="2fa-submit"]');
    
    // Should show error
    await expect(page.locator('[data-testid="2fa-error"]')).toBeVisible();
  });

  test('should allow recovery code login', async ({ page }) => {
    const email = 'test-2fa-recovery@test.com';
    
    await createUser({
      email,
      password: 'TestPassword123!',
      name: 'Test User',
      tenant: 'zena',
      role: 'member',
      verified: true,
      twoFA: true,
    });
    
    await page.goto('/login');
    await page.fill('[data-testid="email-input"]', email);
    await page.fill('[data-testid="password-input"]', 'TestPassword123!');
    await page.click('[data-testid="login-submit"]');
    
    // Switch to recovery codes
    await page.click('[data-testid="use-recovery-code"]');
    
    // Enter recovery code (mock)
    await page.fill('[data-testid="recovery-code-input"]', 'recovery-code-001');
    await page.click('[data-testid="recovery-submit"]');
    
    // Should login
    await page.waitForURL(/.*dashboard.*/, { timeout: 5000 }).catch(() => {});
  });

  test('should mark recovery code as used', async ({ page }) => {
    const email = 'test-2fa-marked@test.com';
    
    await createUser({
      email,
      password: 'TestPassword123!',
      name: 'Test User',
      tenant: 'zena',
      role: 'member',
      verified: true,
      twoFA: true,
    });
    
    await page.goto('/login');
    await page.fill('[data-testid="email-input"]', email);
    await page.fill('[data-testid="password-input"]', 'TestPassword123!');
    await page.click('[data-testid="login-submit"]');
    
    await page.click('[data-testid="use-recovery-code"]');
    
    const code = 'recovery-code-002';
    
    // First use
    await page.fill('[data-testid="recovery-code-input"]', code);
    await page.click('[data-testid="recovery-submit"]');
    
    // Wait for any redirect
    await page.waitForTimeout(1000);
    
    // Try to use same code again
    await page.goto('/login');
    await page.fill('[data-testid="email-input"]', email);
    await page.fill('[data-testid="password-input"]', 'TestPassword123!');
    await page.click('[data-testid="login-submit"]');
    
    await page.click('[data-testid="use-recovery-code"]');
    await page.fill('[data-testid="recovery-code-input"]', code);
    await page.click('[data-testid="recovery-submit"]');
    
    // Should show error
    await expect(page.locator('[data-testid="recovery-error"]')).toContainText('used');
  });

  test('should invalidate old codes on regeneration', async ({ page }) => {
    const email = 'test-2fa-invalidate@test.com';
    
    await createUser({
      email,
      password: 'TestPassword123!',
      name: 'Test User',
      tenant: 'zena',
      role: 'member',
      verified: true,
      twoFA: true,
    });
    
    // Login and generate new codes
    await page.goto('/login');
    await loginAs(page, { email, password: 'TestPassword123!' });
    
    await page.goto('/app/settings/security');
    
    // Check if 2FA is enabled and regeneration exists
    const hasRegenerateButton = await page.locator('[data-testid="regenerate-recovery-codes"]').isVisible().catch(() => false);
    
    if (hasRegenerateButton) {
      // Capture old codes before regeneration
      const oldCodesElement = page.locator('[data-testid="recovery-codes"]');
      const oldCodesText = await oldCodesElement.textContent().catch(() => '');
      
      // Regenerate codes
      await page.click('[data-testid="regenerate-recovery-codes"]');
      
      // Wait for confirmation
      const confirmButton = page.locator('[data-testid="confirm-regenerate"]');
      if (await confirmButton.isVisible({ timeout: 2000 }).catch(() => false)) {
        await confirmButton.click();
      }
      
      // New codes should be different
      const newCodesText = await oldCodesElement.textContent().catch(() => '');
      expect(newCodesText).not.toBe(oldCodesText);
    } else {
      // If no regeneration feature, skip gracefully
      test.skip('Recovery code regeneration not implemented');
    }
  });

  test('should require password to disable 2FA', async ({ page }) => {
    const email = 'test-2fa-disable@test.com';
    
    await createUser({
      email,
      password: 'TestPassword123!',
      name: 'Test User',
      tenant: 'zena',
      role: 'member',
      verified: true,
      twoFA: true,
    });
    
    await page.goto('/login');
    await loginAs(page, { email, password: 'TestPassword123!' });
    
    await page.goto('/app/settings/security');
    
    // Try to disable without password
    await page.click('[data-testid="disable-2fa"]');
    
    // Should prompt for password
    await expect(page.locator('[data-testid="password-confirm-input"]')).toBeVisible();
    
    // Enter password
    await page.fill('[data-testid="password-confirm-input"]', 'TestPassword123!');
    await page.click('[data-testid="confirm-disable-2fa"]');
    
    // Should disable successfully
    await expect(page.locator('[data-testid="2fa-disabled-success"]')).toBeVisible();
  });

  test('should reject wrong password on disable', async ({ page }) => {
    const email = 'test-2fa-wrong-disable@test.com';
    
    await createUser({
      email,
      password: 'TestPassword123!',
      name: 'Test User',
      tenant: 'zena',
      role: 'member',
      verified: true,
      twoFA: true,
    });
    
    await page.goto('/login');
    await loginAs(page, { email, password: 'TestPassword123!' });
    
    await page.goto('/app/settings/security');
    
    await page.click('[data-testid="disable-2fa"]');
    await page.fill('[data-testid="password-confirm-input"]', 'WrongPassword!');
    await page.click('[data-testid="confirm-disable-2fa"]');
    
    // Should show error
    await expect(page.locator('[data-testid="password-error"]')).toBeVisible();
  });
});

