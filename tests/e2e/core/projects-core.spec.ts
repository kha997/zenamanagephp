import { test, expect } from '@playwright/test';
import { authHeaders, login, expectSuccess, uniqueName } from '../helpers/apiClient';

test.describe('Projects Core Flow', () => {
  test('@core project CRUD lifecycle works end-to-end', async ({ request }) => {
    const session = await login(request, 'admin@zena.local', 'password');
    const projectName = uniqueName('Core Project');
    const startDate = new Date().toISOString().slice(0, 10);
    const endDate = new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);

    const createResponse = await request.post('/api/projects', {
      headers: authHeaders(session.token),
      data: {
        name: projectName,
        description: 'Core suite generated project',
        code: uniqueName('CORE'),
        status: 'planning',
        priority: 'high',
        start_date: startDate,
        end_date: endDate,
      },
    });

    const createBody = await expectSuccess(createResponse, 201);
    const projectId = createBody.data.id;

    const listResponse = await request.get('/api/projects', {
      headers: authHeaders(session.token),
      params: {
        search: projectName,
        per_page: 5,
      },
    });

    const listBody = await expectSuccess(listResponse);
    const foundProject = listBody.data?.data?.find((item: any) => item.id === projectId);
    expect(foundProject?.name).toBe(projectName);

    const updatedName = `${projectName}-updated`;
    const updateResponse = await request.put(`/api/projects/${projectId}`, {
      headers: authHeaders(session.token),
      data: {
        name: updatedName,
        status: 'active',
      },
    });

    await expectSuccess(updateResponse);

    const showResponse = await request.get(`/api/projects/${projectId}`, {
      headers: authHeaders(session.token),
    });

    const showBody = await expectSuccess(showResponse);
    expect(showBody.data.name).toBe(updatedName);
    expect(showBody.data.status).toBe('active');

    const deleteResponse = await request.delete(`/api/projects/${projectId}`, {
      headers: authHeaders(session.token),
    });

    await expectSuccess(deleteResponse);

    const afterDeleteResponse = await request.get(`/api/projects/${projectId}`, {
      headers: authHeaders(session.token),
    });

    expect(afterDeleteResponse.status()).toBe(404);
  });
});
