import { test, expect } from '@playwright/test';
import { createUser } from '../../auth/helpers/seeds';
import { loginAs } from '../../auth/helpers/auth';

test.describe('Project Status Visibility', () => {
  test('completed projects do not appear in active view', async ({ page, request }) => {
    const testEmail = `test-project-status-${Date.now()}@zena.local`;
    const password = 'TestPassword123!';

    // Create test user
    await createUser({
      email: testEmail,
      password,
      name: 'Test User',
      tenant: 'zena',
      role: 'pm',
      verified: true,
    });

    // Login
    await page.goto('/login');
    await page.fill('input[type="email"]', testEmail);
    await page.fill('input[type="password"]', password);
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/.*dashboard.*/, { timeout: 5000 });

    // Get auth token for API calls
    const loginResponse = await request.post('/api/auth/login', {
      data: {
        email: testEmail,
        password,
      },
    });
    const loginData = await loginResponse.json();
    const token = loginData.data?.token || loginData.token;

    // Create a project via API
    const createProjectResponse = await request.post('/api/v1/app/projects', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      data: {
        name: `Test Project Status ${Date.now()}`,
        description: 'Test project for status visibility',
        status: 'active',
        start_date: new Date().toISOString(),
        end_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString(),
      },
    });

    expect(createProjectResponse.ok()).toBeTruthy();
    const projectData = await createProjectResponse.json();
    const projectId = projectData.data?.id || projectData.id;

    // Navigate to projects list
    await page.goto('/app/projects');

    // Verify project appears in active view (default or when filter is active)
    await page.waitForTimeout(1000);
    const projectName = projectData.data?.name || projectData.name;
    await expect(page.locator(`text=${projectName}`)).toBeVisible({ timeout: 5000 });

    // Change project status to completed via API
    const updateResponse = await request.put(`/api/v1/app/projects/${projectId}`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      data: {
        status: 'completed',
      },
    });

    expect(updateResponse.ok()).toBeTruthy();

    // Refresh page
    await page.reload();
    await page.waitForTimeout(1000);

    // Apply "Active" filter if there's a filter UI
    const activeFilter = page.locator('button:has-text("Active")').or(
      page.locator('[data-testid="filter-active"]')
    );
    if (await activeFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
      await activeFilter.click();
      await page.waitForTimeout(1000);
    } else {
      // If no filter button, navigate directly with status=active
      await page.goto('/app/projects?status=active');
      await page.waitForTimeout(1000);
    }

    // Verify completed project does NOT appear in active view
    await expect(page.locator(`text=${projectName}`)).not.toBeVisible({ timeout: 3000 });

    // Navigate to "All" view (no status filter or explicit "All" filter)
    const allFilter = page.locator('button:has-text("All")').or(
      page.locator('[data-testid="filter-all"]')
    );
    if (await allFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
      await allFilter.click();
    } else {
      // Navigate without status filter
      await page.goto('/app/projects');
    }
    await page.waitForTimeout(1000);

    // Verify completed project DOES appear in "All" view
    await expect(page.locator(`text=${projectName}`)).toBeVisible({ timeout: 5000 });

    // Navigate to "Completed" filter
    const completedFilter = page.locator('button:has-text("Completed")').or(
      page.locator('[data-testid="filter-completed"]')
    );
    if (await completedFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
      await completedFilter.click();
      await page.waitForTimeout(1000);
    } else {
      // Navigate directly with status=completed
      await page.goto('/app/projects?status=completed');
      await page.waitForTimeout(1000);
    }

    // Verify completed project DOES appear in "Completed" filter
    await expect(page.locator(`text=${projectName}`)).toBeVisible({ timeout: 5000 });

    // Change back to active
    const changeToActiveResponse = await request.put(`/api/v1/app/projects/${projectId}`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      data: {
        status: 'active',
      },
    });
    expect(changeToActiveResponse.ok()).toBeTruthy();

    // Refresh page
    await page.reload();
    await page.waitForTimeout(1000);

    // Apply "Active" filter
    if (await activeFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
      await activeFilter.click();
      await page.waitForTimeout(1000);
    } else {
      await page.goto('/app/projects?status=active');
      await page.waitForTimeout(1000);
    }

    // Verify project A now appears in "Open/Active" view
    await expect(page.locator(`text=${projectName}`)).toBeVisible({ timeout: 5000 });
  });

  test('project status transitions: active → on_hold → completed → archived', async ({ page, request }) => {
    const testEmail = `test-project-transitions-${Date.now()}@zena.local`;
    const password = 'TestPassword123!';

    // Create test user
    await createUser({
      email: testEmail,
      password,
      name: 'Test User',
      tenant: 'zena',
      role: 'pm',
      verified: true,
    });

    // Login
    await page.goto('/login');
    await page.fill('input[type="email"]', testEmail);
    await page.fill('input[type="password"]', password);
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/.*dashboard.*/, { timeout: 5000 });

    // Get auth token for API calls
    const loginResponse = await request.post('/api/auth/login', {
      data: {
        email: testEmail,
        password,
      },
    });
    const loginData = await loginResponse.json();
    const token = loginData.data?.token || loginData.token;

    // Create a project via API
    const createProjectResponse = await request.post('/api/v1/app/projects', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      data: {
        name: `Test Project Transitions ${Date.now()}`,
        description: 'Test project for status transitions',
        status: 'active',
        start_date: new Date().toISOString(),
        end_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString(),
      },
    });

    expect(createProjectResponse.ok()).toBeTruthy();
    const projectData = await createProjectResponse.json();
    const projectId = projectData.data?.id || projectData.id;
    const projectName = projectData.data?.name || projectData.name;

    // Step 1: active → on_hold
    const onHoldResponse = await request.put(`/api/v1/app/projects/${projectId}`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      data: {
        status: 'on_hold',
      },
    });
    expect(onHoldResponse.ok()).toBeTruthy();

    // Step 2: on_hold → completed
    const completedResponse = await request.put(`/api/v1/app/projects/${projectId}`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      data: {
        status: 'completed',
      },
    });
    expect(completedResponse.ok()).toBeTruthy();

    // Step 3: Verify "Active/Open" filter doesn't show the project
    await page.goto('/app/projects?status=active');
    await page.waitForTimeout(1000);
    await expect(page.locator(`text=${projectName}`)).not.toBeVisible({ timeout: 3000 });

    // Step 4: Verify "All" filter still shows the project
    await page.goto('/app/projects');
    await page.waitForTimeout(1000);
    await expect(page.locator(`text=${projectName}`)).toBeVisible({ timeout: 5000 });

    // Step 5: completed → archived
    const archivedResponse = await request.put(`/api/v1/app/projects/${projectId}`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      data: {
        status: 'archived',
      },
    });
    expect(archivedResponse.ok()).toBeTruthy();

    // Step 6: Verify archived project appears in "Archived" filter (if exists)
    await page.goto('/app/projects');
    await page.waitForTimeout(1000);
    
    // Check if archived filter exists
    const archivedFilter = page.locator('button:has-text("Archived")').or(
      page.locator('[data-testid="filter-archived"]')
    );
    if (await archivedFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
      await archivedFilter.click();
      await page.waitForTimeout(1000);
      await expect(page.locator(`text=${projectName}`)).toBeVisible({ timeout: 5000 });
    } else {
      // If no archived filter, navigate directly
      await page.goto('/app/projects?status=archived');
      await page.waitForTimeout(1000);
      await expect(page.locator(`text=${projectName}`)).toBeVisible({ timeout: 5000 });
    }
  });
});

