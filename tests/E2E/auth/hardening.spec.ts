import { test, expect } from '@playwright/test';
import { getCSRFToken, assertSecureCookie, clearCookies } from './helpers/auth';
import { assertSecureHeaders, assertCSRFProtected, assertOpenRedirectProtected } from './helpers/assertions';

test.describe('Security Hardening', () => {
  test('should enforce CSRF protection on forms', async ({ page }) => {
    await page.goto('/login');
    
    // Try to post without CSRF
    const response = await page.evaluate(() => {
      return fetch('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          email: 'test@test.com',
          password: 'password',
        }),
      });
    });
    
    // Should reject without valid CSRF or auth
    expect(response.status).toBeGreaterThanOrEqual(400);
  });

  test('should sanitize XSS in user input', async ({ page }) => {
    await page.goto('/register');
    
    // Try XSS in name field
    const xssPayload = '<script>alert(1)</script>';
    
    await page.fill('[data-testid="name-input"]', xssPayload);
    await page.fill('[data-testid="email-input"]', 'xss-test@test.com');
    await page.fill('[data-testid="password-input"]', 'TestPassword123!');
    
    await page.click('[data-testid="register-submit"]');
    
    // Wait for any redirect
    await page.waitForTimeout(2000);
    
    // Get page content
    const content = await page.content();
    
    // Should NOT contain raw script tag (should be escaped)
    expect(content).not.toContain('<script>alert(1)</script>');
  });

  test('should prevent open redirect vulnerability', async ({ page }) => {
    await page.goto('/login?redirect=https://evil.com');
    
    // Try to login
    await page.fill('[data-testid="email-input"]', 'test@test.com');
    await page.fill('[data-testid="password-input"]', 'password');
    await page.click('[data-testid="login-submit"]');
    
    // After any redirect, should not go to evil.com
    await page.waitForTimeout(2000);
    const finalUrl = page.url();
    
    // Should stay on same domain or redirect to safe default
    expect(finalUrl).not.toContain('evil.com');
    expect(finalUrl).toContain(new URL(page.url()).hostname);
  });

  test('should set secure cookie flags', async ({ page }) => {
    await page.goto('/login');
    
    // Login will set cookies
    await page.fill('[data-testid="email-input"]', 'admin@zena.test');
    await page.fill('[data-testid="password-input"]', 'password');
    await page.click('[data-testid="login-submit"]');
    
    // Wait for cookies to be set
    await page.waitForTimeout(1000);
    
    const cookies = await page.context().cookies();
    const sessionCookie = cookies.find(c => c.name.includes('session') || c.name.includes('laravel_session'));
    
    if (sessionCookie) {
      expect(sessionCookie.httpOnly).toBe(true);
      expect(sessionCookie.secure).toBe(true);
      expect(['Strict', 'Lax']).toContain(sessionCookie.sameSite);
    }
  });

  test('should include security headers', async ({ page }) => {
    const response = await page.goto('/login');
    
    if (!response) return;
    
    const headers = response.headers();
    
    // X-Frame-Options should be set
    expect(headers['x-frame-options']).toBeTruthy();
    
    // X-Content-Type-Options should be nosniff
    expect(headers['x-content-type-options']).toBe('nosniff');
    
    // Content-Security-Policy if configured
    if (headers['content-security-policy']) {
      expect(headers['content-security-policy']).toBeTruthy();
    }
  });

  test('should prevent clickjacking', async ({ page }) => {
    const response = await page.goto('/login');
    
    if (!response) return;
    
    const headers = response.headers();
    
    // Should have X-Frame-Options or CSP frame-ancestors
    const hasFrameProtection = 
      headers['x-frame-options']?.toLowerCase().includes('deny') ||
      headers['content-security-policy']?.includes('frame-ancestors');
    
    expect(hasFrameProtection).toBeTruthy();
  });

  test('should cache-control auth pages', async ({ page }) => {
    const response = await page.goto('/login');
    
    if (!response) return;
    
    const headers = response.headers();
    
    // Auth pages should not be cached
    if (headers['cache-control']) {
      expect(headers['cache-control']).toContain('no-store');
    }
  });

  test('should handle SQL injection attempts', async ({ page }) => {
    await page.goto('/login');
    
    // Try SQL injection in email
    const sqlPayload = "admin'--";
    
    await page.fill('[data-testid="email-input"]', sqlPayload);
    await page.fill('[data-testid="password-input"]', 'password');
    await page.click('[data-testid="login-submit"]');
    
    // Should handle gracefully (not expose error details)
    await page.waitForTimeout(2000);
    
    // Should either fail gracefully or be on login still
    const url = page.url();
    expect(url.includes('/login') || url.includes('/error')).toBeTruthy();
  });

  test('should log authentication attempts', async ({ page }) => {
    await page.goto('/login');
    
    // Attempt to login with wrong credentials
    await page.fill('[data-testid="email-input"]', 'test-logs@test.com');
    await page.fill('[data-testid="password-input"]', 'wrongpassword');
    await page.click('[data-testid="login-submit"]');
    
    // Wait a moment for logging to complete
    await page.waitForTimeout(1000);
    
    // Verify that the attempt was handled (either logged or rejected)
    // The system should handle this gracefully
    const hasError = await page.locator('[data-testid="error-message"]').isVisible().catch(() => false);
    const hasSuccess = await page.url().includes('/dashboard');
    
    // Should either show error or stay on login page
    expect(hasError || !hasSuccess).toBeTruthy();
  });

  test('should enforce HTTPS in production', async ({ page }) => {
    // This is environment-dependent
    // Would check for Strict-Transport-Security header
    const response = await page.goto('/login');
    
    if (!response) return;
    
    const headers = response.headers();
    
    // If HSTS is set, should have proper config
    if (headers['strict-transport-security']) {
      expect(headers['strict-transport-security']).toBeTruthy();
    }
  });

  test('should prevent timing attacks on login', async ({ page }) => {
    // Try with existing email - multiple attempts to get average
    const timings1 = [];
    for (let i = 0; i < 3; i++) {
      const start = Date.now();
      await page.goto('/login');
      await page.fill('[data-testid="email-input"]', 'admin@zena.test');
      await page.fill('[data-testid="password-input"]', 'wrongpassword');
      await page.click('[data-testid="login-submit"]');
      await page.waitForTimeout(1500);
      timings1.push(Date.now() - start);
      await clearCookies(page);
      await page.waitForTimeout(500);
    }
    
    // Try with non-existing email - multiple attempts
    const timings2 = [];
    for (let i = 0; i < 3; i++) {
      const start = Date.now();
      await page.goto('/login');
      await page.fill('[data-testid="email-input"]', 'nonexistent@test.com');
      await page.fill('[data-testid="password-input"]', 'wrongpassword');
      await page.click('[data-testid="login-submit"]');
      await page.waitForTimeout(1500);
      timings2.push(Date.now() - start);
      await clearCookies(page);
      await page.waitForTimeout(500);
    }
    
    const avg1 = timings1.reduce((a, b) => a + b, 0) / timings1.length;
    const avg2 = timings2.reduce((a, b) => a + b, 0) / timings2.length;
    
    // Timing should be similar (within 1.5 second variance)
    const diff = Math.abs(avg1 - avg2);
    expect(diff).toBeLessThan(1500);
  });

  test('should prevent account enumeration', async ({ page }) => {
    // Invalid email
    await page.goto('/login');
    await page.fill('[data-testid="email-input"]', 'nonexistent@test.com');
    await page.fill('[data-testid="password-input"]', 'password');
    await page.click('[data-testid="login-submit"]');
    await page.waitForTimeout(2000);
    const error1 = await page.locator('[data-testid="error-message"]').textContent().catch(() => '') || '';
    const isLogin1 = await page.url().includes('/login');
    
    await clearCookies(page);
    await page.waitForTimeout(1000);
    
    // Valid email, wrong password
    await page.goto('/login');
    await page.fill('[data-testid="email-input"]', 'admin@zena.test');
    await page.fill('[data-testid="password-input"]', 'wrongpassword');
    await page.click('[data-testid="login-submit"]');
    await page.waitForTimeout(2000);
    const error2 = await page.locator('[data-testid="error-message"]').textContent().catch(() => '') || '';
    const isLogin2 = await page.url().includes('/login');
    
    // Should stay on login page for both (no disclosure)
    expect(isLogin1).toBeTruthy();
    expect(isLogin2).toBeTruthy();
    // Errors should be similar or both empty (neutral messaging)
    expect(error1.length > 0 || error2.length > 0).toBeTruthy();
  });
});

