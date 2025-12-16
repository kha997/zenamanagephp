import { createApiClient, mapAxiosError } from '../../shared/api/client';
import type {
  TenantMember,
  TenantMembersResponse,
  UpdateMemberRoleRequest,
  TenantInvitation,
  TenantInvitationsResponse,
  CreateInvitationRequest,
  TenantMemberChangeResponse,
} from './types';

const apiClient = createApiClient();

/**
 * Tenant Members & Invitations API Client
 * 
 * Endpoints from routes/api_v1.php: /api/v1/app/tenant/*
 */
export const tenantApi = {
  /**
   * Get list of tenant members
   * GET /api/v1/app/tenant/members
   */
  async getMembers(filters?: { search?: string; role?: string; page?: number; per_page?: number }): Promise<TenantMembersResponse> {
    try {
      const params = new URLSearchParams();
      if (filters?.search) params.append('search', filters.search);
      if (filters?.role) params.append('role', filters.role);
      if (filters?.page) params.append('page', filters.page.toString());
      if (filters?.per_page) params.append('per_page', filters.per_page.toString());

      const response = await apiClient.get<{ success?: boolean; data?: TenantMembersResponse } | TenantMembersResponse>(
        `/v1/app/tenant/members${params.toString() ? `?${params.toString()}` : ''}`
      );

      // Handle standardized response format: { success: true, data: { members: [...], pagination: {...} } }
      if (response.data && typeof response.data === 'object' && 'data' in response.data) {
        const apiResponse = response.data as { data: TenantMembersResponse };
        return apiResponse.data;
      }

      // Handle direct response format
      return response.data as TenantMembersResponse;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Update member role
   * PATCH /api/v1/app/tenant/members/{id}
   */
  async updateMemberRole(id: string | number, data: UpdateMemberRoleRequest): Promise<{ data: TenantMember }> {
    try {
      const response = await apiClient.patch<{ data: TenantMember }>(
        `/v1/app/tenant/members/${id}`,
        data
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Remove member from tenant
   * DELETE /api/v1/app/tenant/members/{id}
   */
  async removeMember(id: string | number): Promise<void> {
    try {
      await apiClient.delete(`/v1/app/tenant/members/${id}`);
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get list of tenant invitations
   * GET /api/v1/app/tenant/invitations
   */
  async getInvitations(): Promise<TenantInvitationsResponse> {
    try {
      const response = await apiClient.get<{ success?: boolean; data?: TenantInvitationsResponse } | TenantInvitationsResponse>(
        '/v1/app/tenant/invitations'
      );

      // Handle standardized response format: { success: true, data: { invitations: [...] } }
      if (response.data && typeof response.data === 'object' && 'data' in response.data) {
        const apiResponse = response.data as { data: TenantInvitationsResponse };
        return apiResponse.data;
      }

      // Handle direct response format
      return response.data as TenantInvitationsResponse;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Create new invitation
   * POST /api/v1/app/tenant/invitations
   */
  async createInvitation(data: CreateInvitationRequest): Promise<{ data: TenantInvitation }> {
    try {
      const response = await apiClient.post<{ data: TenantInvitation }>(
        '/v1/app/tenant/invitations',
        data
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Revoke invitation
   * DELETE /api/v1/app/tenant/invitations/{id}
   */
  async revokeInvitation(id: string | number): Promise<void> {
    try {
      await apiClient.delete(`/v1/app/tenant/invitations/${id}`);
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Resend invitation email
   * POST /api/v1/app/tenant/invitations/{id}/resend
   */
  async resendInvitation(invitationId: string): Promise<{ data: TenantInvitation }> {
    try {
      const response = await apiClient.post<{ data: TenantInvitation }>(
        `/v1/app/tenant/invitations/${invitationId}/resend`
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Leave current tenant (self-service)
   * POST /api/v1/app/tenant/leave
   */
  async leaveCurrentTenant(): Promise<void> {
    try {
      await apiClient.post('/v1/app/tenant/leave');
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Promote member to owner (or transfer ownership)
   * POST /api/v1/app/tenant/members/{id}/make-owner
   */
  async makeOwner(memberId: string | number, options?: { demoteSelf?: boolean }): Promise<{ data: TenantMemberChangeResponse }> {
    try {
      const response = await apiClient.post<{ data: TenantMemberChangeResponse }>(
        `/v1/app/tenant/members/${memberId}/make-owner`,
        {
          demote_self: options?.demoteSelf ?? false,
        }
      );
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Switch/select tenant for current user
   * POST /api/v1/me/tenants/{tenantId}/select
   * 
   * Sets the selected tenant for the current session and updates is_default flag.
   */
  async switchTenant(tenantId: string | number): Promise<{
    tenant_id: string | number;
    tenant_name: string;
    message: string;
    me?: {
      user: any;
      permissions: string[];
      abilities: string[];
      tenants_summary?: {
        count: number;
        items: Array<{ id: string; name: string; slug?: string }>;
      };
    };
  }> {
    try {
      const response = await apiClient.post<{
        success?: boolean;
        data?: {
          tenant_id: string | number;
          tenant_name: string;
          message: string;
          me?: {
            user: any;
            permissions: string[];
            abilities: string[];
            tenants_summary?: {
              count: number;
              items: Array<{ id: string; name: string; slug?: string }>;
            };
          };
        };
      }>(
        `/v1/me/tenants/${tenantId}/select?include_me=true`,
        {}
      );

      // Handle standardized response format: { success: true, data: { ... } }
      if (response.data?.data) {
        return response.data.data;
      }

      // Handle direct response format
      return response.data as any;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

