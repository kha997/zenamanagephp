import { renderHook, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import React from 'react';
import { vi, describe, it, expect, beforeEach } from 'vitest';
import { useUpdateTask } from '../../hooks';
import { tasksApi } from '../../api';
import { invalidateFor } from '@/shared/api/invalidateMap';
import type { Task } from '../../types';

// Mock the tasksApi
vi.mock('../../api', () => ({
  tasksApi: {
    updateTask: vi.fn(),
  },
}));

// Mock the invalidation module
vi.mock('@/shared/api/invalidateMap', () => ({
  invalidateFor: vi.fn(),
  createInvalidationContext: vi.fn((queryClient, options) => ({
    queryClient,
    ...options,
  })),
}));

const mockTasksApi = vi.mocked(tasksApi);
const mockInvalidateFor = vi.mocked(invalidateFor);

describe('useUpdateTask', () => {
  let queryClient: QueryClient;
  let wrapper: React.ComponentType<{ children: React.ReactNode }>;

  beforeEach(() => {
    vi.clearAllMocks();
    
    queryClient = new QueryClient({
      defaultOptions: {
        queries: {
          retry: false,
        },
        mutations: {
          retry: false,
        },
      },
    });

    wrapper = ({ children }: { children: React.ReactNode }) => {
      return React.createElement(QueryClientProvider, { client: queryClient }, children);
    };
  });

  it('should pass resourceId and projectId from task response into invalidateFor', async () => {
    // Arrange
    const taskId = 'task-123';
    const projectId = 'project-456';
    const taskResponse: { data: Task } = {
      data: {
        id: taskId,
        title: 'Test Task',
        status: 'in_progress',
        project_id: projectId,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      },
    };

    mockTasksApi.updateTask.mockResolvedValue(taskResponse as any);

    const { result } = renderHook(() => useUpdateTask(), { wrapper });

    // Act
    await waitFor(async () => {
      await result.current.mutateAsync({
        id: taskId,
        data: { status: 'done' },
      });
    });

    // Assert
    expect(mockInvalidateFor).toHaveBeenCalledWith(
      'task.update',
      expect.objectContaining({
        queryClient: expect.anything(),
        resourceId: taskId,
        projectId: projectId,
      })
    );
  });

  it('should use task.id and task.project_id from response even when variables.data.project_id is undefined', async () => {
    // Arrange
    const taskId = 'task-789';
    const projectId = 'project-999';
    const taskResponse: { data: Task } = {
      data: {
        id: taskId,
        title: 'Test Task',
        status: 'done',
        project_id: projectId,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      },
    };

    mockTasksApi.updateTask.mockResolvedValue(taskResponse as any);

    const { result } = renderHook(() => useUpdateTask(), { wrapper });

    // Act - Update with only status (no project_id in variables.data)
    await waitFor(async () => {
      await result.current.mutateAsync({
        id: taskId,
        data: { status: 'done' }, // No project_id here
      });
    });

    // Assert - Should still use project_id from response
    expect(mockInvalidateFor).toHaveBeenCalledWith(
      'task.update',
      expect.objectContaining({
        resourceId: taskId,
        projectId: projectId, // From response, not from variables
      })
    );
  });

  it('should fallback to variables.id and variables.data.project_id if response lacks data', async () => {
    // Arrange
    const taskId = 'task-111';
    const projectId = 'project-222';
    // Response without data property (direct Task object)
    const taskResponse: Task = {
      id: taskId,
      title: 'Test Task',
      status: 'in_progress',
      project_id: projectId,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    };

    mockTasksApi.updateTask.mockResolvedValue(taskResponse as any);

    const { result } = renderHook(() => useUpdateTask(), { wrapper });

    // Act
    await waitFor(async () => {
      await result.current.mutateAsync({
        id: taskId,
        data: { status: 'in_progress', project_id: projectId },
      });
    });

    // Assert - Should use task from response (direct Task object)
    expect(mockInvalidateFor).toHaveBeenCalledWith(
      'task.update',
      expect.objectContaining({
        resourceId: taskId,
        projectId: projectId,
      })
    );
  });

  it('should fallback to variables when response has no id or project_id', async () => {
    // Arrange
    const taskId = 'task-333';
    const projectId = 'project-444';
    // Response with minimal data
    const taskResponse: { data: Partial<Task> } = {
      data: {
        title: 'Updated Task',
        status: 'done',
        // No id or project_id in response
      },
    };

    mockTasksApi.updateTask.mockResolvedValue(taskResponse as any);

    const { result } = renderHook(() => useUpdateTask(), { wrapper });

    // Act
    await waitFor(async () => {
      await result.current.mutateAsync({
        id: taskId,
        data: { status: 'done', project_id: projectId },
      });
    });

    // Assert - Should fallback to variables
    expect(mockInvalidateFor).toHaveBeenCalledWith(
      'task.update',
      expect.objectContaining({
        resourceId: taskId, // From variables
        projectId: projectId, // From variables.data.project_id
      })
    );
  });

  it('should handle assignee_id update without project_id in variables', async () => {
    // Arrange
    const taskId = 'task-555';
    const projectId = 'project-666';
    const assigneeId = 'user-777';
    const taskResponse: { data: Task } = {
      data: {
        id: taskId,
        title: 'Test Task',
        status: 'in_progress',
        project_id: projectId,
        assignee_id: assigneeId,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      },
    };

    mockTasksApi.updateTask.mockResolvedValue(taskResponse as any);

    const { result } = renderHook(() => useUpdateTask(), { wrapper });

    // Act - Quick action: assign to me (only assignee_id, no project_id)
    await waitFor(async () => {
      await result.current.mutateAsync({
        id: taskId,
        data: { assignee_id: assigneeId }, // No project_id in variables
      });
    });

    // Assert - Should use project_id from response
    expect(mockInvalidateFor).toHaveBeenCalledWith(
      'task.update',
      expect.objectContaining({
        resourceId: taskId,
        projectId: projectId, // From response, not from variables
      })
    );
  });
});

