import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { adminRolesApi } from './api';
import type {
  AdminRolesFilters,
  CreateAdminRoleRequest,
  UpdateAdminRoleRequest,
  AssignRoleRequest,
  BulkAssignRoleRequest
} from './types';

// Query Keys
export const adminRolesKeys = {
  all: ['admin', 'roles'] as const,
  lists: () => [...adminRolesKeys.all, 'list'] as const,
  list: (filters: AdminRolesFilters) => [...adminRolesKeys.lists(), filters] as const,
  details: () => [...adminRolesKeys.all, 'detail'] as const,
  detail: (id: number) => [...adminRolesKeys.details(), id] as const,
  permissions: () => [...adminRolesKeys.all, 'permissions'] as const,
};

// Get roles list with filters
export const useAdminRoles = (filters: AdminRolesFilters = {}) => {
  return useQuery({
    queryKey: adminRolesKeys.list(filters),
    queryFn: () => adminRolesApi.getRoles(filters),
    staleTime: 30_000, // 30 seconds
    retry: 1,
  });
};

// Get single role
export const useAdminRole = (id: number, enabled: boolean = true) => {
  return useQuery({
    queryKey: adminRolesKeys.detail(id),
    queryFn: () => adminRolesApi.getRole(id),
    enabled: enabled && !!id,
    staleTime: 60_000, // 1 minute
    retry: 1,
  });
};

// Get all permissions
export const useAdminPermissions = () => {
  return useQuery({
    queryKey: adminRolesKeys.permissions(),
    queryFn: () => adminRolesApi.getPermissions(),
    staleTime: 300_000, // 5 minutes
    retry: 1,
  });
};

// Create role mutation
export const useCreateAdminRole = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (roleData: CreateAdminRoleRequest) => adminRolesApi.createRole(roleData),
    onSuccess: () => {
      // Invalidate roles list
      queryClient.invalidateQueries({ queryKey: adminRolesKeys.lists() });
    },
  });
};

// Update role mutation
export const useUpdateAdminRole = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ id, roleData }: { id: number; roleData: UpdateAdminRoleRequest }) =>
      adminRolesApi.updateRole(id, roleData),
    onSuccess: (_, { id }) => {
      // Invalidate specific role and roles list
      queryClient.invalidateQueries({ queryKey: adminRolesKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: adminRolesKeys.lists() });
    },
  });
};

// Delete role mutation
export const useDeleteAdminRole = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (id: number) => adminRolesApi.deleteRole(id),
    onSuccess: () => {
      // Invalidate roles list
      queryClient.invalidateQueries({ queryKey: adminRolesKeys.lists() });
    },
  });
};

// Assign role mutation
export const useAssignRole = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (assignment: AssignRoleRequest) => adminRolesApi.assignRole(assignment),
    onSuccess: () => {
      // Invalidate users list to refresh role assignments
      queryClient.invalidateQueries({ queryKey: ['admin', 'users'] });
    },
  });
};

// Bulk assign role mutation
export const useBulkAssignRole = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (assignment: BulkAssignRoleRequest) => adminRolesApi.bulkAssignRole(assignment),
    onSuccess: () => {
      // Invalidate users list to refresh role assignments
      queryClient.invalidateQueries({ queryKey: ['admin', 'users'] });
    },
  });
};

// Remove role mutation
export const useRemoveRole = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ userId, roleId }: { userId: number; roleId: number }) =>
      adminRolesApi.removeRole(userId, roleId),
    onSuccess: () => {
      // Invalidate users list to refresh role assignments
      queryClient.invalidateQueries({ queryKey: ['admin', 'users'] });
    },
  });
};
