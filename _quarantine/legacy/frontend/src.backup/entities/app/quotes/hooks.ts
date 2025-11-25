import { useQuery } from '@tanstack/react-query';
import { quotesApi } from './api';

// Query Keys
export const quotesKeys = {
  all: ['quotes'] as const,
  kpis: (period?: string) => [...quotesKeys.all, 'kpis', period] as const,
  alerts: () => [...quotesKeys.all, 'alerts'] as const,
  activity: (limit: number) => [...quotesKeys.all, 'activity', limit] as const,
};

// Get Quotes KPIs
export const useQuotesKpis = (period?: string) => {
  return useQuery({
    queryKey: quotesKeys.kpis(period),
    queryFn: () => quotesApi.getQuotesKpis(period),
    staleTime: 60_000, // 1 minute
    retry: 1,
    refetchOnWindowFocus: false,
    refetchOnMount: false,
  });
};

// Get Quotes Alerts
export const useQuotesAlerts = () => {
  return useQuery({
    queryKey: quotesKeys.alerts(),
    queryFn: () => quotesApi.getQuotesAlerts(),
    staleTime: 60_000, // 1 minute
    retry: 1,
    refetchOnWindowFocus: false,
    refetchOnMount: false,
  });
};

// Get Quotes Activity
export const useQuotesActivity = (limit: number = 10) => {
  return useQuery({
    queryKey: quotesKeys.activity(limit),
    queryFn: () => quotesApi.getQuotesActivity(limit),
    staleTime: 60_000, // 1 minute
    retry: 1,
    refetchOnWindowFocus: false,
    refetchOnMount: false,
  });
};

