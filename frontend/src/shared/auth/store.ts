import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import { apiClient } from '../api/client';

export interface User {
  id: string;
  name: string;
  email: string;
  avatar?: string;
  roles: string[];
  permissions: string[];
  tenant_id?: string;
  tenant_name?: string;
}

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
          // First, get CSRF cookie - use absolute path to avoid base URL prefix
          await apiClient.get('/sanctum/csrf-cookie', {
            baseURL: window.location.origin
          });

          // Then login
          const response = await apiClient.post('/api/v1/auth/login', {
            email,
            password,
          });

          const { user, token } = response.data;

          // Set token in localStorage and axios headers
          localStorage.setItem('auth_token', token);
          apiClient.defaults.headers.common['Authorization'] = `Bearer ${token}`;

          set({
            user,
            token,
            isAuthenticated: true,
            isLoading: false,
            error: null,
          });
        } catch (error: any) {
          const errorMessage = error.response?.data?.message || 'Login failed';
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
          set({ user: response.data.user });
        } catch (error) {
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
