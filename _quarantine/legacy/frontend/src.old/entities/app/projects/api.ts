import { apiClient } from '../../../shared/api/client';
import type {
  Project,
  ProjectsResponse,
  ProjectsFilters,
  CreateProjectRequest,
  UpdateProjectRequest,
  ProjectsMetrics,
  ProjectAlert,
  ProjectActivity
} from './types';
import type { ApiResponse } from '../../dashboard/types';

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

    const response = await apiClient.get(`/app/projects?${params.toString()}`);
    const payload = response.data ?? {};
    const meta = payload.meta ?? {};
    const currentPage = meta.current_page ?? 1;
    const lastPage = meta.last_page ?? currentPage;

    return {
      data: payload.data ?? [],
      meta: {
        current_page: currentPage,
        last_page: lastPage,
        per_page: meta.per_page ?? filters.per_page ?? 15,
        total: meta.total ?? (payload.data?.length ?? 0),
      },
      links: payload.links ?? {
        first: payload.links?.first ?? '',
        last: payload.links?.last ?? '',
        prev: currentPage > 1 ? String(currentPage - 1) : undefined,
        next: currentPage < lastPage ? String(currentPage + 1) : undefined,
      },
    };
  },

  // Get single project by ID
  getProject: async (id: number): Promise<{ data: Project }> => {
    const response = await apiClient.get(`/app/projects/${id}`);
    return response.data;
  },

  // Create new project
  createProject: async (projectData: CreateProjectRequest): Promise<{ data: Project }> => {
    const response = await apiClient.post('/app/projects', projectData);
    return response.data;
  },

  // Update project
  updateProject: async (id: number, projectData: UpdateProjectRequest): Promise<{ data: Project }> => {
    const response = await apiClient.put(`/app/projects/${id}`, projectData);
    return response.data;
  },

  // Delete project
  deleteProject: async (id: number): Promise<void> => {
    await apiClient.delete(`/app/projects/${id}`);
  },

  // Project statistics
  getProjectStats: async (id: number): Promise<{ data: any }> => {
    const response = await apiClient.get(`/app/projects/${id}/stats`);
    return response.data;
  },

  // Add team member
  addTeamMember: async (projectId: number, userId: number, role: string): Promise<void> => {
    await apiClient.post(`/app/projects/${projectId}/team-members`, {
      user_id: userId,
      role
    });
  },

  // Remove team member
  removeTeamMember: async (projectId: number, userId: number): Promise<void> => {
    await apiClient.delete(`/app/projects/${projectId}/team-members/${userId}`);
  },

  // Export projects to CSV
  exportProjects: async (filters: ProjectsFilters = {}): Promise<Blob> => {
    const params = new URLSearchParams();
    
    if (filters.search) params.append('search', filters.search);
    if (filters.status) params.append('status', filters.status);
    if (filters.priority) params.append('priority', filters.priority);
    if (filters.tenant_id) params.append('tenant_id', filters.tenant_id.toString());
    if (filters.created_by) params.append('created_by', filters.created_by.toString());
    params.append('format', 'csv');

    const response = await apiClient.get(`/admin/csv/export/projects?${params.toString()}`, {
      responseType: 'blob',
      headers: {
        'Accept': 'text/csv'
      }
    });
    return response.data;
  },

  // Get Projects KPIs
  getProjectsKpis: async (period?: string): Promise<ApiResponse<ProjectsMetrics>> => {
    const params = new URLSearchParams();
    if (period) params.append('period', period);
    
    const response = await apiClient.get(`/app/projects/kpis?${params.toString()}`);
    return response.data;
  },

  // Get Projects Alerts
  getProjectsAlerts: async (): Promise<ApiResponse<ProjectAlert[]>> => {
    const response = await apiClient.get('/app/projects/alerts');
    return response.data;
  },

  // Get Projects Activity
  getProjectsActivity: async (limit: number = 10): Promise<ApiResponse<ProjectActivity[]>> => {
    const params = new URLSearchParams();
    params.append('limit', limit.toString());
    
    const response = await apiClient.get(`/app/projects/activity?${params.toString()}`);
    return response.data;
  },
};
