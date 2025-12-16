import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { tasksApi } from './api';
import type { Task, TaskFilters } from './types';
import { invalidateFor, createInvalidationContext } from '@/shared/api/invalidateMap';

export const useTasks = (filters?: TaskFilters, pagination?: { page?: number; per_page?: number }) => {
  return useQuery({
    queryKey: ['tasks', filters, pagination],
    queryFn: () => tasksApi.getTasks(filters, pagination),
  });
};

export const useTask = (id: string | number) => {
  return useQuery({
    queryKey: ['task', id],
    queryFn: () => tasksApi.getTask(id),
    enabled: !!id,
  });
};

export const useCreateTask = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (data: Partial<Task>) => tasksApi.createTask(data),
    onSuccess: (_, data) => {
      invalidateFor('task.create', createInvalidationContext(queryClient, {
        projectId: data.project_id,
      }));
    },
  });
};

export const useUpdateTask = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ id, data }: { id: string | number; data: Partial<Task> }) =>
      tasksApi.updateTask(id, data),
    onSuccess: (taskResponse, variables) => {
      // Invalidate queries to refetch fresh data
      // Note: For drag & drop operations, component handles timing to prevent premature refetch
      // For other updates, invalidate immediately
      
      // Extract task data from response (response may be { data: Task } or Task directly)
      const task = (taskResponse as any)?.data ?? taskResponse;
      
      // Use task.id and task.project_id from server response (source of truth)
      // Fallback to variables if response doesn't have the data
      const resourceId = task?.id ?? variables.id;
      const projectId = task?.project_id ?? task?.projectId ?? variables.data?.project_id;
      
      invalidateFor('task.update', createInvalidationContext(queryClient, {
        resourceId,
        projectId,
      }));
    },
    onError: (error) => {
      console.error('Task update error:', error);
      // Error notification is handled in the component that calls the mutation
    },
  });
};

export const useDeleteTask = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (id: string | number) => tasksApi.deleteTask(id),
    onSuccess: (_, id) => {
      invalidateFor('task.delete', createInvalidationContext(queryClient, {
        resourceId: id,
      }));
    },
  });
};

/**
 * Move task to a new status (Kanban drag-drop)
 * Note: This hook uses invalidateMap for cache invalidation
 */
export const useMoveTask = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ id, data }: { 
      id: string | number; 
      data: {
        to_status: string;
        before_id?: string | number;
        after_id?: string | number;
        reason?: string;
        version?: number;
      }
    }) => tasksApi.moveTask(id, data),
    onSuccess: (_, variables) => {
      // Invalidate cache using invalidateMap
      // Note: Component may handle timing for drag-drop operations
      invalidateFor('task.move', createInvalidationContext(queryClient, {
        resourceId: variables.id,
      }));
    },
  });
};

export const useTasksKpis = (period?: string) => {
  return useQuery({
    queryKey: ['tasks', 'kpis', period],
    queryFn: () => tasksApi.getKpis(period),
    staleTime: 60000, // 1 minute - KPI data doesn't need to be real-time
    cacheTime: 300000, // 5 minutes - keep cached data longer
  });
};

export const useTasksAlerts = () => {
  return useQuery({
    queryKey: ['tasks', 'alerts'],
    queryFn: () => tasksApi.getAlerts(),
    staleTime: 30000, // 30 seconds - alerts need to be fresher than KPIs
    cacheTime: 120000, // 2 minutes
  });
};

export const useTasksActivity = (limit?: number) => {
  return useQuery({
    queryKey: ['tasks', 'activity', limit],
    queryFn: () => tasksApi.getActivity(limit),
  });
};

// Bulk actions hooks
export const useBulkDeleteTasks = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (ids: (string | number)[]) => tasksApi.bulkDeleteTasks(ids),
    onSuccess: () => {
      invalidateFor('task.bulkDelete', createInvalidationContext(queryClient));
    },
  });
};

export const useBulkUpdateStatus = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ ids, status }: { ids: (string | number)[]; status: string }) =>
      tasksApi.bulkUpdateStatus(ids, status),
    onSuccess: () => {
      invalidateFor('task.bulkUpdate', createInvalidationContext(queryClient));
    },
  });
};

export const useBulkAssignTasks = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ ids, assigneeId }: { ids: (string | number)[]; assigneeId: string | number }) =>
      tasksApi.bulkAssignTasks(ids, assigneeId),
    onSuccess: () => {
      invalidateFor('task.bulkAssign', createInvalidationContext(queryClient));
    },
  });
};

/**
 * Get task documents
 */
export const useTaskDocuments = (taskId: string | number) => {
  return useQuery({
    queryKey: ['task', taskId, 'documents'],
    queryFn: () => tasksApi.getTaskDocuments(taskId),
    enabled: !!taskId,
  });
};

/**
 * Get task history/audit log
 */
export const useTaskHistory = (taskId: string | number) => {
  return useQuery({
    queryKey: ['task', taskId, 'history'],
    queryFn: () => tasksApi.getTaskHistory(taskId),
    enabled: !!taskId,
  });
};

