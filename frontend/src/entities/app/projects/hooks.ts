import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { projectsApi } from './api';
import type {
  Project,
  ProjectsFilters,
  CreateProjectRequest,
  UpdateProjectRequest
} from './types';

// Query Keys
export const projectsKeys = {
  all: ['projects'] as const,
  lists: () => [...projectsKeys.all, 'list'] as const,
  list: (filters: ProjectsFilters) => [...projectsKeys.lists(), filters] as const,
  details: () => [...projectsKeys.all, 'detail'] as const,
  detail: (id: number) => [...projectsKeys.details(), id] as const,
  stats: (id: number) => [...projectsKeys.detail(id), 'stats'] as const,
};

// Get projects list with filters
export const useProjects = (filters: ProjectsFilters = {}) => {
  return useQuery({
    queryKey: projectsKeys.list(filters),
    queryFn: () => projectsApi.getProjects(filters),
    staleTime: 30_000, // 30 seconds
    retry: 1,
  });
};

// Get single project
export const useProject = (id: number, enabled: boolean = true) => {
  return useQuery({
    queryKey: projectsKeys.detail(id),
    queryFn: () => projectsApi.getProject(id),
    enabled: enabled && !!id,
    staleTime: 60_000, // 1 minute
    retry: 1,
  });
};

// Get project stats
export const useProjectStats = (id: number, enabled: boolean = true) => {
  return useQuery({
    queryKey: projectsKeys.stats(id),
    queryFn: () => projectsApi.getProjectStats(id),
    enabled: enabled && !!id,
    staleTime: 30_000, // 30 seconds
    retry: 1,
  });
};

// Create project mutation
export const useCreateProject = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (projectData: CreateProjectRequest) => projectsApi.createProject(projectData),
    onSuccess: () => {
      // Invalidate projects list
      queryClient.invalidateQueries({ queryKey: projectsKeys.lists() });
    },
  });
};

// Update project mutation
export const useUpdateProject = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ id, projectData }: { id: number; projectData: UpdateProjectRequest }) =>
      projectsApi.updateProject(id, projectData),
    onSuccess: (_, { id }) => {
      // Invalidate specific project and projects list
      queryClient.invalidateQueries({ queryKey: projectsKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: projectsKeys.lists() });
    },
  });
};

// Delete project mutation
export const useDeleteProject = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (id: number) => projectsApi.deleteProject(id),
    onSuccess: () => {
      // Invalidate projects list
      queryClient.invalidateQueries({ queryKey: projectsKeys.lists() });
    },
  });
};

// Add team member mutation
export const useAddTeamMember = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ projectId, userId, role }: { projectId: number; userId: number; role: string }) =>
      projectsApi.addTeamMember(projectId, userId, role),
    onSuccess: (_, { projectId }) => {
      // Invalidate project details
      queryClient.invalidateQueries({ queryKey: projectsKeys.detail(projectId) });
    },
  });
};

// Remove team member mutation
export const useRemoveTeamMember = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ projectId, userId }: { projectId: number; userId: number }) =>
      projectsApi.removeTeamMember(projectId, userId),
    onSuccess: (_, { projectId }) => {
      // Invalidate project details
      queryClient.invalidateQueries({ queryKey: projectsKeys.detail(projectId) });
    },
  });
};
