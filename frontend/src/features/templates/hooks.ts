import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { templatesApi, taskTemplatesApi } from './api';
import type { Template, TemplateFilters, CreateTemplateData, UpdateTemplateData, TaskTemplate, TaskTemplatePayload } from './api';

/**
 * Templates Hooks (React Query)
 * 
 * Round 192: Templates Vertical MVP
 */

/**
 * Get templates list with filters
 */
export const useTemplates = (
  filters?: TemplateFilters,
  pagination?: { page?: number; per_page?: number }
) => {
  return useQuery({
    queryKey: ['templates', filters, pagination],
    queryFn: () => templatesApi.getTemplates(filters, pagination),
    staleTime: 30 * 1000, // 30 seconds
    gcTime: 5 * 60 * 1000, // 5 minutes
  });
};

/**
 * Get a single template by ID
 */
export const useTemplate = (id: string | number | null) => {
  return useQuery({
    queryKey: ['template', id],
    queryFn: () => templatesApi.getTemplate(id!),
    enabled: !!id,
  });
};

/**
 * Create a new template
 */
export const useCreateTemplate = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: CreateTemplateData) => templatesApi.createTemplate(data),
    onSuccess: () => {
      // Invalidate templates list queries
      queryClient.invalidateQueries({ queryKey: ['templates'] });
    },
  });
};

/**
 * Update an existing template
 */
export const useUpdateTemplate = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, data }: { id: string | number; data: UpdateTemplateData }) =>
      templatesApi.updateTemplate(id, data),
    onSuccess: (_, variables) => {
      // Invalidate both list and detail queries
      queryClient.invalidateQueries({ queryKey: ['templates'] });
      queryClient.invalidateQueries({ queryKey: ['template', variables.id] });
    },
  });
};

/**
 * Delete a template
 */
export const useDeleteTemplate = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: string | number) => templatesApi.deleteTemplate(id),
    onSuccess: () => {
      // Invalidate templates list queries
      queryClient.invalidateQueries({ queryKey: ['templates'] });
    },
  });
};

/**
 * Create a project from a template
 */
export const useCreateProjectFromTemplate = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      templateId,
      data,
    }: {
      templateId: string | number;
      data: {
        name: string;
        description?: string;
        code?: string;
        status?: string;
        priority?: string;
        start_date?: string;
        end_date?: string;
        budget_total?: number;
        owner_id?: string;
        client_id?: string;
        tags?: string[];
      };
    }) => templatesApi.createProjectFromTemplate(templateId, data),
    onSuccess: () => {
      // Invalidate projects list queries
      queryClient.invalidateQueries({ queryKey: ['projects'] });
    },
  });
};

/**
 * Task Templates Hooks (React Query)
 * 
 * Round 200: Task Template Vertical MVP
 */

/**
 * Get task templates list for a template
 */
export const useTaskTemplates = (templateId: string | number | null) => {
  return useQuery({
    queryKey: ['taskTemplates', templateId],
    queryFn: () => taskTemplatesApi.getTaskTemplates(templateId!),
    enabled: !!templateId,
    staleTime: 30 * 1000, // 30 seconds
    gcTime: 5 * 60 * 1000, // 5 minutes
  });
};

/**
 * Create a new task template
 */
export const useCreateTaskTemplate = (templateId: string | number) => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: TaskTemplatePayload) => taskTemplatesApi.createTaskTemplate(templateId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['taskTemplates', templateId] });
    },
  });
};

/**
 * Update an existing task template
 */
export const useUpdateTaskTemplate = (templateId: string | number, taskId: string | number) => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: TaskTemplatePayload) => taskTemplatesApi.updateTaskTemplate(templateId, taskId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['taskTemplates', templateId] });
    },
  });
};

/**
 * Delete a task template
 */
export const useDeleteTaskTemplate = (templateId: string | number) => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (taskId: string | number) => taskTemplatesApi.deleteTaskTemplate(templateId, taskId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['taskTemplates', templateId] });
    },
  });
};

