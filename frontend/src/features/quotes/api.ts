import { createApiClient, mapAxiosError } from '../../shared/api/client';

const apiClient = createApiClient();

export interface Quote {
  id: string | number;
  title: string;
  client_id?: string | number;
  status?: 'draft' | 'pending' | 'accepted' | 'rejected';
  amount?: number;
  created_at: string;
  updated_at: string;
}

export interface QuotesResponse {
  data: Quote[];
  meta?: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
}

/**
 * Quotes API Client
 * 
 * Endpoints from routes/api_v1.php: /api/v1/app/quotes/*
 */
export const quotesApi = {
  async getQuotes(filters?: any, pagination?: { page?: number; per_page?: number }): Promise<QuotesResponse> {
    try {
      const params = new URLSearchParams();
      if (filters?.search) params.append('search', filters.search);
      if (filters?.status) params.append('status', filters.status);
      if (pagination?.page) params.append('page', String(pagination.page));
      if (pagination?.per_page) params.append('per_page', String(pagination.per_page));

      const response = await apiClient.get<QuotesResponse>(`/v1/app/quotes?${params.toString()}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getQuote(id: string | number): Promise<{ data: Quote }> {
    try {
      const response = await apiClient.get<{ data: Quote }>(`/v1/app/quotes/${id}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getKpis(period?: string): Promise<any> {
    try {
      const params = period ? `?period=${period}` : '';
      const response = await apiClient.get(`/v1/app/quotes/kpis${params}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getAlerts(): Promise<any> {
    try {
      const response = await apiClient.get('/v1/app/quotes/alerts');
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getActivity(limit?: number): Promise<any> {
    try {
      const params = limit ? `?limit=${limit}` : '';
      const response = await apiClient.get(`/v1/app/quotes/activity${params}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async createQuote(data: Partial<Quote>): Promise<{ data: Quote }> {
    try {
      const response = await apiClient.post<{ data: Quote }>('/v1/app/quotes', data);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async updateQuote(id: string | number, data: Partial<Quote>): Promise<{ data: Quote }> {
    try {
      const response = await apiClient.put<{ data: Quote }>(`/v1/app/quotes/${id}`, data);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async deleteQuote(id: string | number): Promise<void> {
    try {
      await apiClient.delete(`/v1/app/quotes/${id}`);
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

