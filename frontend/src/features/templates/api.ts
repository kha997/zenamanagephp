import { createApiClient, mapAxiosError } from '../../shared/api/client';

const apiClient = createApiClient();

export interface Template {
  id: string | number;
  name: string;
  type?: string; // 'project' | 'task' | 'document' | 'checklist'
  category?: string; // Backend uses category, frontend can use type
  description?: string;
  is_active: boolean;
  metadata?: Record<string, any>;
  created_by?: string | number;
  updated_by?: string | number;
  created_at: string;
  updated_at: string;
}

export interface TemplateFilters {
  type?: 'project' | 'task' | 'document' | 'checklist';
  is_active?: boolean;
  search?: string;
}

export interface TemplatesResponse {
  data: Template[];
  pagination?: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from?: number;
    to?: number;
  };
}

export interface CreateTemplateData {
  name: string;
  type: 'project' | 'task' | 'document' | 'checklist';
  description?: string;
  is_active?: boolean;
  metadata?: Record<string, any>;
}

export interface UpdateTemplateData {
  name?: string;
  type?: 'project' | 'task' | 'document' | 'checklist';
  description?: string;
  is_active?: boolean;
  metadata?: Record<string, any>;
}

/**
 * Templates API Client
 * 
 * Endpoints from routes/api_v1.php: /api/v1/app/templates/*
 * Round 192: Templates Vertical MVP
 */
export const templatesApi = {
  /**
   * List templates with filters and pagination
   * GET /api/v1/app/templates
   */
  async getTemplates(
    filters?: TemplateFilters,
    pagination?: { page?: number; per_page?: number }
  ): Promise<TemplatesResponse> {
    try {
      const params = new URLSearchParams();
      if (filters?.type) params.append('type', filters.type);
      if (filters?.is_active !== undefined) params.append('is_active', String(filters.is_active));
      if (filters?.search) params.append('search', filters.search);
      if (pagination?.page) params.append('page', String(pagination.page));
      if (pagination?.per_page) params.append('per_page', String(pagination.per_page));

      const response = await apiClient.get<{ success?: boolean; data?: Template[]; pagination?: any } | TemplatesResponse>(
        `/app/templates?${params.toString()}`
      );

      // Handle both response formats
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        const apiResponse = response.data as { success: boolean; data: Template[]; pagination?: any };
        return {
          data: apiResponse.data || [],
          pagination: apiResponse.pagination,
        };
      }
      return response.data as TemplatesResponse;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get a single template by ID
   * GET /api/v1/app/templates/{id}
   */
  async getTemplate(id: string | number): Promise<{ data: Template }> {
    try {
      const response = await apiClient.get<{ success?: boolean; data: Template }>(`/app/templates/${id}`);
      // Handle both response formats
      if (response.data.success !== undefined) {
        return { data: (response.data as any).data };
      }
      return response.data as { data: Template };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Create a new template
   * POST /api/v1/app/templates
   */
  async createTemplate(data: CreateTemplateData): Promise<{ data: Template }> {
    try {
      const response = await apiClient.post<{ data: Template }>('/app/templates', data);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Update an existing template
   * PATCH /api/v1/app/templates/{id}
   */
  async updateTemplate(id: string | number, data: UpdateTemplateData): Promise<{ data: Template }> {
    try {
      const response = await apiClient.patch<{ data: Template }>(`/app/templates/${id}`, data);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Delete a template (soft delete)
   * DELETE /api/v1/app/templates/{id}
   */
  async deleteTemplate(id: string | number): Promise<void> {
    try {
      await apiClient.delete(`/app/templates/${id}`);
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Create a project from a template
   * POST /api/v1/app/templates/{templateId}/projects
   */
  async createProjectFromTemplate(
    templateId: string | number,
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
    }
  ): Promise<{ data: any }> {
    try {
      const response = await apiClient.post<{ data: any }>(
        `/app/templates/${templateId}/projects`,
        data
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

/**
 * Task Template Types
 * 
 * Round 200: Task Template Vertical MVP
 */
export interface TaskTemplate {
  id: string;
  template_id: string;
  name: string;
  description?: string | null;
  order_index?: number | null;
  phase_code?: string | null;
  phase_label?: string | null;
  group_label?: string | null;
  estimated_hours?: number | null;
  is_required: boolean;
  metadata?: Record<string, any> | null;
  created_by?: string | null;
  updated_by?: string | null;
  created_at: string;
  updated_at: string;
}

export interface TaskTemplatePayload {
  name: string;
  description?: string | null;
  order_index?: number | null;
  phase_code?: string | null;
  phase_label?: string | null;
  group_label?: string | null;
  estimated_hours?: number | null;
  is_required?: boolean;
  metadata?: Record<string, any> | null;
}

export interface TaskTemplatesResponse {
  data: TaskTemplate[];
  pagination?: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from?: number;
    to?: number;
  };
}

/**
 * Task Templates API Client
 * 
 * Endpoints from routes/api_v1.php: /api/v1/app/templates/{tpl}/task-templates/*
 * Round 200: Task Template Vertical MVP
 */
export const taskTemplatesApi = {
  /**
   * List task templates for a template
   * GET /api/v1/app/templates/{templateId}/task-templates
   */
  async getTaskTemplates(
    templateId: string | number,
    params?: { is_required?: boolean; search?: string; sort_by?: string; sort_direction?: string; per_page?: number }
  ): Promise<TaskTemplatesResponse> {
    try {
      const queryParams = new URLSearchParams();
      if (params?.is_required !== undefined) queryParams.append('is_required', String(params.is_required));
      if (params?.search) queryParams.append('search', params.search);
      if (params?.sort_by) queryParams.append('sort_by', params.sort_by);
      if (params?.sort_direction) queryParams.append('sort_direction', params.sort_direction);
      if (params?.per_page) queryParams.append('per_page', String(params.per_page));

      const response = await apiClient.get<{ success?: boolean; data?: TaskTemplate[]; meta?: any } | TaskTemplatesResponse>(
        `/app/templates/${templateId}/task-templates${queryParams.toString() ? `?${queryParams.toString()}` : ''}`
      );

      // Handle both response formats
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        const apiResponse = response.data as { success: boolean; data: TaskTemplate[]; meta?: any };
        return {
          data: apiResponse.data || [],
          pagination: apiResponse.meta,
        };
      }
      return response.data as TaskTemplatesResponse;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Create a new task template
   * POST /api/v1/app/templates/{templateId}/task-templates
   */
  async createTaskTemplate(
    templateId: string | number,
    data: TaskTemplatePayload
  ): Promise<{ data: TaskTemplate }> {
    try {
      const response = await apiClient.post<{ data: TaskTemplate }>(
        `/app/templates/${templateId}/task-templates`,
        data
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Update an existing task template
   * PATCH /api/v1/app/templates/{templateId}/task-templates/{taskId}
   */
  async updateTaskTemplate(
    templateId: string | number,
    taskId: string | number,
    data: TaskTemplatePayload
  ): Promise<{ data: TaskTemplate }> {
    try {
      const response = await apiClient.patch<{ data: TaskTemplate }>(
        `/app/templates/${templateId}/task-templates/${taskId}`,
        data
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Delete a task template (soft delete)
   * DELETE /api/v1/app/templates/{templateId}/task-templates/{taskId}
   */
  async deleteTaskTemplate(templateId: string | number, taskId: string | number): Promise<void> {
    try {
      await apiClient.delete(`/app/templates/${templateId}/task-templates/${taskId}`);
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

