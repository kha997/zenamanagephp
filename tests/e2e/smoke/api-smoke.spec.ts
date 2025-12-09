import { test, expect } from '@playwright/test';
import { authHeaders, login, expectSuccess, uniqueName } from '../helpers/apiClient';

test.describe('E2E Smoke Suite', () => {
  test('@smoke authenticates admin and loads dashboard data', async ({ request }) => {
    const session = await login(request, 'admin@zena.local', 'password');

    const dashboardResponse = await request.get('/api/dashboard/data', {
      headers: authHeaders(session.token),
    });

    const body = await expectSuccess(dashboardResponse);
    expect(body.data).toBeDefined();
    expect(body.data.stats).toBeDefined();
    expect(body.data.recent_projects).toBeInstanceOf(Array);
  });

  test('@smoke admin dashboard KPIs expose tenant metrics', async ({ request }) => {
    const session = await login(request, 'admin@zena.local', 'password');

    const kpiResponse = await request.get('/api/dashboard/kpis', {
      headers: authHeaders(session.token),
    });

    const body = await expectSuccess(kpiResponse);
    expect(body.data).toHaveProperty('projects');
    expect(body.data.projects).toHaveProperty('total');
  });

  test('@smoke project creation succeeds with minimal payload', async ({ request }) => {
    const session = await login(request, 'admin@zena.local', 'password');
    const projectName = uniqueName('Smoke Project');
    const today = new Date();
    const startDate = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 1)
      .toISOString()
      .slice(0, 10);
    const endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 10)
      .toISOString()
      .slice(0, 10);

    const createResponse = await request.post('/api/projects', {
      headers: authHeaders(session.token),
      data: {
        name: projectName,
        description: 'Smoke test project creation',
        code: uniqueName('SMK'),
        status: 'planning',
        start_date: startDate,
        end_date: endDate,
      },
    });

    const body = await expectSuccess(createResponse, 201);
    expect(body.data).toHaveProperty('id');

    const projectId = body.data.id;

    const cleanupResponse = await request.delete(`/api/projects/${projectId}`, {
      headers: authHeaders(session.token),
    });

    await expectSuccess(cleanupResponse);
  });

  test('@smoke dashboard preferences can be persisted', async ({ request }) => {
    const session = await login(request, 'admin@zena.local', 'password');

    const preferences = {
      theme: 'dark',
      density: 'comfortable',
      notifications: {
        email: true,
        push: false,
      },
    };

    const saveResponse = await request.post('/api/dashboard/preferences', {
      headers: authHeaders(session.token),
      data: {
        preferences,
      },
    });

    const body = await expectSuccess(saveResponse);
    expect(body.preferences ?? body.data?.preferences ?? {}).toMatchObject(preferences);
  });
});
