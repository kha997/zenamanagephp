import { test, expect } from '@playwright/test';
import { authHeaders, login, expectSuccess, uniqueName } from '../helpers/apiClient';

async function createProjectForTenant(
  request: any,
  token: string,
  projectLabel: string,
  status: 'planning' | 'active' = 'active'
) {
  const name = uniqueName(projectLabel);
  const response = await request.post('/api/projects', {
    headers: authHeaders(token),
    data: {
      name,
      description: `Multi-tenant project: ${projectLabel}`,
      code: uniqueName(projectLabel.toUpperCase()),
      status,
      start_date: new Date().toISOString().slice(0, 10),
      end_date: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10),
    },
  });

  const body = await expectSuccess(response, 201);
  return { id: body.data.id as string, name };
}

test.describe('Multi-tenant Search Core Flow', () => {
  test('@core project searches are scoped per tenant', async ({ request }) => {
    const zenaSession = await login(request, 'admin@zena.local', 'password');
    const ttfSession = await login(request, 'admin@ttf.local', 'password');

    const zenaProject = await createProjectForTenant(request, zenaSession.token, 'ZENA');
    const ttfProject = await createProjectForTenant(request, ttfSession.token, 'TTF');

    const zenaSearchResponse = await request.get('/api/projects', {
      headers: authHeaders(zenaSession.token),
      params: { search: zenaProject.name },
    });

    const zenaSearchBody = await expectSuccess(zenaSearchResponse);
    const zenaResults: any[] = zenaSearchBody.data?.data ?? [];
    expect(zenaResults.find((project) => project.id === zenaProject.id)).toBeDefined();
    expect(zenaResults.find((project) => project.id === ttfProject.id)).toBeUndefined();

    const ttfSearchResponse = await request.get('/api/projects', {
      headers: authHeaders(ttfSession.token),
      params: { search: ttfProject.name },
    });

    const ttfSearchBody = await expectSuccess(ttfSearchResponse);
    const ttfResults: any[] = ttfSearchBody.data?.data ?? [];
    expect(ttfResults.find((project) => project.id === ttfProject.id)).toBeDefined();
    expect(ttfResults.find((project) => project.id === zenaProject.id)).toBeUndefined();

    await expectSuccess(
      await request.delete(`/api/projects/${zenaProject.id}`, {
        headers: authHeaders(zenaSession.token),
      })
    );

    await expectSuccess(
      await request.delete(`/api/projects/${ttfProject.id}`, {
        headers: authHeaders(ttfSession.token),
      })
    );
  });
});
