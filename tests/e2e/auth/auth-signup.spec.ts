import { test, expect } from '@playwright/test';
import { MinimalAuthHelper } from '../helpers/auth';
import { createUser } from './helpers/seeds';

const API_BASE_URL = process.env.API_BASE_URL || process.env.BASE_URL || 'http://127.0.0.1:8000';

test.describe('User Signup / Self-registration', () => {
  let authHelper: MinimalAuthHelper;

  test.beforeEach(async ({ page }) => {
    authHelper = new MinimalAuthHelper(page);
  });

  test('happy path: user can register and login with new account', async ({ page }) => {
    // Generate unique test data
    const timestamp = Date.now();
    const random = Math.random().toString(36).substring(7);
    const testEmail = `test-signup-${timestamp}-${random}@test.local`;
    const testPassword = 'SecurePass@2024';
    const testName = 'Test Signup User';
    const testTenantName = `Test Company ${random}`;

    // Step 1: Navigate to register page
    await page.goto('/register');
    await page.waitForLoadState('networkidle');

    // Step 2: Check if signup is enabled (if disabled, page shows message)
    const signupDisabledMessage = page.locator('text=Đăng ký tạm thời bị tắt').or(
      page.locator('text=Public registration is currently disabled')
    );
    
    if (await signupDisabledMessage.isVisible({ timeout: 2000 }).catch(() => false)) {
      test.skip(true, 'Public signup is disabled. Enable PUBLIC_SIGNUP_ENABLED=true for this test.');
      return;
    }

    // Step 3: Fill registration form
    await expect(page.locator('[data-testid="register-form"]').or(page.locator('form'))).toBeVisible({ timeout: 5000 });
    
    await page.fill('[data-testid="register-email"]', testEmail);
    await page.fill('input[id="name"]', testName);
    await page.fill('input[id="tenant_name"]', testTenantName);
    await page.fill('[data-testid="register-password"]', testPassword);
    await page.fill('input[id="password_confirmation"]', testPassword);
    
    // Accept terms
    await page.check('input[id="terms"]');

    // Step 4: Submit form
    await page.click('[data-testid="register-submit"]');

    // Step 5: Wait for success message
    await expect(
      page.locator('[data-testid="register-success"]').or(
        page.locator('text=Đăng ký thành công')
      )
    ).toBeVisible({ timeout: 10000 });

    // Step 6: Verify redirect to login or success message with login link
    const loginLink = page.locator('a:has-text("Đăng nhập ngay")').or(
      page.locator('a[href="/login"]')
    );
    await expect(loginLink).toBeVisible({ timeout: 5000 });

    // Step 7: Click login link or navigate to login
    await loginLink.click();
    await page.waitForURL(/.*login.*/, { timeout: 5000 });

    // Step 8: Login with newly created account
    await authHelper.login(testEmail, testPassword);

    // Step 9: Verify successful login - should redirect to dashboard
    await expect(page).toHaveURL(/.*dashboard.*/, { timeout: 10000 });
    
    // Verify we're logged in
    await expect(authHelper.isLoggedIn()).resolves.toBe(true);
  });

  test('validation: password mismatch shows error', async ({ page }) => {
    const timestamp = Date.now();
    const random = Math.random().toString(36).substring(7);
    const testEmail = `test-signup-validation-${timestamp}-${random}@test.local`;
    const testPassword = 'SecurePass@2024';
    const mismatchedPassword = 'DifferentPass@2024';
    const testName = 'Test Validation User';
    const testTenantName = `Test Company ${random}`;

    // Navigate to register page
    await page.goto('/register');
    await page.waitForLoadState('networkidle');

    // Check if signup is enabled
    const signupDisabledMessage = page.locator('text=Đăng ký tạm thời bị tắt');
    if (await signupDisabledMessage.isVisible({ timeout: 2000 }).catch(() => false)) {
      test.skip(true, 'Public signup is disabled. Enable PUBLIC_SIGNUP_ENABLED=true for this test.');
      return;
    }

    // Fill form with mismatched passwords
    await page.fill('[data-testid="register-email"]', testEmail);
    await page.fill('input[id="name"]', testName);
    await page.fill('input[id="tenant_name"]', testTenantName);
    await page.fill('[data-testid="register-password"]', testPassword);
    await page.fill('input[id="password_confirmation"]', mismatchedPassword);
    await page.check('input[id="terms"]');

    // Try to submit - should show error before submit or disable button
    const submitButton = page.locator('[data-testid="register-submit"]');
    
    // Check if button is disabled (client-side validation)
    const isDisabled = await submitButton.isDisabled();
    if (isDisabled) {
      // Button is disabled, which is correct behavior
      await expect(submitButton).toBeDisabled();
    } else {
      // If button is enabled, click it and check for error
      await submitButton.click();
      
      // Should show error message
      await expect(
        page.locator('[data-testid="register-error"]').or(
          page.locator('text=Mật khẩu xác nhận không khớp')
        )
      ).toBeVisible({ timeout: 5000 });
    }
  });

  test('validation: password too short shows error', async ({ page }) => {
    const timestamp = Date.now();
    const random = Math.random().toString(36).substring(7);
    const testEmail = `test-signup-short-pwd-${timestamp}-${random}@test.local`;
    const shortPassword = 'Short1!'; // Less than 8 characters
    const testName = 'Test Short Password User';
    const testTenantName = `Test Company ${random}`;

    // Navigate to register page
    await page.goto('/register');
    await page.waitForLoadState('networkidle');

    // Check if signup is enabled
    const signupDisabledMessage = page.locator('text=Đăng ký tạm thời bị tắt');
    if (await signupDisabledMessage.isVisible({ timeout: 2000 }).catch(() => false)) {
      test.skip(true, 'Public signup is disabled. Enable PUBLIC_SIGNUP_ENABLED=true for this test.');
      return;
    }

    // Fill form with short password
    await page.fill('[data-testid="register-email"]', testEmail);
    await page.fill('input[id="name"]', testName);
    await page.fill('input[id="tenant_name"]', testTenantName);
    await page.fill('[data-testid="register-password"]', shortPassword);
    await page.fill('input[id="password_confirmation"]', shortPassword);
    await page.check('input[id="terms"]');

    // Submit form
    await page.click('[data-testid="register-submit"]');

    // Should show validation error (either client-side or server-side)
    await expect(
      page.locator('[data-testid="register-error"]').or(
        page.locator('text=Mật khẩu phải có ít nhất 8 ký tự').or(
          page.locator('text=password').or(
            page.locator('.text-red-600, .text-red-800')
          )
        )
      )
    ).toBeVisible({ timeout: 5000 });
  });

  test('feature flag disabled: shows appropriate message', async ({ page }) => {
    // Navigate to register page
    await page.goto('/register');
    await page.waitForLoadState('networkidle');

    // Check if signup is disabled
    const signupDisabledMessage = page.locator('text=Đăng ký tạm thời bị tắt').or(
      page.locator('text=Public registration is currently disabled')
    );
    
    if (await signupDisabledMessage.isVisible({ timeout: 2000 }).catch(() => false)) {
      // Signup is disabled - verify message is shown
      await expect(signupDisabledMessage).toBeVisible();
      
      // Verify there's a link to contact admin or login
      const loginLink = page.locator('a:has-text("Đăng nhập")').or(
        page.locator('a[href="/login"]')
      );
      await expect(loginLink).toBeVisible({ timeout: 2000 });
    } else {
      // Signup is enabled - skip this test
      test.skip(true, 'Public signup is enabled. This test requires PUBLIC_SIGNUP_ENABLED=false.');
    }
  });
});

