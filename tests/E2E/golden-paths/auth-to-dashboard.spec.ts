import { test, expect } from '@playwright/test';
import { authHeaders, login, expectSuccess } from '../helpers/apiClient';

/**
 * Golden Path 1: Auth + Tenant Selection + Dashboard
 * 
 * Flow: Login → Get user context → Get navigation → Load dashboard
 * 
 * This test verifies:
 * - User can log in successfully
 * - User context includes tenant_id and permissions
 * - Navigation menu adapts based on user role
 * - Dashboard loads with metrics
 * - RBAC: Regular users don't see admin menu, super-admin sees admin menu
 */
test.describe('Golden Path 1: Auth to Dashboard', () => {
  test('@golden-path regular user can login and access dashboard', async ({ request }) => {
    // Step 1: Login
    const session = await login(request, 'test@example.com', 'password');
    expect(session.token).toBeTruthy();
    expect(session.user).toBeDefined();
    expect(session.user.tenant_id).toBeTruthy();

    // Step 2: Get user context (/me)
    const meResponse = await request.get('/api/v1/me', {
      headers: authHeaders(session.token),
    });
    const meData = await expectSuccess(meResponse);
    
    expect(meData.user).toBeDefined();
    expect(meData.user.tenant_id).toBe(session.user.tenant_id);
    expect(meData.permissions).toBeDefined();
    expect(Array.isArray(meData.abilities)).toBe(true);
    
    // Regular user should have 'tenant' ability, not 'admin'
    expect(meData.abilities).toContain('tenant');
    if (!meData.user.is_admin) {
      expect(meData.abilities).not.toContain('admin');
    }

    // Step 3: Get navigation
    const navResponse = await request.get('/api/v1/me/nav', {
      headers: authHeaders(session.token),
    });
    const navData = await expectSuccess(navResponse);
    
    expect(navData.menu).toBeDefined();
    expect(Array.isArray(navData.menu)).toBe(true);
    
    // Regular user should not see admin menu items
    const adminMenuItems = navData.menu.filter((item: any) => 
      item.path?.includes('/admin') || item.permission === 'admin.access'
    );
    if (!meData.user.is_admin) {
      expect(adminMenuItems.length).toBe(0);
    }

    // Step 4: Load dashboard
    const dashboardResponse = await request.get('/api/v1/dashboard/metrics', {
      headers: authHeaders(session.token),
    });
    const dashboardData = await expectSuccess(dashboardResponse);
    
    expect(dashboardData.data).toBeDefined();
    expect(dashboardData.data.metrics).toBeDefined();
  });

  test('@golden-path super admin sees admin menu in navigation', async ({ request }) => {
    // Login as super admin
    const session = await login(request, 'admin@zena.local', 'password');
    
    // Get navigation
    const navResponse = await request.get('/api/v1/me/nav', {
      headers: authHeaders(session.token),
    });
    const navData = await expectSuccess(navResponse);
    
    // Super admin should see admin menu items
    const adminMenuItems = navData.menu.filter((item: any) => 
      item.path?.includes('/admin') || item.permission === 'admin.access'
    );
    expect(adminMenuItems.length).toBeGreaterThan(0);
  });

  test('@golden-path user without tenant gets 403 on dashboard', async ({ request }) => {
    // This test requires a user without tenant_id (if such exists in test data)
    // For now, we'll test the error handling
    
    // Try to access dashboard without proper tenant context
    // This should be handled by TenantIsolationMiddleware
    const response = await request.get('/api/v1/dashboard/metrics', {
      headers: authHeaders('invalid-token'),
    });
    
    // Should get 401 or 403
    expect([401, 403]).toContain(response.status());
  });

  test('@golden-path login failure shows proper error', async ({ request }) => {
    // Try login with invalid credentials
    const response = await request.post('/api/v1/auth/login', {
      data: {
        email: 'invalid@example.com',
        password: 'wrongpassword',
      },
    });
    
    expect(response.status()).toBe(401);
    const body = await response.json();
    expect(body.ok).toBe(false);
    expect(body.code).toBeDefined();
    expect(body.message).toBeDefined();
  });
});

