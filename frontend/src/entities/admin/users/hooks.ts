import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { adminUsersApi } from './api';
import type {
  AdminUsersFilters,
  CreateAdminUserRequest,
  UpdateAdminUserRequest
} from './types';

// Query Keys
export const adminUsersKeys = {
  all: ['admin', 'users'] as const,
  lists: () => [...adminUsersKeys.all, 'list'] as const,
  list: (filters: AdminUsersFilters) => [...adminUsersKeys.lists(), filters] as const,
  details: () => [...adminUsersKeys.all, 'detail'] as const,
  detail: (id: number) => [...adminUsersKeys.details(), id] as const,
};

// Get users list with filters
export const useAdminUsers = (filters: AdminUsersFilters = {}) => {
  return useQuery({
    queryKey: adminUsersKeys.list(filters),
    queryFn: () => adminUsersApi.getUsers(filters),
    staleTime: 30_000, // 30 seconds
    retry: 1,
  });
};

// Get single user
export const useAdminUser = (id: number, enabled: boolean = true) => {
  return useQuery({
    queryKey: adminUsersKeys.detail(id),
    queryFn: () => adminUsersApi.getUser(id),
    enabled: enabled && !!id,
    staleTime: 60_000, // 1 minute
    retry: 1,
  });
};

// Create user mutation
export const useCreateAdminUser = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (userData: CreateAdminUserRequest) => adminUsersApi.createUser(userData),
    onSuccess: () => {
      // Invalidate users list
      queryClient.invalidateQueries({ queryKey: adminUsersKeys.lists() });
    },
  });
};

// Update user mutation
export const useUpdateAdminUser = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ id, userData }: { id: number; userData: UpdateAdminUserRequest }) =>
      adminUsersApi.updateUser(id, userData),
    onSuccess: (_, { id }) => {
      // Invalidate specific user and users list
      queryClient.invalidateQueries({ queryKey: adminUsersKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: adminUsersKeys.lists() });
    },
  });
};

// Delete user mutation
export const useDeleteAdminUser = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (id: number) => adminUsersApi.deleteUser(id),
    onSuccess: () => {
      // Invalidate users list
      queryClient.invalidateQueries({ queryKey: adminUsersKeys.lists() });
    },
  });
};

// Bulk update status mutation
export const useBulkUpdateUserStatus = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ userIds, status }: { userIds: number[]; status: 'active' | 'inactive' | 'suspended' }) =>
      adminUsersApi.bulkUpdateStatus(userIds, status),
    onSuccess: () => {
      // Invalidate users list
      queryClient.invalidateQueries({ queryKey: adminUsersKeys.lists() });
    },
  });
};

// Bulk delete mutation
export const useBulkDeleteUsers = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (userIds: number[]) => adminUsersApi.bulkDelete(userIds),
    onSuccess: () => {
      // Invalidate users list
      queryClient.invalidateQueries({ queryKey: adminUsersKeys.lists() });
    },
  });
};
