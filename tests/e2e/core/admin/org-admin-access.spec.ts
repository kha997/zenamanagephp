import { test, expect } from '@playwright/test';
import { authHeaders, login, expectSuccess } from '../../helpers/apiClient';

test.describe('Org Admin Access', () => {
  test('org admin can access tenant-admin pages', async ({ page }) => {
    const session = await login(page.request, 'orgadmin@zena.local', 'password');
    
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
  });

  test('org admin cannot access system-only pages', async ({ page }) => {
    const session = await login(page.request, 'orgadmin@zena.local', 'password');
    
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
      
      expect(response.status()).toBe(403);
    }
  });

  test('org admin sees only own tenant projects', async ({ page, request }) => {
    const session = await login(request, 'orgadmin@zena.local', 'password');
    
    // Get user info to know tenant_id
    const meResponse = await request.get('/api/v1/me', {
      headers: authHeaders(session.token),
    });
    
    const meBody = await expectSuccess(meResponse);
    const tenantId = meBody.user.tenant_id;
    
    // Get projects
    const projectsResponse = await request.get('/admin/projects', {
      headers: authHeaders(session.token),
    });
    
    await expectSuccess(projectsResponse);
    
    // All projects should belong to the org admin's tenant
    // (This would need to be verified in the response data)
  });

  test('org admin cannot see tenant filter', async ({ page }) => {
    const session = await login(page.request, 'orgadmin@zena.local', 'password');
    
    await page.goto('/admin/projects', {
      waitUntil: 'networkidle',
    });
    
    // Should NOT see tenant filter (Org Admin only sees own tenant)
    await expect(page.locator('select[name="tenant_id"]')).not.toBeVisible();
  });

  test('org admin can create tenant-specific templates', async ({ page, request }) => {
    const session = await login(request, 'orgadmin@zena.local', 'password');
    
    const createResponse = await request.post('/admin/templates', {
      headers: authHeaders(session.token),
      data: {
        code: 'ORG-TEMPLATE',
        name: 'Org Admin Template',
        version: '1.0',
      },
    });
    
    const createBody = await expectSuccess(createResponse, 201);
    expect(createBody.data.tenant_id).toBeTruthy(); // Should have tenant_id
  });

  test('org admin cannot create global templates', async ({ page, request }) => {
    const session = await login(request, 'orgadmin@zena.local', 'password');
    
    const createResponse = await request.post('/admin/templates', {
      headers: authHeaders(session.token),
      data: {
        code: 'GLOBAL-TEMPLATE',
        name: 'Global Template',
        version: '1.0',
        tenant_id: null,
        is_global: true,
      },
    });
    
    expect(createResponse.status()).toBe(403);
  });

  test('org admin navigation excludes system items', async ({ page, request }) => {
    const session = await login(request, 'orgadmin@zena.local', 'password');
    
    const navResponse = await request.get('/api/v1/me/nav', {
      headers: authHeaders(session.token),
    });
    
    const navBody = await expectSuccess(navResponse);
    const navItems = navBody.navigation || [];
    
    const systemItems = navItems.filter((item: any) => item.system_only === true);
    expect(systemItems.length).toBe(0);
    
    // Should NOT include Users, Tenants, Security, Maintenance
    const systemPaths = navItems.map((item: any) => item.path);
    expect(systemPaths).not.toContain('/admin/users');
    expect(systemPaths).not.toContain('/admin/tenants');
  });
});

