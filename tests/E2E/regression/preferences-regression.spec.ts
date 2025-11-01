import { test, expect } from '@playwright/test';
import { authHeaders, login, expectSuccess } from '../helpers/apiClient';

test.describe('Preferences Regression Suite', () => {
  test('@regression user preferences store language and timezone', async ({ request }) => {
    const session = await login(request, 'admin@zena.local', 'password');

    const updateResponse = await request.put(`/api/users/${session.user.id}/preferences`, {
      headers: authHeaders(session.token),
      data: {
        preferences: {
          language: 'fr',
          timezone: 'Europe/Paris',
          date_format: 'DD/MM/YYYY',
          time_format: '24',
        },
      },
    });

    const updateBody = await expectSuccess(updateResponse);
    expect(updateBody.data.preferences.language).toBe('fr');
    expect(updateBody.data.preferences.timezone).toBe('Europe/Paris');
  });

  test('@regression theme preference toggles successfully', async ({ request }) => {
    const session = await login(request, 'admin@zena.local', 'password');

    const themeResponse = await request.post('/api/user/preferences/theme', {
      headers: authHeaders(session.token),
      data: { theme: 'dark' },
    });

    const themeBody = await expectSuccess(themeResponse);
    expect(themeBody.data.theme).toBe('dark');
  });
});
