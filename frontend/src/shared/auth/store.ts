import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import { apiClient } from '../api/client';

export interface RoleInfo {
  id: string;
  name: string;
  scope?: string;
  description?: string;
  permissions: string[];
  created_at?: string;
  updated_at?: string;
}

export interface User {
  id: string;
  name: string;
  display_name?: string;
  email: string;
  avatar?: string | null;
  roles: RoleInfo[];
  permissions: string[];
  tenant_id?: string;
  tenant_name?: string;
}

type RawRole = Partial<RoleInfo> & {
  role?: string;
  role_id?: string;
};

type RawTenant = {
  id?: string;
  name?: string;
};

type RawUser = {
  id?: string;
  name?: string;
  display_name?: string;
  email?: string;
  avatar?: string | null;
  tenant_id?: string;
  tenant_name?: string;
  tenant?: RawTenant;
  roles?: Array<RawRole | string>;
  role?: RawRole | string;
  role_id?: string;
  permissions?: string[];
  role_permissions?: string[];
  [key: string]: unknown;
};

export interface AuthState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  error: string | null;
}

export interface AuthActions {
  login: (email: string, password: string) => Promise<void>;
  logout: () => void;
  setUser: (user: User) => void;
  setToken: (token: string) => void;
  setError: (error: string | null) => void;
  setLoading: (loading: boolean) => void;
  clearAuth: () => void;
  refreshUser: () => Promise<void>;
}

export interface AuthStore extends AuthState, AuthActions {}

const fallbackTimestamp = () => new Date().toISOString();

const normalizeRole = (role: RawRole | string): RoleInfo => {
  if (typeof role === 'string') {
    const timestamp = fallbackTimestamp();
    return {
      id: `role-${role}`,
      name: role,
      scope: 'custom',
      permissions: [],
      created_at: timestamp,
      updated_at: timestamp,
    };
  }

  const timestamp = role.created_at ?? fallbackTimestamp();
  const name = role.name ?? role.role ?? 'member';

  return {
    id: role.id ?? role.role_id ?? `role-${name}`,
    name,
    scope: role.scope ?? 'custom',
    description: role.description,
    permissions: Array.isArray(role.permissions) ? role.permissions : [],
    created_at: timestamp,
    updated_at: role.updated_at ?? timestamp,
  };
};

const normalizePermissions = (payload: RawUser): string[] => {
  if (Array.isArray(payload.permissions) && payload.permissions.length) {
    return payload.permissions;
  }
  if (Array.isArray(payload.role_permissions) && payload.role_permissions.length) {
    return payload.role_permissions;
  }
  return [];
};

const toUser = (payload: RawUser): User => {
  const normalizedRoles = Array.isArray(payload.roles) && payload.roles.length > 0
    ? payload.roles.map(normalizeRole)
    : payload.role
      ? [normalizeRole(payload.role)]
      : [];

  const fallbackName = payload.display_name ?? payload.name ?? 'User';

  return {
    id: payload.id ?? 'user',
    name: payload.name ?? fallbackName,
    display_name: payload.display_name ?? payload.name,
    email: payload.email ?? '',
    avatar: payload.avatar ?? null,
    tenant_id: payload.tenant_id ?? payload.tenant?.id,
    tenant_name: payload.tenant_name ?? payload.tenant?.name,
    roles: normalizedRoles,
    permissions: normalizePermissions(payload),
  };
};

export const useAuthStore = create<AuthStore>()(
  persist(
    (set, get) => ({
      // State
      user: null,
      token: null,
      isAuthenticated: false,
      isLoading: false,
      error: null,

      // Actions
      login: async (email: string, password: string) => {
        set({ isLoading: true, error: null });

        try {
          // Login via API and request Laravel to start a web session
          const response = await apiClient.post(
            '/auth/login',
            {
              email,
              password,
            },
            {
              headers: {
                'X-Web-Login': 'spa-client',
              },
              withCredentials: true,
            }
          );

          // API returns: { status, success, message, data: { token, user, ... } }
          const responseData = response.data;
          const token = responseData.data?.token || responseData.token;
          const userData = responseData.data?.user || responseData.user;

          if (!token || !userData) {
            throw new Error('Invalid response from server');
          }

          // Set token in localStorage and axios headers
          localStorage.setItem('auth_token', token);
          apiClient.defaults.headers.common['Authorization'] = `Bearer ${token}`;

          set({
            user: toUser(userData),
            token,
            isAuthenticated: true,
            isLoading: false,
            error: null,
          });
        } catch (error: unknown) {
          const responseMessage =
            typeof error === 'object' &&
            error !== null &&
            'response' in error &&
            typeof (error as { response?: { data?: { message?: string } } }).response?.data?.message === 'string'
              ? (error as { response?: { data?: { message?: string } } }).response!.data!.message
              : undefined;
          const errorMessage = responseMessage ?? (error instanceof Error ? error.message : 'Login failed');
          set({
            isLoading: false,
            error: errorMessage,
            isAuthenticated: false,
            user: null,
            token: null,
          });
          throw error;
        }
      },

      logout: () => {
        // Clear token from localStorage and axios headers
        localStorage.removeItem('auth_token');
        delete apiClient.defaults.headers.common['Authorization'];

        set({
          user: null,
          token: null,
          isAuthenticated: false,
          error: null,
        });
      },

      setUser: (user: User) => {
        set({ user });
      },

      setToken: (token: string) => {
        localStorage.setItem('auth_token', token);
        apiClient.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        set({ token, isAuthenticated: true });
      },

      setError: (error: string | null) => {
        set({ error });
      },

      setLoading: (loading: boolean) => {
        set({ isLoading: loading });
      },

      clearAuth: () => {
        localStorage.removeItem('auth_token');
        delete apiClient.defaults.headers.common['Authorization'];
        set({
          user: null,
          token: null,
          isAuthenticated: false,
          error: null,
          isLoading: false,
        });
      },

      refreshUser: async () => {
        const { token } = get();
        if (!token) return;

        try {
          const response = await apiClient.get('/api/v1/auth/me');
          set({ user: toUser(response.data.user) });
        } catch (error: unknown) {
          console.error('Failed to refresh user:', error);
          get().logout();
        }
      },
    }),
    {
      name: 'auth-storage',
      partialize: (state) => ({
        user: state.user,
        token: state.token,
        isAuthenticated: state.isAuthenticated,
      }),
    }
  )
);

// Initialize auth state from localStorage on app start
export const initializeAuth = () => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    apiClient.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    useAuthStore.setState({ token, isAuthenticated: true });
  }
};
