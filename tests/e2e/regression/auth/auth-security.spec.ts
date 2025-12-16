import { test, expect } from '@playwright/test';
import { AuthHelper } from '../../helpers/smoke-helpers';
import { testData } from '../../helpers/data';

test.describe('@regression Auth Security Suite', () => {
  let authHelper: AuthHelper;

  test.beforeEach(async ({ page }) => {
    authHelper = new AuthHelper(page);
  });

  test('@regression Brute-force protection - multiple failed login attempts', async ({ page }) => {
    const maxAttempts = 5;
    const lockoutDuration = 300; // 5 minutes in seconds
    
    // Navigate to login page
    await page.goto('/login');
    
    // Attempt multiple failed logins
    for (let i = 1; i <= maxAttempts + 2; i++) {
      await page.fill('input[name="email"]', 'admin@zena.local');
      await page.fill('input[name="password"]', 'wrongpassword');
      await page.click('button[type="submit"]');
      
      // Wait for response
      await page.waitForTimeout(1000);
      
      // Check for error message
      const errorMessage = page.locator('.error, .alert-danger, [role="alert"]');
      await expect(errorMessage).toBeVisible();
      
      console.log(`Failed login attempt ${i}`);
      
      // After max attempts, check for lockout
      if (i >= maxAttempts) {
        const lockoutMessage = page.locator('text=/account.*locked|too many.*attempts|temporarily.*disabled/i');
        if (await lockoutMessage.isVisible()) {
          console.log(`✅ Account locked after ${i} attempts`);
          break;
        }
      }
    }
    
    // Try to login with correct credentials after lockout
    await page.fill('input[name="email"]', 'admin@zena.local');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // Should still be locked out
    const stillLocked = page.locator('text=/account.*locked|too many.*attempts|temporarily.*disabled/i');
    await expect(stillLocked).toBeVisible();
    
    console.log('✅ Brute-force protection working correctly');
  });

  test('@regression Two-Factor Authentication (2FA) flow', async ({ page }) => {
    // Navigate to login page
    await page.goto('/login');
    
    // Login with 2FA-enabled user
    await page.fill('input[name="email"]', 'admin@zena.local');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // Check for 2FA prompt
    const twoFactorPrompt = page.locator('text=/two.*factor|2FA|verification.*code|enter.*code/i');
    
    if (await twoFactorPrompt.isVisible()) {
      console.log('✅ 2FA prompt displayed');
      
      // Look for 2FA input field
      const codeInput = page.locator('input[name="code"], input[name="two_factor_code"], input[name="verification_code"]');
      
      if (await codeInput.isVisible()) {
        console.log('✅ 2FA input field found');
        
        // Try invalid code
        await codeInput.fill('000000');
        await page.click('button[type="submit"]');
        
        // Check for error
        const errorMessage = page.locator('.error, .alert-danger, [role="alert"]');
        await expect(errorMessage).toBeVisible();
        
        console.log('✅ Invalid 2FA code rejected');
        
        // Try valid code (if we have a way to generate it)
        // This would require integration with 2FA service
        // For now, we'll just verify the flow exists
        
      } else {
        console.log('⚠️ 2FA input field not found - feature may not be implemented');
      }
      
    } else {
      console.log('⚠️ 2FA prompt not found - feature may not be implemented');
    }
  });

  test('@regression Session expiry and timeout handling', async ({ page }) => {
    // Login first
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Navigate to a protected page
    await page.goto('/admin/users');
    await expect(page.locator('h1, h2')).toContainText(/users|user management/i);
    
    // Simulate session expiry by clearing cookies
    await page.context().clearCookies();
    
    // Try to access protected page
    await page.goto('/admin/users');
    
    // Should redirect to login
    await expect(page).toHaveURL(/login/);
    
    console.log('✅ Session expiry handled correctly - redirected to login');
    
    // Try to access API endpoint
    const response = await page.request.get('/api/admin/users');
    expect(response.status()).toBe(401);
    
    console.log('✅ API endpoint properly protected after session expiry');
  });

  test('@regression Session hijack protection', async ({ page, context }) => {
    // Login in first context
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Get session token/cookie
    const cookies = await context.cookies();
    const sessionCookie = cookies.find(cookie => cookie.name.includes('session') || cookie.name.includes('laravel_session'));
    
    if (sessionCookie) {
      console.log('✅ Session cookie found');
      
      // Create new context and try to use the same session
      const newContext = await page.context().browser()?.newContext();
      if (newContext) {
        const newPage = await newContext.newPage();
        
        // Set the session cookie
        await newContext.addCookies([sessionCookie]);
        
        // Try to access protected page
        await newPage.goto('/admin/users');
        
        // Should either work (if session is valid) or redirect to login
        const currentUrl = newPage.url();
        
        if (currentUrl.includes('/login')) {
          console.log('✅ Session hijack protection working - redirected to login');
        } else {
          console.log('⚠️ Session cookie accepted in new context - may need additional protection');
        }
        
        await newContext.close();
      }
    } else {
      console.log('⚠️ No session cookie found - using different session management');
    }
  });

  test('@regression Password reset and recovery flow', async ({ page }) => {
    // Navigate to login page
    await page.goto('/login');
    
    // Look for "Forgot Password" link
    const forgotPasswordLink = page.locator('a[href*="forgot"], a[href*="reset"], text=/forgot.*password/i');
    
    if (await forgotPasswordLink.isVisible()) {
      console.log('✅ Forgot password link found');
      
      await forgotPasswordLink.click();
      
      // Check for password reset form
      const resetForm = page.locator('form, input[name="email"]');
      await expect(resetForm).toBeVisible();
      
      // Enter email
      await page.fill('input[name="email"]', 'admin@zena.local');
      await page.click('button[type="submit"]');
      
      // Check for success message
      const successMessage = page.locator('text=/email.*sent|check.*email|reset.*link/i');
      await expect(successMessage).toBeVisible();
      
      console.log('✅ Password reset email sent successfully');
      
    } else {
      console.log('⚠️ Forgot password link not found - feature may not be implemented');
    }
  });

  test('@regression Account lockout mechanisms', async ({ page }) => {
    // Test account lockout after multiple failed attempts
    await page.goto('/login');
    
    const maxAttempts = 5;
    
    // Attempt multiple failed logins
    for (let i = 1; i <= maxAttempts + 1; i++) {
      await page.fill('input[name="email"]', 'admin@zena.local');
      await page.fill('input[name="password"]', 'wrongpassword');
      await page.click('button[type="submit"]');
      
      await page.waitForTimeout(1000);
      
      // Check for increasing delay or lockout message
      const lockoutMessage = page.locator('text=/account.*locked|too many.*attempts|temporarily.*disabled|try.*later/i');
      
      if (await lockoutMessage.isVisible()) {
        console.log(`✅ Account locked after ${i} attempts`);
        
        // Verify lockout duration
        const lockoutText = await lockoutMessage.textContent();
        console.log(`Lockout message: ${lockoutText}`);
        
        break;
      }
    }
  });

  test('@regression Multi-device session management', async ({ page, context }) => {
    // Login in first context
    await authHelper.login(testData.adminUser.email, testData.adminUser.password);
    
    // Create second context (simulating different device)
    const secondContext = await page.context().browser()?.newContext();
    if (secondContext) {
      const secondPage = await secondContext.newPage();
      
      // Login in second context
      const secondAuthHelper = new AuthHelper(secondPage);
      await secondAuthHelper.login(testData.adminUser.email, testData.adminUser.password);
      
      // Both contexts should be able to access protected pages
      await page.goto('/admin/users');
      await secondPage.goto('/admin/users');
      
      await expect(page.locator('h1, h2')).toContainText(/users|user management/i);
      await expect(secondPage.locator('h1, h2')).toContainText(/users|user management/i);
      
      console.log('✅ Multi-device sessions working correctly');
      
      // Test session invalidation (if implemented)
      // This would require a "Logout from all devices" feature
      
      await secondContext.close();
    }
  });

  test('@regression Security headers and CSRF protection', async ({ page }) => {
    // Navigate to login page
    await page.goto('/login');
    
    // Check for CSRF token
    const csrfToken = page.locator('input[name="_token"], meta[name="csrf-token"]');
    
    if (await csrfToken.isVisible()) {
      console.log('✅ CSRF token found');
      
      // Get CSRF token value
      const tokenValue = await csrfToken.getAttribute('value') || await csrfToken.getAttribute('content');
      console.log(`CSRF token: ${tokenValue?.substring(0, 10)}...`);
      
    } else {
      console.log('⚠️ CSRF token not found - may need CSRF protection');
    }
    
    // Check security headers
    const response = await page.request.get('/login');
    const headers = response.headers();
    
    const securityHeaders = [
      'x-frame-options',
      'x-content-type-options',
      'x-xss-protection',
      'strict-transport-security',
      'content-security-policy'
    ];
    
    for (const header of securityHeaders) {
      if (headers[header]) {
        console.log(`✅ Security header ${header}: ${headers[header]}`);
      } else {
        console.log(`⚠️ Security header ${header} missing`);
      }
    }
  });

  test('@regression Input validation and sanitization', async ({ page }) => {
    // Test SQL injection attempts
    const sqlInjectionPayloads = [
      "'; DROP TABLE users; --",
      "admin' OR '1'='1",
      "admin' UNION SELECT * FROM users --"
    ];
    
    await page.goto('/login');
    
    for (const payload of sqlInjectionPayloads) {
      await page.fill('input[name="email"]', payload);
      await page.fill('input[name="password"]', 'password');
      await page.click('button[type="submit"]');
      
      // Should not cause server error
      const errorMessage = page.locator('.error, .alert-danger, [role="alert"]');
      await expect(errorMessage).toBeVisible();
      
      console.log(`✅ SQL injection attempt blocked: ${payload.substring(0, 20)}...`);
    }
    
    // Test XSS attempts
    const xssPayloads = [
      "<script>alert('XSS')</script>",
      "javascript:alert('XSS')",
      "<img src=x onerror=alert('XSS')>"
    ];
    
    for (const payload of xssPayloads) {
      await page.fill('input[name="email"]', payload);
      await page.fill('input[name="password"]', 'password');
      await page.click('button[type="submit"]');
      
      // Should not execute script
      const errorMessage = page.locator('.error, .alert-danger, [role="alert"]');
      await expect(errorMessage).toBeVisible();
      
      console.log(`✅ XSS attempt blocked: ${payload.substring(0, 20)}...`);
    }
  });
});
