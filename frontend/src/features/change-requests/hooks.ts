import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { changeRequestsApi } from './api';
import type { ChangeRequest, ChangeRequestFilters } from './api';

export const useChangeRequests = (
  filters?: ChangeRequestFilters,
  pagination?: { page?: number; per_page?: number }
) => {
  return useQuery({
    queryKey: ['change-requests', filters, pagination],
    queryFn: () => changeRequestsApi.getChangeRequests(filters, pagination),
  });
};

export const useChangeRequest = (id: string | number) => {
  return useQuery({
    queryKey: ['change-request', id],
    queryFn: () => changeRequestsApi.getChangeRequest(id),
    enabled: !!id,
  });
};

export const useCreateChangeRequest = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (data: Partial<ChangeRequest>) => changeRequestsApi.createChangeRequest(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['change-requests'] });
      queryClient.invalidateQueries({ queryKey: ['change-requests', 'kpis'] });
      queryClient.invalidateQueries({ queryKey: ['change-requests', 'alerts'] });
      queryClient.invalidateQueries({ queryKey: ['change-requests', 'activity'] });
    },
  });
};

export const useUpdateChangeRequest = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ id, data }: { id: string | number; data: Partial<ChangeRequest> }) =>
      changeRequestsApi.updateChangeRequest(id, data),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['change-requests'] });
      queryClient.invalidateQueries({ queryKey: ['change-request', variables.id] });
    },
  });
};

export const useDeleteChangeRequest = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (id: string | number) => changeRequestsApi.deleteChangeRequest(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['change-requests'] });
    },
  });
};

export const useSubmitChangeRequest = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (id: string | number) => changeRequestsApi.submitChangeRequest(id),
    onSuccess: (_, id) => {
      queryClient.invalidateQueries({ queryKey: ['change-requests'] });
      queryClient.invalidateQueries({ queryKey: ['change-request', id] });
    },
  });
};

export const useApproveChangeRequest = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ id, notes }: { id: string | number; notes?: string }) =>
      changeRequestsApi.approveChangeRequest(id, notes),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['change-requests'] });
      queryClient.invalidateQueries({ queryKey: ['change-request', variables.id] });
    },
  });
};

export const useRejectChangeRequest = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ id, reason }: { id: string | number; reason?: string }) =>
      changeRequestsApi.rejectChangeRequest(id, reason),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['change-requests'] });
      queryClient.invalidateQueries({ queryKey: ['change-request', variables.id] });
    },
  });
};

export const useChangeRequestsKpis = (period?: string) => {
  return useQuery({
    queryKey: ['change-requests', 'kpis', period],
    queryFn: () => changeRequestsApi.getKpis(period),
  });
};

export const useChangeRequestsAlerts = () => {
  return useQuery({
    queryKey: ['change-requests', 'alerts'],
    queryFn: () => changeRequestsApi.getAlerts(),
  });
};

export const useChangeRequestsActivity = (limit?: number) => {
  return useQuery({
    queryKey: ['change-requests', 'activity', limit],
    queryFn: () => changeRequestsApi.getActivity(limit),
  });
};

