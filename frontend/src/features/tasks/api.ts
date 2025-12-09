import { createApiClient, mapAxiosError } from '../../shared/api/client';
import type {
  Task,
  TaskCreate,
  TaskUpdate,
  TasksListResponse,
  TaskGetResponse,
  TaskCreateResponse,
  TaskUpdateResponse,
  TaskMoveResponse,
  TaskBulkDeleteResponse,
  TaskBulkStatusResponse,
  TaskBulkAssignResponse,
  TaskCreateRequest,
  TaskUpdateRequest,
  TaskMoveRequest,
  TaskBulkDeleteRequest,
  TaskBulkStatusRequest,
  TaskBulkAssignRequest,
  TasksListQuery,
} from '../../shared/api/types';
import { generateIdempotencyKey } from '../../shared/utils/idempotency';

const apiClient = createApiClient();

// Re-export types for backward compatibility
export type { Task, TaskCreate, TaskUpdate };

// TaskFilters matches query parameters from OpenAPI
// Extract from the actual query type
export type TaskFilters = NonNullable<TasksListQuery>;

// TasksResponse matches the response structure
export type TasksResponse = TasksListResponse;

/**
 * Tasks API Client
 * 
 * Endpoints from routes/api_v1.php: /api/v1/app/tasks/*
 */
export const tasksApi = {
  /**
   * PR #4: Uses generated types from OpenAPI spec
   */
  async getTasks(filters?: TaskFilters, pagination?: { page?: number; per_page?: number }): Promise<TasksListResponse> {
    try {
      const params = new URLSearchParams();
      if (filters?.project_id) params.append('project_id', String(filters.project_id));
      if (filters?.status) params.append('status', filters.status);
      if (filters?.priority) params.append('priority', filters.priority);
      if (filters?.assignee_id) params.append('assignee_id', String(filters.assignee_id));
      if (filters?.search) params.append('search', filters.search);
      if (pagination?.page) params.append('page', String(pagination.page));
      if (pagination?.per_page) params.append('per_page', String(pagination.per_page));

      const response = await apiClient.get<TasksListResponse>(`/v1/app/tasks?${params.toString()}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getTask(id: string | number): Promise<TaskGetResponse> {
    try {
      const response = await apiClient.get<TaskGetResponse>(`/v1/app/tasks/${id}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async createTask(data: TaskCreateRequest): Promise<TaskCreateResponse> {
    try {
      const idempotencyKey = generateIdempotencyKey('task', 'create');
      const response = await apiClient.post<TaskCreateResponse>('/v1/app/tasks', data, {
        headers: {
          'Idempotency-Key': idempotencyKey,
        },
      });
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async updateTask(id: string | number, data: TaskUpdateRequest): Promise<TaskUpdateResponse> {
    try {
      const idempotencyKey = generateIdempotencyKey('task', 'update');
      const response = await apiClient.put<TaskUpdateResponse>(`/v1/app/tasks/${id}`, data, {
        headers: {
          'Idempotency-Key': idempotencyKey,
        },
      });
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Move task to a new status (Kanban drag-drop)
   * PR #4: Uses generated types from OpenAPI spec
   * 
   * @param id - Task ID
   * @param data - Move data: to_status, before_id?, after_id?, reason?, version?
   * @returns Task data with optional warning
   */
  async moveTask(id: string | number, data: TaskMoveRequest): Promise<TaskMoveResponse> {
    try {
      const idempotencyKey = generateIdempotencyKey('task', 'move');
      const response = await apiClient.patch<TaskMoveResponse>(
        `/v1/app/tasks/${id}/move`,
        data,
        {
          headers: {
            'Idempotency-Key': idempotencyKey,
          },
        }
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async deleteTask(id: string | number): Promise<void> {
    try {
      await apiClient.delete(`/v1/app/tasks/${id}`);
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getKpis(period?: string): Promise<any> {
    try {
      const params = period ? `?period=${period}` : '';
      const response = await apiClient.get(`/v1/app/tasks/kpis${params}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getAlerts(): Promise<any> {
    try {
      const response = await apiClient.get('/v1/app/tasks/alerts');
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getActivity(limit?: number): Promise<any> {
    try {
      const params = limit ? `?limit=${limit}` : '';
      const response = await apiClient.get(`/v1/app/tasks/activity${params}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  // Task Comments API
  async getComments(taskId: string | number): Promise<{ data: TaskComment[] }> {
    try {
      const response = await apiClient.get<{ data: TaskComment[] }>(`/v1/app/task-comments/task/${taskId}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async createComment(data: { task_id: string | number; content: string; parent_id?: string | number }): Promise<{ data: TaskComment }> {
    try {
      const response = await apiClient.post<{ data: TaskComment }>('/v1/app/task-comments', data);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async updateComment(id: string | number, data: { content: string }): Promise<{ data: TaskComment }> {
    try {
      const response = await apiClient.put<{ data: TaskComment }>(`/v1/app/task-comments/${id}`, data);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async deleteComment(id: string | number): Promise<void> {
    try {
      await apiClient.delete(`/v1/app/task-comments/${id}`);
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  // Bulk actions
  // PR #4: Uses generated types from OpenAPI spec
  async bulkDeleteTasks(ids: (string | number)[]): Promise<TaskBulkDeleteResponse> {
    try {
      const idempotencyKey = generateIdempotencyKey('task', 'bulk_delete');
      const response = await apiClient.post<TaskBulkDeleteResponse>(
        '/v1/app/tasks/bulk-delete',
        { ids } as TaskBulkDeleteRequest,
        {
          headers: {
            'Idempotency-Key': idempotencyKey,
          },
        }
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async bulkUpdateStatus(ids: (string | number)[], status: string): Promise<TaskBulkStatusResponse> {
    try {
      const idempotencyKey = generateIdempotencyKey('task', 'bulk_status');
      const response = await apiClient.post<TaskBulkStatusResponse>(
        '/v1/app/tasks/bulk-status',
        { ids, status } as TaskBulkStatusRequest,
        {
          headers: {
            'Idempotency-Key': idempotencyKey,
          },
        }
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async bulkAssignTasks(ids: (string | number)[], assigneeId: string | number): Promise<TaskBulkAssignResponse> {
    try {
      const idempotencyKey = generateIdempotencyKey('task', 'bulk_assign');
      const response = await apiClient.post<TaskBulkAssignResponse>(
        '/v1/app/tasks/bulk-assign',
        { ids, assignee_id: assigneeId } as TaskBulkAssignRequest,
        {
          headers: {
            'Idempotency-Key': idempotencyKey,
          },
        }
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get task documents
   * @param taskId - Task ID
   * @returns List of documents associated with the task
   */
  async getTaskDocuments(taskId: string | number): Promise<{ data: any[] }> {
    try {
      const response = await apiClient.get<{ data: any[] }>(`/v1/app/tasks/${taskId}/documents`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get task history/audit log
   * @param taskId - Task ID
   * @returns List of history entries for the task
   */
  async getTaskHistory(taskId: string | number): Promise<{ data: any[] }> {
    try {
      const response = await apiClient.get<{ data: any[] }>(`/v1/app/tasks/${taskId}/history`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

export interface TaskComment {
  id: string | number;
  task_id: string | number;
  user_id: string | number;
  content: string;
  parent_id?: string | number;
  type?: 'comment' | 'status_change' | 'assignment' | 'mention' | 'system';
  is_internal?: boolean;
  is_pinned?: boolean;
  created_at: string;
  updated_at: string;
  user?: {
    id: string | number;
    name: string;
    email?: string;
    avatar?: string;
  };
  replies?: TaskComment[];
}

