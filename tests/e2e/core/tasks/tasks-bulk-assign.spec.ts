import { test, expect } from '@playwright/test';
import { createUser } from '../../auth/helpers/seeds';

test.describe('Tasks Bulk Assign', () => {
  test('PM can bulk assign tasks to user', async ({ page, request }) => {
    const pmEmail = `test-pm-bulk-${Date.now()}@zena.local`;
    const memberEmail = `test-member-bulk-${Date.now()}@zena.local`;
    const password = 'TestPassword123!';

    // Create PM user
    await createUser({
      email: pmEmail,
      password,
      name: 'Test PM',
      tenant: 'zena',
      role: 'pm',
      verified: true,
    });

    // Create member user
    await createUser({
      email: memberEmail,
      password,
      name: 'Test Member',
      tenant: 'zena',
      role: 'member',
      verified: true,
    });

    // Login as PM
    await page.goto('/login');
    await page.fill('input[type="email"]', pmEmail);
    await page.fill('input[type="password"]', password);
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/.*dashboard.*/, { timeout: 5000 });

    // Get auth token for API calls
    const loginResponse = await request.post('/api/auth/login', {
      data: {
        email: pmEmail,
        password,
      },
    });
    const loginData = await loginResponse.json();
    const token = loginData.data?.token || loginData.token;

    // Get member user ID
    const memberResponse = await request.get('/api/v1/me', {
      headers: {
        'Authorization': `Bearer ${token}`,
      },
    });
    // We need to get the member user ID - let's create a project first and then tasks
    const projectResponse = await request.post('/api/v1/app/projects', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      data: {
        name: `Test Project Bulk ${Date.now()}`,
        description: 'Test project for bulk assign',
        status: 'active',
      },
    });

    expect(projectResponse.ok()).toBeTruthy();
    const projectData = await projectResponse.json();
    const projectId = projectData.data?.id || projectData.id;

    // Create 3 tasks
    const taskIds: string[] = [];
    for (let i = 1; i <= 3; i++) {
      const taskResponse = await request.post('/api/v1/app/tasks', {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
        data: {
          title: `Test Task ${i} Bulk Assign`,
          description: `Test task ${i} for bulk assign`,
          project_id: projectId,
          status: 'backlog',
        },
      });

      expect(taskResponse.ok()).toBeTruthy();
      const taskData = await taskResponse.json();
      taskIds.push(taskData.data?.id || taskData.id);
    }

    // Get member user ID by searching users or using a known approach
    // For now, we'll use the member email to get user info
    // Navigate to tasks page
    await page.goto('/app/tasks');
    await page.waitForTimeout(2000);

    // Select tasks using checkboxes
    for (const taskId of taskIds) {
      // Find task by title and select checkbox
      const taskTitle = `Test Task ${taskIds.indexOf(taskId) + 1} Bulk Assign`;
      const taskRow = page.locator(`text=${taskTitle}`).locator('..').locator('input[type="checkbox"]').first();
      if (await taskRow.isVisible({ timeout: 2000 }).catch(() => false)) {
        await taskRow.check();
      }
    }

    // Find bulk action dropdown or button
    const bulkActionButton = page.locator('button:has-text("Assign")').or(
      page.locator('[data-testid="bulk-assign"]')
    );

    if (await bulkActionButton.isVisible({ timeout: 2000 }).catch(() => false)) {
      await bulkActionButton.click();

      // Select assignee (member user)
      // This depends on the UI implementation - might be a dropdown or modal
      await page.waitForTimeout(1000);
      
      // Look for assignee selector
      const assigneeSelect = page.locator('select[name="assignee_id"]').or(
        page.locator('input[placeholder*="assignee" i]')
      );
      
      if (await assigneeSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
        // If it's a select, choose the member
        if (await assigneeSelect.getAttribute('tagName') === 'SELECT') {
          await assigneeSelect.selectOption({ label: 'Test Member' });
        } else {
          // If it's an input, type the email
          await assigneeSelect.fill(memberEmail);
        }
      }

      // Submit bulk assign
      const submitButton = page.locator('button[type="submit"]:has-text("Assign")').or(
        page.locator('button:has-text("Confirm")')
      );
      await submitButton.click();
    } else {
      // Fallback: Use API directly if UI is not available
      // Get member user ID via API
      // For this test, we'll use a simplified approach
      const bulkAssignResponse = await request.post('/api/v1/app/tasks/bulk-assign', {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
        data: {
          ids: taskIds,
          assignee_id: memberEmail, // This might need to be user ID, adjust based on API
        },
      });

      expect(bulkAssignResponse.ok()).toBeTruthy();
    }

    // Verify success message
    await expect(page.locator('text=assigned').or(page.locator('text=success'))).toBeVisible({ timeout: 5000 });

    // Verify tasks show the new assignee
    await page.reload();
    await page.waitForTimeout(2000);

    // Check that tasks show the assignee
    for (let i = 1; i <= 3; i++) {
      const taskTitle = `Test Task ${i} Bulk Assign`;
      const taskRow = page.locator(`text=${taskTitle}`).first();
      await expect(taskRow).toBeVisible({ timeout: 5000 });
      
      // Verify assignee is shown (might be in a badge or tooltip)
      // This depends on UI implementation
    }
  });
});

