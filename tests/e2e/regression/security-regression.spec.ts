import { test, expect } from '@playwright/test';
import { authHeaders, login, expectSuccess, uniqueName } from '../helpers/apiClient';

test.describe('Security Regression Suite', () => {
  test('@regression invalid credentials are rejected', async ({ request }) => {
    const response = await request.post('/api/v1/auth/login', {
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      data: {
        email: 'admin@zena.local',
        password: 'wrong-password',
      },
    });

    expect(response.status()).toBe(401);
    const body = await response.json();
    expect(body.success ?? false).toBe(false);
  });

  test('@regression tenant role restrictions enforced', async ({ request }) => {
    const session = await login(request, 'dev@zena.local', 'password');

    const response = await request.get('/api/app/users', {
      headers: authHeaders(session.token),
    });

    expect(response.status()).toBe(403);
  });

  test('@regression cross-tenant document access denied', async ({ request }) => {
    const zenaSession = await login(request, 'admin@zena.local', 'password');
    const ttfSession = await login(request, 'admin@ttf.local', 'password');
    const documentName = `${uniqueName('reg-doc')}.txt`;

    const uploadResponse = await request.post('/api/documents', {
      headers: authHeaders(zenaSession.token),
      multipart: {
        file: {
          name: documentName,
          mimeType: 'text/plain',
          buffer: Buffer.from('Regression security document'),
        },
        description: 'Tenant isolation verification',
      },
    });

    const uploadBody = await expectSuccess(uploadResponse, 201);
    const documentId = uploadBody.data.id;

    const forbiddenResponse = await request.get(`/api/documents/${documentId}`, {
      headers: authHeaders(ttfSession.token),
    });

    expect(forbiddenResponse.status()).toBe(403);

    await expectSuccess(
      await request.delete(`/api/documents/${documentId}`, {
        headers: authHeaders(zenaSession.token),
      })
    );
  });
});
