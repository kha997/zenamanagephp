import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { invitationApi } from './api';
import type { PublicInvitationResponse } from './types';

/**
 * Invitation Hooks (React Query)
 * 
 * Hooks for public invitation flow (Round 21)
 */

/**
 * Get public invitation details by token
 * 
 * @param token - Invitation token from URL
 * @param options - Query options (enabled, etc.)
 */
export const usePublicInvitation = (
  token: string | undefined,
  options?: { enabled?: boolean }
) => {
  const { enabled = true } = options ?? {};
  
  return useQuery({
    queryKey: ['invitations', 'public', token],
    queryFn: () => invitationApi.getPublicInvitation(token!),
    enabled: enabled && !!token,
    staleTime: 30 * 1000, // 30 seconds
    gcTime: 5 * 60 * 1000, // 5 minutes
    retry: (failureCount, error: any) => {
      // Don't retry on 404 (invalid token) or 422 (expired/already processed)
      if (error?.status === 404 || error?.status === 422) {
        return false;
      }
      return failureCount < 2;
    },
  });
};

/**
 * Accept invitation mutation
 * 
 * On success, invalidates auth queries to refresh user/tenant context
 */
export const useAcceptInvitation = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (token: string) => invitationApi.acceptInvitation(token),
    onSuccess: () => {
      // Invalidate invitation query to refetch updated status
      queryClient.invalidateQueries({ queryKey: ['invitations', 'public'] });
      // Invalidate auth queries to refresh user/tenant context
      queryClient.invalidateQueries({ queryKey: ['auth', 'me'] });
    },
  });
};

/**
 * Decline invitation mutation
 * 
 * On success, invalidates invitation query to show updated status
 */
export const useDeclineInvitation = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (token: string) => invitationApi.declineInvitation(token),
    onSuccess: () => {
      // Invalidate invitation query to refetch updated status
      queryClient.invalidateQueries({ queryKey: ['invitations', 'public'] });
    },
  });
};

