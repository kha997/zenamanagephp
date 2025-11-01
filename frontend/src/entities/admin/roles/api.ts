import { apiClient } from '../../../shared/api/client';
import type {
  AdminRole,
  AdminRolesResponse,
  AdminRolesFilters,
  CreateAdminRoleRequest,
  UpdateAdminRoleRequest,
  AssignRoleRequest,
  BulkAssignRoleRequest
} from './types';

export const adminRolesApi = {
  // Get paginated list of roles
  getRoles: async (filters: AdminRolesFilters = {}): Promise<AdminRolesResponse> => {
    const params = new URLSearchParams();
    
    if (filters.search) params.append('search', filters.search);
    if (filters.page) params.append('page', filters.page.toString());
    if (filters.per_page) params.append('per_page', filters.per_page.toString());

    const response = await apiClient.get(`/api/v1/admin/roles?${params.toString()}`);
    return response.data;
  },

  // Get single role by ID
  getRole: async (id: number): Promise<{ data: AdminRole }> => {
    const response = await apiClient.get(`/api/v1/admin/roles/${id}`);
    return response.data;
  },

  // Create new role
  createRole: async (roleData: CreateAdminRoleRequest): Promise<{ data: AdminRole }> => {
    const response = await apiClient.post('/api/v1/admin/roles', roleData);
    return response.data;
  },

  // Update role
  updateRole: async (id: number, roleData: UpdateAdminRoleRequest): Promise<{ data: AdminRole }> => {
    const response = await apiClient.put(`/api/v1/admin/roles/${id}`, roleData);
    return response.data;
  },

  // Delete role
  deleteRole: async (id: number): Promise<void> => {
    await apiClient.delete(`/api/v1/admin/roles/${id}`);
  },

  // Get all permissions
  getPermissions: async (): Promise<{ data: any[] }> => {
    const response = await apiClient.get('/api/v1/admin/permissions');
    return response.data;
  },

  // Assign role to user
  assignRole: async (assignment: AssignRoleRequest): Promise<void> => {
    await apiClient.post('/api/v1/admin/role-assignments', assignment);
  },

  // Bulk assign role
  bulkAssignRole: async (assignment: BulkAssignRoleRequest): Promise<void> => {
    await apiClient.post('/api/v1/admin/role-assignments/bulk', assignment);
  },

  // Remove role from user
  removeRole: async (userId: number, roleId: number): Promise<void> => {
    await apiClient.delete(`/api/v1/admin/users/${userId}/roles/${roleId}`);
  }
};
