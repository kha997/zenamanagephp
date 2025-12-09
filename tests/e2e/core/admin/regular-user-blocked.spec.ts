import { test, expect } from '@playwright/test';
import { authHeaders, login } from '../../helpers/apiClient';

test.describe('Regular User Blocked from Admin', () => {
  test('regular user cannot access any admin pages', async ({ page }) => {
    const session = await login(page.request, 'user@zena.local', 'password');
    
    const adminPages = [
      '/admin/dashboard',
      '/admin/projects',
      '/admin/templates',
      '/admin/analytics',
      '/admin/activities',
      '/admin/settings',
      '/admin/users',
      '/admin/tenants',
      '/admin/security',
      '/admin/maintenance',
    ];
    
    for (const route of adminPages) {
      const response = await page.request.get(route, {
        headers: authHeaders(session.token),
      });
      
      expect(response.status()).toBe(403);
    }
  });

  test('regular user navigation excludes admin items', async ({ page, request }) => {
    const session = await login(request, 'user@zena.local', 'password');
    
    const navResponse = await request.get('/api/v1/me/nav', {
      headers: authHeaders(session.token),
    });
    
    const navBody = await navResponse.json();
    const navItems = navBody.navigation || [];
    
    // Should NOT include any admin items
    const adminPaths = navItems
      .map((item: any) => item.path)
      .filter((path: string) => path.startsWith('/admin'));
    
    expect(adminPaths.length).toBe(0);
  });

  test('regular user redirected when trying to access admin', async ({ page }) => {
    const session = await login(page.request, 'user@zena.local', 'password');
    
    await page.goto('/admin/dashboard', {
      waitUntil: 'networkidle',
    });
    
    // Should be redirected or see 403 error
    const currentUrl = page.url();
    expect(currentUrl).not.toContain('/admin/dashboard');
  });
});

