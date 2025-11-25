import { useQuery } from '@tanstack/react-query';
import { adminDashboardApi } from './api';

// Query Keys
export const adminDashboardKeys = {
  all: ['admin', 'dashboard'] as const,
  summary: () => [...adminDashboardKeys.all, 'summary'] as const,
  charts: () => [...adminDashboardKeys.all, 'charts'] as const,
  activity: () => [...adminDashboardKeys.all, 'activity'] as const,
  export: () => [...adminDashboardKeys.all, 'export'] as const,
};

// Get dashboard summary
export const useAdminDashboardSummary = () => {
  return useQuery({
    queryKey: adminDashboardKeys.summary(),
    queryFn: () => adminDashboardApi.getSummary(),
    staleTime: 60_000, // 1 minute
    retry: 1,
  });
};

// Get dashboard charts
export const useAdminDashboardCharts = () => {
  return useQuery({
    queryKey: adminDashboardKeys.charts(),
    queryFn: () => adminDashboardApi.getCharts(),
    staleTime: 300_000, // 5 minutes
    retry: 1,
  });
};

// Get dashboard activity
export const useAdminDashboardActivity = () => {
  return useQuery({
    queryKey: adminDashboardKeys.activity(),
    queryFn: () => adminDashboardApi.getActivity(),
    staleTime: 30_000, // 30 seconds
    retry: 1,
  });
};

// Get export URL
export const useAdminDashboardExport = () => {
  return useQuery({
    queryKey: adminDashboardKeys.export(),
    queryFn: () => adminDashboardApi.getExport(),
    staleTime: 300_000, // 5 minutes
    retry: 1,
  });
};
