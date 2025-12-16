import { createApiClient, mapAxiosError } from '../../shared/api/client';

const apiClient = createApiClient();

export interface TemplatePhase {
  id: string;
  code: string;
  name: string;
  order_index: number;
}

export interface TemplateDiscipline {
  id: string;
  code: string;
  name: string;
  color_hex?: string;
  order_index: number;
}

export interface TemplatePreset {
  id: string;
  code: string;
  name: string;
  description?: string;
  filters: {
    phases?: string[];
    disciplines?: string[];
    tasks?: string[];
    include?: string[];
    exclude?: string[];
  };
}

export interface TemplateSet {
  id: string;
  code: string;
  name: string;
  description?: string;
  version: string;
  is_global: boolean;
  phases: TemplatePhase[];
  disciplines: TemplateDiscipline[];
  presets: TemplatePreset[];
}

export interface TemplatePreviewResult {
  total_tasks: number;
  total_dependencies: number;
  estimated_duration: number;
  breakdown: {
    phase: Record<string, number>;
    discipline: Record<string, number>;
  };
}

export interface TemplateApplyResult {
  tasks_created: number;
  dependencies_created: number;
  warnings: string[];
  errors: string[];
}

export interface TemplateApplyLog {
  id: string;
  set: {
    id: string;
    code: string;
    name: string;
  };
  preset_code?: string;
  selections: {
    phases?: string[];
    disciplines?: string[];
    tasks?: string[];
  };
  counts: {
    tasks_created: number;
    dependencies_created: number;
    warnings_count: number;
    errors_count: number;
  };
  executor: {
    id: string;
    name: string;
    email: string;
  };
  duration_ms: number;
  created_at: string;
}

export interface TemplatePreviewPayload {
  set_id: string;
  project_id: string;
  preset_code?: string;
  selections?: {
    phases?: string[];
    disciplines?: string[];
    tasks?: string[];
  };
  options?: {
    map_phase_to_kanban?: boolean;
    auto_assign_by_role?: boolean;
    create_deliverable_folders?: boolean;
  };
}

export interface TemplateApplyPayload {
  set_id: string;
  preset_code?: string;
  selections?: {
    phases?: string[];
    disciplines?: string[];
    tasks?: string[];
  };
  options?: {
    conflict_behavior?: 'skip' | 'rename' | 'merge';
    map_phase_to_kanban?: boolean;
    auto_assign_by_role?: boolean;
    create_deliverable_folders?: boolean;
  };
}

/**
 * Templates API Client
 * 
 * Endpoints from routes/api_v1.php: /api/v1/app/template-sets/*
 */
export const templatesApi = {
  /**
   * Get all available template sets
   */
  async getTemplates(): Promise<{ data: TemplateSet[] }> {
    try {
      const response = await apiClient.get<{ success: boolean; data: TemplateSet[] }>('/v1/app/template-sets');
      // Handle ApiResponse format
      if (response.data.success !== undefined) {
        return { data: (response.data as any).data || [] };
      }
      return { data: (response.data as any) || [] };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Preview template application
   */
  async previewTemplate(payload: TemplatePreviewPayload): Promise<{ data: TemplatePreviewResult }> {
    try {
      const response = await apiClient.post<{ success: boolean; data: TemplatePreviewResult }>(
        '/v1/app/template-sets/preview',
        payload
      );
      // Handle ApiResponse format
      if (response.data.success !== undefined) {
        return { data: (response.data as any).data };
      }
      return { data: response.data as any };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Apply template to project
   */
  async applyTemplate(projectId: string, payload: TemplateApplyPayload): Promise<{ data: TemplateApplyResult }> {
    try {
      const response = await apiClient.post<{ success: boolean; data: TemplateApplyResult }>(
        `/v1/app/projects/${projectId}/apply-template`,
        payload
      );
      // Handle ApiResponse format
      if (response.data.success !== undefined) {
        return { data: (response.data as any).data };
      }
      return { data: response.data as any };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get template application history for a project
   */
  async getTemplateHistory(projectId: string): Promise<{ data: TemplateApplyLog[] }> {
    try {
      const response = await apiClient.get<{ success: boolean; data: TemplateApplyLog[] }>(
        `/v1/app/projects/${projectId}/template-history`
      );
      // Handle ApiResponse format
      if (response.data.success !== undefined) {
        return { data: (response.data as any).data || [] };
      }
      return { data: (response.data as any) || [] };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};
