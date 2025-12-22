import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { dashboardApi } from './api';

/**
 * Dashboard Hooks (React Query)
 */

export const useDashboard = () => {
  return useQuery({
    queryKey: ['dashboard'],
    queryFn: () => dashboardApi.getDashboard(),
    staleTime: 30 * 1000, // 30 seconds
    gcTime: 5 * 60 * 1000, // 5 minutes
  });
};

export const useDashboardStats = (options?: { enabled?: boolean }) => {
  const { enabled = true } = options ?? {};
  return useQuery({
    queryKey: ['dashboard', 'stats'],
    queryFn: () => dashboardApi.getStats(),
    enabled,
    staleTime: 60 * 1000, // 60 seconds - KPIs can be cached longer
    gcTime: 5 * 60 * 1000, // 5 minutes
  });
};

export const useRecentProjects = (limit: number = 5, options?: { enabled?: boolean }) => {
  const { enabled = true } = options ?? {};
  return useQuery({
    queryKey: ['dashboard', 'recent-projects', limit],
    queryFn: () => dashboardApi.getRecentProjects(limit),
    enabled,
    staleTime: 30 * 1000, // 30 seconds
    gcTime: 5 * 60 * 1000, // 5 minutes
  });
};

export const useRecentTasks = (limit: number = 5, options?: { enabled?: boolean }) => {
  const { enabled = true } = options ?? {};
  return useQuery({
    queryKey: ['dashboard', 'recent-tasks', limit],
    queryFn: () => dashboardApi.getRecentTasks(limit),
    enabled,
    staleTime: 30 * 1000, // 30 seconds
    gcTime: 5 * 60 * 1000, // 5 minutes
  });
};

export const useRecentActivity = (limit: number = 10, options?: { enabled?: boolean }) => {
  const { enabled = true } = options ?? {};
  return useQuery({
    queryKey: ['dashboard', 'recent-activity', limit],
    queryFn: () => dashboardApi.getRecentActivity(limit),
    enabled,
    staleTime: 15 * 1000, // 15 seconds - activity updates more frequently
    gcTime: 5 * 60 * 1000, // 5 minutes
  });
};

export const useDashboardAlerts = (options?: { enabled?: boolean }) => {
  const { enabled = true } = options ?? {};
  return useQuery({
    queryKey: ['dashboard', 'alerts'],
    queryFn: () => dashboardApi.getAlerts(),
    enabled,
    staleTime: 30 * 1000, // 30 seconds
    gcTime: 5 * 60 * 1000, // 5 minutes
  });
};

export const useDashboardMetrics = () => {
  return useQuery({
    queryKey: ['dashboard', 'metrics'],
    queryFn: () => dashboardApi.getMetrics(),
  });
};

export const useTeamStatus = () => {
  return useQuery({
    queryKey: ['dashboard', 'team-status'],
    queryFn: () => dashboardApi.getTeamStatus(),
  });
};

export const useMarkAlertAsRead = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (id: string | number) => dashboardApi.markAlertAsRead(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['dashboard', 'alerts'] });
    },
  });
};

export const useMarkAllAlertsAsRead = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: () => dashboardApi.markAllAlertsAsRead(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['dashboard', 'alerts'] });
    },
  });
};

// Admin Dashboard Hooks

export const useAdminDashboard = () => {
  return useQuery({
    queryKey: ['admin', 'dashboard'],
    queryFn: () => dashboardApi.getAdminDashboard(),
    staleTime: 60 * 1000, // 60 seconds - admin stats can be cached longer
    gcTime: 5 * 60 * 1000, // 5 minutes
  });
};

export const useAdminStats = () => {
  return useQuery({
    queryKey: ['admin', 'dashboard', 'stats'],
    queryFn: () => dashboardApi.getAdminStats(),
  });
};

