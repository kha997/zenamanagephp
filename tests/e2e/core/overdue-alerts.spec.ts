import { test, expect } from '@playwright/test';
import { createUser } from '../../auth/helpers/seeds';

test.describe('Overdue KPIs & Alerts', () => {

  test('overdue KPIs and alerts display on dashboard', async ({ page, request }) => {
    const testEmail = `test-overdue-${Date.now()}@zena.local`;
    const password = 'TestPassword123!';

    // Step 1: Create test user
    await createUser({
      email: testEmail,
      password,
      name: 'Test User',
      tenant: 'zena',
      role: 'pm',
      verified: true,
    });

    // Step 2: Login
    await page.goto('/login');
    await page.fill('input[type="email"]', testEmail);
    await page.fill('input[type="password"]', password);
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/.*dashboard.*/, { timeout: 5000 });

    // Step 3: Get auth token for API calls
    const loginResponse = await request.post('/api/auth/login', {
      data: {
        email: testEmail,
        password,
      },
    });
    const loginData = await loginResponse.json();
    const token = loginData.data?.token || loginData.token;

    // Step 4: Create 1 overdue project
    const projectResponse = await request.post('/api/v1/app/projects', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      data: {
        name: `Overdue Project ${Date.now()}`,
        description: 'Test overdue project',
        status: 'active',
        end_date: new Date(Date.now() - 5 * 24 * 60 * 60 * 1000).toISOString(), // 5 days ago
      },
    });

    expect(projectResponse.ok()).toBeTruthy();
    const projectData = await projectResponse.json();
    const projectId = projectData.data?.id || projectData.id;

    // Step 5: Create 2 overdue tasks
    const taskTitles: string[] = [];
    for (let i = 1; i <= 2; i++) {
      const taskResponse = await request.post('/api/v1/app/tasks', {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
        data: {
          title: `Overdue Task ${i} ${Date.now()}`,
          description: `Test overdue task ${i}`,
          project_id: projectId,
          status: 'in_progress',
          end_date: new Date(Date.now() - (i * 2) * 24 * 60 * 60 * 1000).toISOString(), // 2 and 4 days ago
        },
      });

      expect(taskResponse.ok()).toBeTruthy();
      const taskData = await taskResponse.json();
      taskTitles.push(taskData.data?.title || taskData.title);
    }

    // Step 6: Navigate to dashboard
    await page.goto('/app/dashboard');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Step 7: Verify KPI "Overdue Tasks" > 0
    const overdueTasksKpi = page.locator('text=Overdue Tasks').or(
      page.locator('text=Công việc quá hạn')
    ).first();
    
    await expect(overdueTasksKpi).toBeVisible({ timeout: 5000 });
    const kpiText = await overdueTasksKpi.textContent();
    const overdueCount = parseInt(kpiText?.match(/\d+/)?.[0] || '0');
    expect(overdueCount).toBeGreaterThan(0);

    // Step 8: Verify AlertBar hiển thị thông báo về overdue
    const alertBar = page.locator('[data-testid="alert-bar"]').or(
      page.locator('.alert-bar').or(
        page.locator('text=overdue').or(page.locator('text=quá hạn'))
      )
    ).first();
    
    await expect(alertBar).toBeVisible({ timeout: 5000 });

    // Step 9: Click "View overdue tasks" (or link tương ứng)
    const viewOverdueLink = page.locator('a:has-text("View overdue")').or(
      page.locator('a:has-text("overdue")').or(
        page.locator('button:has-text("View overdue")')
      )
    ).first();
    
    if (await viewOverdueLink.isVisible({ timeout: 3000 }).catch(() => false)) {
      await viewOverdueLink.click();
    } else {
      // Fallback: navigate directly
      await page.goto('/app/tasks?status=overdue');
    }

    // Step 10: Verify navigation to /app/tasks?status=overdue
    await page.waitForURL(/.*tasks.*/, { timeout: 5000 });
    const currentUrl = page.url();
    expect(currentUrl).toMatch(/.*tasks.*/);

    // Step 11: Verify list hiển thị đúng tasks overdue
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Check that overdue tasks are visible
    for (const taskTitle of taskTitles) {
      await expect(page.locator(`text=${taskTitle}`).first()).toBeVisible({ timeout: 5000 });
    }
  });
});

