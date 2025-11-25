import { apiClient } from '../../../shared/api/client';
import type {
  AdminTenant,
  AdminTenantsResponse,
  AdminTenantsFilters,
  CreateAdminTenantRequest,
  UpdateAdminTenantRequest,
  TenantStats
} from './types';

export const adminTenantsApi = {
  // Get paginated list of tenants
  getTenants: async (filters: AdminTenantsFilters = {}): Promise<AdminTenantsResponse> => {
    const params = new URLSearchParams();
    
    if (filters.search) params.append('search', filters.search);
    if (filters.status) params.append('status', filters.status);
    if (filters.plan) params.append('plan', filters.plan);
    if (filters.page) params.append('page', filters.page.toString());
    if (filters.per_page) params.append('per_page', filters.per_page.toString());

    const response = await apiClient.get(`/api/v1/admin/tenants?${params.toString()}`);
    return response.data;
  },

  // Get single tenant by ID
  getTenant: async (id: number): Promise<{ data: AdminTenant }> => {
    const response = await apiClient.get(`/api/v1/admin/tenants/${id}`);
    return response.data;
  },

  // Create new tenant
  createTenant: async (tenantData: CreateAdminTenantRequest): Promise<{ data: AdminTenant }> => {
    const response = await apiClient.post('/api/v1/admin/tenants', tenantData);
    return response.data;
  },

  // Update tenant
  updateTenant: async (id: number, tenantData: UpdateAdminTenantRequest): Promise<{ data: AdminTenant }> => {
    const response = await apiClient.put(`/api/v1/admin/tenants/${id}`, tenantData);
    return response.data;
  },

  // Delete tenant
  deleteTenant: async (id: number): Promise<void> => {
    await apiClient.delete(`/api/v1/admin/tenants/${id}`);
  },

  // Get tenant stats
  getTenantStats: async (id: number): Promise<{ data: TenantStats }> => {
    const response = await apiClient.get(`/api/v1/admin/tenants/${id}/stats`);
    return response.data;
  },

  // Suspend tenant
  suspendTenant: async (id: number, reason?: string): Promise<void> => {
    await apiClient.post(`/api/v1/admin/tenants/${id}/suspend`, { reason });
  },

  // Activate tenant
  activateTenant: async (id: number): Promise<void> => {
    await apiClient.post(`/api/v1/admin/tenants/${id}/activate`);
  },

  // Bulk operations
  bulkUpdateStatus: async (tenantIds: number[], status: 'active' | 'inactive' | 'suspended'): Promise<void> => {
    await apiClient.post('/api/v1/admin/tenants/bulk-update-status', {
      tenant_ids: tenantIds,
      status
    });
  },

  bulkDelete: async (tenantIds: number[]): Promise<void> => {
    await apiClient.post('/api/v1/admin/tenants/bulk-delete', {
      tenant_ids: tenantIds
    });
  },

  // Extend trial
  extendTrial: async (id: number, days: number): Promise<void> => {
    await apiClient.post(`/api/v1/admin/tenants/${id}/extend-trial`, { days });
  }
};
