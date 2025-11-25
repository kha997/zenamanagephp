import { apiClient } from '../../../shared/api/client';
import type {
  Task,
  TasksResponse,
  TasksFilters,
  TasksMetrics,
  TaskAlert,
  TaskActivity
} from './types';
import type { ApiResponse } from '../../dashboard/types';

export const tasksApi = {
  // Get Tasks KPIs
  getTasksKpis: async (period?: string): Promise<ApiResponse<TasksMetrics>> => {
    const params = new URLSearchParams();
    if (period) params.append('period', period);
    
    const response = await apiClient.get(`/app/tasks/kpis?${params.toString()}`);
    return response.data;
  },

  // Get Tasks Alerts
  getTasksAlerts: async (): Promise<ApiResponse<TaskAlert[]>> => {
    const response = await apiClient.get('/app/tasks/alerts');
    return response.data;
  },

  // Get Tasks Activity
  getTasksActivity: async (limit: number = 10): Promise<ApiResponse<TaskActivity[]>> => {
    const params = new URLSearchParams();
    params.append('limit', limit.toString());
    
    const response = await apiClient.get(`/app/tasks/activity?${params.toString()}`);
    return response.data;
  },
};

