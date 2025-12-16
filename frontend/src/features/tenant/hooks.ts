import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { tenantApi } from './api';
import type {
  UpdateMemberRoleRequest,
  CreateInvitationRequest,
} from './types';

/**
 * Tenant Members & Invitations Hooks (React Query)
 */

export const useTenantMembers = (
  filters?: { search?: string; role?: string; page?: number; per_page?: number },
  options?: { enabled?: boolean }
) => {
  const { enabled = true } = options ?? {};
  return useQuery({
    queryKey: ['tenant', 'members', filters],
    queryFn: () => tenantApi.getMembers(filters),
    enabled,
    staleTime: 30 * 1000, // 30 seconds
    gcTime: 5 * 60 * 1000, // 5 minutes
  });
};

export const useTenantInvitations = (options?: { enabled?: boolean }) => {
  const { enabled = true } = options ?? {};
  return useQuery({
    queryKey: ['tenant', 'invitations'],
    queryFn: () => tenantApi.getInvitations(),
    enabled,
    staleTime: 30 * 1000, // 30 seconds
    gcTime: 5 * 60 * 1000, // 5 minutes
  });
};

export const useUpdateMemberRole = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, data }: { id: string | number; data: UpdateMemberRoleRequest }) =>
      tenantApi.updateMemberRole(id, data),
    onSuccess: () => {
      // Invalidate members list to refetch
      queryClient.invalidateQueries({ queryKey: ['tenant', 'members'] });
    },
  });
};

export const useRemoveMember = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: string | number) => tenantApi.removeMember(id),
    onSuccess: () => {
      // Invalidate members list to refetch
      queryClient.invalidateQueries({ queryKey: ['tenant', 'members'] });
    },
  });
};

export const useCreateInvitation = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: CreateInvitationRequest) => tenantApi.createInvitation(data),
    onSuccess: () => {
      // Invalidate invitations list to refetch
      queryClient.invalidateQueries({ queryKey: ['tenant', 'invitations'] });
    },
  });
};

export const useRevokeInvitation = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: string | number) => tenantApi.revokeInvitation(id),
    onSuccess: () => {
      // Invalidate invitations list to refetch
      queryClient.invalidateQueries({ queryKey: ['tenant', 'invitations'] });
    },
  });
};

export const useResendInvitation = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (invitationId: string) => tenantApi.resendInvitation(invitationId),
    onSuccess: () => {
      // Refresh invitations list
      queryClient.invalidateQueries({ queryKey: ['tenant', 'invitations'] });
    },
  });
};

export const useLeaveCurrentTenant = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => tenantApi.leaveCurrentTenant(),
    onSuccess: async () => {
      // Invalidate /me + tenant/các thứ liên quan
      await queryClient.invalidateQueries({ queryKey: ['auth', 'me'] });
      await queryClient.invalidateQueries({ queryKey: ['tenant', 'members'] });
      await queryClient.invalidateQueries({ queryKey: ['tenant', 'invitations'] });
    },
  });
};

export const useMakeOwner = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (params: { memberId: string | number; demoteSelf?: boolean }) =>
      tenantApi.makeOwner(params.memberId, { demoteSelf: params.demoteSelf }),
    onSuccess: async () => {
      // Refresh member list + auth/me (Role của current user có thể thay đổi)
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['tenant', 'members'] }),
        queryClient.invalidateQueries({ queryKey: ['auth', 'me'] }),
      ]);
    },
  });
};

/**
 * Hook for switching/selecting tenant
 * 
 * Uses POST /api/v1/me/tenants/{tenantId}/select endpoint.
 * Invalidates auth/me query to refresh user context after switch.
 */
export const useSwitchTenant = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (tenantId: string | number) => tenantApi.switchTenant(tenantId),
    onSuccess: async () => {
      // Invalidate auth/me to refresh user context (including current tenant)
      await queryClient.invalidateQueries({ queryKey: ['auth', 'me'] });
      // Invalidate tenant list to refresh current_tenant_id
      await queryClient.invalidateQueries({ queryKey: ['tenant', 'list'] });
      // Optionally invalidate other tenant-related queries
      await queryClient.invalidateQueries({ queryKey: ['tenant'] });
    },
  });
};

