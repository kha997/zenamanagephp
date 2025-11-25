import { createApiClient, mapAxiosError } from '../../shared/api/client';
import type {
  PublicInvitationResponse,
  AcceptInvitationResponse,
  DeclineInvitationResponse,
} from './types';

const apiClient = createApiClient();

/**
 * Public Invitation API Client
 * 
 * Endpoints from Round 20: Tenant Invitation Lifecycle
 * - GET /api/v1/tenant/invitations/{token} (public, no auth)
 * - POST /api/v1/tenant/invitations/{token}/accept (auth:sanctum)
 * - POST /api/v1/tenant/invitations/{token}/decline (auth:sanctum)
 */
export const invitationApi = {
  /**
   * Get public invitation details by token
   * GET /api/v1/tenant/invitations/{token}
   * 
   * Public endpoint - no authentication required
   */
  async getPublicInvitation(token: string): Promise<PublicInvitationResponse> {
    try {
      const response = await apiClient.get<{
        success?: boolean;
        data?: PublicInvitationResponse['data'];
        status?: number;
        message?: string;
      }>(
        `/v1/tenant/invitations/${token}`
      );

      // Handle standardized response format: { success: true, data: { tenant_name, email, ... } }
      if (response.data && typeof response.data === 'object' && 'data' in response.data) {
        return {
          success: response.data.success ?? true,
          data: response.data.data!,
          message: response.data.message,
        };
      }

      // Handle direct response format (shouldn't happen with current backend, but for safety)
      return response.data as PublicInvitationResponse;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Accept invitation by token
   * POST /api/v1/tenant/invitations/{token}/accept
   * 
   * Requires authentication (auth:sanctum)
   */
  async acceptInvitation(token: string): Promise<AcceptInvitationResponse> {
    try {
      const response = await apiClient.post<{
        success?: boolean;
        data?: AcceptInvitationResponse['data'];
        message?: string;
      }>(
        `/v1/tenant/invitations/${token}/accept`
      );

      // Handle standardized response format: { success: true, data: { invitation_status, ... } }
      if (response.data && typeof response.data === 'object' && 'data' in response.data) {
        return {
          success: response.data.success ?? true,
          data: response.data.data,
          message: response.data.message,
        };
      }

      // Handle direct response format
      return response.data as AcceptInvitationResponse;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Decline invitation by token
   * POST /api/v1/tenant/invitations/{token}/decline
   * 
   * Requires authentication (auth:sanctum)
   */
  async declineInvitation(token: string): Promise<DeclineInvitationResponse> {
    try {
      const response = await apiClient.post<{
        success?: boolean;
        data?: DeclineInvitationResponse['data'];
        message?: string;
      }>(
        `/v1/tenant/invitations/${token}/decline`
      );

      // Handle standardized response format: { success: true, data: { invitation_status, ... } }
      if (response.data && typeof response.data === 'object' && 'data' in response.data) {
        return {
          success: response.data.success ?? true,
          data: response.data.data,
          message: response.data.message,
        };
      }

      // Handle direct response format
      return response.data as DeclineInvitationResponse;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

