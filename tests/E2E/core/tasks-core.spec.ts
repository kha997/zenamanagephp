import { test, expect } from '@playwright/test';
import { authHeaders, login, expectSuccess, uniqueName } from '../helpers/apiClient';

async function createProject(request: any, token: string) {
  const projectName = uniqueName('Task Project');
  const startDate = new Date().toISOString().slice(0, 10);
  const endDate = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);

  const response = await request.post('/api/projects', {
    headers: authHeaders(token),
    data: {
      name: projectName,
      description: 'Temporary project for task flow',
      code: uniqueName('TASK'),
      status: 'planning',
      start_date: startDate,
      end_date: endDate,
    },
  });

  const body = await expectSuccess(response, 201);
  return { id: body.data.id, name: projectName };
}

test.describe('Tasks Core Flow', () => {
  test('@core task lifecycle within a project', async ({ request }) => {
    const session = await login(request, 'admin@zena.local', 'password');
    const project = await createProject(request, session.token);

    const createTaskResponse = await request.post('/api/tasks', {
      headers: authHeaders(session.token),
      data: {
        title: uniqueName('Core Task'),
        description: 'Automated core flow task',
        project_id: project.id,
        status: 'pending',
        priority: 'medium',
        assignee_id: session.user.id,
        due_date: new Date(Date.now() + 5 * 24 * 60 * 60 * 1000).toISOString(),
      },
    });

    const createTaskBody = await expectSuccess(createTaskResponse, 201);
    const taskId = createTaskBody.data.id;

    const updateTaskResponse = await request.put(`/api/tasks/${taskId}`, {
      headers: authHeaders(session.token),
      data: {
        status: 'in_progress',
        progress_percent: 45,
        description: 'Progress updated by core flow',
      },
    });

    await expectSuccess(updateTaskResponse);

    const showTaskResponse = await request.get(`/api/tasks/${taskId}`, {
      headers: authHeaders(session.token),
    });

    const showTaskBody = await expectSuccess(showTaskResponse);
    expect(showTaskBody.data.status).toBe('in_progress');
    expect(showTaskBody.data.progress_percent).toBe(45);

    const deleteTaskResponse = await request.delete(`/api/tasks/${taskId}`, {
      headers: authHeaders(session.token),
    });

    await expectSuccess(deleteTaskResponse);

    const deleteProjectResponse = await request.delete(`/api/projects/${project.id}`, {
      headers: authHeaders(session.token),
    });

    await expectSuccess(deleteProjectResponse);
  });
});
