/**
 * Authentication Types
 */

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

export interface AuthState {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  error: string | null;
}

export interface PermissionsResponse {
  permissions: string[];
  roles: string[];
}

