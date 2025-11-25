import { apiClient } from '../api/client';

export interface LoginRequest {
  email: string;
  password: string;
}

export interface LoginResponse {
  user: {
    id: string;
    name: string;
    email: string;
    avatar?: string;
    roles: string[];
    permissions: string[];
    tenant_id?: string;
    tenant_name?: string;
  };
  token: string;
}

export interface ForgotPasswordRequest {
  email: string;
}

export interface ResetPasswordRequest {
  email: string;
  token: string;
  password: string;
  password_confirmation: string;
}

export interface TwoFactorRequest {
  code: string;
}

export const authApi = {
  // Login
  login: async (data: LoginRequest): Promise<LoginResponse> => {
    const response = await apiClient.post('/api/v1/auth/login', data);
    return response.data;
  },

  // Logout
  logout: async (): Promise<void> => {
    await apiClient.post('/api/v1/auth/logout');
  },

  // Get current user
  me: async () => {
    const response = await apiClient.get('/api/v1/auth/me');
    return response.data;
  },

  // Forgot password
  forgotPassword: async (data: ForgotPasswordRequest): Promise<void> => {
    await apiClient.post('/api/v1/auth/password/forgot', data);
  },

  // Reset password
  resetPassword: async (data: ResetPasswordRequest): Promise<void> => {
    await apiClient.post('/api/v1/auth/password/reset', data);
  },

  // Two-factor authentication
  verifyTwoFactor: async (data: TwoFactorRequest): Promise<LoginResponse> => {
    const response = await apiClient.post('/api/v1/auth/2fa/verify', data);
    return response.data;
  },

  // Resend two-factor code
  resendTwoFactor: async (): Promise<void> => {
    await apiClient.post('/api/v1/auth/2fa/resend');
  },

  // Get CSRF cookie
  getCsrfCookie: async (): Promise<void> => {
    await apiClient.get('/sanctum/csrf-cookie');
  },
};
