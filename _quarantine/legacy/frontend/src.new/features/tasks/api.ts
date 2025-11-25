import { createApiClient, mapAxiosError } from '../../shared/api/client';

const apiClient = createApiClient();

export interface Task {
  id: string | number;
  title: string;
  description?: string;
  status: 'pending' | 'in_progress' | 'completed' | 'cancelled';
  priority?: 'low' | 'medium' | 'high' | 'urgent';
  project_id?: string | number;
  assignee_id?: string | number;
  due_date?: string;
  created_at: string;
  updated_at: string;
}

export interface TaskFilters {
  project_id?: string | number;
  status?: string;
  priority?: string;
  assignee_id?: string | number;
  search?: string;
}

export interface TasksResponse {
  data: Task[];
  meta?: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
}

/**
 * Tasks API Client
 * 
 * Endpoints from routes/api.php: /api/v1/app/tasks/*
 */
export const tasksApi = {
  async getTasks(filters?: TaskFilters, pagination?: { page?: number; per_page?: number }): Promise<TasksResponse> {
    try {
      const params = new URLSearchParams();
      if (filters?.project_id) params.append('project_id', String(filters.project_id));
      if (filters?.status) params.append('status', filters.status);
      if (filters?.priority) params.append('priority', filters.priority);
      if (filters?.assignee_id) params.append('assignee_id', String(filters.assignee_id));
      if (filters?.search) params.append('search', filters.search);
      if (pagination?.page) params.append('page', String(pagination.page));
      if (pagination?.per_page) params.append('per_page', String(pagination.per_page));

      const response = await apiClient.get<TasksResponse>(`/app/tasks?${params.toString()}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getTask(id: string | number): Promise<{ data: Task }> {
    try {
      const response = await apiClient.get<{ data: Task }>(`/app/tasks/${id}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async createTask(data: Partial<Task>): Promise<{ data: Task }> {
    try {
      const response = await apiClient.post<{ data: Task }>('/app/tasks', data);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async updateTask(id: string | number, data: Partial<Task>): Promise<{ data: Task }> {
    try {
      const response = await apiClient.put<{ data: Task }>(`/app/tasks/${id}`, data);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async deleteTask(id: string | number): Promise<void> {
    try {
      await apiClient.delete(`/app/tasks/${id}`);
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getKpis(period?: string): Promise<any> {
    try {
      const params = period ? `?period=${period}` : '';
      const response = await apiClient.get(`/app/tasks/kpis${params}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getAlerts(): Promise<any> {
    try {
      const response = await apiClient.get('/app/tasks/alerts');
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getActivity(limit?: number): Promise<any> {
    try {
      const params = limit ? `?limit=${limit}` : '';
      const response = await apiClient.get(`/app/tasks/activity${params}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

