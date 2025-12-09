import { test, expect } from '@playwright/test';
import { authHeaders, login, expectSuccess } from '../../helpers/apiClient';

test.describe('Super Admin Access', () => {
  test('super admin can access all admin pages', async ({ page }) => {
    // Login as super admin
    const session = await login(page.request, 'superadmin@zena.local', 'password');
    
    // Test access to tenant-admin pages
    const tenantAdminPages = [
      '/admin/dashboard',
      '/admin/projects',
      '/admin/templates',
      '/admin/analytics',
      '/admin/activities',
      '/admin/settings',
    ];
    
    for (const route of tenantAdminPages) {
      const response = await page.request.get(route, {
        headers: authHeaders(session.token),
      });
      
      expect(response.status()).toBeLessThan(400);
    }
    
    // Test access to system-only pages
    const systemPages = [
      '/admin/users',
      '/admin/tenants',
      '/admin/security',
      '/admin/maintenance',
    ];
    
    for (const route of systemPages) {
      const response = await page.request.get(route, {
        headers: authHeaders(session.token),
      });
      
      expect(response.status()).toBeLessThan(400);
    }
  });

  test('super admin sees all tenants in projects list', async ({ page }) => {
    const session = await login(page.request, 'superadmin@zena.local', 'password');
    
    await page.goto('/admin/projects', {
      waitUntil: 'networkidle',
    });
    
    // Should see tenant filter (Super Admin only)
    await expect(page.locator('select[name="tenant_id"]')).toBeVisible();
  });

  test('super admin can freeze any project', async ({ page, request }) => {
    const session = await login(request, 'superadmin@zena.local', 'password');
    
    // Get a project (any tenant)
    const projectsResponse = await request.get('/api/v1/app/projects', {
      headers: authHeaders(session.token),
    });
    
    const projectsBody = await expectSuccess(projectsResponse);
    const projectId = projectsBody.data?.[0]?.id;
    
    if (projectId) {
      const freezeResponse = await request.post(`/admin/projects/${projectId}/freeze`, {
        headers: authHeaders(session.token),
        data: {
          reason: 'E2E test freeze',
        },
      });
      
      await expectSuccess(freezeResponse);
    }
  });

  test('super admin navigation includes system items', async ({ page, request }) => {
    const session = await login(request, 'superadmin@zena.local', 'password');
    
    const navResponse = await request.get('/api/v1/me/nav', {
      headers: authHeaders(session.token),
    });
    
    const navBody = await expectSuccess(navResponse);
    const navItems = navBody.navigation || [];
    
    const systemItems = navItems.filter((item: any) => item.system_only === true);
    expect(systemItems.length).toBeGreaterThan(0);
    
    // Should include Users, Tenants, Security, Maintenance
    const systemPaths = systemItems.map((item: any) => item.path);
    expect(systemPaths).toContain('/admin/users');
    expect(systemPaths).toContain('/admin/tenants');
  });
});

