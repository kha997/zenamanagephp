import { createApiClient, mapAxiosError } from '../../../shared/api/client';
import type {
  AdminUser,
  AdminUsersResponse,
  AdminUsersFilters,
  CreateAdminUserRequest,
  UpdateAdminUserRequest
} from './types';
import type {
  Invitation,
  CreateInvitationRequest,
  BulkInvitationRequest,
  InvitationsResponse,
  InvitationsFilters,
  BulkInvitationResult,
  InvitationValidationResponse
} from './invitation-types';

const apiClient = createApiClient();

export const adminUsersApi = {
  // Get paginated list of users
  getUsers: async (filters: AdminUsersFilters = {}): Promise<AdminUsersResponse> => {
    try {
      const params = new URLSearchParams();
      
      if (filters.search) params.append('search', filters.search);
      if (filters.tenant_id) params.append('tenant_id', filters.tenant_id.toString());
      if (filters.role_id) params.append('role_id', filters.role_id.toString());
      if (filters.status) params.append('status', filters.status);
      if (filters.page) params.append('page', filters.page.toString());
      if (filters.per_page) params.append('per_page', filters.per_page.toString());

      const response = await apiClient.get<{ success: boolean; data: AdminUsersResponse }>(`/admin/users?${params.toString()}`);
      // ApiResponse::success wraps data in { success: true, data: {...} }, so we need to extract it
      // Response structure: { success: true, data: { data: [...], meta: {...}, links: {...} } }
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData as AdminUsersResponse;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  // Get single user by ID
  getUser: async (id: number): Promise<{ data: AdminUser }> => {
    try {
      const response = await apiClient.get<{ data: AdminUser }>(`/admin/users/${id}`);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  // Create new user
  createUser: async (userData: CreateAdminUserRequest): Promise<{ data: AdminUser }> => {
    try {
      const response = await apiClient.post<{ data: AdminUser }>('/admin/users', userData);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  // Update user
  updateUser: async (id: number, userData: UpdateAdminUserRequest): Promise<{ data: AdminUser }> => {
    try {
      const response = await apiClient.put<{ data: AdminUser }>(`/admin/users/${id}`, userData);
      return response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  // Delete user
  deleteUser: async (id: number): Promise<void> => {
    try {
      await apiClient.delete(`/admin/users/${id}`);
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  // Bulk operations
  bulkUpdateStatus: async (userIds: number[], status: 'active' | 'inactive' | 'suspended'): Promise<void> => {
    try {
      await apiClient.post('/admin/users/bulk-update-status', {
        user_ids: userIds,
        status
      });
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  bulkDelete: async (userIds: number[]): Promise<void> => {
    try {
      await apiClient.post('/admin/users/bulk-delete', {
        user_ids: userIds
      });
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  // Invitation methods
  getInvitations: async (filters: InvitationsFilters = {}): Promise<InvitationsResponse> => {
    try {
      const params = new URLSearchParams();
      
      if (filters.tenant_id) params.append('tenant_id', filters.tenant_id.toString());
      if (filters.status) params.append('status', filters.status);
      if (filters.search) params.append('search', filters.search);
      if (filters.page) params.append('page', filters.page.toString());
      if (filters.per_page) params.append('per_page', filters.per_page.toString());

      const response = await apiClient.get<{ success: boolean; data: InvitationsResponse }>(`/admin/invitations?${params.toString()}`);
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData as InvitationsResponse;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  createInvitation: async (data: CreateInvitationRequest): Promise<{ invitation: Invitation; email_sent: boolean }> => {
    try {
      const response = await apiClient.post<{ success: boolean; data: { invitation: Invitation; email_sent: boolean } }>('/admin/invitations', data);
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  bulkCreateInvitations: async (data: BulkInvitationRequest): Promise<BulkInvitationResult> => {
    try {
      const response = await apiClient.post<{ success: boolean; data: BulkInvitationResult }>('/admin/invitations/bulk', data);
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  resendInvitation: async (id: number): Promise<{ invitation: Invitation; email_sent: boolean }> => {
    try {
      const response = await apiClient.post<{ success: boolean; data: { invitation: Invitation; email_sent: boolean } }>(`/admin/invitations/${id}/resend`);
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  validateInvitationToken: async (token: string): Promise<InvitationValidationResponse> => {
    try {
      const response = await apiClient.get<{ success: boolean; data: InvitationValidationResponse }>(`/invitations/${token}/validate`);
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  acceptInvitation: async (token: string, userData: { name?: string; password?: string; password_confirmation?: string; first_name?: string; last_name?: string; phone?: string; job_title?: string }): Promise<{ user: { id: number; name: string; email: string; tenant_id: string }; message: string }> => {
    try {
      const response = await apiClient.post<{ success: boolean; data: { user: { id: number; name: string; email: string; tenant_id: string }; message: string } }>(`/invitations/${token}/accept`, userData);
      const responseData = response.data?.data;
      if (!responseData) {
        throw new Error('Invalid response format from API');
      }
      return responseData;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  }
};

