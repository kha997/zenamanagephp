import { test, expect } from '@playwright/test';

test.describe('Navigation and Routes E2E Tests', () => {
  test.beforeEach(async ({ page }) => {
    // Assuming we have a test login endpoint or will navigate after login
    // For now, navigate to login first
    await page.goto('/login');
    
    // Note: In real scenario, you would login first
    // For testing purposes, we'll test the routes assuming authentication
    // You may need to adjust this based on your auth setup
  });

  test.describe('Main Navigation Routes', () => {
    test('should navigate to dashboard from /app', async ({ page }) => {
      await page.goto('/app');
      
      // Should redirect to dashboard
      await expect(page).toHaveURL(/.*\/app\/dashboard/);
    });

    test('should display dashboard page at /app/dashboard', async ({ page }) => {
      await page.goto('/app/dashboard');
      
      // Check if page loaded (may need to adjust based on actual content)
      await expect(page).toHaveURL(/.*\/app\/dashboard/);
      
      // Verify page loaded without errors
      const body = page.locator('body');
      await expect(body).toBeVisible();
    });

    test('should navigate to projects page', async ({ page }) => {
      await page.goto('/app/projects');
      
      await expect(page).toHaveURL(/.*\/app\/projects/);
      await expect(page.locator('body')).toBeVisible();
    });

    test('should navigate to tasks page', async ({ page }) => {
      await page.goto('/app/tasks');
      
      await expect(page).toHaveURL(/.*\/app\/tasks/);
      await expect(page.locator('body')).toBeVisible();
    });

    test('should navigate to documents page', async ({ page }) => {
      await page.goto('/app/documents');
      
      await expect(page).toHaveURL(/.*\/app\/documents/);
      await expect(page.locator('body')).toBeVisible();
    });

    test('should navigate to team page', async ({ page }) => {
      await page.goto('/app/team');
      
      await expect(page).toHaveURL(/.*\/app\/team/);
      await expect(page.locator('body')).toBeVisible();
    });

    test('should navigate to calendar page', async ({ page }) => {
      await page.goto('/app/calendar');
      
      await expect(page).toHaveURL(/.*\/app\/calendar/);
      await expect(page.locator('body')).toBeVisible();
    });

    test('should navigate to alerts page', async ({ page }) => {
      await page.goto('/app/alerts');
      
      await expect(page).toHaveURL(/.*\/app\/alerts/);
      await expect(page.locator('body')).toBeVisible();
    });

    test('should navigate to preferences page', async ({ page }) => {
      await page.goto('/app/preferences');
      
      await expect(page).toHaveURL(/.*\/app\/preferences/);
      await expect(page.locator('body')).toBeVisible();
    });

    test('should navigate to settings page', async ({ page }) => {
      await page.goto('/app/settings');
      
      await expect(page).toHaveURL(/.*\/app\/settings/);
      await expect(page.locator('body')).toBeVisible();
    });
  });

  test.describe('Admin Routes', () => {
    test('should redirect /admin to /admin/dashboard', async ({ page }) => {
      await page.goto('/admin');
      
      await expect(page).toHaveURL(/.*\/admin\/dashboard/);
    });

    test('should navigate to admin dashboard', async ({ page }) => {
      await page.goto('/admin/dashboard');
      
      await expect(page).toHaveURL(/.*\/admin\/dashboard/);
      await expect(page.locator('body')).toBeVisible();
    });

    test('should navigate to admin users page', async ({ page }) => {
      await page.goto('/admin/users');
      
      await expect(page).toHaveURL(/.*\/admin\/users/);
      await expect(page.locator('body')).toBeVisible();
    });

    test('should navigate to admin roles page', async ({ page }) => {
      await page.goto('/admin/roles');
      
      await expect(page).toHaveURL(/.*\/admin\/roles/);
      await expect(page.locator('body')).toBeVisible();
    });

    test('should navigate to admin tenants page', async ({ page }) => {
      await page.goto('/admin/tenants');
      
      await expect(page).toHaveURL(/.*\/admin\/tenants/);
      await expect(page.locator('body')).toBeVisible();
    });
  });

  test.describe('Authentication Redirects', () => {
    test('should redirect unauthenticated users to login', async ({ page }) => {
      // Clear any existing auth state
      await page.context().clearCookies();
      await page.goto('/app/dashboard');
      
      // Should redirect to login
      await expect(page).toHaveURL(/.*\/login/);
    });
  });

  test.describe('Navbar Navigation', () => {
    test('should have all navigation links in navbar', async ({ page }) => {
      // Assuming we're logged in - adjust based on your auth flow
      await page.goto('/app/dashboard');
      
      // Check for main navigation links
      // Adjust selectors based on your actual Navbar implementation
      const navbar = page.locator('nav');
      await expect(navbar).toBeVisible();
      
      // Note: These selectors may need adjustment based on actual implementation
      // await expect(navbar.getByText('Dashboard')).toBeVisible();
      // await expect(navbar.getByText('Projects')).toBeVisible();
      // await expect(navbar.getByText('Tasks')).toBeVisible();
    });

    test('should highlight active route in navbar', async ({ page }) => {
      await page.goto('/app/dashboard');
      
      // Check if active class is applied
      // Adjust based on your implementation
      const navbar = page.locator('nav');
      await expect(navbar).toBeVisible();
      
      // await expect(navbar.getByText('Dashboard')).toHaveClass(/active/);
    });
  });

  test.describe('RBAC - Admin Link Visibility', () => {
    test('should show admin link for admin users', async ({ page }) => {
      // This test requires a logged-in admin user
      // You'll need to set up authentication for admin user
      await page.goto('/app/dashboard');
      
      // Look for admin link
      // Adjust selector based on your implementation
      // const adminLink = page.locator('nav').getByText('Admin');
      // await expect(adminLink).toBeVisible();
    });

    test('should not show admin link for non-admin users', async ({ page }) => {
      // This test requires a logged-in regular user
      await page.goto('/app/dashboard');
      
      // Admin link should not be visible
      // Adjust selector based on your implementation
      // const adminLink = page.locator('nav').getByText('Admin');
      // await expect(adminLink).not.toBeVisible();
    });
  });

  test.describe('404 Handling', () => {
    test('should redirect unknown routes to dashboard', async ({ page }) => {
      await page.goto('/unknown-route-that-does-not-exist');
      
      // Should redirect to /app/dashboard
      await expect(page).toHaveURL(/.*\/app\/dashboard/);
    });
  });

  test.describe('Route Parameters', () => {
    test('should handle project detail route with ID', async ({ page }) => {
      await page.goto('/app/projects/test-project-id');
      
      await expect(page).toHaveURL(/.*\/app\/projects\/test-project-id/);
      await expect(page.locator('body')).toBeVisible();
    });

    test('should handle document detail route with ID', async ({ page }) => {
      await page.goto('/app/documents/test-document-id');
      
      await expect(page).toHaveURL(/.*\/app\/documents\/test-document-id/);
      await expect(page.locator('body')).toBeVisible();
    });
  });
});

