import { test, expect } from '@playwright/test';
import { createUser } from '../../auth/helpers/seeds';
import { loginAs } from '../../auth/helpers/auth';

test.describe('Project Status Flow', () => {
  test('project status transitions: planning → active → on_hold → completed', async ({ page, request }) => {
    const testEmail = `test-status-flow-${Date.now()}@zena.local`;
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

    // Create a project with planning status via API
    const createProjectResponse = await request.post('/api/v1/app/projects', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      data: {
        name: `Test Project Flow ${Date.now()}`,
        description: 'Test project for status flow',
        status: 'planning',
        start_date: new Date().toISOString(),
        end_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString(),
      },
    });

    expect(createProjectResponse.ok()).toBeTruthy();
    const projectData = await createProjectResponse.json();
    const projectId = projectData.data?.id || projectData.id;
    const projectName = projectData.data?.name || projectData.name;

    // Navigate to projects list
    await page.goto('/app/projects');
    await page.waitForTimeout(1000);

    // Verify project appears
    await expect(page.locator(`text=${projectName}`)).toBeVisible({ timeout: 5000 });

    // Step 1: planning → active
    const activeResponse = await request.put(`/api/v1/app/projects/${projectId}`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      data: {
        status: 'active',
      },
    });
    expect(activeResponse.ok()).toBeTruthy();

    // Refresh and verify in active filter
    await page.reload();
    await page.waitForTimeout(1000);
    await page.goto('/app/projects?status=active');
    await page.waitForTimeout(1000);
    await expect(page.locator(`text=${projectName}`)).toBeVisible({ timeout: 5000 });

    // Step 2: active → on_hold
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

    // Step 3: on_hold → completed
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

    // Step 4: Verify "Open/Active" filter does NOT show project
    await page.goto('/app/projects?status=active');
    await page.waitForTimeout(1000);
    await expect(page.locator(`text=${projectName}`)).not.toBeVisible({ timeout: 3000 });

    // Step 5: Verify "All" filter shows project
    await page.goto('/app/projects');
    await page.waitForTimeout(1000);
    await expect(page.locator(`text=${projectName}`)).toBeVisible({ timeout: 5000 });

    // Step 6: Verify "Completed" filter shows project
    await page.goto('/app/projects?status=completed');
    await page.waitForTimeout(1000);
    await expect(page.locator(`text=${projectName}`)).toBeVisible({ timeout: 5000 });
  });

  test('project cannot be completed from planning if has unfinished tasks', async ({ page, request }) => {
    const testEmail = `test-conditional-block-${Date.now()}@zena.local`;
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

    // Get auth token
    const loginResponse = await request.post('/api/auth/login', {
      data: {
        email: testEmail,
        password,
      },
    });
    const loginData = await loginResponse.json();
    const token = loginData.data?.token || loginData.token;

    // Create project with planning status
    const createProjectResponse = await request.post('/api/v1/app/projects', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      data: {
        name: `Test Conditional Block ${Date.now()}`,
        description: 'Test conditional transition block',
        status: 'planning',
      },
    });

    expect(createProjectResponse.ok()).toBeTruthy();
    const projectData = await createProjectResponse.json();
    const projectId = projectData.data?.id || projectData.id;

    // Create unfinished task (in_progress)
    const createTaskResponse = await request.post('/api/v1/app/tasks', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      data: {
        project_id: projectId,
        name: 'Unfinished Task',
        status: 'in_progress',
      },
    });
    expect(createTaskResponse.ok()).toBeTruthy();

    // Try to complete project - should fail
    const completeResponse = await request.put(`/api/v1/app/projects/${projectId}`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      data: {
        status: 'completed',
      },
    });

    expect(completeResponse.status()).toBe(422);
    const errorData = await completeResponse.json();
    expect(errorData.error?.code || errorData.code).toMatch(/unfinished|has_unfinished_tasks/i);
  });

  test('project cannot be moved to planning from active if has active tasks', async ({ page, request }) => {
    const testEmail = `test-active-tasks-block-${Date.now()}@zena.local`;
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

    // Get auth token
    const loginResponse = await request.post('/api/auth/login', {
      data: {
        email: testEmail,
        password,
      },
    });
    const loginData = await loginResponse.json();
    const token = loginData.data?.token || loginData.token;

    // Create project with active status
    const createProjectResponse = await request.post('/api/v1/app/projects', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      data: {
        name: `Test Active Tasks Block ${Date.now()}`,
        description: 'Test active tasks block',
        status: 'active',
      },
    });

    expect(createProjectResponse.ok()).toBeTruthy();
    const projectData = await createProjectResponse.json();
    const projectId = projectData.data?.id || projectData.id;

    // Create active task (in_progress)
    const createTaskResponse = await request.post('/api/v1/app/tasks', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      data: {
        project_id: projectId,
        name: 'Active Task',
        status: 'in_progress',
      },
    });
    expect(createTaskResponse.ok()).toBeTruthy();

    // Try to move to planning - should fail
    const planningResponse = await request.put(`/api/v1/app/projects/${projectId}`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      data: {
        status: 'planning',
      },
    });

    expect(planningResponse.status()).toBe(422);
    const errorData = await planningResponse.json();
    expect(errorData.error?.code || errorData.code).toMatch(/active.*tasks|has_active_tasks/i);
  });

  test('archived project cannot be changed (terminal state)', async ({ page, request }) => {
    const testEmail = `test-archived-terminal-${Date.now()}@zena.local`;
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

    // Get auth token
    const loginResponse = await request.post('/api/auth/login', {
      data: {
        email: testEmail,
        password,
      },
    });
    const loginData = await loginResponse.json();
    const token = loginData.data?.token || loginData.token;

    // Create project and archive it
    const createProjectResponse = await request.post('/api/v1/app/projects', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      data: {
        name: `Test Archived Terminal ${Date.now()}`,
        description: 'Test archived terminal state',
        status: 'completed',
      },
    });

    expect(createProjectResponse.ok()).toBeTruthy();
    const projectData = await createProjectResponse.json();
    const projectId = projectData.data?.id || projectData.id;

    // Archive the project
    const archiveResponse = await request.put(`/api/v1/app/projects/${projectId}`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      data: {
        status: 'archived',
      },
    });
    expect(archiveResponse.ok()).toBeTruthy();

    // Try to change from archived - should fail
    const changeResponse = await request.put(`/api/v1/app/projects/${projectId}`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      data: {
        status: 'active',
      },
    });

    expect(changeResponse.status()).toBe(422);
    const errorData = await changeResponse.json();
    expect(errorData.error?.code || errorData.code).toMatch(/invalid.*transition|terminal/i);
  });
});

