import { apiClient } from '../../../shared/api/client';
import type { ApiResponse } from '../../dashboard/types';
import type { ReportsMetrics, ReportAlert, ReportActivity } from './types';

export const reportsApi = {
  getReportsKpis: async (period?: string): Promise<ApiResponse<ReportsMetrics>> => {
    const params = new URLSearchParams();
    if (period) params.append('period', period);
    const response = await apiClient.get(`/app/reports/kpis?${params.toString()}`);
    return response.data;
  },

  getReportsAlerts: async (): Promise<ApiResponse<ReportAlert[]>> => {
    const response = await apiClient.get('/app/reports/alerts');
    return response.data;
  },

  getReportsActivity: async (limit: number = 10): Promise<ApiResponse<ReportActivity[]>> => {
    const params = new URLSearchParams();
    params.append('limit', limit.toString());
    const response = await apiClient.get(`/app/reports/activity?${params.toString()}`);
    return response.data;
  },
};

