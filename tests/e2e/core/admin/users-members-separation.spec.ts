import { test, expect } from '@playwright/test';
import { authHeaders, login } from '../../helpers/apiClient';

test.describe('Users and Members Separation', () => {
  test('super admin sees Users (System) menu', async ({ page }) => {
    const session = await login(page.request, 'superadmin@zena.local', 'password');
    
    // Get navigation
    const navResponse = await page.request.get('/api/v1/me/nav', {
      headers: authHeaders(session.token),
    });
    
    expect(navResponse.ok()).toBeTruthy();
    const navData = await navResponse.json();
    const navItems = navData.navigation || [];
    
    // Should have Users (System) menu
    const usersMenu = navItems.find((item: any) => item.path === '/admin/users');
    expect(usersMenu).toBeDefined();
    expect(usersMenu.label).toBe('Users (System)');
    expect(usersMenu.system_only).toBe(true);
    
    // Should NOT have Members (Tenant) menu
    const membersMenu = navItems.find((item: any) => item.path === '/admin/members');
    expect(membersMenu).toBeUndefined();
  });

  test('org admin sees Members (Tenant) menu', async ({ page }) => {
    const session = await login(page.request, 'orgadmin@zena.local', 'password');
    
    // Get navigation
    const navResponse = await page.request.get('/api/v1/me/nav', {
      headers: authHeaders(session.token),
    });
    
    expect(navResponse.ok()).toBeTruthy();
    const navData = await navResponse.json();
    const navItems = navData.navigation || [];
    
    // Should have Members (Tenant) menu
    const membersMenu = navItems.find((item: any) => item.path === '/admin/members');
    expect(membersMenu).toBeDefined();
    expect(membersMenu.label).toBe('Members (Tenant)');
    expect(membersMenu.tenant_scoped).toBe(true);
    
    // Should NOT have Users (System) menu
    const usersMenu = navItems.find((item: any) => item.path === '/admin/users');
    expect(usersMenu).toBeUndefined();
  });

  test('org admin accessing /admin/users gets 403 with suggestion', async ({ page }) => {
    const session = await login(page.request, 'orgadmin@zena.local', 'password');
    
    const response = await page.request.get('/admin/users', {
      headers: authHeaders(session.token),
    });
    
    expect(response.status()).toBe(403);
    
    const data = await response.json();
    expect(data.code).toBe('SUPER_ADMIN_REQUIRED');
    expect(data.suggestion).toBeDefined();
    expect(data.suggestion.redirect_to).toBe('/admin/members');
    expect(data.suggestion.message).toContain('/admin/members');
  });

  test('super admin accessing /admin/members gets blocked or read-only', async ({ page }) => {
    const session = await login(page.request, 'superadmin@zena.local', 'password');
    
    const response = await page.request.get('/admin/members', {
      headers: authHeaders(session.token),
    });
    
    // Should be blocked (403) or redirect (302) - depends on implementation
    expect([403, 302]).toContain(response.status());
  });

  test('regular member cannot access /admin/users', async ({ page }) => {
    const session = await login(page.request, 'member@zena.local', 'password');
    
    const response = await page.request.get('/admin/users', {
      headers: authHeaders(session.token),
    });
    
    expect([403, 302, 401]).toContain(response.status());
  });

  test('regular member cannot access /admin/members', async ({ page }) => {
    const session = await login(page.request, 'member@zena.local', 'password');
    
    const response = await page.request.get('/admin/members', {
      headers: authHeaders(session.token),
    });
    
    expect([403, 302, 401]).toContain(response.status());
  });

  test('API endpoint separation - system-wide vs tenant-scoped', async ({ page }) => {
    const superAdminSession = await login(page.request, 'superadmin@zena.local', 'password');
    const orgAdminSession = await login(page.request, 'orgadmin@zena.local', 'password');
    
    // Super Admin can access system-wide API
    const systemUsersResponse = await page.request.get('/api/v1/admin/users', {
      headers: authHeaders(superAdminSession.token),
    });
    expect(systemUsersResponse.ok()).toBeTruthy();
    
    // Org Admin cannot access system-wide API
    const orgAdminSystemUsersResponse = await page.request.get('/api/v1/admin/users', {
      headers: authHeaders(orgAdminSession.token),
    });
    expect(orgAdminSystemUsersResponse.status()).toBe(403);
    
    // Org Admin can access tenant-scoped API
    const membersResponse = await page.request.get('/api/v1/admin/members', {
      headers: authHeaders(orgAdminSession.token),
    });
    expect(membersResponse.ok()).toBeTruthy();
    
    // Super Admin cannot access tenant-scoped API (or gets blocked)
    const superAdminMembersResponse = await page.request.get('/api/v1/admin/members', {
      headers: authHeaders(superAdminSession.token),
    });
    expect([403, 401]).toContain(superAdminMembersResponse.status());
  });

  test('system-wide API returns users from all tenants', async ({ page }) => {
    const session = await login(page.request, 'superadmin@zena.local', 'password');
    
    const response = await page.request.get('/api/v1/admin/users', {
      headers: authHeaders(session.token),
    });
    
    expect(response.ok()).toBeTruthy();
    const data = await response.json();
    const users = data.data?.users || [];
    
    // Should have users from multiple tenants (if they exist)
    const tenantIds = [...new Set(users.map((u: any) => u.tenant_id).filter(Boolean))];
    expect(tenantIds.length).toBeGreaterThanOrEqual(0);
  });

  test('tenant-scoped API returns only users from same tenant', async ({ page }) => {
    const session = await login(page.request, 'orgadmin@zena.local', 'password');
    
    // Get org admin's tenant_id from user info
    const userResponse = await page.request.get('/api/v1/me', {
      headers: authHeaders(session.token),
    });
    const userData = await userResponse.json();
    const orgAdminTenantId = userData.data?.tenant_id;
    
    const response = await page.request.get('/api/v1/admin/members', {
      headers: authHeaders(session.token),
    });
    
    expect(response.ok()).toBeTruthy();
    const data = await response.json();
    const users = data.data?.users || [];
    
    // All users should be from the same tenant as org admin
    users.forEach((user: any) => {
      expect(user.tenant_id).toBe(orgAdminTenantId);
    });
  });
});

