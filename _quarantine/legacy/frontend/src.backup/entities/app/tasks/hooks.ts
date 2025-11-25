import { useQuery } from '@tanstack/react-query';
import { tasksApi } from './api';

// Query Keys
export const tasksKeys = {
  all: ['tasks'] as const,
  kpis: (period?: string) => [...tasksKeys.all, 'kpis', period] as const,
  alerts: () => [...tasksKeys.all, 'alerts'] as const,
  activity: (limit: number) => [...tasksKeys.all, 'activity', limit] as const,
};

// Get Tasks KPIs
export const useTasksKpis = (period?: string) => {
  return useQuery({
    queryKey: tasksKeys.kpis(period),
    queryFn: () => tasksApi.getTasksKpis(period),
    staleTime: 60_000, // 1 minute
    retry: 1,
    refetchOnWindowFocus: false,
    refetchOnMount: false,
  });
};

// Get Tasks Alerts
export const useTasksAlerts = () => {
  return useQuery({
    queryKey: tasksKeys.alerts(),
    queryFn: () => tasksApi.getTasksAlerts(),
    staleTime: 60_000, // 1 minute
    retry: 1,
    refetchOnWindowFocus: false,
    refetchOnMount: false,
  });
};

// Get Tasks Activity
export const useTasksActivity = (limit: number = 10) => {
  return useQuery({
    queryKey: tasksKeys.activity(limit),
    queryFn: () => tasksApi.getTasksActivity(limit),
    staleTime: 60_000, // 1 minute
    retry: 1,
    refetchOnWindowFocus: false,
    refetchOnMount: false,
  });
};

