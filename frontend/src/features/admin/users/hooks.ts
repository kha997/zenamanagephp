import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { adminUsersApi } from './api';
import type {
  AdminUsersFilters,
  CreateAdminUserRequest,
  UpdateAdminUserRequest
} from './types';
import type {
  InvitationsFilters,
  CreateInvitationRequest,
  BulkInvitationRequest
} from './invitation-types';

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
    // Keep previous data while fetching to prevent UI flicker
    placeholderData: (previousData) => previousData,
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

// Invitation Query Keys
export const invitationKeys = {
  all: ['admin', 'invitations'] as const,
  lists: () => [...invitationKeys.all, 'list'] as const,
  list: (filters: InvitationsFilters) => [...invitationKeys.lists(), filters] as const,
  details: () => [...invitationKeys.all, 'detail'] as const,
  detail: (id: number) => [...invitationKeys.details(), id] as const,
  validation: (token: string) => [...invitationKeys.all, 'validate', token] as const,
};

// Get invitations list with filters
export const useInvitations = (filters: InvitationsFilters = {}) => {
  return useQuery({
    queryKey: invitationKeys.list(filters),
    queryFn: () => adminUsersApi.getInvitations(filters),
    staleTime: 30_000, // 30 seconds
    retry: 1,
    placeholderData: (previousData) => previousData,
  });
};

// Create invitation mutation
export const useCreateInvitation = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (data: CreateInvitationRequest) => adminUsersApi.createInvitation(data),
    onSuccess: () => {
      // Invalidate invitations list
      queryClient.invalidateQueries({ queryKey: invitationKeys.lists() });
      // Also invalidate users list as new users might be created
      queryClient.invalidateQueries({ queryKey: adminUsersKeys.lists() });
    },
  });
};

// Bulk create invitations mutation
export const useBulkCreateInvitations = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (data: BulkInvitationRequest) => adminUsersApi.bulkCreateInvitations(data),
    onSuccess: () => {
      // Invalidate invitations list
      queryClient.invalidateQueries({ queryKey: invitationKeys.lists() });
      // Also invalidate users list
      queryClient.invalidateQueries({ queryKey: adminUsersKeys.lists() });
    },
  });
};

// Resend invitation mutation
export const useResendInvitation = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (id: number) => adminUsersApi.resendInvitation(id),
    onSuccess: (_, id) => {
      // Invalidate specific invitation and list
      queryClient.invalidateQueries({ queryKey: invitationKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: invitationKeys.lists() });
    },
  });
};

// Validate invitation token (public)
export const useValidateInvitationToken = (token: string, enabled: boolean = true) => {
  return useQuery({
    queryKey: invitationKeys.validation(token),
    queryFn: () => adminUsersApi.validateInvitationToken(token),
    enabled: enabled && !!token,
    staleTime: 60_000, // 1 minute
    retry: 1,
  });
};

// Accept invitation mutation (public)
export const useAcceptInvitation = () => {
  return useMutation({
    mutationFn: ({ token, userData }: { token: string; userData: { name?: string; password?: string; password_confirmation?: string; first_name?: string; last_name?: string; phone?: string; job_title?: string } }) =>
      adminUsersApi.acceptInvitation(token, userData),
  });
};

