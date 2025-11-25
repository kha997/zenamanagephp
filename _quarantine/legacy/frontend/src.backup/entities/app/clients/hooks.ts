import { useQuery } from '@tanstack/react-query';
import { clientsApi } from './api';

// Query Keys
export const clientsKeys = {
  all: ['clients'] as const,
  kpis: (period?: string) => [...clientsKeys.all, 'kpis', period] as const,
  alerts: () => [...clientsKeys.all, 'alerts'] as const,
  activity: (limit: number) => [...clientsKeys.all, 'activity', limit] as const,
};

// Get Clients KPIs
export const useClientsKpis = (period?: string) => {
  return useQuery({
    queryKey: clientsKeys.kpis(period),
    queryFn: () => clientsApi.getClientsKpis(period),
    staleTime: 60_000, // 1 minute
    retry: 1,
    refetchOnWindowFocus: false,
    refetchOnMount: false,
  });
};

// Get Clients Alerts
export const useClientsAlerts = () => {
  return useQuery({
    queryKey: clientsKeys.alerts(),
    queryFn: () => clientsApi.getClientsAlerts(),
    staleTime: 60_000, // 1 minute
    retry: 1,
    refetchOnWindowFocus: false,
    refetchOnMount: false,
  });
};

// Get Clients Activity
export const useClientsActivity = (limit: number = 10) => {
  return useQuery({
    queryKey: clientsKeys.activity(limit),
    queryFn: () => clientsApi.getClientsActivity(limit),
    staleTime: 60_000, // 1 minute
    retry: 1,
    refetchOnWindowFocus: false,
    refetchOnMount: false,
  });
};

