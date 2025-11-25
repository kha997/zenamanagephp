import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { clientsApi } from './api';
import type { Client } from './api';

export const useClients = (filters?: any, pagination?: { page?: number; per_page?: number }) => {
  return useQuery({
    queryKey: ['clients', filters, pagination],
    queryFn: () => clientsApi.getClients(filters, pagination),
  });
};

export const useClient = (id: string | number) => {
  return useQuery({
    queryKey: ['client', id],
    queryFn: () => clientsApi.getClient(id),
    enabled: !!id,
  });
};

export const useClientsKpis = (period?: string) => {
  return useQuery({
    queryKey: ['clients', 'kpis', period],
    queryFn: () => clientsApi.getKpis(period),
  });
};

export const useClientsAlerts = () => {
  return useQuery({
    queryKey: ['clients', 'alerts'],
    queryFn: () => clientsApi.getAlerts(),
  });
};

export const useClientsActivity = (limit?: number) => {
  return useQuery({
    queryKey: ['clients', 'activity', limit],
    queryFn: () => clientsApi.getActivity(limit),
  });
};

export const useCreateClient = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (data: Partial<Client>) => clientsApi.createClient(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['clients'] });
    },
  });
};

export const useUpdateClient = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ id, data }: { id: string | number; data: Partial<Client> }) =>
      clientsApi.updateClient(id, data),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['clients'] });
      queryClient.invalidateQueries({ queryKey: ['client', variables.id] });
    },
  });
};

export const useDeleteClient = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (id: string | number) => clientsApi.deleteClient(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['clients'] });
    },
  });
};

