import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';
import { createUser } from './helpers/seeds';

test.describe('Change Password', () => {
  test.beforeEach(async ({ page }) => {
    const email = 'test-change@test.com';
    
    await createUser({
      email,
      password: 'OldPassword123!',
      name: 'Test User',
      tenant: 'zena',
      role: 'member',
      verified: true,
    });
    
    await page.goto('/login');
    await loginAs(page, { email, password: 'OldPassword123!' });
  });

  test('should require current password', async ({ page }) => {
    await page.goto('/app/settings/security');
    
    await page.fill('[data-testid="current-password-input"]', '');
    await page.fill('[data-testid="new-password-input"]', 'NewPassword123!');
    await page.fill('[data-testid="confirm-new-password-input"]', 'NewPassword123!');
    
    await page.click('[data-testid="change-password-submit"]');
    
    await expect(page.locator('[data-testid="current-password-error"]')).toBeVisible();
  });

  test('should validate current password', async ({ page }) => {
    await page.goto('/app/settings/security');
    
    await page.fill('[data-testid="current-password-input"]', 'WrongPassword!');
    await page.fill('[data-testid="new-password-input"]', 'NewPassword123!');
    await page.fill('[data-testid="confirm-new-password-input"]', 'NewPassword123!');
    
    await page.click('[data-testid="change-password-submit"]');
    
    await expect(page.locator('[data-testid="current-password-error"]')).toContainText('incorrect');
  });

  test('should enforce password policy on new password', async ({ page }) => {
    await page.goto('/app/settings/security');
    
    await page.fill('[data-testid="current-password-input"]', 'OldPassword123!');
    await page.fill('[data-testid="new-password-input"]', 'weak');
    await page.fill('[data-testid="confirm-new-password-input"]', 'weak');
    
    await page.click('[data-testid="change-password-submit"]');
    
    await expect(page.locator('[data-testid="new-password-error"]')).toBeVisible();
  });

  test('should require new password confirmation', async ({ page }) => {
    await page.goto('/app/settings/security');
    
    await page.fill('[data-testid="current-password-input"]', 'OldPassword123!');
    await page.fill('[data-testid="new-password-input"]', 'NewPassword123!');
    await page.fill('[data-testid="confirm-new-password-input"]', 'DifferentPassword!');
    
    await page.click('[data-testid="change-password-submit"]');
    
    await expect(page.locator('[data-testid="confirm-password-error"]')).toBeVisible();
  });

  test('should successfully change password', async ({ page }) => {
    const newPassword = 'NewPassword123!';
    
    await page.goto('/app/settings/security');
    
    await page.fill('[data-testid="current-password-input"]', 'OldPassword123!');
    await page.fill('[data-testid="new-password-input"]', newPassword);
    await page.fill('[data-testid="confirm-new-password-input"]', newPassword);
    
    await page.click('[data-testid="change-password-submit"]');
    
    // Should show success
    await expect(page.locator('[data-testid="password-change-success"]')).toBeVisible();
  });

  test('should invalidate other sessions after change', async ({ page, context }) => {
    // Create a second session
    const page2 = await context.newPage();
    await page2.goto('/login');
    await loginAs(page2, { email: 'test-change@test.com', password: 'OldPassword123!' });
    
    // Change password in first session
    await page.goto('/app/settings/security');
    await page.fill('[data-testid="current-password-input"]', 'OldPassword123!');
    await page.fill('[data-testid="new-password-input"]', 'NewPassword123!');
    await page.fill('[data-testid="confirm-new-password-input"]', 'NewPassword123!');
    await page.click('[data-testid="change-password-submit"]');
    
    await expect(page.locator('[data-testid="password-change-success"]')).toBeVisible();
    
    // Wait for second session to detect logout
    await page2.waitForTimeout(2000);
    
    // Second session should be logged out
    await page2.goto('/app/dashboard');
    await expect(page2).toHaveURL(/.*login.*/);
  });

  test('should prevent reusing old passwords', async ({ page }) => {
    const oldPassword = 'OldPassword123!';
    
    // Change password first time
    await page.goto('/app/settings/security');
    await page.fill('[data-testid="current-password-input"]', oldPassword);
    await page.fill('[data-testid="new-password-input"]', 'NewPassword123!');
    await page.fill('[data-testid="confirm-new-password-input"]', 'NewPassword123!');
    await page.click('[data-testid="change-password-submit"]');
    
    await expect(page.locator('[data-testid="password-change-success"]')).toBeVisible();
    
    // Logout and login with new password
    await page.click('[data-testid="logout-button"]');
    await page.goto('/login');
    await loginAs(page, { email: 'test-change@test.com', password: 'NewPassword123!' });
    
    // Try to change back to old password
    await page.goto('/app/settings/security');
    await page.fill('[data-testid="current-password-input"]', 'NewPassword123!');
    await page.fill('[data-testid="new-password-input"]', oldPassword);
    await page.fill('[data-testid="confirm-new-password-input"]', oldPassword);
    await page.click('[data-testid="change-password-submit"]');
    
    // Should show error about reusing old password
    await expect(page.locator('[data-testid="password-reuse-error"]')).toBeVisible();
  });

  test('should enforce CSRF protection', async ({ page }) => {
    await page.goto('/app/settings/security');
    
    // Try to submit without CSRF token
    await page.evaluate(() => {
      fetch('/app/settings/security/change-password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          current_password: 'OldPassword123!',
          new_password: 'NewPassword123!',
          confirm_password: 'NewPassword123!',
        }),
      });
    });
    
    // Should reject without CSRF
    await expect(page).not.toHaveURL(/.*success.*/);
  });
});

