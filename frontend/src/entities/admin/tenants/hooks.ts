import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { adminTenantsApi } from './api';
import type {
  AdminTenantsFilters,
  CreateAdminTenantRequest,
  UpdateAdminTenantRequest
} from './types';

// Query Keys
export const adminTenantsKeys = {
  all: ['admin', 'tenants'] as const,
  lists: () => [...adminTenantsKeys.all, 'list'] as const,
  list: (filters: AdminTenantsFilters) => [...adminTenantsKeys.lists(), filters] as const,
  details: () => [...adminTenantsKeys.all, 'detail'] as const,
  detail: (id: number) => [...adminTenantsKeys.details(), id] as const,
  stats: (id: number) => [...adminTenantsKeys.detail(id), 'stats'] as const,
};

// Get tenants list with filters
export const useAdminTenants = (filters: AdminTenantsFilters = {}) => {
  return useQuery({
    queryKey: adminTenantsKeys.list(filters),
    queryFn: () => adminTenantsApi.getTenants(filters),
    staleTime: 30_000, // 30 seconds
    retry: 1,
  });
};

// Get single tenant
export const useAdminTenant = (id: number, enabled: boolean = true) => {
  return useQuery({
    queryKey: adminTenantsKeys.detail(id),
    queryFn: () => adminTenantsApi.getTenant(id),
    enabled: enabled && !!id,
    staleTime: 60_000, // 1 minute
    retry: 1,
  });
};

// Get tenant stats
export const useAdminTenantStats = (id: number, enabled: boolean = true) => {
  return useQuery({
    queryKey: adminTenantsKeys.stats(id),
    queryFn: () => adminTenantsApi.getTenantStats(id),
    enabled: enabled && !!id,
    staleTime: 30_000, // 30 seconds
    retry: 1,
  });
};

// Create tenant mutation
export const useCreateAdminTenant = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (tenantData: CreateAdminTenantRequest) => adminTenantsApi.createTenant(tenantData),
    onSuccess: () => {
      // Invalidate tenants list
      queryClient.invalidateQueries({ queryKey: adminTenantsKeys.lists() });
    },
  });
};

// Update tenant mutation
export const useUpdateAdminTenant = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ id, tenantData }: { id: number; tenantData: UpdateAdminTenantRequest }) =>
      adminTenantsApi.updateTenant(id, tenantData),
    onSuccess: (_, { id }) => {
      // Invalidate specific tenant and tenants list
      queryClient.invalidateQueries({ queryKey: adminTenantsKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: adminTenantsKeys.lists() });
    },
  });
};

// Delete tenant mutation
export const useDeleteAdminTenant = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (id: number) => adminTenantsApi.deleteTenant(id),
    onSuccess: () => {
      // Invalidate tenants list
      queryClient.invalidateQueries({ queryKey: adminTenantsKeys.lists() });
    },
  });
};

// Suspend tenant mutation
export const useSuspendTenant = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason?: string }) =>
      adminTenantsApi.suspendTenant(id, reason),
    onSuccess: (_, { id }) => {
      // Invalidate specific tenant and tenants list
      queryClient.invalidateQueries({ queryKey: adminTenantsKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: adminTenantsKeys.lists() });
    },
  });
};

// Activate tenant mutation
export const useActivateTenant = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (id: number) => adminTenantsApi.activateTenant(id),
    onSuccess: (_, id) => {
      // Invalidate specific tenant and tenants list
      queryClient.invalidateQueries({ queryKey: adminTenantsKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: adminTenantsKeys.lists() });
    },
  });
};

// Bulk update status mutation
export const useBulkUpdateTenantStatus = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ tenantIds, status }: { tenantIds: number[]; status: 'active' | 'inactive' | 'suspended' }) =>
      adminTenantsApi.bulkUpdateStatus(tenantIds, status),
    onSuccess: () => {
      // Invalidate tenants list
      queryClient.invalidateQueries({ queryKey: adminTenantsKeys.lists() });
    },
  });
};

// Bulk delete mutation
export const useBulkDeleteTenants = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (tenantIds: number[]) => adminTenantsApi.bulkDelete(tenantIds),
    onSuccess: () => {
      // Invalidate tenants list
      queryClient.invalidateQueries({ queryKey: adminTenantsKeys.lists() });
    },
  });
};

// Extend trial mutation
export const useExtendTrial = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ id, days }: { id: number; days: number }) =>
      adminTenantsApi.extendTrial(id, days),
    onSuccess: (_, { id }) => {
      // Invalidate specific tenant
      queryClient.invalidateQueries({ queryKey: adminTenantsKeys.detail(id) });
    },
  });
};
