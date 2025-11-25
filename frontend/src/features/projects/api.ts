import { createApiClient, mapAxiosError } from '../../shared/api/client';

const apiClient = createApiClient();

export interface Project {
  id: string | number;
  name: string;
  description?: string;
  status: 'planning' | 'active' | 'on_hold' | 'completed' | 'cancelled';
  order?: number;
  priority?: string;
  owner_id?: string | number;
  start_date?: string;
  end_date?: string;
  budget_total?: number;
  created_at: string;
  updated_at: string;
}

export interface ProjectFilters {
  search?: string;
  status?: string;
  priority?: string;
  owner_id?: string | number;
}

export interface ProjectsResponse {
  data: Project[];
  meta?: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
}

/**
 * Projects API Client
 * 
 * Endpoints from routes/api_v1.php: /api/v1/app/projects/*
 * Route prefix: Route::prefix('v1/app') -> Route::prefix('projects') under auth:sanctum + ability:tenant middleware
 */
export const projectsApi = {
  async getProjects(filters?: ProjectFilters, pagination?: { page?: number; per_page?: number }): Promise<ProjectsResponse> {
    try {
      const params = new URLSearchParams();
      if (filters?.search) params.append('search', filters.search);
      if (filters?.status) params.append('status', filters.status);
      if (filters?.priority) params.append('priority', filters.priority);
      if (filters?.owner_id) params.append('owner_id', String(filters.owner_id));
      if (pagination?.page) params.append('page', String(pagination.page));
      if (pagination?.per_page) params.append('per_page', String(pagination.per_page));

      const response = await apiClient.get<{ success?: boolean; data?: Project[]; meta?: any } | ProjectsResponse>(`/v1/app/projects?${params.toString()}`);
      // Handle both response formats: { success: true, data: [...], meta: {...} } or { data: [...], meta: {...} }
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        const apiResponse = response.data as { success: boolean; data: Project[]; meta?: any };
        return {
          data: apiResponse.data || [],
          meta: apiResponse.meta,
        };
      }
      return response.data as ProjectsResponse;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getProject(id: string | number): Promise<{ data: Project }> {
    try {
      const response = await apiClient.get<{ success?: boolean; data: Project }>(`/v1/app/projects/${id}`);
      // Handle both response formats: { success: true, data: {...} } or { data: {...} }
      if (response.data.success !== undefined) {
        return { data: (response.data as any).data };
      }
      return response.data as { data: Project };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async createProject(data: Partial<Project>): Promise<{ data: Project }> {
    try {
      const response = await apiClient.post<{ data: Project }>('/v1/app/projects', data);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async updateProject(id: string | number, data: Partial<Project>): Promise<{ data: Project }> {
    try {
      const response = await apiClient.put<{ data: Project }>(`/v1/app/projects/${id}`, data);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async deleteProject(id: string | number): Promise<void> {
    try {
      await apiClient.delete(`/v1/app/projects/${id}`);
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getKpis(period?: string): Promise<any> {
    try {
      const params = period ? `?period=${period}` : '';
      const response = await apiClient.get(`/v1/app/projects/kpis${params}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getAlerts(): Promise<any> {
    try {
      const response = await apiClient.get('/v1/app/projects/alerts');
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getActivity(limit?: number): Promise<any> {
    try {
      const params = limit ? `?limit=${limit}` : '';
      const response = await apiClient.get(`/v1/app/projects/activity${params}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getProjectTasks(projectId: string | number, filters?: { status?: string; search?: string }, pagination?: { page?: number; per_page?: number }): Promise<any> {
    try {
      const params = new URLSearchParams();
      if (filters?.status) params.append('status', filters.status);
      if (filters?.search) params.append('search', filters.search);
      if (pagination?.page) params.append('page', String(pagination.page));
      if (pagination?.per_page) params.append('per_page', String(pagination.per_page));

      const queryString = params.toString();
      const url = `/v1/app/projects/${projectId}/tasks${queryString ? `?${queryString}` : ''}`;
      const response = await apiClient.get(url);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getProjectDocuments(projectId: string | number, filters?: { search?: string }, pagination?: { page?: number; per_page?: number }): Promise<any> {
    try {
      const params = new URLSearchParams();
      if (filters?.search) params.append('search', filters.search);
      if (pagination?.page) params.append('page', String(pagination.page));
      if (pagination?.per_page) params.append('per_page', String(pagination.per_page));

      const queryString = params.toString();
      const url = `/v1/app/projects/${projectId}/documents${queryString ? `?${queryString}` : ''}`;
      const response = await apiClient.get(url);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async archiveProject(id: string | number): Promise<{ data: Project }> {
    try {
      const response = await apiClient.put<{ data: Project }>(`/v1/app/projects/${id}/archive`, {});
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async addTeamMember(projectId: string | number, userId: string | number, roleId?: string | number): Promise<{ data: Project }> {
    try {
      const payload: any = { user_id: userId };
      if (roleId) {
        payload.role_id = roleId;
      }
      const response = await apiClient.post<{ data: Project }>(`/v1/app/projects/${projectId}/team-members`, payload);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async removeTeamMember(projectId: string | number, userId: string | number): Promise<{ data: Project }> {
    try {
      const response = await apiClient.delete<{ data: Project }>(`/v1/app/projects/${projectId}/team-members/${userId}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getTeamMembers(projectId: string | number): Promise<{ data: Array<{ id: string | number; name: string; email: string; role?: string }> }> {
    try {
      const response = await apiClient.get<{ data: Array<{ id: string | number; name: string; email: string; role?: string }> }>(`/v1/app/projects/${projectId}/team-members`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async uploadProjectDocument(projectId: string | number, file: File, data?: { name?: string; description?: string; category?: string }): Promise<{ data: any }> {
    try {
      const formData = new FormData();
      formData.append('file', file);
      formData.append('project_id', String(projectId));
      if (data?.name) formData.append('name', data.name);
      if (data?.description) formData.append('description', data.description);
      if (data?.category) formData.append('category', data.category);

      const response = await apiClient.post<{ data: any }>('/v1/app/documents', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getProjectKpis(projectId: string | number): Promise<any> {
    try {
      const response = await apiClient.get(`/v1/app/projects/${projectId}/kpis`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getProjectAlerts(projectId: string | number): Promise<any> {
    try {
      const response = await apiClient.get(`/v1/app/projects/${projectId}/alerts`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getProjectOverview(projectId: string | number): Promise<{ data: ProjectOverviewDto }> {
    try {
      const response = await apiClient.get<{ success?: boolean; data: ProjectOverviewDto }>(`/v1/app/projects/${projectId}/overview`);
      // Handle both response formats: { success: true, data: {...} } or { data: {...} }
      if (response.data.success !== undefined) {
        return { data: (response.data as any).data };
      }
      return response.data as { data: ProjectOverviewDto };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getProjectHealthHistory(projectId: string | number, params?: { limit?: number; signal?: AbortSignal }): Promise<ProjectHealthSnapshot[]> {
    try {
      const urlParams = new URLSearchParams();
      if (params?.limit) {
        urlParams.append('limit', String(params.limit));
      }
      const queryString = urlParams.toString();
      const url = `/v1/app/projects/${projectId}/health/history${queryString ? `?${queryString}` : ''}`;
      
      const response = await apiClient.get<{ success?: boolean; data?: ProjectHealthSnapshot[] } | { data: ProjectHealthSnapshot[] }>(url, {
        signal: params?.signal,
      });
      
      // Handle both response formats: { success: true, data: [...] } or { data: [...] }
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        const apiResponse = response.data as { success: boolean; data?: ProjectHealthSnapshot[] };
        return apiResponse.data || [];
      }
      const directResponse = response.data as { data: ProjectHealthSnapshot[] };
      return directResponse.data || [];
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

/**
 * Project Overview Task Summary
 * 
 * Round 68: Key Tasks
 */
export type ProjectOverviewTaskSummary = {
  id: string;
  name: string;
  status: string;
  priority: string | null;
  end_date: string | null;
  assignee: { id: string; name: string } | null;
};

/**
 * Project Overview Health Summary
 * 
 * Round 70: Project Health Summary
 */
export type ProjectOverviewHealth = {
  tasks_completion_rate: number | null;
  blocked_tasks_ratio: number | null;
  overdue_tasks: number;
  schedule_status: 'on_track' | 'at_risk' | 'delayed' | 'no_tasks';
  cost_status: 'on_budget' | 'over_budget' | 'at_risk' | 'no_data';
  cost_overrun_percent: number | null;
  overall_status: 'good' | 'warning' | 'critical';
};

/**
 * Project Health Snapshot
 * 
 * Round 87: Project Health History UI
 */
export interface ProjectHealthSnapshot {
  id: string;
  snapshot_date: string; // 'YYYY-MM-DD'
  overall_status: string;
  schedule_status: string;
  cost_status: string;
  tasks_completion_rate: number | null;
  blocked_tasks_ratio: number | null;
  overdue_tasks: number;
  created_at: string | null;
}

/**
 * Project Overview DTO
 * 
 * Round 67: Project Overview Cockpit
 * Round 68: Key Tasks
 * Round 70: Project Health Summary
 */
export type ProjectOverviewDto = {
  project: {
    id: string;
    code: string | null;
    name: string;
    status: string;
    priority: string | null;
    risk_level: string | null;
    start_date: string | null;
    end_date: string | null;
    client: { id: string; name: string } | null;
    owner: { id: string; name: string } | null;
  };
  financials: {
    has_financial_data: boolean;
    contracts_count: number;
    contracts_value_total: number | null;
    budget_total: number | null;
    actual_total: number | null;
    overrun_amount_total: number | null;
    over_budget_contracts_count: number;
    overrun_contracts_count: number;
    currency: string | null;
  };
  tasks: {
    total: number;
    by_status: Record<string, number>;
    overdue: number;
    due_soon: number;
    key_tasks: {
      overdue: ProjectOverviewTaskSummary[];
      due_soon: ProjectOverviewTaskSummary[];
      blocked: ProjectOverviewTaskSummary[];
    };
  };
  health: ProjectOverviewHealth;
};

/**
 * Template Set types
 * 
 * Round 99: Apply Template Set to Project
 */
export interface TemplateSet {
  id: string;
  code: string;
  name: string;
  description?: string;
  version?: string;
  is_active?: boolean;
  is_global?: boolean;
}

export interface TemplatePreset {
  id: string;
  code: string;
  name: string;
  description?: string;
}

export interface TemplateSetDetail extends TemplateSet {
  presets?: TemplatePreset[];
}

export interface ApplyTemplatePayload {
  template_set_id: string;
  preset_id?: string | null;
  options?: {
    include_dependencies?: boolean;
  };
}

export interface ApplyTemplateResponse {
  project_id: string;
  template_set_id: string;
  created_tasks: number;
  created_dependencies: number;
}

/**
 * Template Sets API
 * 
 * Round 99: Apply Template Set to Project
 */
export const templateSetsApi = {
  /**
   * List available template sets for the tenant
   * GET /api/v1/app/task-templates
   */
  async listTemplateSets(filters?: { search?: string; is_active?: boolean }): Promise<{ data: TemplateSet[] }> {
    try {
      const params = new URLSearchParams();
      if (filters?.search) params.append('search', filters.search);
      if (filters?.is_active !== undefined) params.append('is_active', String(filters.is_active));

      const queryString = params.toString();
      const url = `/v1/app/task-templates${queryString ? `?${queryString}` : ''}`;
      const response = await apiClient.get<{ success?: boolean; data?: TemplateSet[] } | { data: TemplateSet[] }>(url);
      
      // Handle both response formats
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        const apiResponse = response.data as { success: boolean; data?: TemplateSet[] };
        return { data: apiResponse.data || [] };
      }
      return response.data as { data: TemplateSet[] };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get template set detail with presets
   * GET /api/v1/app/task-templates/{set}
   */
  async getTemplateSetDetail(setId: string): Promise<{ data: TemplateSetDetail }> {
    try {
      const response = await apiClient.get<{ success?: boolean; data?: TemplateSetDetail } | { data: TemplateSetDetail }>(`/v1/app/task-templates/${setId}`);
      
      // Handle both response formats
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        const apiResponse = response.data as { success: boolean; data?: TemplateSetDetail };
        return { data: apiResponse.data! };
      }
      return response.data as { data: TemplateSetDetail };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Apply template set to project
   * POST /api/v1/app/projects/{project}/task-templates/apply
   */
  async applyTemplateToProject(
    projectId: string | number,
    payload: ApplyTemplatePayload,
    idempotencyKey: string,
    signal?: AbortSignal
  ): Promise<{ data: ApplyTemplateResponse }> {
    try {
      const response = await apiClient.post<{ data: ApplyTemplateResponse }>(
        `/v1/app/projects/${projectId}/task-templates/apply`,
        payload,
        {
          headers: {
            'Idempotency-Key': idempotencyKey,
          },
          signal,
        }
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

