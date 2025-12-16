import { createApiClient, mapAxiosError } from '../../shared/api/client';
import axios from 'axios';

const apiClient = createApiClient();

export interface LoginCredentials {
  email: string;
  password: string;
  remember?: boolean;
}

export interface LoginResponse {
  session_id: string;
  token: string;
  token_type: string;
  expires_in: number;
  onboarding_state?: any;
  user: User;
  redirect_path?: string;
}

export interface User {
  id: string | number;
  name: string;
  email: string;
  tenant_id?: string | number;
  role?: string;
  permissions?: string[];
  avatar?: string | null;
  can_access_admin?: boolean;
  is_super_admin?: boolean;
  is_org_admin?: boolean;
}

export interface PermissionsResponse {
  permissions: string[];
  roles: string[];
}

/**
 * Authentication API Client
 * 
 * Handles all authentication-related API calls.
 * Endpoints are from routes/api.php with prefix /api/v1/auth
 */
export const authApi = {
  /**
   * Get CSRF token for React frontend
   * GET /api/auth/csrf-token
   */
  async getCsrfToken(): Promise<string> {
    try {
      const response = await axios.get<{ 
        success?: boolean; 
        data?: { csrf_token: string };
        error?: { message?: string; id?: string };
        message?: string;
        csrf_token?: string; // Fallback: direct token in response
      }>(
        '/api/auth/csrf-token',
        {
          withCredentials: true,
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
        }
      );
      
      // Handle case where response.data might be a string with HTML warnings prepended
      let responseData = response.data;
      if (typeof responseData === 'string') {
        // Try to extract JSON from string (in case HTML warnings are prepended)
        const jsonMatch = responseData.match(/\{[\s\S]*\}/);
        if (jsonMatch) {
          try {
            responseData = JSON.parse(jsonMatch[0]);
            console.warn('[authApi.getCsrfToken] Response contained HTML, extracted JSON:', responseData);
          } catch (e) {
            console.error('[authApi.getCsrfToken] Failed to parse JSON from string response:', e);
          }
        }
      }
      
      // Log full response for debugging
      console.log('[authApi.getCsrfToken] Full response:', {
        status: response.status,
        headers: response.headers,
        data: responseData,
      });
      
      // Check if response has the expected structure: { success: true, data: { csrf_token: "..." } }
      if (responseData?.data?.csrf_token) {
        return responseData.data.csrf_token;
      }
      
      // Fallback: check if token is directly in response.data
      if (responseData?.csrf_token) {
        return responseData.csrf_token;
      }
      
      // Handle error response format
      if (responseData?.error) {
        throw new Error(responseData.error.message || 'Failed to get CSRF token');
      }
      
      // Handle unexpected response format
      console.error('[authApi.getCsrfToken] Unexpected response format:', {
        responseData: responseData,
        responseStatus: response.status,
        responseHeaders: response.headers,
      });
      throw new Error(`Unexpected CSRF token response format. Response: ${JSON.stringify(responseData)}`);
    } catch (error: any) {
      console.error('[authApi.getCsrfToken] Error getting CSRF token:', {
        error,
        message: error?.message,
        response: error?.response?.data,
        status: error?.response?.status,
      });
      
      // If it's already an ApiError, re-throw it
      if (error instanceof Error && error.name === 'ApiError') {
        throw error;
      }
      
      throw mapAxiosError(error);
    }
  },

  /**
   * Login user
   * POST /api/auth/login (route is /api/auth/login, not /api/v1/auth/login)
   */
  async login(credentials: LoginCredentials): Promise<LoginResponse> {
    try {
      // Get CSRF token first
      const csrfToken = await this.getCsrfToken();
      
      const response = await apiClient.post<{ 
        status?: string;
        success?: boolean;
        data?: LoginResponse;
        message?: string;
      }>(
        '/auth/login',
        {
          email: credentials.email,
          password: credentials.password,
          remember: credentials.remember ?? false,
        },
        {
          headers: {
            'X-Web-Login': 'true', // Request session-based auth
            'X-CSRF-TOKEN': csrfToken, // Include CSRF token
          },
        }
      );

      // Handle ApiResponse format: { status: 'success', data: { token, user, ... } }
      // or direct format: { token, user, ... }
      let data: LoginResponse;
      
      if (response.data?.data) {
        // ApiResponse format: response.data.data contains the actual data
        data = response.data.data;
      } else if (response.data?.token) {
        // Direct format: response.data is the LoginResponse
        data = response.data as LoginResponse;
      } else {
        console.error('[authApi.login] Unexpected response format:', response.data);
        throw new Error('Unexpected login response format');
      }
      
      // Store token in localStorage
      if (data.token && typeof window !== 'undefined') {
        // Log token info for debugging
        console.log('[authApi.login] Token received:', {
          length: data.token.length,
          preview: data.token.substring(0, 30) + '...',
          full_token: data.token // Log full token for debugging (remove in production)
        });
        
        window.localStorage.setItem('auth_token', data.token);
        
        // Verify token was saved correctly
        const savedToken = window.localStorage.getItem('auth_token');
        if (savedToken === data.token) {
          console.log('[authApi.login] Token saved to localStorage successfully');
        } else {
          console.error('[authApi.login] Token mismatch after save!', {
            original_length: data.token.length,
            saved_length: savedToken?.length,
            match: savedToken === data.token
          });
        }
      } else {
        console.warn('[authApi.login] No token found in response:', data);
      }

      return data;
    } catch (error) {
      console.error('[authApi.login] Login failed:', error);
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Logout user
   * POST /api/v1/auth/logout (route is in v1/auth prefix)
   */
  async logout(): Promise<void> {
    try {
      await apiClient.post('/auth/logout');
      
      // Remove token from localStorage
      if (typeof window !== 'undefined') {
        window.localStorage.removeItem('auth_token');
      }
    } catch (error) {
      // Even if logout fails, clear local token
      if (typeof window !== 'undefined') {
        window.localStorage.removeItem('auth_token');
      }
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get current user
   * GET /api/v1/me (canonical endpoint)
   * Uses session auth via withCredentials for stateful authentication
   */
  async getMe(): Promise<User> {
    try {
      // Use canonical /api/v1/me endpoint
      // Use session-based auth with withCredentials since login uses X-Web-Login
      const response = await axios.get<{ 
        success?: boolean;
        data?: {
          user: User;
          permissions: string[];
          abilities: string[];
          tenants_summary?: {
            count: number;
            items: Array<{ id: string; name: string; slug?: string }>;
          };
          onboarding_state?: 'email_verification' | 'tenant_setup' | 'completed';
        };
        user?: User; // Fallback for old format
      }>('/api/v1/me', {
        withCredentials: true,
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': typeof window !== 'undefined' 
            ? (window.Laravel?.csrfToken ?? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content)
            : undefined,
          // Try token auth first, fallback to session
          ...(typeof window !== 'undefined' && window.localStorage.getItem('auth_token') 
            ? { 'Authorization': `Bearer ${window.localStorage.getItem('auth_token')}` }
            : {}),
        },
      });
      
      // Handle standardized response format: { success: true, data: { user, permissions, abilities, ... } }
      if (response.data?.data?.user) {
        return response.data.data.user;
      }
      
      // Fallback for old format: { user: {...}, permissions: [...], abilities: [...] }
      if (response.data?.user) {
        return response.data.user;
      }
      
      // Last resort: assume response.data is the user object
      return response.data as User;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get user permissions
   * GET /api/auth/permissions (route is /api/auth/permissions, not /api/v1/auth/permissions)
   * Uses session auth via withCredentials, not token auth
   */
  async getPermissions(): Promise<PermissionsResponse> {
    try {
      // Route is /api/auth/permissions (not /api/v1/auth/permissions)
      // Use session-based auth with withCredentials since login uses X-Web-Login
      const response = await axios.get<{ data: PermissionsResponse }>('/api/auth/permissions', {
        withCredentials: true,
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': typeof window !== 'undefined' 
            ? (window.Laravel?.csrfToken ?? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content)
            : undefined,
          // Try token auth first, fallback to session
          ...(typeof window !== 'undefined' && window.localStorage.getItem('auth_token') 
            ? { 'Authorization': `Bearer ${window.localStorage.getItem('auth_token')}` }
            : {}),
        },
      });
      return response.data?.data || response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Send password reset link
   * POST /api/auth/password/forgot
   */
  async forgotPassword(email: string): Promise<{ message: string }> {
    try {
      const response = await apiClient.post<{
        success?: boolean;
        data?: { message: string };
        message?: string;
        error?: { message?: string; code?: string };
      }>(
        '/auth/password/forgot',
        { email },
        {
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
          },
        }
      );

      // Handle ApiResponse format: { success: true, data: { message: "..." } }
      // or direct format: { message: "..." }
      if (response.data?.data?.message) {
        return { message: response.data.data.message };
      }
      if (response.data?.message) {
        return { message: response.data.message };
      }

      throw new Error('Unexpected response format');
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Reset password with token
   * POST /api/auth/password/reset
   */
  async resetPassword(data: {
    email: string;
    password: string;
    password_confirmation: string;
    token: string;
  }): Promise<{ message: string }> {
    try {
      const response = await apiClient.post<{
        success?: boolean;
        data?: { message: string };
        message?: string;
        error?: { message?: string; code?: string };
      }>(
        '/auth/password/reset',
        data,
        {
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
          },
        }
      );

      // Handle ApiResponse format: { success: true, data: { message: "..." } }
      // or direct format: { message: "..." }
      if (response.data?.data?.message) {
        return { message: response.data.data.message };
      }
      if (response.data?.message) {
        return { message: response.data.message };
      }

      throw new Error('Unexpected response format');
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Register new user
   * POST /api/auth/register
   */
  async register(data: {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    tenant_name: string;
    phone?: string;
    terms: boolean;
  }): Promise<{
    message: string;
    user: User;
    tenant: {
      id: string;
      name: string;
      slug: string;
    };
    verification_sent: boolean;
  }> {
    try {
      const response = await apiClient.post<{
        success?: boolean;
        data?: {
          message: string;
          user: User;
          tenant: {
            id: string;
            name: string;
            slug: string;
          };
          verification_sent: boolean;
        };
        message?: string;
        error?: { message?: string; code?: string };
      }>(
        '/auth/register',
        data,
        {
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
          },
        }
      );

      // Handle ApiResponse format: { success: true, data: { ... } }
      if (response.data?.data) {
        return response.data.data;
      }

      throw new Error('Unexpected response format');
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Change password for authenticated user
   * POST /api/auth/password/change
   */
  async changePassword(data: {
    current_password: string;
    password: string;
    password_confirmation: string;
  }): Promise<{ message: string }> {
    try {
      const response = await apiClient.post<{
        success?: boolean;
        data?: { message: string };
        message?: string;
        error?: { message?: string; code?: string };
      }>(
        '/auth/password/change',
        data,
        {
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
          },
        }
      );

      // Handle ApiResponse format: { success: true, data: { message: "..." } }
      // or direct format: { message: "..." }
      if (response.data?.data?.message) {
        return { message: response.data.data.message };
      }
      if (response.data?.message) {
        return { message: response.data.message };
      }

      throw new Error('Unexpected response format');
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

