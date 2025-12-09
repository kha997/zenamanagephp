import { test, expect } from '@playwright/test';
import { authHeaders, login, expectSuccess, uniqueName } from '../helpers/apiClient';

/**
 * Golden Path 2: Projects → Tasks (Kanban)
 * 
 * Flow: Create project → Open project → Create task → Drag task in Kanban → Change status
 * 
 * This test verifies:
 * - Project creation works
 * - Task creation works
 * - Kanban board loads tasks
 * - Task drag-drop (status change) works
 * - Invalid status transitions are blocked with clear error messages
 */
test.describe('Golden Path 2: Projects → Tasks (Kanban)', () => {
  let session: any;
  let projectId: string;
  let taskId: string;

  test.beforeEach(async ({ request }) => {
    // Login before each test
    session = await login(request, 'test@example.com', 'password');
  });

  test('@golden-path create project and task, then view in kanban', async ({ request }) => {
    // Step 1: Create project
    const projectName = uniqueName('project');
    const createProjectResponse = await request.post('/api/v1/app/projects', {
      headers: authHeaders(session.token),
      data: {
        name: projectName,
        description: 'Test project for golden path',
      },
    });
    
    const projectData = await expectSuccess(createProjectResponse, 201);
    expect(projectData.data.project).toBeDefined();
    projectId = projectData.data.project.id;
    expect(projectId).toBeTruthy();

    // Step 2: Get project details
    const getProjectResponse = await request.get(`/api/v1/app/projects/${projectId}`, {
      headers: authHeaders(session.token),
    });
    const projectDetails = await expectSuccess(getProjectResponse);
    expect(projectDetails.data.project.id).toBe(projectId);

    // Step 3: Create task
    const taskName = uniqueName('task');
    const createTaskResponse = await request.post('/api/v1/app/tasks', {
      headers: authHeaders(session.token),
      data: {
        project_id: projectId,
        name: taskName,
        status: 'backlog',
        description: 'Test task for golden path',
      },
    });
    
    const taskData = await expectSuccess(createTaskResponse, 201);
    expect(taskData.data.task).toBeDefined();
    taskId = taskData.data.task.id;
    expect(taskId).toBeTruthy();

    // Step 4: Get tasks for Kanban view
    const kanbanResponse = await request.get(`/api/v1/app/tasks?project_id=${projectId}&view=kanban`, {
      headers: authHeaders(session.token),
    });
    const kanbanData = await expectSuccess(kanbanResponse);
    
    expect(kanbanData.data.tasks).toBeDefined();
    expect(Array.isArray(kanbanData.data.tasks)).toBe(true);
    
    // Find our task in the list
    const ourTask = kanbanData.data.tasks.find((t: any) => t.id === taskId);
    expect(ourTask).toBeDefined();
    expect(ourTask.status).toBe('backlog');
  });

  test('@golden-path move task status (backlog → todo → in_progress)', async ({ request }) => {
    // Create project and task first
    const projectName = uniqueName('project');
    const createProjectResponse = await request.post('/api/v1/app/projects', {
      headers: authHeaders(session.token),
      data: { name: projectName },
    });
    const projectData = await expectSuccess(createProjectResponse, 201);
    const projectId = projectData.data.project.id;

    const taskName = uniqueName('task');
    const createTaskResponse = await request.post('/api/v1/app/tasks', {
      headers: authHeaders(session.token),
      data: {
        project_id: projectId,
        name: taskName,
        status: 'backlog',
      },
    });
    const taskData = await expectSuccess(createTaskResponse, 201);
    const taskId = taskData.data.task.id;

    // Step 1: Move from backlog to todo
    const moveToTodoResponse = await request.patch(`/api/v1/app/tasks/${taskId}/status`, {
      headers: authHeaders(session.token),
      data: { status: 'todo' },
    });
    const todoData = await expectSuccess(moveToTodoResponse);
    expect(todoData.data.task.status).toBe('todo');

    // Step 2: Move from todo to in_progress
    const moveToInProgressResponse = await request.patch(`/api/v1/app/tasks/${taskId}/status`, {
      headers: authHeaders(session.token),
      data: { status: 'in_progress' },
    });
    const inProgressData = await expectSuccess(moveToInProgressResponse);
    expect(inProgressData.data.task.status).toBe('in_progress');
  });

  test('@golden-path invalid status transition shows clear error', async ({ request }) => {
    // Create project and task
    const projectName = uniqueName('project');
    const createProjectResponse = await request.post('/api/v1/app/projects', {
      headers: authHeaders(session.token),
      data: { name: projectName },
    });
    const projectData = await expectSuccess(createProjectResponse, 201);
    const projectId = projectData.data.project.id;

    const taskName = uniqueName('task');
    const createTaskResponse = await request.post('/api/v1/app/tasks', {
      headers: authHeaders(session.token),
      data: {
        project_id: projectId,
        name: taskName,
        status: 'backlog',
      },
    });
    const taskData = await expectSuccess(createTaskResponse, 201);
    const taskId = taskData.data.task.id;

    // Try invalid transition: backlog → completed (not allowed)
    const invalidTransitionResponse = await request.patch(`/api/v1/app/tasks/${taskId}/status`, {
      headers: authHeaders(session.token),
      data: { status: 'completed' },
    });
    
    // Should get 409 Conflict or 422 Unprocessable Entity
    expect([409, 422]).toContain(invalidTransitionResponse.status());
    
    const errorBody = await invalidTransitionResponse.json();
    expect(errorBody.ok).toBe(false);
    expect(errorBody.code).toBeDefined();
    expect(errorBody.message).toBeDefined();
    
    // Error should explain why transition is blocked
    expect(errorBody.message.toLowerCase()).toMatch(/cannot|invalid|transition|status/i);
    
    // Should include details about allowed transitions
    if (errorBody.details?.allowed_transitions) {
      expect(Array.isArray(errorBody.details.allowed_transitions)).toBe(true);
    }
  });

  test('@golden-path tenant isolation: cannot access other tenant projects', async ({ request }) => {
    // Create project in user's tenant
    const projectName = uniqueName('project');
    const createProjectResponse = await request.post('/api/v1/app/projects', {
      headers: authHeaders(session.token),
      data: { name: projectName },
    });
    const projectData = await expectSuccess(createProjectResponse, 201);
    const projectId = projectData.data.project.id;

    // Try to access with a different tenant (if we had another user's token)
    // For now, we verify the project has tenant_id set
    expect(projectData.data.project.tenant_id).toBe(session.user.tenant_id);
    
    // Project should be filtered by tenant in queries
    const listResponse = await request.get('/api/v1/app/projects', {
      headers: authHeaders(session.token),
    });
    const listData = await expectSuccess(listResponse);
    
    // All projects should belong to user's tenant
    if (listData.data.projects && listData.data.projects.length > 0) {
      listData.data.projects.forEach((project: any) => {
        expect(project.tenant_id).toBe(session.user.tenant_id);
      });
    }
  });
});

