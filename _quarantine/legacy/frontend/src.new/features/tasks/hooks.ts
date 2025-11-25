import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { tasksApi } from './api';
import type { Task, TaskFilters } from './types';

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
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
    },
  });
};

export const useUpdateTask = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ id, data }: { id: string | number; data: Partial<Task> }) =>
      tasksApi.updateTask(id, data),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
      queryClient.invalidateQueries({ queryKey: ['task', variables.id] });
    },
  });
};

export const useDeleteTask = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (id: string | number) => tasksApi.deleteTask(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
    },
  });
};

export const useTasksKpis = (period?: string) => {
  return useQuery({
    queryKey: ['tasks', 'kpis', period],
    queryFn: () => tasksApi.getKpis(period),
  });
};

export const useTasksAlerts = () => {
  return useQuery({
    queryKey: ['tasks', 'alerts'],
    queryFn: () => tasksApi.getAlerts(),
  });
};

export const useTasksActivity = (limit?: number) => {
  return useQuery({
    queryKey: ['tasks', 'activity', limit],
    queryFn: () => tasksApi.getActivity(limit),
  });
};

