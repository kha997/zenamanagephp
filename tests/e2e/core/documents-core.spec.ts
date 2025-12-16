import { test, expect } from '@playwright/test';
import { authHeaders, login, expectSuccess, uniqueName } from '../helpers/apiClient';

async function ensureProject(request: any, token: string) {
  const response = await request.post('/api/projects', {
    headers: authHeaders(token),
    data: {
      name: uniqueName('Document Project'),
      description: 'Project for document flow validation',
      code: uniqueName('DOC'),
      status: 'active',
      start_date: new Date().toISOString().slice(0, 10),
      end_date: new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10),
    },
  });

  const body = await expectSuccess(response, 201);
  return body.data.id as string;
}

test.describe('Documents Core Flow', () => {
  test('@core document upload, update, and retrieval', async ({ request }) => {
    const session = await login(request, 'admin@zena.local', 'password');
    const projectId = await ensureProject(request, session.token);
    const documentName = `${uniqueName('core-doc')}.txt`;

    const uploadResponse = await request.post('/api/documents', {
      headers: authHeaders(session.token),
      multipart: {
        file: {
          name: documentName,
          mimeType: 'text/plain',
          buffer: Buffer.from('Core document content for automated flow'),
        },
        project_id: projectId,
        category: 'report',
        description: 'Uploaded via core flow',
      },
    });

    const uploadBody = await expectSuccess(uploadResponse, 201);
    expect(uploadBody.data.original_name).toBe(documentName);
    const documentId = uploadBody.data.id;

    const listResponse = await request.get('/api/documents', {
      headers: authHeaders(session.token),
      params: {
        search: documentName,
        per_page: 5,
      },
    });

    const listBody = await expectSuccess(listResponse);
    const items: any[] = listBody.data ?? [];
    expect(items.some((item) => item.id === documentId)).toBe(true);

    const updateResponse = await request.put(`/api/documents/${documentId}`, {
      headers: authHeaders(session.token),
      data: {
        category: 'general',
        description: 'Metadata updated',
        tags: ['core', 'e2e'],
      },
    });

    await expectSuccess(updateResponse);

    const showResponse = await request.get(`/api/documents/${documentId}`, {
      headers: authHeaders(session.token),
    });

    const showBody = await expectSuccess(showResponse);
    expect(showBody.data.category).toBe('general');
    expect(showBody.data.tags).toContain('core');

    const deleteResponse = await request.delete(`/api/documents/${documentId}`, {
      headers: authHeaders(session.token),
    });

    await expectSuccess(deleteResponse);

    const deleteProjectResponse = await request.delete(`/api/projects/${projectId}`, {
      headers: authHeaders(session.token),
    });

    await expectSuccess(deleteProjectResponse);
  });
});
