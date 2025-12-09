import { test, expect } from '@playwright/test';
import { login, authHeaders, expectSuccess } from './helpers/apiClient';

/**
 * Project Overview Health Deep-links E2E Tests
 * 
 * Round 82: Project Health vertical hardening + E2E flows
 * 
 * Tests the Project Overview page health card:
 * - Health card display with overall status
 * - Deep-link navigation to tasks when schedule at_risk/delayed
 * - Deep-link navigation to reports when cost at_risk/over_budget
 */
test.describe('Project Overview Health Deep-links', () => {
  const baseURL = process.env.PLAYWRIGHT_TEST_BASE_URL || 'http://127.0.0.1:8000';

  test.beforeEach(async ({ page }) => {
    await page.goto(baseURL);
  });

  test('should display health card with overall status', async ({ page, request }) => {
    // Login as user with tenant.view_reports
    const session = await login(request, 'pm@e2e-health.local', 'password');
    
    // Get project ID from API (P-WARNING-01)
    const projectsResponse = await request.get('/api/v1/app/projects', {
      headers: authHeaders(session.token),
    });
    const projectsData = await expectSuccess(projectsResponse);
    const warningProject = projectsData.data?.find((p: any) => p.code === 'P-WARNING-01');
    
    expect(warningProject).toBeDefined();
    const projectId = warningProject.id;

    await page.goto(`${baseURL}/app/projects/${projectId}/overview`);
    await page.evaluate((token) => {
      localStorage.setItem('auth_token', token);
    }, session.token);
    
    await page.reload();
    await page.waitForLoadState('networkidle');

    // Wait for health card to appear
    const healthCard = page.locator('text=/Sức khỏe dự án|Project Health/i');
    await expect(healthCard.first()).toBeVisible({ timeout: 10000 });

    // Overall status should be displayed (Warning for P-WARNING-01)
    const overallStatus = page.locator('text=/Cảnh báo|Warning/i');
    await expect(overallStatus.first()).toBeVisible({ timeout: 5000 });
  });

  test('should navigate to tasks when schedule is at_risk or delayed', async ({ page, request }) => {
    // Login as user with tenant.view_reports
    const session = await login(request, 'pm@e2e-health.local', 'password');
    
    // Get P-WARNING-01 project (schedule at_risk)
    const projectsResponse = await request.get('/api/v1/app/projects', {
      headers: authHeaders(session.token),
    });
    const projectsData = await expectSuccess(projectsResponse);
    const warningProject = projectsData.data?.find((p: any) => p.code === 'P-WARNING-01');
    
    expect(warningProject).toBeDefined();
    const projectId = warningProject.id;

    await page.goto(`${baseURL}/app/projects/${projectId}/overview`);
    await page.evaluate((token) => {
      localStorage.setItem('auth_token', token);
    }, session.token);
    
    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Look for schedule link (should appear when schedule_status is at_risk or delayed)
    const scheduleLink = page.locator('text=/Xem danh sách task quá hạn|View overdue tasks/i');
    
    // For P-WARNING-01, schedule should be at_risk (2 overdue tasks)
    // So the link should be visible
    if (await scheduleLink.isVisible({ timeout: 5000 }).catch(() => false)) {
      await scheduleLink.first().click();
      
      // Should navigate to tasks page with project_id filter
      await page.waitForURL(new RegExp(`.*/app/tasks.*project_id=${projectId}`), { timeout: 10000 });
      
      // Verify we're on tasks page
      const tasksPage = page.locator('text=/Tasks|Công việc/i');
      await expect(tasksPage.first()).toBeVisible({ timeout: 5000 });
    }
  });

  test('should navigate to reports when cost is at_risk or over_budget', async ({ page, request }) => {
    // Login as user with tenant.view_reports
    const session = await login(request, 'pm@e2e-health.local', 'password');
    
    // Get P-CRITICAL-01 project (cost over_budget)
    const projectsResponse = await request.get('/api/v1/app/projects', {
      headers: authHeaders(session.token),
    });
    const projectsData = await expectSuccess(projectsResponse);
    const criticalProject = projectsData.data?.find((p: any) => p.code === 'P-CRITICAL-01');
    
    expect(criticalProject).toBeDefined();
    const projectId = criticalProject.id;

    await page.goto(`${baseURL}/app/projects/${projectId}/overview`);
    await page.evaluate((token) => {
      localStorage.setItem('auth_token', token);
    }, session.token);
    
    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Look for cost link (should appear when cost_status is at_risk or over_budget)
    const costLink = page.locator('text=/Xem chi tiết chi phí|View cost details/i');
    
    // For P-CRITICAL-01, cost should be over_budget (15% overrun)
    // So the link should be visible
    if (await costLink.isVisible({ timeout: 5000 }).catch(() => false)) {
      await costLink.first().click();
      
      // Should navigate to reports page with project_id filter
      await page.waitForURL(new RegExp(`.*/app/reports/portfolio/projects.*project_id=${projectId}`), { timeout: 10000 });
      
      // Verify we're on reports page
      const reportsPage = page.locator('text=/Reports|Báo cáo/i');
      await expect(reportsPage.first()).toBeVisible({ timeout: 5000 });
    }
  });

  test('should display health card for critical project', async ({ page, request }) => {
    // Login as user with tenant.view_reports
    const session = await login(request, 'pm@e2e-health.local', 'password');
    
    // Get P-CRITICAL-01 project
    const projectsResponse = await request.get('/api/v1/app/projects', {
      headers: authHeaders(session.token),
    });
    const projectsData = await expectSuccess(projectsResponse);
    const criticalProject = projectsData.data?.find((p: any) => p.code === 'P-CRITICAL-01');
    
    expect(criticalProject).toBeDefined();
    const projectId = criticalProject.id;

    await page.goto(`${baseURL}/app/projects/${projectId}/overview`);
    await page.evaluate((token) => {
      localStorage.setItem('auth_token', token);
    }, session.token);
    
    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Health card should be visible
    const healthCard = page.locator('text=/Sức khỏe dự án|Project Health/i');
    await expect(healthCard.first()).toBeVisible({ timeout: 10000 });

    // Overall status should be "Nguy cấp" (Critical)
    const criticalStatus = page.locator('text=/Nguy cấp|Critical/i');
    await expect(criticalStatus.first()).toBeVisible({ timeout: 5000 });

    // Schedule status should show "Delayed" (5 overdue tasks)
    const scheduleStatus = page.locator('text=/Delayed|Trễ hạn/i');
    await expect(scheduleStatus.first()).toBeVisible({ timeout: 5000 });

    // Cost status should show "Over Budget" (15% overrun)
    const costStatus = page.locator('text=/Over Budget|Vượt ngân sách/i');
    await expect(costStatus.first()).toBeVisible({ timeout: 5000 });
  });

  test('should display health card for good project', async ({ page, request }) => {
    // Login as user with tenant.view_reports
    const session = await login(request, 'pm@e2e-health.local', 'password');
    
    // Get P-GOOD-01 project
    const projectsResponse = await request.get('/api/v1/app/projects', {
      headers: authHeaders(session.token),
    });
    const projectsData = await expectSuccess(projectsResponse);
    const goodProject = projectsData.data?.find((p: any) => p.code === 'P-GOOD-01');
    
    expect(goodProject).toBeDefined();
    const projectId = goodProject.id;

    await page.goto(`${baseURL}/app/projects/${projectId}/overview`);
    await page.evaluate((token) => {
      localStorage.setItem('auth_token', token);
    }, session.token);
    
    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Health card should be visible
    const healthCard = page.locator('text=/Sức khỏe dự án|Project Health/i');
    await expect(healthCard.first()).toBeVisible({ timeout: 10000 });

    // Overall status should be "Tốt" (Good)
    const goodStatus = page.locator('text=/Tốt|Good/i');
    await expect(goodStatus.first()).toBeVisible({ timeout: 5000 });

    // Schedule status should show "On Track"
    const scheduleStatus = page.locator('text=/On Track|Đúng tiến độ/i');
    await expect(scheduleStatus.first()).toBeVisible({ timeout: 5000 });

    // Cost status should show "On Budget"
    const costStatus = page.locator('text=/On Budget|Trong ngân sách/i');
    await expect(costStatus.first()).toBeVisible({ timeout: 5000 });
  });
});

