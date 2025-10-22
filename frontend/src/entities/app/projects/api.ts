import { apiClient } from '../../../shared/api/client';
import type {
  Project,
  ProjectsResponse,
  ProjectsFilters,
  CreateProjectRequest,
  UpdateProjectRequest
} from './types';

export const projectsApi = {
  // Get paginated list of projects
  getProjects: async (filters: ProjectsFilters = {}): Promise<ProjectsResponse> => {
    const params = new URLSearchParams();
    
    if (filters.search) params.append('search', filters.search);
    if (filters.status) params.append('status', filters.status);
    if (filters.priority) params.append('priority', filters.priority);
    if (filters.tenant_id) params.append('tenant_id', filters.tenant_id.toString());
    if (filters.created_by) params.append('created_by', filters.created_by.toString());
    if (filters.page) params.append('page', filters.page.toString());
    if (filters.per_page) params.append('per_page', filters.per_page.toString());

    const response = await apiClient.get(`/api/v1/projects?${params.toString()}`);
    return response.data;
  },

  // Get single project by ID
  getProject: async (id: number): Promise<{ data: Project }> => {
    const response = await apiClient.get(`/api/v1/projects/${id}`);
    return response.data;
  },

  // Create new project
  createProject: async (projectData: CreateProjectRequest): Promise<{ data: Project }> => {
    const response = await apiClient.post('/api/v1/projects', projectData);
    return response.data;
  },

  // Update project
  updateProject: async (id: number, projectData: UpdateProjectRequest): Promise<{ data: Project }> => {
    const response = await apiClient.put(`/api/v1/projects/${id}`, projectData);
    return response.data;
  },

  // Delete project
  deleteProject: async (id: number): Promise<void> => {
    await apiClient.delete(`/api/v1/projects/${id}`);
  },

  // Project statistics
  getProjectStats: async (id: number): Promise<{ data: any }> => {
    const response = await apiClient.get(`/api/v1/projects/${id}/stats`);
    return response.data;
  },

  // Add team member
  addTeamMember: async (projectId: number, userId: number, role: string): Promise<void> => {
    await apiClient.post(`/api/v1/projects/${projectId}/team-members`, {
      user_id: userId,
      role
    });
  },

  // Remove team member
  removeTeamMember: async (projectId: number, userId: number): Promise<void> => {
    await apiClient.delete(`/api/v1/projects/${projectId}/team-members/${userId}`);
  }
};
