import { createApiClient, mapAxiosError } from '../../shared/api/client';

const apiClient = createApiClient();

export interface Client {
  id: string | number;
  name: string;
  email?: string;
  phone?: string;
  status?: 'active' | 'inactive' | 'prospect';
  created_at: string;
  updated_at: string;
}

export interface ClientsResponse {
  data: Client[];
  meta?: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
}

/**
 * Clients API Client
 * 
 * Endpoints from routes/api_v1.php: /api/v1/app/clients/*
 */
export const clientsApi = {
  async getClients(filters?: any, pagination?: { page?: number; per_page?: number }): Promise<ClientsResponse> {
    try {
      const params = new URLSearchParams();
      if (filters?.search) params.append('search', filters.search);
      if (filters?.status) params.append('status', filters.status);
      if (pagination?.page) params.append('page', String(pagination.page));
      if (pagination?.per_page) params.append('per_page', String(pagination.per_page));

      const response = await apiClient.get<ClientsResponse>(`/v1/app/clients?${params.toString()}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getClient(id: string | number): Promise<{ data: Client }> {
    try {
      const response = await apiClient.get<{ data: Client }>(`/v1/app/clients/${id}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getKpis(period?: string): Promise<any> {
    try {
      const params = period ? `?period=${period}` : '';
      const response = await apiClient.get(`/v1/app/clients/kpis${params}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getAlerts(): Promise<any> {
    try {
      const response = await apiClient.get('/v1/app/clients/alerts');
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getActivity(limit?: number): Promise<any> {
    try {
      const params = limit ? `?limit=${limit}` : '';
      const response = await apiClient.get(`/v1/app/clients/activity${params}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async createClient(data: Partial<Client>): Promise<{ data: Client }> {
    try {
      const response = await apiClient.post<{ data: Client }>('/v1/app/clients', data);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async updateClient(id: string | number, data: Partial<Client>): Promise<{ data: Client }> {
    try {
      const response = await apiClient.put<{ data: Client }>(`/v1/app/clients/${id}`, data);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async deleteClient(id: string | number): Promise<void> {
    try {
      await apiClient.delete(`/v1/app/clients/${id}`);
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

