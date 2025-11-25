import { test, expect } from '@playwright/test';
import { getAuthToken, setAuthToken } from '../helpers/auth-api';

test.describe('Idempotency Retry Tests', () => {
  let authToken: string;

  test.beforeAll(async ({ request }) => {
    // Get auth token
    authToken = await getAuthToken(request, 'test@example.com', 'password');
    expect(authToken).toBeTruthy();
  });

  test('double-submit within 10 minutes returns same result with X-Idempotency-Cache header', async ({
    request,
  }) => {
    // Generate idempotency key
    const idempotencyKey = `project_create_${Date.now()}_${Math.random().toString(36).substring(7)}`;

    const projectData = {
      name: `Test Project ${Date.now()}`,
      code: `TEST${Date.now()}`,
      description: 'Test project for idempotency',
    };

    // First request
    const response1 = await request.post('/api/v1/app/projects', {
      headers: {
        Authorization: `Bearer ${authToken}`,
        'Idempotency-Key': idempotencyKey,
        'Content-Type': 'application/json',
      },
      data: projectData,
    });

    expect(response1.ok()).toBeTruthy();
    const result1 = await response1.json();
    expect(result1).toHaveProperty('data');
    expect(result1.data).toHaveProperty('id');

    const projectId = result1.data.id;

    // Second request with same idempotency key (simulating retry)
    const response2 = await request.post('/api/v1/app/projects', {
      headers: {
        Authorization: `Bearer ${authToken}`,
        'Idempotency-Key': idempotencyKey,
        'Content-Type': 'application/json',
      },
      data: projectData,
    });

    expect(response2.ok()).toBeTruthy();
    
    // Check for idempotency cache header
    const cacheHeader = response2.headers()['x-idempotency-cache'];
    expect(cacheHeader).toBe('hit');

    const result2 = await response2.json();
    
    // Should return same result
    expect(result2.data.id).toBe(projectId);
    expect(result2.data.name).toBe(result1.data.name);
  });

  test('network retry still results in single action', async ({ request }) => {
    const idempotencyKey = `task_create_${Date.now()}_${Math.random().toString(36).substring(7)}`;

    const taskData = {
      name: `Test Task ${Date.now()}`,
      description: 'Test task for idempotency retry',
      status: 'backlog',
    };

    // Simulate network retry: send same request twice quickly
    const [response1, response2] = await Promise.all([
      request.post('/api/v1/app/tasks', {
        headers: {
          Authorization: `Bearer ${authToken}`,
          'Idempotency-Key': idempotencyKey,
          'Content-Type': 'application/json',
        },
        data: taskData,
      }),
      // Simulate retry after 100ms
      new Promise((resolve) =>
        setTimeout(
          () =>
            resolve(
              request.post('/api/v1/app/tasks', {
                headers: {
                  Authorization: `Bearer ${authToken}`,
                  'Idempotency-Key': idempotencyKey,
                  'Content-Type': 'application/json',
                },
                data: taskData,
              })
            ),
          100
        )
      ),
    ]);

    // Both should succeed
    expect((await response1).ok()).toBeTruthy();
    expect((await response2).ok()).toBeTruthy();

    const result1 = await (await response1).json();
    const result2 = await (await response2).json();

    // Should return same task ID (only one task created)
    expect(result1.data.id).toBe(result2.data.id);

    // Second response should have cache hit header
    const cacheHeader = (await response2).headers()['x-idempotency-cache'];
    expect(cacheHeader).toBe('hit');
  });

  test('different request body with same idempotency key returns 409 conflict', async ({
    request,
  }) => {
    const idempotencyKey = `project_create_${Date.now()}_${Math.random().toString(36).substring(7)}`;

    const projectData1 = {
      name: `Test Project 1 ${Date.now()}`,
      code: `TEST1${Date.now()}`,
      description: 'First project',
    };

    // First request
    const response1 = await request.post('/api/v1/app/projects', {
      headers: {
        Authorization: `Bearer ${authToken}`,
        'Idempotency-Key': idempotencyKey,
        'Content-Type': 'application/json',
      },
      data: projectData1,
    });

    expect(response1.ok()).toBeTruthy();

    // Second request with same key but different body
    const projectData2 = {
      name: `Test Project 2 ${Date.now()}`,
      code: `TEST2${Date.now()}`,
      description: 'Second project with different data',
    };

    const response2 = await request.post('/api/v1/app/projects', {
      headers: {
        Authorization: `Bearer ${authToken}`,
        'Idempotency-Key': idempotencyKey,
        'Content-Type': 'application/json',
      },
      data: projectData2,
    });

    // Should return 409 Conflict
    expect(response2.status()).toBe(409);
    const error = await response2.json();
    expect(error.code).toBe('IDEMPOTENCY_KEY_CONFLICT');
  });
});

