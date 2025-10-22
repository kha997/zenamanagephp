import { test, expect } from '@playwright/test';
import { authHeaders, login, expectSuccess, uniqueName } from '../helpers/apiClient';

test.describe('Data Integrity Regression Suite', () => {
  test('@regression user removal leaves no residual access', async ({ request }) => {
    const session = await login(request, 'admin@zena.local', 'password');
    const email = `${uniqueName('reg-user')}@zena.local`;

    const createResponse = await request.post('/api/users', {
      headers: authHeaders(session.token),
      data: {
        name: 'Regression User',
        first_name: 'Regression',
        last_name: 'User',
        email,
        role: 'member',
        status: 'active',
        password: 'Password123!',
        password_confirmation: 'Password123!',
      },
    });

    const createBody = await expectSuccess(createResponse, 201);
    const userId = createBody.data.id;

    await expectSuccess(
      await request.delete(`/api/users/${userId}`, {
        headers: authHeaders(session.token),
      })
    );

    const getResponse = await request.get(`/api/users/${userId}`, {
      headers: authHeaders(session.token),
    });

    expect(getResponse.status()).toBe(404);
  });

  test('@regression dashboard configuration export and import round-trip', async ({ request }) => {
    const session = await login(request, 'admin@zena.local', 'password');

    const exportResponse = await request.get('/api/v1/dashboard/customization/export', {
      headers: authHeaders(session.token),
    });

    const exportBody = await expectSuccess(exportResponse);
    expect(exportBody.data).toHaveProperty('layout');

    const importResponse = await request.post('/api/v1/dashboard/customization/import', {
      headers: authHeaders(session.token),
      data: {
        dashboard_config: {
          version: '1.0',
          dashboard: {
            name: 'Regression Dashboard',
            layout: { columns: 3 },
            preferences: { density: 'comfortable' },
          },
          widgets: [],
        },
      },
    });

    await expectSuccess(importResponse);
  });

  test('@regression metrics endpoint responds for performance monitoring', async ({ request }) => {
    const session = await login(request, 'admin@zena.local', 'password');

    const metricsResponse = await request.get('/api/metrics', {
      headers: authHeaders(session.token),
    });

    const metricsBody = await expectSuccess(metricsResponse);
    expect(metricsBody.data).toHaveProperty('queue');
    expect(metricsBody.data.queue.status ?? metricsBody.data.queue?.status).toBeDefined();
  });
});
