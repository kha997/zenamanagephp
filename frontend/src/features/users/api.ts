import { apiClient, mapAxiosError } from '../../shared/api/client';

export interface User {
  id: string | number;
  name: string;
  email: string;
  tenant_id?: string | number;
  role?: string;
  permissions?: string[];
  avatar?: string | null;
  is_active?: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface UsersResponse {
  data: User[];
  meta?: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
}

/**
 * Users API Client
 * 
 * Endpoints from routes/api_v1.php: /api/v1/app/users/*
 */
export const usersApi = {
  async getUsers(filters?: { search?: string; role?: string }, pagination?: { page?: number; per_page?: number }): Promise<UsersResponse> {
    try {
      const params = new URLSearchParams();
      if (filters?.search) params.append('search', filters.search);
      if (filters?.role) params.append('role', filters.role);
      if (pagination?.page) params.append('page', String(pagination.page));
      if (pagination?.per_page) params.append('per_page', String(pagination.per_page));

      const response = await apiClient.get<UsersResponse>(`/v1/app/users?${params.toString()}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  async getUser(id: string | number): Promise<{ data: User }> {
    try {
      const response = await apiClient.get<{ data: User }>(`/v1/app/users/${id}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

