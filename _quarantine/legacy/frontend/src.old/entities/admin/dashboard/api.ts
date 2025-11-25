import { apiClient } from '../../../shared/api/client';
import type {
  AdminDashboardSummary,
  AdminDashboardCharts,
  AdminDashboardActivity,
  AdminDashboardExport
} from './types';

export const adminDashboardApi = {
  // Get dashboard summary stats
  getSummary: async (): Promise<{ data: AdminDashboardSummary }> => {
    const response = await apiClient.get('/api/v1/admin/dashboard/summary');
    return response.data;
  },

  // Get dashboard charts data
  getCharts: async (): Promise<{ data: AdminDashboardCharts }> => {
    const response = await apiClient.get('/api/v1/admin/dashboard/charts');
    return response.data;
  },

  // Get recent activity
  getActivity: async (): Promise<{ data: AdminDashboardActivity }> => {
    const response = await apiClient.get('/api/v1/admin/dashboard/activity');
    return response.data;
  },

  // Get export URL
  getExport: async (): Promise<{ data: AdminDashboardExport }> => {
    const response = await apiClient.get('/api/v1/admin/dashboard/export');
    return response.data;
  }
};
