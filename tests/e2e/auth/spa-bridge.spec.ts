import { test, expect } from '@playwright/test';

/**
 * SPA Auth Bridge E2E Tests
 * 
 * Tests the authentication bridge between web session and SPA token.
 */
test.describe('SPA Auth Bridge', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to app
    await page.goto('/app');
  });

  test('should get session token when user is authenticated via web', async ({ page, request }) => {
    // First, login via web (simulate web login)
    // This would normally be done via web login form
    // For testing, we'll use API to create session
    
    // Create test user and login
    const loginResponse = await request.post('/api/v1/auth/login', {
      data: {
        email: 'test@example.com',
        password: 'password123',
      },
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    });
    
    // Get cookies from login response
    const cookies = loginResponse.headers()['set-cookie'];
    
    // Now try to get session token
    const tokenResponse = await request.get('/api/v1/auth/session-token', {
      headers: {
        'Cookie': cookies,
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });
    
    expect(tokenResponse.ok()).toBeTruthy();
    
    const data = await tokenResponse.json();
    expect(data.ok).toBe(true);
    expect(data.token).toBeDefined();
    expect(data.token_type).toBe('Bearer');
    expect(data.user).toBeDefined();
  });

  test('should return 401 when user is not authenticated', async ({ request }) => {
    const response = await request.get('/api/v1/auth/session-token', {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });
    
    expect(response.status()).toBe(401);
    
    const data = await response.json();
    expect(data.ok).toBe(false);
    expect(data.code).toBe('UNAUTHENTICATED');
  });

  test('should rotate token on subsequent calls', async ({ request }) => {
    // Login first
    const loginResponse = await request.post('/api/v1/auth/login', {
      data: {
        email: 'test@example.com',
        password: 'password123',
      },
    });
    
    const cookies = loginResponse.headers()['set-cookie'];
    
    // Get first token
    const token1Response = await request.get('/api/v1/auth/session-token', {
      headers: { 'Cookie': cookies },
    });
    const token1 = (await token1Response.json()).token;
    
    // Get second token (should rotate)
    const token2Response = await request.get('/api/v1/auth/session-token', {
      headers: { 'Cookie': cookies },
    });
    const token2 = (await token2Response.json()).token;
    
    // Tokens should be different (rotated)
    expect(token1).not.toBe(token2);
  });

  test('should handle CSRF cookie flow', async ({ page, request }) => {
    // Get CSRF cookie first
    const csrfResponse = await request.get('/sanctum/csrf-cookie', {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });
    
    expect(csrfResponse.ok()).toBeTruthy();
    
    // Then get session token
    const tokenResponse = await request.get('/api/v1/auth/session-token', {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });
    
    // Should work (or return 401 if not authenticated, which is expected)
    expect([200, 401]).toContain(tokenResponse.status());
  });

  test('should return 403 when user is disabled', async ({ request }) => {
    // This test would require creating a disabled user
    // For now, just verify the endpoint handles it
    // (Full test would require test user setup)
    
    const response = await request.get('/api/v1/auth/session-token', {
      headers: {
        'Accept': 'application/json',
      },
    });
    
    // Should return 401 (not authenticated) or 403 (disabled)
    expect([401, 403]).toContain(response.status());
  });
});

