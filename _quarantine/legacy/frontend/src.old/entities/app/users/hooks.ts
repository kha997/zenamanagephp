import { useQuery } from '@tanstack/react-query';
import { usersApi } from './api';

export const usersKeys = {
  all: ['users'] as const,
  kpis: (period?: string) => [...usersKeys.all, 'kpis', period] as const,
  alerts: () => [...usersKeys.all, 'alerts'] as const,
  activity: (limit: number) => [...usersKeys.all, 'activity', limit] as const,
};

export const useUsersKpis = (period?: string) => {
  return useQuery({
    queryKey: usersKeys.kpis(period),
    queryFn: () => usersApi.getUsersKpis(period),
    staleTime: 60_000,
    retry: 1,
    refetchOnWindowFocus: false,
    refetchOnMount: false,
  });
};

export const useUsersAlerts = () => {
  return useQuery({
    queryKey: usersKeys.alerts(),
    queryFn: () => usersApi.getUsersAlerts(),
    staleTime: 60_000,
    retry: 1,
    refetchOnWindowFocus: false,
    refetchOnMount: false,
  });
};

export const useUsersActivity = (limit: number = 10) => {
  return useQuery({
    queryKey: usersKeys.activity(limit),
    queryFn: () => usersApi.getUsersActivity(limit),
    staleTime: 60_000,
    retry: 1,
    refetchOnWindowFocus: false,
    refetchOnMount: false,
  });
};

