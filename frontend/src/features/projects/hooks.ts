import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { projectsApi, templateSetsApi } from './api';
import type { Project, ProjectFilters, ApplyTemplatePayload } from './api';
import { invalidateFor, createInvalidationContext } from '@/shared/api/invalidateMap';

export const useProjects = (filters?: ProjectFilters, pagination?: { page?: number; per_page?: number }) => {
  return useQuery({
    queryKey: ['projects', filters, pagination],
    queryFn: () => projectsApi.getProjects(filters, pagination),
  });
};

export const useProject = (id: string | number) => {
  console.log('[useProject] Hook called with id:', id, 'enabled:', !!id);
  return useQuery({
    queryKey: ['project', id],
    queryFn: () => {
      console.log('[useProject] Fetching project with id:', id);
      return projectsApi.getProject(id);
    },
    enabled: !!id,
  });
};

export const useCreateProject = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (data: Partial<Project>) => projectsApi.createProject(data),
    onSuccess: () => {
      invalidateFor('project.create', createInvalidationContext(queryClient));
    },
  });
};

export const useUpdateProject = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ id, data }: { id: string | number; data: Partial<Project> }) =>
      projectsApi.updateProject(id, data),
    onSuccess: (_, variables) => {
      invalidateFor('project.update', createInvalidationContext(queryClient, {
        resourceId: variables.id,
      }));
    },
  });
};

export const useDeleteProject = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (id: string | number) => projectsApi.deleteProject(id),
    onSuccess: (_, id) => {
      invalidateFor('project.delete', createInvalidationContext(queryClient, {
        resourceId: id,
      }));
    },
  });
};

export const useProjectsKpis = (period?: string) => {
  return useQuery({
    queryKey: ['projects', 'kpis', period],
    queryFn: () => projectsApi.getKpis(period),
  });
};

export const useProjectsAlerts = () => {
  return useQuery({
    queryKey: ['projects', 'alerts'],
    queryFn: () => projectsApi.getAlerts(),
  });
};

export const useProjectsActivity = (limit?: number) => {
  return useQuery({
    queryKey: ['projects', 'activity', limit],
    queryFn: () => projectsApi.getActivity(limit),
  });
};

export const useProjectTasks = (projectId: string | number, filters?: { status?: string; search?: string }, pagination?: { page?: number; per_page?: number }) => {
  return useQuery({
    queryKey: ['projects', projectId, 'tasks', filters, pagination],
    queryFn: () => projectsApi.getProjectTasks(projectId, filters, pagination),
    enabled: !!projectId,
  });
};

export const useProjectDocuments = (projectId: string | number, filters?: { search?: string }, pagination?: { page?: number; per_page?: number }) => {
  return useQuery({
    queryKey: ['projects', projectId, 'documents', filters, pagination],
    queryFn: () => projectsApi.getProjectDocuments(projectId, filters, pagination),
    enabled: !!projectId,
  });
};

export const useArchiveProject = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (id: string | number) => projectsApi.archiveProject(id),
    onSuccess: (_, id) => {
      invalidateFor('project.archive', createInvalidationContext(queryClient, {
        resourceId: id,
      }));
    },
  });
};

export const useAddTeamMember = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ projectId, userId, roleId }: { projectId: string | number; userId: string | number; roleId?: string | number }) =>
      projectsApi.addTeamMember(projectId, userId, roleId),
    onSuccess: (_, variables) => {
      invalidateFor('project.addTeamMember', createInvalidationContext(queryClient, {
        resourceId: variables.projectId,
      }));
    },
  });
};

export const useRemoveTeamMember = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ projectId, userId }: { projectId: string | number; userId: string | number }) =>
      projectsApi.removeTeamMember(projectId, userId),
    onSuccess: (_, variables) => {
      invalidateFor('project.removeTeamMember', createInvalidationContext(queryClient, {
        resourceId: variables.projectId,
      }));
    },
  });
};

export const useTeamMembers = (projectId: string | number) => {
  return useQuery({
    queryKey: ['projects', projectId, 'team-members'],
    queryFn: () => projectsApi.getTeamMembers(projectId),
    enabled: !!projectId,
  });
};

export const useUploadProjectDocument = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ projectId, file, data }: { projectId: string | number; file: File; data?: { name?: string; description?: string; category?: string } }) =>
      projectsApi.uploadProjectDocument(projectId, file, data),
    onSuccess: (_, variables) => {
      invalidateFor('project.uploadDocument', createInvalidationContext(queryClient, {
        resourceId: variables.projectId,
      }));
    },
  });
};

export const useProjectKpis = (projectId: string | number) => {
  return useQuery({
    queryKey: ['project', projectId, 'kpis'],
    queryFn: () => projectsApi.getProjectKpis(projectId),
    enabled: !!projectId,
  });
};

export const useProjectAlerts = (projectId: string | number) => {
  return useQuery({
    queryKey: ['project', projectId, 'alerts'],
    queryFn: () => projectsApi.getProjectAlerts(projectId),
    enabled: !!projectId,
  });
};

export const useProjectOverview = (projectId: string | number | undefined) => {
  return useQuery({
    queryKey: ['project-overview', projectId],
    queryFn: () => {
      if (!projectId) {
        throw new Error('Missing projectId');
      }
      return projectsApi.getProjectOverview(projectId);
    },
    enabled: !!projectId,
  });
};

export interface UseProjectHealthHistoryOptions {
  enabled?: boolean;
  limit?: number;
}

export const useProjectHealthHistory = (projectId: string | number | undefined, options?: UseProjectHealthHistoryOptions) => {
  const enabled = !!projectId && (options?.enabled ?? true);

  return useQuery({
    queryKey: ['project-health-history', projectId, options?.limit ?? 30],
    enabled,
    queryFn: async ({ signal }) => {
      if (!projectId) return [];
      return projectsApi.getProjectHealthHistory(projectId, { limit: options?.limit, signal });
    },
  });
};

/**
 * Template Sets Hooks
 * 
 * Round 99: Apply Template Set to Project
 */
export const useTemplateSets = (options?: { enabled?: boolean; filters?: { search?: string; is_active?: boolean } }) => {
  return useQuery({
    queryKey: ['template-sets', options?.filters],
    queryFn: () => templateSetsApi.listTemplateSets(options?.filters),
    enabled: options?.enabled ?? true,
  });
};

export const useTemplateSetDetail = (setId: string | null, options?: { enabled?: boolean }) => {
  return useQuery({
    queryKey: ['template-set-detail', setId],
    queryFn: () => {
      if (!setId) throw new Error('Template set ID is required');
      return templateSetsApi.getTemplateSetDetail(setId);
    },
    enabled: (options?.enabled ?? true) && !!setId,
  });
};

export const useApplyTemplateToProject = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      projectId,
      payload,
      idempotencyKey,
      signal,
    }: {
      projectId: string | number;
      payload: ApplyTemplatePayload;
      idempotencyKey: string;
      signal?: AbortSignal;
    }) => templateSetsApi.applyTemplateToProject(projectId, payload, idempotencyKey, signal),
    onSuccess: (_, variables) => {
      // Invalidate project tasks and overview to refresh the UI
      queryClient.invalidateQueries({ queryKey: ['projects', variables.projectId, 'tasks'] });
      queryClient.invalidateQueries({ queryKey: ['project-overview', variables.projectId] });
      queryClient.invalidateQueries({ queryKey: ['project', variables.projectId] });
    },
  });
};

