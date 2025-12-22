import { createApiClient, mapAxiosError } from '../../shared/api/client';

const apiClient = createApiClient();

export interface ChangeRequest {
  id: string;
  change_number?: string;
  title: string;
  description?: string;
  project_id?: string;
  task_id?: string;
  change_type?: string;
  priority?: 'low' | 'medium' | 'high' | 'urgent';
  status: 'draft' | 'awaiting_approval' | 'approved' | 'rejected';
  impact_level?: string;
  requested_by?: string;
  assigned_to?: string;
  approved_by?: string;
  rejected_by?: string;
  requested_at?: string;
  due_date?: string;
  approved_at?: string;
  rejected_at?: string;
  implemented_at?: string;
  estimated_cost?: number;
  actual_cost?: number;
  estimated_days?: number;
  actual_days?: number;
  approval_notes?: string;
  rejection_reason?: string;
  implementation_notes?: string;
  attachments?: any[];
  impact_analysis?: any;
  risk_assessment?: any;
  created_at: string;
  updated_at: string;
  tenant_id?: string;
}

export interface ChangeRequestsResponse {
  data: ChangeRequest[];
  meta?: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
}

export interface ChangeRequestFilters {
  search?: string;
  status?: string;
  priority?: string;
  project_id?: string;
  change_type?: string;
  page?: number;
  per_page?: number;
}

/**
 * Change Requests API Client
 * 
 * Endpoints: /api/v1/app/change-requests/*
 */
export const changeRequestsApi = {
  async getChangeRequests(
    filters?: ChangeRequestFilters,
    pagination?: { page?: number; per_page?: number }
  ): Promise<ChangeRequestsResponse> {
    try {
      const params = new URLSearchParams();
      if (filters?.search) params.append('search', filters.search);
      if (filters?.status) params.append('status', filters.status);
      if (filters?.priority) params.append('priority', filters.priority);
      if (filters?.project_id) params.append('project_id', filters.project_id);
      if (filters?.change_type) params.append('change_type', filters.change_type);
      if (pagination?.page) params.append('page', String(pagination.page));
      if (pagination?.per_page) params.append('per_page', String(pagination.per_page));

      const response = await apiClient.get<ChangeRequestsResponse>(
        `/v1/app/change-requests?${params.toString()}`
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getChangeRequest(id: string | number): Promise<{ data: ChangeRequest }> {
    try {
      const response = await apiClient.get<{ data: ChangeRequest }>(
        `/v1/app/change-requests/${id}`
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async createChangeRequest(data: Partial<ChangeRequest>): Promise<{ data: ChangeRequest }> {
    try {
      const response = await apiClient.post<{ data: ChangeRequest }>(
        '/v1/app/change-requests',
        data
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async updateChangeRequest(
    id: string | number,
    data: Partial<ChangeRequest>
  ): Promise<{ data: ChangeRequest }> {
    try {
      const response = await apiClient.put<{ data: ChangeRequest }>(
        `/v1/app/change-requests/${id}`,
        data
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async deleteChangeRequest(id: string | number): Promise<void> {
    try {
      await apiClient.delete(`/v1/app/change-requests/${id}`);
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async submitChangeRequest(id: string | number): Promise<{ data: ChangeRequest }> {
    try {
      const response = await apiClient.post<{ data: ChangeRequest }>(
        `/v1/app/change-requests/${id}/submit`
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async approveChangeRequest(
    id: string | number,
    notes?: string
  ): Promise<{ data: ChangeRequest }> {
    try {
      const response = await apiClient.post<{ data: ChangeRequest }>(
        `/v1/app/change-requests/${id}/approve`,
        { decision_note: notes }
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async rejectChangeRequest(
    id: string | number,
    reason?: string
  ): Promise<{ data: ChangeRequest }> {
    try {
      const response = await apiClient.post<{ data: ChangeRequest }>(
        `/v1/app/change-requests/${id}/reject`,
        { decision_note: reason }
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getKpis(period?: string): Promise<any> {
    try {
      const params = period ? `?period=${period}` : '';
      const response = await apiClient.get(`/v1/app/change-requests/kpis${params}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getAlerts(): Promise<any> {
    try {
      const response = await apiClient.get('/v1/app/change-requests/alerts');
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getActivity(limit?: number): Promise<any> {
    try {
      const params = limit ? `?limit=${limit}` : '';
      const response = await apiClient.get(`/v1/app/change-requests/activity${params}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

