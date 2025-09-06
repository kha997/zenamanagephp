/**
 * Custom hook để quản lý data fetching và caching cho Interaction Logs
 * Sử dụng React Query để tối ưu hóa performance và caching
 */
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { InteractionLogsApi } from '../api/interactionLogsApi';
import { 
  InteractionLog, 
  InteractionLogFilters, 
  CreateInteractionLogForm, 
  UpdateInteractionLogForm,
  PaginationState,
  JSendResponse
} from '../types/interactionLog';
import { isJSendSuccess, extractJSendData } from '../utils/jsend';

// Query keys cho React Query
export const INTERACTION_LOGS_QUERY_KEYS = {
  all: ['interaction-logs'] as const,
  lists: () => [...INTERACTION_LOGS_QUERY_KEYS.all, 'list'] as const,
  list: (filters: InteractionLogFilters) => [...INTERACTION_LOGS_QUERY_KEYS.lists(), filters] as const,
  details: () => [...INTERACTION_LOGS_QUERY_KEYS.all, 'detail'] as const,
  detail: (id: number) => [...INTERACTION_LOGS_QUERY_KEYS.details(), id] as const,
  stats: (projectId: number) => [...INTERACTION_LOGS_QUERY_KEYS.all, 'stats', projectId] as const,
};

/**
 * Hook để fetch danh sách interaction logs với pagination và filtering
 */
export const useInteractionLogs = (filters: InteractionLogFilters) => {
  const [pagination, setPagination] = useState<PaginationState>({
    page: 1,
    perPage: 20,
    total: 0,
    totalPages: 0
  });

  const query = useQuery({
    queryKey: INTERACTION_LOGS_QUERY_KEYS.list(filters),
    queryFn: async () => {
      const response = await InteractionLogsApi.list(filters);
      
      if (!isJSendSuccess(response)) {
        throw new Error(response.message || 'Failed to fetch interaction logs');
      }
      
      const data = extractJSendData(response);
      
      // Cập nhật pagination state từ response
      if (data.pagination) {
        setPagination(data.pagination);
      }
      
      return data;
    },
    staleTime: 5 * 60 * 1000, // 5 phút
    cacheTime: 10 * 60 * 1000, // 10 phút
  });

  return {
    ...query,
    pagination,
    setPagination,
    logs: query.data?.logs || [],
  };
};

/**
 * Hook để fetch chi tiết một interaction log
 */
export const useInteractionLog = (id: number) => {
  return useQuery({
    queryKey: INTERACTION_LOGS_QUERY_KEYS.detail(id),
    queryFn: async () => {
      const response = await InteractionLogsApi.getById(id);
      
      if (!isJSendSuccess(response)) {
        throw new Error(response.message || 'Failed to fetch interaction log');
      }
      
      return extractJSendData(response);
    },
    enabled: !!id,
    staleTime: 5 * 60 * 1000,
  });
};

/**
 * Hook để tạo mới interaction log
 */
export const useCreateInteractionLog = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async (data: CreateInteractionLogForm) => {
      const response = await InteractionLogsApi.create(data);
      
      if (!isJSendSuccess(response)) {
        throw new Error(response.message || 'Failed to create interaction log');
      }
      
      return extractJSendData(response);
    },
    onSuccess: (data) => {
      // Invalidate và refetch các queries liên quan
      queryClient.invalidateQueries({ queryKey: INTERACTION_LOGS_QUERY_KEYS.lists() });
      queryClient.invalidateQueries({ queryKey: INTERACTION_LOGS_QUERY_KEYS.stats(data.project_id) });
    },
  });
};

/**
 * Hook để cập nhật interaction log
 */
export const useUpdateInteractionLog = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: UpdateInteractionLogForm }) => {
      const response = await InteractionLogsApi.update(id, data);
      
      if (!isJSendSuccess(response)) {
        throw new Error(response.message || 'Failed to update interaction log');
      }
      
      return extractJSendData(response);
    },
    onSuccess: (data, variables) => {
      // Cập nhật cache cho item cụ thể
      queryClient.setQueryData(
        INTERACTION_LOGS_QUERY_KEYS.detail(variables.id),
        data
      );
      
      // Invalidate lists
      queryClient.invalidateQueries({ queryKey: INTERACTION_LOGS_QUERY_KEYS.lists() });
    },
  });
};

/**
 * Hook để xóa interaction log
 */
export const useDeleteInteractionLog = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async (id: number) => {
      const response = await InteractionLogsApi.delete(id);
      
      if (!isJSendSuccess(response)) {
        throw new Error(response.message || 'Failed to delete interaction log');
      }
      
      return extractJSendData(response);
    },
    onSuccess: (_, id) => {
      // Remove từ cache
      queryClient.removeQueries({ queryKey: INTERACTION_LOGS_QUERY_KEYS.detail(id) });
      
      // Invalidate lists
      queryClient.invalidateQueries({ queryKey: INTERACTION_LOGS_QUERY_KEYS.lists() });
    },
  });
};

/**
 * Hook để approve interaction log cho client
 */
export const useApproveInteractionLog = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async (id: number) => {
      const response = await InteractionLogsApi.approveForClient(id);
      
      if (!isJSendSuccess(response)) {
        throw new Error(response.message || 'Failed to approve interaction log');
      }
      
      return extractJSendData(response);
    },
    onSuccess: (data, id) => {
      // Cập nhật cache
      queryClient.setQueryData(
        INTERACTION_LOGS_QUERY_KEYS.detail(id),
        data
      );
      
      // Invalidate lists để refresh
      queryClient.invalidateQueries({ queryKey: INTERACTION_LOGS_QUERY_KEYS.lists() });
    },
  });
};

/**
 * Hook để fetch thống kê interaction logs theo project
 */
export const useInteractionLogStats = (projectId: number) => {
  return useQuery({
    queryKey: INTERACTION_LOGS_QUERY_KEYS.stats(projectId),
    queryFn: async () => {
      const response = await InteractionLogsApi.getStatsByProject(projectId);
      
      if (!isJSendSuccess(response)) {
        throw new Error(response.message || 'Failed to fetch interaction log stats');
      }
      
      return extractJSendData(response);
    },
    enabled: !!projectId,
    staleTime: 10 * 60 * 1000, // 10 phút
  });
};