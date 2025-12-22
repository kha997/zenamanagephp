import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { quotesApi } from './api';
import type { Quote } from './api';

export const useQuotes = (filters?: any, pagination?: { page?: number; per_page?: number }) => {
  return useQuery({
    queryKey: ['quotes', filters, pagination],
    queryFn: () => quotesApi.getQuotes(filters, pagination),
  });
};

export const useQuote = (id: string | number) => {
  return useQuery({
    queryKey: ['quote', id],
    queryFn: () => quotesApi.getQuote(id),
    enabled: !!id,
  });
};

export const useQuotesKpis = (period?: string) => {
  return useQuery({
    queryKey: ['quotes', 'kpis', period],
    queryFn: () => quotesApi.getKpis(period),
  });
};

export const useQuotesAlerts = () => {
  return useQuery({
    queryKey: ['quotes', 'alerts'],
    queryFn: () => quotesApi.getAlerts(),
  });
};

export const useQuotesActivity = (limit?: number) => {
  return useQuery({
    queryKey: ['quotes', 'activity', limit],
    queryFn: () => quotesApi.getActivity(limit),
  });
};

export const useCreateQuote = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (data: Partial<Quote>) => quotesApi.createQuote(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['quotes'] });
    },
  });
};

export const useUpdateQuote = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ id, data }: { id: string | number; data: Partial<Quote> }) =>
      quotesApi.updateQuote(id, data),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['quotes'] });
      queryClient.invalidateQueries({ queryKey: ['quote', variables.id] });
    },
  });
};

export const useDeleteQuote = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (id: string | number) => quotesApi.deleteQuote(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['quotes'] });
    },
  });
};

