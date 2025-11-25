import { create } from 'zustand';
import axios from 'axios';
import { authApi } from './api';
import type { User, LoginCredentials, AuthState } from './types';

interface AuthStore extends AuthState {
  // State
  selectedTenantId: string | number | null;
  permissions: string[];
  abilities: string[];
  tenantsCount: number;
  currentTenantRole?: string | null;
  currentTenantPermissions: string[];
  
  // Actions
  login: (credentials: LoginCredentials) => Promise<string | undefined>;
  logout: () => Promise<void>;
  setUser: (user: User | null) => void;
  checkAuth: () => Promise<void>;
  clearError: () => void;
  selectTenant: (tenantId: string | number) => Promise<void>;
  hasTenantPermission: (permission: string) => boolean;
}

/**
 * Auth Store (Zustand)
 * 
 * Manages authentication state and user session.
 * Persists user data to localStorage manually.
 */
// Load initial state from localStorage
const loadFromStorage = (): Partial<AuthState & { currentTenantRole?: string | null; currentTenantPermissions?: string[] }> => {
  if (typeof window === 'undefined') {
    return { user: null, isAuthenticated: false };
  }
  
  try {
    const stored = window.localStorage.getItem('auth-storage');
    if (stored) {
      const parsed = JSON.parse(stored);
      return {
        user: parsed.user || null,
        isAuthenticated: parsed.isAuthenticated || false,
        currentTenantRole: parsed.currentTenantRole ?? null,
        currentTenantPermissions: parsed.currentTenantPermissions ?? [],
      };
    }
  } catch (error) {
    console.error('Failed to load auth from storage:', error);
  }
  
  return { user: null, isAuthenticated: false };
};

const saveToStorage = (state: Partial<AuthState & { currentTenantRole?: string | null; currentTenantPermissions?: string[] }>) => {
  if (typeof window === 'undefined') return;
  
  try {
    window.localStorage.setItem(
      'auth-storage',
      JSON.stringify({
        user: state.user,
        isAuthenticated: state.isAuthenticated,
        currentTenantRole: state.currentTenantRole ?? null,
        currentTenantPermissions: state.currentTenantPermissions ?? [],
      })
    );
  } catch (error) {
    console.error('Failed to save auth to storage:', error);
  }
};

// Initialize initial state
const initialState = loadFromStorage();

export const useAuthStore = create<AuthStore>()((set, get) => ({
      // Initial state
      user: initialState.user || null,
      isAuthenticated: initialState.isAuthenticated || false,
      isLoading: false,
      error: null,
      selectedTenantId: initialState.user?.tenant_id || null,
      permissions: [],
      abilities: [],
      tenantsCount: 0,
      currentTenantRole: (initialState as any).currentTenantRole ?? null,
      currentTenantPermissions: (initialState as any).currentTenantPermissions ?? [],

      // Login action
      login: async (credentials: LoginCredentials) => {
        set({ isLoading: true, error: null });
        
        try {
          console.log('[authStore] Starting login...');
          const response = await authApi.login(credentials);
          console.log('[authStore] Login API call successful, user:', response.user?.email);
          
          // Verify token is in localStorage
          if (typeof window !== 'undefined') {
            const token = window.localStorage.getItem('auth_token');
            if (token) {
              console.log('[authStore] Token verified in localStorage');
            } else {
              console.warn('[authStore] Token not found in localStorage after login!');
            }
          }
          
          // Optionally call getMe() to normalize shape and get full context
          // This ensures we have permissions, abilities, tenants_summary, etc.
          try {
            const meResponse = await axios.get<{
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
            }>('/api/v1/me', {
              withCredentials: true,
              headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': typeof window !== 'undefined' 
                  ? (window.Laravel?.csrfToken ?? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content)
                  : undefined,
                ...(typeof window !== 'undefined' && window.localStorage.getItem('auth_token') 
                  ? { 'Authorization': `Bearer ${window.localStorage.getItem('auth_token')}` }
                  : {}),
              },
            });
            
            const meData = meResponse.data?.data || meResponse.data as any;
            const userData = meData?.user || response.user;
            const permissions = meData?.permissions || [];
            const abilities = meData?.abilities || [];
            const tenantsCount = meData?.tenants_summary?.count || 0;
            const currentTenantRole = meData?.current_tenant_role ?? null;
            const currentTenantPermissions = meData?.current_tenant_permissions ?? [];
            
            const newState = {
              user: userData,
              isAuthenticated: true,
              isLoading: false,
              error: null,
              permissions,
              abilities,
              tenantsCount,
              selectedTenantId: userData?.tenant_id || null,
              currentTenantRole,
              currentTenantPermissions,
            };
            set(newState);
            saveToStorage(newState);
            console.log('[authStore] Auth state saved to localStorage with full context');
          } catch (meError) {
            // If getMe() fails, still use login response
            console.warn('[authStore] Failed to get full context, using login response:', meError);
            const newState = {
              user: response.user,
              isAuthenticated: true,
              isLoading: false,
              error: null,
              permissions: [],
              abilities: [],
              tenantsCount: 0,
              selectedTenantId: response.user?.tenant_id || null,
              currentTenantRole: null,
              currentTenantPermissions: [],
            };
            set(newState);
            saveToStorage(newState);
          }
          
          // Return redirect_path for navigation with fallback
          return response.redirect_path || '/app/dashboard';
        } catch (error: any) {
          console.error('[authStore] Login failed:', error);
          set({
            user: null,
            isAuthenticated: false,
            isLoading: false,
            error: error.message || 'Đăng nhập thất bại',
            permissions: [],
            abilities: [],
            tenantsCount: 0,
            selectedTenantId: null,
            currentTenantRole: null,
          });
          throw error;
        }
      },

      // Logout action
      logout: async () => {
        set({ isLoading: true });
        
        try {
          await authApi.logout();
        } catch (error) {
          // Continue with logout even if API call fails
          console.error('Logout API call failed:', error);
        } finally {
          const newState = {
            user: null,
            isAuthenticated: false,
            isLoading: false,
            error: null,
            permissions: [],
            abilities: [],
            tenantsCount: 0,
            selectedTenantId: null,
            currentTenantRole: null,
            currentTenantPermissions: [],
          };
          set(newState);
          saveToStorage(newState);
        }
      },

      // Set user directly
      setUser: (user: User | null) => {
        const newState = {
          user,
          isAuthenticated: !!user,
          permissions: [],
          abilities: [],
          tenantsCount: 0,
          selectedTenantId: user?.tenant_id || null,
          currentTenantRole: null,
          currentTenantPermissions: [],
        };
        set(newState);
        saveToStorage(newState);
      },

      // Check authentication status
      checkAuth: async () => {
        const { user } = get();
        
        set({ isLoading: true });
        
        try {
          // Always call canonical /api/v1/me to get fresh data
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
          }>('/api/v1/me', {
            withCredentials: true,
            headers: {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': typeof window !== 'undefined' 
                ? (window.Laravel?.csrfToken ?? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content)
                : undefined,
              ...(typeof window !== 'undefined' && window.localStorage.getItem('auth_token') 
                ? { 'Authorization': `Bearer ${window.localStorage.getItem('auth_token')}` }
                : {}),
            },
          });
          
          // Extract data from standardized response
          const meData = response.data?.data || response.data as any;
          const userData = meData?.user || meData;
          const permissions = meData?.permissions || [];
          const abilities = meData?.abilities || [];
          const tenantsCount = meData?.tenants_summary?.count || 0;
          const currentTenantRole = meData?.current_tenant_role ?? null;
          const currentTenantPermissions = meData?.current_tenant_permissions ?? [];
          
          const newState = {
            user: userData,
            isAuthenticated: true,
            isLoading: false,
            error: null,
            permissions,
            abilities,
            tenantsCount,
            selectedTenantId: userData?.tenant_id || null,
            currentTenantRole,
            currentTenantPermissions,
          };
          set(newState);
          saveToStorage(newState);
        } catch (error: any) {
          // If failed, clear auth state
          const newState = {
            user: null,
            isAuthenticated: false,
            isLoading: false,
            error: error.message || 'Không thể xác thực',
            permissions: [],
            abilities: [],
            tenantsCount: 0,
            selectedTenantId: null,
            currentTenantRole: null,
            currentTenantPermissions: [],
          };
          set(newState);
          saveToStorage(newState);
        }
      },

      // Clear error
      clearError: () => {
        set({ error: null });
      },

      // Select tenant
      selectTenant: async (tenantId: string | number) => {
        try {
          set({ isLoading: true, error: null });
          
          // Call API to select tenant with include_me=true to get fresh Me payload
          const response = await axios.post<{
            success?: boolean;
            data?: {
              tenant_id: string | number;
              tenant_name: string;
              message: string;
              me?: {
                user: User;
                permissions: string[];
                abilities: string[];
                tenants_summary?: {
                  count: number;
                  items: Array<{ id: string; name: string; slug?: string }>;
                };
              };
            };
          }>(`/api/v1/me/tenants/${tenantId}/select?include_me=true`, {}, {
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': typeof window !== 'undefined' 
                ? (window.Laravel?.csrfToken ?? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '')
                : '',
            },
            withCredentials: true,
          });

          const responseData = response.data?.data || response.data as any;
          
          // If response includes fresh Me payload, use it to update state
          if (responseData?.me) {
            const meData = responseData.me;
            const newState = {
              user: meData.user,
              selectedTenantId: tenantId,
              permissions: meData.permissions || [],
              abilities: meData.abilities || [],
              tenantsCount: meData.tenants_summary?.count || 0,
              currentTenantRole: meData.current_tenant_role ?? null,
              currentTenantPermissions: meData.current_tenant_permissions ?? [],
              isLoading: false,
              error: null,
            };
            set(newState);
            saveToStorage(newState);
          } else {
            // Otherwise, update tenant_id and refresh via getMe()
            const { user } = get();
            if (user) {
              set({
                user: {
                  ...user,
                  tenant_id: tenantId,
                },
                selectedTenantId: tenantId,
                isLoading: false,
                error: null,
              });
            }
            
            // Refresh full state via getMe()
            await get().checkAuth();
          }
        } catch (error: any) {
          console.error('[authStore] Select tenant failed:', error);
          set({
            isLoading: false,
            error: error.response?.data?.message || error.message || 'Không thể chọn tenant',
          });
          throw error;
        }
      },

      // Check if user has a specific tenant permission
      hasTenantPermission: (permission: string) => {
        const { currentTenantPermissions } = get();
        return currentTenantPermissions.includes(permission);
      },
    }));

