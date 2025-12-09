import { test, expect } from '@playwright/test';
import { authHeaders, login, expectSuccess, uniqueName } from '../helpers/apiClient';

test.describe('Admin Users and Roles Core Flow', () => {
  test('@core admin can list, create, update role, and delete users', async ({ request }) => {
    const session = await login(request, 'admin@zena.local', 'password');

    const listResponse = await request.get('/api/users', {
      headers: authHeaders(session.token),
      params: { per_page: 5 },
    });

    const listBody = await expectSuccess(listResponse);
    expect(Array.isArray(listBody.data?.data ?? listBody.data)).toBe(true);

    const email = `${uniqueName('core-user')}@zena.local`;
    const createResponse = await request.post('/api/users', {
      headers: authHeaders(session.token),
      data: {
        name: 'Core Suite User',
        first_name: 'Core',
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
    expect(createBody.data.email).toBe(email);

    const updateRoleResponse = await request.put(`/api/users/${userId}/role`, {
      headers: authHeaders(session.token),
      data: { role: 'client' },
    });

    const updateRoleBody = await expectSuccess(updateRoleResponse);
    expect(updateRoleBody.data.role).toBe('client');

    const deleteResponse = await request.delete(`/api/users/${userId}`, {
      headers: authHeaders(session.token),
    });

    await expectSuccess(deleteResponse);
  });
});
