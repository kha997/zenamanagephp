import { apiClient } from '../../../shared/api/client';
import type { ApiResponse } from '../../dashboard/types';
import type { UsersMetrics, UserAlert, UserActivity } from './types';

export const usersApi = {
  getUsersKpis: async (period?: string): Promise<ApiResponse<UsersMetrics>> => {
    const params = new URLSearchParams();
    if (period) params.append('period', period);
    const response = await apiClient.get(`/app/users/kpis?${params.toString()}`);
    return response.data;
  },

  getUsersAlerts: async (): Promise<ApiResponse<UserAlert[]>> => {
    const response = await apiClient.get('/app/users/alerts');
    return response.data;
  },

  getUsersActivity: async (limit: number = 10): Promise<ApiResponse<UserActivity[]>> => {
    const params = new URLSearchParams();
    params.append('limit', limit.toString());
    const response = await apiClient.get(`/app/users/activity?${params.toString()}`);
    return response.data;
  },
};

