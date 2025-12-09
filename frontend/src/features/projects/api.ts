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

/**
 * ProjectTask interface
 * 
 * Round 203: ProjectTasks checklist view
 * Round 207: Added is_completed and completed_at fields
 * Round 213: Added assignee_id for task assignment
 * Round 217: Added project field for My Tasks grouping
 * Checklist tasks auto-generated from TaskTemplates when creating a project from a template.
 */
export interface ProjectTask {
  id: string;
  project_id: string;
  template_task_id?: string | null;
  phase_code?: string | null;
  phase_label?: string | null;
  group_label?: string | null;
  name: string;
  description?: string | null;
  sort_order: number;
  is_milestone: boolean;
  status?: string | null;
  due_date?: string | null; // ISO date string
  is_completed: boolean;
  completed_at?: string | null; // ISO date string
  assignee_id?: string | null; // Round 213: Task assignment
  metadata?: Record<string, unknown> | null;
  created_at: string;
  updated_at: string;
  project?: { // Round 217: Project info for My Tasks grouping
    id: string;
    name: string;
    code?: string | null;
    status?: string | null;
  } | null;
}

/**
 * ProjectTaskUpdatePayload interface
 * 
 * Round 207: Payload for updating project task
 * Round 213: Added assignee_id for task assignment
 */
export interface ProjectTaskUpdatePayload {
  name?: string;
  description?: string;
  status?: string;
  due_date?: string | null; // ISO date string
  sort_order?: number;
  is_milestone?: boolean;
  phase_code?: string | null;
  phase_label?: string | null;
  group_label?: string | null;
  assignee_id?: string | null; // Round 213: Task assignment
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

      const response = await apiClient.get<{ success?: boolean; data?: Project[]; meta?: any } | ProjectsResponse>(`/app/projects?${params.toString()}`);
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
      const response = await apiClient.get<{ success?: boolean; data: Project }>(`/app/projects/${id}`);
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
      await apiClient.delete(`/app/projects/${id}`);
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getKpis(period?: string): Promise<any> {
    try {
      const params = period ? `?period=${period}` : '';
      const response = await apiClient.get(`/app/projects/kpis${params}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getAlerts(): Promise<any> {
    try {
      const response = await apiClient.get('/app/projects/alerts');
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

  /**
   * List ProjectTasks (checklist tasks from templates)
   * GET /api/v1/app/projects/{projectId}/tasks
   * 
   * Round 203: ProjectTasks checklist view
   * These are the checklist tasks auto-generated from TaskTemplates when creating a project from a template.
   */
  async listProjectTasks(
    projectId: string | number,
    filters?: {
      status?: string;
      is_milestone?: boolean;
      is_hidden?: boolean;
      search?: string;
    },
    pagination?: { page?: number; per_page?: number }
  ): Promise<{ data: ProjectTask[]; meta?: any; links?: any }> {
    try {
      const params = new URLSearchParams();
      if (filters?.status) params.append('status', filters.status);
      if (filters?.is_milestone !== undefined) params.append('is_milestone', String(filters.is_milestone));
      if (filters?.is_hidden !== undefined) params.append('is_hidden', String(filters.is_hidden));
      if (filters?.search) params.append('search', filters.search);
      if (pagination?.page) params.append('page', String(pagination.page));
      if (pagination?.per_page) params.append('per_page', String(pagination.per_page));

      const queryString = params.toString();
      const url = `/v1/app/projects/${projectId}/tasks${queryString ? `?${queryString}` : ''}`;
      const response = await apiClient.get<{ success?: boolean; data?: ProjectTask[]; meta?: any; links?: any } | { data: ProjectTask[]; meta?: any; links?: any }>(url);
      
      // Handle both response formats: { success: true, data: [...], meta: {...} } or { data: [...], meta: {...} }
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        const apiResponse = response.data as { success: boolean; data?: ProjectTask[]; meta?: any; links?: any };
        return {
          data: apiResponse.data || [],
          meta: apiResponse.meta,
          links: (apiResponse as any).links,
        };
      }
      return response.data as { data: ProjectTask[]; meta?: any; links?: any };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Update ProjectTask
   * PATCH /api/v1/app/projects/{projectId}/tasks/{taskId}
   * 
   * Round 207: Update task fields (name, description, status, due_date, sort_order, is_milestone)
   */
  async updateProjectTask(
    projectId: string | number,
    taskId: string,
    payload: ProjectTaskUpdatePayload
  ): Promise<{ data: ProjectTask }> {
    try {
      const url = `/v1/app/projects/${projectId}/tasks/${taskId}`;
      const response = await apiClient.patch<{ success?: boolean; data?: ProjectTask } | { data: ProjectTask }>(url, payload);
      
      // Handle both response formats: { success: true, data: {...} } or { data: {...} }
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        const apiResponse = response.data as { success: boolean; data?: ProjectTask };
        return { data: apiResponse.data! };
      }
      return response.data as { data: ProjectTask };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Complete ProjectTask
   * POST /api/v1/app/projects/{projectId}/tasks/{taskId}/complete
   * 
   * Round 207: Mark task as completed with timestamp
   */
  async completeProjectTask(
    projectId: string | number,
    taskId: string
  ): Promise<{ data: ProjectTask }> {
    try {
      const url = `/v1/app/projects/${projectId}/tasks/${taskId}/complete`;
      const response = await apiClient.post<{ success?: boolean; data?: ProjectTask } | { data: ProjectTask }>(url);
      
      // Handle both response formats: { success: true, data: {...} } or { data: {...} }
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        const apiResponse = response.data as { success: boolean; data?: ProjectTask };
        return { data: apiResponse.data! };
      }
      return response.data as { data: ProjectTask };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Mark ProjectTask as incomplete
   * POST /api/v1/app/projects/{projectId}/tasks/{taskId}/incomplete
   * 
   * Round 207: Mark task as incomplete, clear completion timestamp
   */
  async incompleteProjectTask(
    projectId: string | number,
    taskId: string
  ): Promise<{ data: ProjectTask }> {
    try {
      const url = `/v1/app/projects/${projectId}/tasks/${taskId}/incomplete`;
      const response = await apiClient.post<{ success?: boolean; data?: ProjectTask } | { data: ProjectTask }>(url);
      
      // Handle both response formats: { success: true, data: {...} } or { data: {...} }
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        const apiResponse = response.data as { success: boolean; data?: ProjectTask };
        return { data: apiResponse.data! };
      }
      return response.data as { data: ProjectTask };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Reorder ProjectTasks
   * POST /api/v1/app/projects/{projectId}/tasks/reorder
   * 
   * Round 210: Reorder tasks within a project by updating sort_order
   */
  async reorderProjectTasks(
    projectId: string | number,
    payload: { orderedIds: string[] }
  ): Promise<void> {
    try {
      const url = `/v1/app/projects/${projectId}/tasks/reorder`;
      await apiClient.post(url, { ordered_ids: payload.orderedIds });
      // Returns 204 No Content, so no response body
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getProjectDocuments(projectId: string | number, filters?: { category?: string; status?: string; search?: string }, pagination?: { page?: number; per_page?: number }): Promise<any> {
    try {
      const params = new URLSearchParams();
      if (filters?.search) params.append('search', filters.search);
      if (filters?.category) params.append('category', filters.category);
      if (filters?.status) params.append('status', filters.status);
      if (pagination?.page) params.append('page', String(pagination.page));
      if (pagination?.per_page) params.append('per_page', String(pagination.per_page));

      const queryString = params.toString();
      const url = `/app/projects/${projectId}/documents${queryString ? `?${queryString}` : ''}`;
      const response = await apiClient.get(url);
      // Handle both response formats: { success: true, data: [...] } or { data: [...] }
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        return response.data;
      }
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Download project document (hybrid: stream for small files, signed URL for large files)
   * GET /api/v1/app/projects/{projectId}/documents/{documentId}/download
   * 
   * Round 181: Hybrid document download (stream + signed URL)
   */
  async downloadProjectDocument(projectId: string | number, documentId: string | number): Promise<void> {
    // Build the API URL using the same base as apiClient
    const baseURL = '/api/v1';
    const url = `${baseURL}/app/projects/${projectId}/documents/${documentId}/download`;

    // Get auth token from localStorage
    const token = typeof window !== 'undefined' ? window.localStorage.getItem('auth_token') : null;
    
    // Build headers
    const headers: HeadersInit = {
      'Accept': 'application/json, */*',
      'X-Requested-With': 'XMLHttpRequest',
    };

    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    // Get CSRF token if available
    const csrfToken = typeof document !== 'undefined'
      ? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
      : undefined;
    if (csrfToken) {
      headers['X-CSRF-TOKEN'] = csrfToken;
    }

    // Get tenant ID if available
    const tenantId = typeof window !== 'undefined' ? window.Laravel?.tenant?.id : undefined;
    if (tenantId) {
      headers['X-Tenant-ID'] = tenantId;
    }

    // Make the request using fetch to handle both JSON and blob responses
    const response = await fetch(url, {
      method: 'GET',
      headers,
      credentials: 'include',
    });

    if (!response.ok) {
      // Try to parse error response
      let errorMessage = `Failed to download document (${response.status})`;
      try {
        const errorData = await response.json();
        errorMessage = errorData.message || errorData.error?.message || errorMessage;
      } catch {
        // If JSON parsing fails, use status text
        errorMessage = response.statusText || errorMessage;
      }
      throw new Error(errorMessage);
    }

    const contentType = response.headers.get('content-type') || '';

    // Check if response is JSON (signed URL for large files)
    if (contentType.includes('application/json')) {
      const json = await response.json();
      
      // Expect JSON contract from backend: { success: true, data: { signed_url: "...", expires_at: "...", mode: "signed_url" } }
      const signedUrl = json?.data?.signed_url;
      if (!signedUrl) {
        throw new Error('Download response missing signed_url');
      }

      // Redirect browser to signed URL (will stream file)
      window.location.href = signedUrl;
      return;
    }

    // Otherwise, treat as file stream (small files)
    const blob = await response.blob();
    const downloadUrl = window.URL.createObjectURL(blob);

    // Determine filename:
    // 1) Prefer Content-Disposition header, if present
    // 2) Fallback to "document-{documentId}"
    const contentDisposition = response.headers.get('content-disposition') || '';
    let filename = `document-${documentId}`;
    const fileNameMatch = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/i);
    if (fileNameMatch && fileNameMatch[1]) {
      // Remove quotes if present
      filename = fileNameMatch[1].replace(/['"]/g, '');
      // Handle UTF-8 encoded filenames (filename*=UTF-8''...)
      if (filename.startsWith("UTF-8''")) {
        filename = decodeURIComponent(filename.substring(7));
      }
    }

    // Create temporary link and trigger download
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(downloadUrl);
  },

  /**
   * Get document versions
   * 
   * Round 187: Document Versioning (View & Download Version)
   */
  async getDocumentVersions(projectId: string | number, documentId: string | number): Promise<any> {
    try {
      const response = await apiClient.get(`/app/projects/${projectId}/documents/${documentId}/versions`);
      // Handle both response formats: { success: true, data: [...] } or { data: [...] }
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        return response.data;
      }
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Download specific document version
   * 
   * Round 187: Document Versioning (View & Download Version)
   */
  async downloadDocumentVersion(projectId: string | number, documentId: string | number, versionId: string | number): Promise<void> {
    // Build the API URL using the same base as apiClient
    const baseURL = '/api/v1';
    const url = `${baseURL}/app/projects/${projectId}/documents/${documentId}/versions/${versionId}/download`;

    // Get auth token from localStorage
    const token = typeof window !== 'undefined' ? window.localStorage.getItem('auth_token') : null;
    
    // Build headers
    const headers: HeadersInit = {
      'Accept': 'application/json, */*',
      'X-Requested-With': 'XMLHttpRequest',
    };

    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    // Get CSRF token if available
    const csrfToken = typeof document !== 'undefined'
      ? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
      : undefined;
    if (csrfToken) {
      headers['X-CSRF-TOKEN'] = csrfToken;
    }

    // Get tenant ID if available
    const tenantId = typeof window !== 'undefined' ? window.Laravel?.tenant?.id : undefined;
    if (tenantId) {
      headers['X-Tenant-ID'] = tenantId;
    }

    // Make the request using fetch to handle both JSON and blob responses
    const response = await fetch(url, {
      method: 'GET',
      headers,
      credentials: 'include',
    });

    if (!response.ok) {
      // Try to parse error response
      let errorMessage = `Failed to download version (${response.status})`;
      try {
        const errorData = await response.json();
        errorMessage = errorData.message || errorData.error?.message || errorMessage;
      } catch {
        // If JSON parsing fails, use status text
        errorMessage = response.statusText || errorMessage;
      }
      throw new Error(errorMessage);
    }

    const contentType = response.headers.get('content-type') || '';

    // Check if response is JSON (signed URL for large files)
    if (contentType.includes('application/json')) {
      const json = await response.json();
      
      // Expect JSON contract from backend: { success: true, data: { signed_url: "...", expires_at: "...", mode: "signed_url" } }
      const signedUrl = json?.data?.signed_url;
      if (!signedUrl) {
        throw new Error('Download response missing signed_url');
      }

      // Redirect browser to signed URL (will stream file)
      window.location.href = signedUrl;
      return;
    }

    // Otherwise, treat as file stream (small files)
    const blob = await response.blob();
    const downloadUrl = window.URL.createObjectURL(blob);

    // Determine filename:
    // 1) Prefer Content-Disposition header, if present
    // 2) Fallback to "version-{versionId}"
    const contentDisposition = response.headers.get('content-disposition') || '';
    let filename = `version-${versionId}`;
    const fileNameMatch = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/i);
    if (fileNameMatch && fileNameMatch[1]) {
      // Remove quotes if present
      filename = fileNameMatch[1].replace(/['"]/g, '');
      // Handle UTF-8 encoded filenames (filename*=UTF-8''...)
      if (filename.startsWith("UTF-8''")) {
        filename = decodeURIComponent(filename.substring(7));
      }
    }

    // Create temporary link and trigger download
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(downloadUrl);
  },

  /**
   * Upload new version for an existing project document
   * POST /api/v1/app/projects/{projectId}/documents/{documentId}/versions
   * 
   * Round 188: Frontend Document Versioning: Upload New Version
   */
  async uploadDocumentVersion(
    projectId: string | number,
    documentId: string | number,
    formData: FormData
  ): Promise<{ success: boolean; data: any; message?: string }> {
    try {
      const response = await apiClient.post<{ success?: boolean; data?: any; message?: string }>(
        `/app/projects/${projectId}/documents/${documentId}/versions`,
        formData,
        {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        }
      );
      // Handle both response formats: { success: true, data: {...}, message: "..." } or { data: {...} }
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        return response.data as { success: boolean; data: any; message?: string };
      }
      return { success: true, data: (response.data as any).data || response.data };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Restore a document to a specific version
   * POST /api/v1/app/projects/{projectId}/documents/{documentId}/versions/{versionId}/restore
   * 
   * Round 189: Restore Document Version
   */
  async restoreDocumentVersion(
    projectId: string | number,
    documentId: string | number,
    versionId: string | number
  ): Promise<{ success: boolean; data: any; message?: string }> {
    try {
      const response = await apiClient.post<{ success?: boolean; data?: any; message?: string }>(
        `/app/projects/${projectId}/documents/${documentId}/versions/${versionId}/restore`
      );
      // Handle both response formats: { success: true, data: {...}, message: "..." } or { data: {...} }
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        return response.data as { success: boolean; data: any; message?: string };
      }
      return { success: true, data: (response.data as any).data || response.data };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getProjectHistory(projectId: string | number, filters?: { action?: string; entity_type?: string; entity_id?: string; limit?: number }): Promise<any> {
    try {
      const params = new URLSearchParams();
      if (filters?.action) params.append('action', filters.action);
      if (filters?.entity_type) params.append('entity_type', filters.entity_type);
      if (filters?.entity_id) params.append('entity_id', filters.entity_id);
      if (filters?.limit) params.append('limit', String(filters.limit));

      const queryString = params.toString();
      const url = `/app/projects/${projectId}/history${queryString ? `?${queryString}` : ''}`;
      const response = await apiClient.get(url);
      // Handle both response formats: { success: true, data: [...] } or { data: [...] }
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        return response.data;
      }
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

  /**
   * Upload project document
   * POST /api/v1/app/projects/{projectId}/documents
   * 
   * Round 182: Frontend project document upload integration
   */
  async uploadProjectDocument(projectId: string | number, formData: FormData): Promise<{ success: boolean; data: any; message?: string }> {
    try {
      const response = await apiClient.post<{ success?: boolean; data?: any; message?: string }>(
        `/app/projects/${projectId}/documents`,
        formData,
        {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        }
      );
      // Handle both response formats: { success: true, data: {...}, message: "..." } or { data: {...} }
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        return response.data as { success: boolean; data: any; message?: string };
      }
      return { success: true, data: (response.data as any).data || response.data };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Update project document metadata
   * PATCH /api/v1/app/projects/{projectId}/documents/{documentId}
   * 
   * Round 184: Frontend project document edit integration
   */
  async updateProjectDocument(
    projectId: string | number,
    documentId: string | number,
    payload: {
      name?: string;
      description?: string;
      category?: 'general' | 'contract' | 'drawing' | 'specification' | 'report' | 'other';
      status?: 'active' | 'archived' | 'draft';
    }
  ): Promise<{ success: boolean; data: any; message?: string }> {
    try {
      const response = await apiClient.patch<{ success?: boolean; data?: any; message?: string }>(
        `/app/projects/${projectId}/documents/${documentId}`,
        payload
      );
      // Handle both response formats: { success: true, data: {...}, message: "..." } or { data: {...} }
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        return response.data as { success: boolean; data: any; message?: string };
      }
      return { success: true, data: (response.data as any).data || response.data };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Delete project document
   * DELETE /api/v1/app/projects/{projectId}/documents/{documentId}
   * 
   * Round 184: Frontend project document delete integration
   */
  async deleteProjectDocument(
    projectId: string | number,
    documentId: string | number
  ): Promise<{ success: boolean; data: null; message?: string }> {
    try {
      const response = await apiClient.delete<{ success?: boolean; data?: null; message?: string }>(
        `/app/projects/${projectId}/documents/${documentId}`
      );
      // Handle both response formats: { success: true, data: null, message: "..." } or { data: null }
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        return response.data as { success: boolean; data: null; message?: string };
      }
      return { success: true, data: null };
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

  /**
   * Get project cost dashboard
   * GET /api/v1/app/projects/{projectId}/cost-dashboard
   * 
   * Round 224: Project Cost Dashboard Frontend
   */
  async getProjectCostDashboard(projectId: string | number): Promise<{ data: ProjectCostDashboardResponse }> {
    try {
      const response = await apiClient.get<{ success?: boolean; data?: ProjectCostDashboardResponse } | { data: ProjectCostDashboardResponse }>(
        `/v1/app/projects/${projectId}/cost-dashboard`
      );
      
      // Handle both response formats: { success: true, data: {...} } or { data: {...} }
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        const apiResponse = response.data as { success: boolean; data?: ProjectCostDashboardResponse };
        return { data: apiResponse.data! };
      }
      return response.data as { data: ProjectCostDashboardResponse };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get project cost health
   * GET /api/v1/app/projects/{projectId}/cost-health
   * 
   * Round 226: Project Cost Health Status + Alert Indicators
   */
  async getProjectCostHealth(projectId: string | number): Promise<{ data: ProjectCostHealthResponse }> {
    try {
      const response = await apiClient.get<{ success?: boolean; data?: ProjectCostHealthResponse } | { data: ProjectCostHealthResponse }>(
        `/v1/app/projects/${projectId}/cost-health`
      );
      
      // Handle both response formats: { success: true, data: {...} } or { data: {...} }
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        const apiResponse = response.data as { success: boolean; data?: ProjectCostHealthResponse };
        return { data: apiResponse.data! };
      }
      return response.data as { data: ProjectCostHealthResponse };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get project cost flow status
   * GET /api/v1/app/projects/{projectId}/cost-flow-status
   * 
   * Round 232: Project Cost Flow Status
   */
  async getProjectCostFlowStatus(projectId: string | number): Promise<{ data: ProjectCostFlowStatusResponse }> {
    try {
      const response = await apiClient.get<{ success?: boolean; data?: ProjectCostFlowStatusResponse } | { data: ProjectCostFlowStatusResponse }>(
        `/v1/app/projects/${projectId}/cost-flow-status`
      );
      
      // Handle both response formats: { success: true, data: {...} } or { data: {...} }
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        const apiResponse = response.data as { success: boolean; data?: ProjectCostFlowStatusResponse };
        return { data: apiResponse.data! };
      }
      return response.data as { data: ProjectCostFlowStatusResponse };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get project cost alerts
   * GET /api/v1/app/projects/{projectId}/cost-alerts
   * 
   * Round 227: Cost Alerts System (Nagging & Attention Flags)
   */
  async getProjectCostAlerts(projectId: string | number): Promise<{ data: ProjectCostAlertsResponse }> {
    try {
      const response = await apiClient.get<{ success?: boolean; data?: ProjectCostAlertsResponse } | { data: ProjectCostAlertsResponse }>(
        `/v1/app/projects/${projectId}/cost-alerts`
      );
      
      // Handle both response formats: { success: true, data: {...} } or { data: {...} }
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        const apiResponse = response.data as { success: boolean; data?: ProjectCostAlertsResponse };
        return { data: apiResponse.data! };
      }
      return response.data as { data: ProjectCostAlertsResponse };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * List my tasks (tasks assigned to current user)
   * GET /api/v1/app/my/tasks
   * 
   * Round 213: My Tasks endpoint
   * Round 217: Added range filter support
   */
  async listMyTasks(params?: { 
    status?: 'open' | 'completed' | 'all';
    range?: 'today' | 'next_7_days' | 'overdue' | 'all';
  }): Promise<{ data: ProjectTask[] }> {
    try {
      const urlParams = new URLSearchParams();
      if (params?.status) {
        urlParams.append('status', params.status);
      }
      if (params?.range) {
        urlParams.append('range', params.range);
      }
      const queryString = urlParams.toString();
      const url = `/v1/app/my/tasks${queryString ? `?${queryString}` : ''}`;
      
      const response = await apiClient.get<{ success?: boolean; data?: ProjectTask[] } | { data: ProjectTask[] }>(url);
      
      // Handle both response formats: { success: true, data: [...] } or { data: [...] }
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        const apiResponse = response.data as { success: boolean; data?: ProjectTask[] };
        return { data: apiResponse.data || [] };
      }
      return response.data as { data: ProjectTask[] };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get project contracts
   * GET /api/v1/app/projects/{projectId}/contracts
   * 
   * Round 225: Contract & Change Order Drilldown
   */
  async getProjectContracts(projectId: string | number): Promise<{ data: ContractSummary[] }> {
    try {
      const response = await apiClient.get<{ success?: boolean; data?: ContractSummary[] } | { data: ContractSummary[] }>(
        `/v1/app/projects/${projectId}/contracts`
      );
      
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        const apiResponse = response.data as { success: boolean; data?: ContractSummary[] };
        return { data: apiResponse.data || [] };
      }
      return response.data as { data: ContractSummary[] };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get contract detail
   * GET /api/v1/app/projects/{projectId}/contracts/{contractId}
   * 
   * Round 225: Contract & Change Order Drilldown
   */
  async getContractDetail(projectId: string | number, contractId: string | number): Promise<{ data: ContractDetail }> {
    try {
      const response = await apiClient.get<{ success?: boolean; data?: ContractDetail } | { data: ContractDetail }>(
        `/v1/app/projects/${projectId}/contracts/${contractId}`
      );
      
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        const apiResponse = response.data as { success: boolean; data?: ContractDetail };
        return { data: apiResponse.data! };
      }
      return response.data as { data: ContractDetail };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get contract change orders
   * GET /api/v1/app/projects/{projectId}/contracts/{contractId}/change-orders
   * 
   * Round 225: Contract & Change Order Drilldown
   */
  async getContractChangeOrders(projectId: string | number, contractId: string | number): Promise<{ data: ChangeOrderSummary[] }> {
    try {
      const response = await apiClient.get<{ success?: boolean; data?: ChangeOrderSummary[] } | { data: ChangeOrderSummary[] }>(
        `/v1/app/projects/${projectId}/contracts/${contractId}/change-orders`
      );
      
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        const apiResponse = response.data as { success: boolean; data?: ChangeOrderSummary[] };
        return { data: apiResponse.data || [] };
      }
      return response.data as { data: ChangeOrderSummary[] };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get change order detail
   * GET /api/v1/app/projects/{projectId}/contracts/{contractId}/change-orders/{coId}
   * 
   * Round 225: Contract & Change Order Drilldown
   */
  async getChangeOrderDetail(projectId: string | number, contractId: string | number, coId: string | number): Promise<{ data: ChangeOrderDetail }> {
    try {
      const response = await apiClient.get<{ success?: boolean; data?: ChangeOrderDetail } | { data: ChangeOrderDetail }>(
        `/v1/app/projects/${projectId}/contracts/${contractId}/change-orders/${coId}`
      );
      
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        const apiResponse = response.data as { success: boolean; data?: ChangeOrderDetail };
        return { data: apiResponse.data! };
      }
      return response.data as { data: ChangeOrderDetail };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get contract payment certificates
   * GET /api/v1/app/projects/{projectId}/contracts/{contractId}/payment-certificates
   * 
   * Round 225: Contract & Change Order Drilldown
   */
  async getContractPaymentCertificates(projectId: string | number, contractId: string | number): Promise<{ data: PaymentCertificateSummary[] }> {
    try {
      const response = await apiClient.get<{ success?: boolean; data?: PaymentCertificateSummary[] } | { data: PaymentCertificateSummary[] }>(
        `/v1/app/projects/${projectId}/contracts/${contractId}/payment-certificates`
      );
      
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        const apiResponse = response.data as { success: boolean; data?: PaymentCertificateSummary[] };
        return { data: apiResponse.data || [] };
      }
      return response.data as { data: PaymentCertificateSummary[] };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get contract payments
   * GET /api/v1/app/projects/{projectId}/contracts/{contractId}/payments
   * 
   * Round 225: Contract & Change Order Drilldown
   */
  async getContractPayments(projectId: string | number, contractId: string | number): Promise<{ data: PaymentSummary[] }> {
    try {
      const response = await apiClient.get<{ success?: boolean; data?: PaymentSummary[] } | { data: PaymentSummary[] }>(
        `/v1/app/projects/${projectId}/contracts/${contractId}/payments`
      );
      
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        const apiResponse = response.data as { success: boolean; data?: PaymentSummary[] };
        return { data: apiResponse.data || [] };
      }
      return response.data as { data: PaymentSummary[] };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Export contract PDF
   * GET /api/v1/app/projects/{projectId}/contracts/{contractId}/export/pdf
   * 
   * Round 228: PDF Export for Contracts, COs, and Payment Certificates
   */
  async exportContractPdf(projectId: string | number, contractId: string | number): Promise<Blob> {
    try {
      const response = await apiClient.get(
        `/v1/app/projects/${projectId}/contracts/${contractId}/export/pdf`,
        { responseType: 'blob' }
      );
      return response.data as Blob;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Export change order PDF
   * GET /api/v1/app/projects/{projectId}/contracts/{contractId}/change-orders/{coId}/export/pdf
   * 
   * Round 228: PDF Export for Contracts, COs, and Payment Certificates
   */
  async exportChangeOrderPdf(projectId: string | number, contractId: string | number, coId: string | number): Promise<Blob> {
    try {
      const response = await apiClient.get(
        `/v1/app/projects/${projectId}/contracts/${contractId}/change-orders/${coId}/export/pdf`,
        { responseType: 'blob' }
      );
      return response.data as Blob;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Export payment certificate PDF
   * GET /api/v1/app/projects/{projectId}/contracts/{contractId}/payment-certificates/{certificateId}/export/pdf
   * 
   * Round 228: PDF Export for Contracts, COs, and Payment Certificates
   */
  async exportPaymentCertificatePdf(projectId: string | number, contractId: string | number, certificateId: string | number): Promise<Blob> {
    try {
      const response = await apiClient.get(
        `/v1/app/projects/${projectId}/contracts/${contractId}/payment-certificates/${certificateId}/export/pdf`,
        { responseType: 'blob' }
      );
      return response.data as Blob;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Workflow endpoints - Round 230
   */

  /**
   * Propose change order (draft → proposed)
   * POST /api/v1/app/projects/{projectId}/contracts/{contractId}/change-orders/{coId}/propose
   */
  async proposeChangeOrder(projectId: string | number, contractId: string | number, coId: string | number): Promise<{ data: ChangeOrderSummary }> {
    try {
      const response = await apiClient.post<{ data: ChangeOrderSummary }>(
        `/v1/app/projects/${projectId}/contracts/${contractId}/change-orders/${coId}/propose`
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Approve change order (proposed → approved)
   * POST /api/v1/app/projects/{projectId}/contracts/{contractId}/change-orders/{coId}/approve
   */
  async approveChangeOrder(projectId: string | number, contractId: string | number, coId: string | number): Promise<{ data: ChangeOrderSummary }> {
    try {
      const response = await apiClient.post<{ data: ChangeOrderSummary }>(
        `/v1/app/projects/${projectId}/contracts/${contractId}/change-orders/${coId}/approve`
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Reject change order (proposed → rejected)
   * POST /api/v1/app/projects/{projectId}/contracts/{contractId}/change-orders/{coId}/reject
   */
  async rejectChangeOrder(projectId: string | number, contractId: string | number, coId: string | number): Promise<{ data: ChangeOrderSummary }> {
    try {
      const response = await apiClient.post<{ data: ChangeOrderSummary }>(
        `/v1/app/projects/${projectId}/contracts/${contractId}/change-orders/${coId}/reject`
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Submit payment certificate (draft → submitted)
   * POST /api/v1/app/projects/{projectId}/contracts/{contractId}/payment-certificates/{certificateId}/submit
   */
  async submitPaymentCertificate(projectId: string | number, contractId: string | number, certificateId: string | number): Promise<{ data: PaymentCertificateSummary }> {
    try {
      const response = await apiClient.post<{ data: PaymentCertificateSummary }>(
        `/v1/app/projects/${projectId}/contracts/${contractId}/payment-certificates/${certificateId}/submit`
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Approve payment certificate (submitted → approved)
   * POST /api/v1/app/projects/{projectId}/contracts/{contractId}/payment-certificates/{certificateId}/approve
   */
  async approvePaymentCertificate(projectId: string | number, contractId: string | number, certificateId: string | number): Promise<{ data: PaymentCertificateSummary }> {
    try {
      const response = await apiClient.post<{ data: PaymentCertificateSummary }>(
        `/v1/app/projects/${projectId}/contracts/${contractId}/payment-certificates/${certificateId}/approve`
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Mark payment as paid (planned → paid)
   * POST /api/v1/app/projects/{projectId}/contracts/{contractId}/payments/{paymentId}/mark-paid
   */
  async markPaymentPaid(projectId: string | number, contractId: string | number, paymentId: string | number): Promise<{ data: PaymentSummary }> {
    try {
      const response = await apiClient.post<{ data: PaymentSummary }>(
        `/v1/app/projects/${projectId}/contracts/${contractId}/payments/${paymentId}/mark-paid`
      );
      return response.data;
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
 * Project Cost Dashboard Response
 * 
 * Round 224: Project Cost Dashboard Frontend
 */
export interface ProjectCostDashboardResponse {
  project_id: string;
  currency: string;
  summary: {
    budget_total: number;
    contract_base_total: number;
    contract_current_total: number;
    total_certified_amount: number;
    total_paid_amount: number;
    outstanding_amount: number;
  };
  variance: {
    pending_change_orders_total: number;
    rejected_change_orders_total: number;
    forecast_final_cost: number;
    variance_vs_budget: number;
    variance_vs_contract_current: number;
  };
  contracts: {
    contract_base_total: number;
    change_orders_approved_total: number;
    change_orders_pending_total: number;
    change_orders_rejected_total: number;
    contract_current_total: number;
  };
  time_series: {
    certificates_per_month: Array<{
      year: number;
      month: number;
      amount_payable_approved: number;
    }>;
    payments_per_month: Array<{
      year: number;
      month: number;
      amount_paid: number;
    }>;
  };
}

/**
 * Contract Summary
 * 
 * Round 225: Contract & Change Order Drilldown
 */
export interface ContractSummary {
  id: string;
  project_id: string;
  code: string;
  name: string;
  type: string;
  party_name: string;
  currency: string;
  base_amount: number | null;
  current_amount: number | null;
  total_certified_amount: number | null;
  total_paid_amount: number | null;
  outstanding_amount: number | null;
  status: string;
  start_date: string | null;
  end_date: string | null;
  signed_at: string | null;
  created_at: string;
  updated_at: string;
}

/**
 * Contract Line
 * 
 * Round 225: Contract & Change Order Drilldown
 */
export interface ContractLine {
  id: string;
  contract_id: string;
  project_id: string;
  budget_line_id: string | null;
  item_code: string | null;
  description: string | null;
  unit: string | null;
  quantity: number;
  unit_price: number;
  amount: number;
  metadata: Record<string, unknown> | null;
  created_at: string;
  updated_at: string;
}

/**
 * Contract Detail
 * 
 * Round 225: Contract & Change Order Drilldown
 */
export interface ContractDetail extends ContractSummary {
  vat_percent: number | null;
  total_amount_with_vat: number | null;
  retention_percent: number | null;
  notes: string | null;
  metadata: Record<string, unknown> | null;
  lines: ContractLine[];
}

/**
 * Change Order Summary
 * 
 * Round 225: Contract & Change Order Drilldown
 */
export interface ChangeOrderSummary {
  id: string;
  tenant_id: string;
  project_id: string;
  contract_id: string;
  code: string;
  title: string;
  reason: string | null;
  status: 'approved' | 'pending' | 'rejected';
  amount_delta: number;
  effective_date: string | null;
  metadata: Record<string, unknown> | null;
  // Round 241: Dual approval fields
  first_approved_by?: string | null;
  first_approved_at?: string | null;
  second_approved_by?: string | null;
  second_approved_at?: string | null;
  requires_dual_approval?: boolean;
  created_at: string;
  updated_at: string;
}

/**
 * Change Order Line
 * 
 * Round 225: Contract & Change Order Drilldown
 */
export interface ChangeOrderLine {
  id: string;
  change_order_id: string;
  contract_id: string;
  project_id: string;
  contract_line_id: string | null;
  budget_line_id: string | null;
  item_code: string | null;
  description: string | null;
  unit: string | null;
  quantity_delta: number | null;
  unit_price_delta: number | null;
  amount_delta: number;
  metadata: Record<string, unknown> | null;
  created_at: string;
  updated_at: string;
}

/**
 * Change Order Detail
 * 
 * Round 225: Contract & Change Order Drilldown
 */
export interface ChangeOrderDetail extends ChangeOrderSummary {
  lines: ChangeOrderLine[];
}

/**
 * Payment Certificate Summary
 * 
 * Round 225: Contract & Change Order Drilldown
 */
export interface PaymentCertificateSummary {
  id: string;
  tenant_id: string;
  project_id: string;
  contract_id: string;
  code: string;
  title: string;
  status: string;
  period_start: string | null;
  period_end: string | null;
  amount_before_retention: number | null;
  retention_percent_override: number | null;
  retention_amount: number | null;
  amount_payable: number | null;
  metadata: Record<string, unknown> | null;
  // Round 241: Dual approval fields
  first_approved_by?: string | null;
  first_approved_at?: string | null;
  second_approved_by?: string | null;
  second_approved_at?: string | null;
  requires_dual_approval?: boolean;
  created_at: string;
  updated_at: string;
}

/**
 * Payment Summary
 * 
 * Round 225: Contract & Change Order Drilldown
 */
export interface PaymentSummary {
  id: string;
  tenant_id: string;
  project_id: string;
  contract_id: string;
  certificate_id: string | null;
  paid_date: string | null;
  amount_paid: number | null;
  currency: string;
  payment_method: string | null;
  reference_no: string | null;
  metadata: Record<string, unknown> | null;
  // Round 241: Dual approval fields
  first_approved_by?: string | null;
  first_approved_at?: string | null;
  second_approved_by?: string | null;
  second_approved_at?: string | null;
  requires_dual_approval?: boolean;
  created_at: string;
  updated_at: string;
}

/**
 * Project Cost Health Response
 * 
 * Round 226: Project Cost Health Status + Alert Indicators
 */
export interface ProjectCostHealthResponse {
  project_id: string;
  cost_health_status: 'UNDER_BUDGET' | 'ON_BUDGET' | 'AT_RISK' | 'OVER_BUDGET';
  stats: {
    budget_total: number;
    forecast_final_cost: number;
    variance_vs_budget: number;
    pending_change_orders_total: number;
  };
}

/**
 * Project Cost Flow Status Response
 * 
 * Round 232: Project Cost Flow Status
 */
export interface ProjectCostFlowStatusResponse {
  project_id: string;
  status: 'OK' | 'PENDING_APPROVAL' | 'DELAYED' | 'BLOCKED';
  metrics: {
    pending_change_orders: number;
    delayed_change_orders: number;
    rejected_change_orders: number;
    pending_certificates: number;
    delayed_certificates: number;
    rejected_certificates: number;
  };
}

/**
 * Project Cost Alerts Response
 * 
 * Round 227: Cost Alerts System (Nagging & Attention Flags)
 */
export interface ProjectCostAlertsResponse {
  project_id: string;
  alerts: string[];
  details: {
    pending_co_count: number;
    overdue_co_count: number;
    unpaid_certificates_count: number;
    cost_health_status: string;
    pending_change_orders_total: string | number;
    budget_total: string | number;
    threshold_days: number;
  };
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

