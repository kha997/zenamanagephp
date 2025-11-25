import { useQuery } from '@tanstack/react-query';
import { reportsApi } from './api';

export const reportsKeys = {
  all: ['reports'] as const,
  kpis: (period?: string) => [...reportsKeys.all, 'kpis', period] as const,
  alerts: () => [...reportsKeys.all, 'alerts'] as const,
  activity: (limit: number) => [...reportsKeys.all, 'activity', limit] as const,
};

export const useReportsKpis = (period?: string) => {
  return useQuery({
    queryKey: reportsKeys.kpis(period),
    queryFn: () => reportsApi.getReportsKpis(period),
    staleTime: 60_000,
    retry: 1,
    refetchOnWindowFocus: false,
    refetchOnMount: false,
  });
};

export const useReportsAlerts = () => {
  return useQuery({
    queryKey: reportsKeys.alerts(),
    queryFn: () => reportsApi.getReportsAlerts(),
    staleTime: 60_000,
    retry: 1,
    refetchOnWindowFocus: false,
    refetchOnMount: false,
  });
};

export const useReportsActivity = (limit: number = 10) => {
  return useQuery({
    queryKey: reportsKeys.activity(limit),
    queryFn: () => reportsApi.getReportsActivity(limit),
    staleTime: 60_000,
    retry: 1,
    refetchOnWindowFocus: false,
    refetchOnMount: false,
  });
};

