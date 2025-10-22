import { apiClient } from '../../../shared/api/client';
import type {
  AdminUser,
  AdminUsersResponse,
  AdminUsersFilters,
  CreateAdminUserRequest,
  UpdateAdminUserRequest
} from './types';

export const adminUsersApi = {
  // Get paginated list of users
  getUsers: async (filters: AdminUsersFilters = {}): Promise<AdminUsersResponse> => {
    const params = new URLSearchParams();
    
    if (filters.search) params.append('search', filters.search);
    if (filters.tenant_id) params.append('tenant_id', filters.tenant_id.toString());
    if (filters.role_id) params.append('role_id', filters.role_id.toString());
    if (filters.status) params.append('status', filters.status);
    if (filters.page) params.append('page', filters.page.toString());
    if (filters.per_page) params.append('per_page', filters.per_page.toString());

    const response = await apiClient.get(`/api/v1/admin/users?${params.toString()}`);
    return response.data;
  },

  // Get single user by ID
  getUser: async (id: number): Promise<{ data: AdminUser }> => {
    const response = await apiClient.get(`/api/v1/admin/users/${id}`);
    return response.data;
  },

  // Create new user
  createUser: async (userData: CreateAdminUserRequest): Promise<{ data: AdminUser }> => {
    const response = await apiClient.post('/api/v1/admin/users', userData);
    return response.data;
  },

  // Update user
  updateUser: async (id: number, userData: UpdateAdminUserRequest): Promise<{ data: AdminUser }> => {
    const response = await apiClient.put(`/api/v1/admin/users/${id}`, userData);
    return response.data;
  },

  // Delete user
  deleteUser: async (id: number): Promise<void> => {
    await apiClient.delete(`/api/v1/admin/users/${id}`);
  },

  // Bulk operations
  bulkUpdateStatus: async (userIds: number[], status: 'active' | 'inactive' | 'suspended'): Promise<void> => {
    await apiClient.post('/api/v1/admin/users/bulk-update-status', {
      user_ids: userIds,
      status
    });
  },

  bulkDelete: async (userIds: number[]): Promise<void> => {
    await apiClient.post('/api/v1/admin/users/bulk-delete', {
      user_ids: userIds
    });
  }
};
