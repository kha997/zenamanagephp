import { apiClient } from '../../../shared/api/client';
import type {
  ClientsMetrics,
  ClientAlert,
  ClientActivity
} from './types';
import type { ApiResponse } from '../../dashboard/types';

export const clientsApi = {
  // Get Clients KPIs
  getClientsKpis: async (period?: string): Promise<ApiResponse<ClientsMetrics>> => {
    const params = new URLSearchParams();
    if (period) params.append('period', period);
    
    const response = await apiClient.get(`/app/clients/kpis?${params.toString()}`);
    return response.data;
  },

  // Get Clients Alerts
  getClientsAlerts: async (): Promise<ApiResponse<ClientAlert[]>> => {
    const response = await apiClient.get('/app/clients/alerts');
    return response.data;
  },

  // Get Clients Activity
  getClientsActivity: async (limit: number = 10): Promise<ApiResponse<ClientActivity[]>> => {
    const params = new URLSearchParams();
    params.append('limit', limit.toString());
    
    const response = await apiClient.get(`/app/clients/activity?${params.toString()}`);
    return response.data;
  },
};

