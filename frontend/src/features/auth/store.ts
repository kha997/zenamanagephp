import { create } from 'zustand';
import axios, { isAxiosError } from 'axios';
import { apiClient } from '../../shared/api/client';
import { authApi } from './api';
import type { User, LoginCredentials, AuthState } from './types';

/**
 * E2E-visible logging helper
 * Writes to window.__e2e_logs (survives minification) + console
 */
function e2eLog(message: string, data?: any) {
  try {
    (window as any).__e2e_logs = (window as any).__e2e_logs || [];
    (window as any).__e2e_logs.push(
      data ? `${message} ${JSON.stringify(data)}` : message
    );
  } catch {}
  // keep console too (even if minified drops it, window log remains)
  // eslint-disable-next-line no-console
  console.info(message, data ?? '');
}

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
    const stored = window.localStorage.getItem('zena-auth-storage');
    if (stored) {
      const parsed = JSON.parse(stored);
      // Normalize permissions to always be an array
      const perms = Array.isArray(parsed.currentTenantPermissions) 
        ? parsed.currentTenantPermissions 
        : [];
      
      console.log('[AuthStore loadFromStorage] Loaded from localStorage:', {
        hasUser: !!parsed.user,
        isAuthenticated: parsed.isAuthenticated || false,
        currentTenantRole: parsed.currentTenantRole ?? null,
        currentTenantPermissionsLength: perms.length,
        hasManageTasks: perms.includes('tenant.manage_tasks'),
      });
      
      return {
        user: parsed.user || null,
        isAuthenticated: parsed.isAuthenticated || false,
        currentTenantRole: parsed.currentTenantRole ?? null,
        currentTenantPermissions: perms,
      };
    }
  } catch (error) {
    console.error('[AuthStore loadFromStorage] Failed to load auth from storage:', error);
  }
  
  return { user: null, isAuthenticated: false };
};

const saveToStorage = (state: Partial<AuthState & { currentTenantRole?: string | null; currentTenantPermissions?: string[] }>) => {
  if (typeof window === 'undefined') {
    e2eLog('[AuthStore saveToStorage] Skipping - window is undefined');
    return;
  }
  
  try {
    const storageData = {
      user: state.user,
      isAuthenticated: state.isAuthenticated,
      currentTenantRole: state.currentTenantRole ?? null,
      currentTenantPermissions: state.currentTenantPermissions ?? [],
    };
    e2eLog('[AuthStore saveToStorage] Saving to localStorage', {
      hasUser: !!storageData.user,
      isAuthenticated: storageData.isAuthenticated,
      currentTenantRole: storageData.currentTenantRole,
      currentTenantPermissionsLength: storageData.currentTenantPermissions.length,
      currentTenantPermissions: storageData.currentTenantPermissions,
    });
    window.localStorage.setItem('zena-auth-storage', JSON.stringify(storageData));
    e2eLog('[AuthStore saveToStorage] Successfully saved to localStorage');
    
    // Verify it was saved
    const verify = window.localStorage.getItem('zena-auth-storage');
    if (verify) {
      const parsed = JSON.parse(verify);
      e2eLog('[AuthStore saveToStorage] Verification - saved permissions length', { length: parsed.currentTenantPermissions?.length || 0 });
    }
  } catch (error) {
    e2eLog('[AuthStore saveToStorage] Failed to save auth to storage', { error: String(error) });
    console.error('[AuthStore saveToStorage] Failed to save auth to storage:', error);
  }
};

/**
 * Safe wrapper for saveToStorage with error handling
 */
const saveToStorageSafe = (state: Partial<AuthState & { currentTenantRole?: string | null; currentTenantPermissions?: string[] }>) => {
  try {
    saveToStorage(state);
    e2eLog('[AuthStore saveToStorageSafe] OK');
  } catch (error) {
    e2eLog('[AuthStore saveToStorageSafe] FAIL', { error: String(error) });
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
      // Round 154: Always call /api/v1/me regardless of token presence
      // Supports both token-based (Bearer) and cookie-based (Sanctum stateful) auth
      checkAuth: async () => {
        const token = typeof window !== 'undefined' ? localStorage.getItem('auth_token') : null;
        e2eLog('[AuthStore] checkAuth start', { tokenPresent: !!token, mode: token ? 'token+cookie' : 'cookie-only' });
        
        set({ isLoading: true });
        
        try {
          // Round 154: Log ME request starting for E2E visibility
          e2eLog('[AuthStore] ME request starting', { endpoint: '/api/v1/me' });
          
          // Round 154: Always call canonical /api/v1/me to get fresh data
          // Use withCredentials: true to support Sanctum cookie-based auth
          // Include Authorization header only if token exists (optional optimization)
          const headers: Record<string, string> = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          };
          
          // Add CSRF token if available
          if (typeof window !== 'undefined') {
            const csrfToken = window.Laravel?.csrfToken ?? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
            if (csrfToken) {
              headers['X-CSRF-TOKEN'] = csrfToken;
            }
          }
          
          // Add Authorization header only if token exists (optional, Sanctum can use cookies)
          if (token) {
            headers['Authorization'] = `Bearer ${token}`;
          }
          
          // Round 154: Use apiClient which is already configured with withCredentials: true
          // This ensures proper cookie handling for Sanctum stateful auth
          const response = await apiClient.get<{
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
              current_tenant_role?: string | null;
              current_tenant_permissions?: string[];
            };
          }>('/me', {
            // apiClient already has withCredentials: true configured globally
            // But we can override headers if needed
            headers,
          });
          
          e2eLog('[AuthStore] /api/v1/me res', { status: response.status });
          
          // Extract data from standardized response
          const meData = response.data?.data || response.data as any;
          const userData = meData?.user || meData;
          const permissions = meData?.permissions || [];
          const abilities = meData?.abilities || [];
          const tenantsCount = meData?.tenants_summary?.count || 0;
          const currentTenantRole = meData?.current_tenant_role ?? null;
          // Robust parse: ensure current_tenant_permissions is always an array
          const rawPerms = meData?.current_tenant_permissions;
          const currentTenantPermissions = Array.isArray(rawPerms) ? rawPerms : [];
          
          // Normalize permissions to always be an array
          const normalizedPerms = Array.isArray(currentTenantPermissions) ? currentTenantPermissions : [];
          
          const newState = {
            user: userData,
            isAuthenticated: response.status === 200,
            isLoading: false,
            error: null,
            permissions,
            abilities,
            tenantsCount,
            selectedTenantId: userData?.tenant_id || null,
            currentTenantRole: currentTenantRole ?? null,
            currentTenantPermissions: normalizedPerms,
          };
          
          e2eLog('[AuthStore] checkAuth ok', {
            ok: response.status === 200,
            permsCount: normalizedPerms.length,
            hasManageTasks: normalizedPerms.includes('tenant.manage_tasks'),
          });
          
          // Set state first
          set(newState);
          
          // Then persist to localStorage (must be called after set)
          saveToStorageSafe(newState);
        } catch (error: any) {
          // Round 154: Handle 401 (unauthorized) as guest state, other errors as failures
          const isUnauthorized = isAxiosError(error) && error.response?.status === 401;
          
          e2eLog('[AuthStore] checkAuth error', { 
            error: String(error),
            status: axios.isAxiosError(error) ? error.response?.status : undefined,
            isUnauthorized,
          });
          
          const newState = {
            user: null,
            isAuthenticated: false,
            isLoading: false,
            error: isUnauthorized ? null : (error.message || 'Không thể xác thực'), // Don't set error for 401 (expected guest state)
            permissions: [],
            abilities: [],
            tenantsCount: 0,
            selectedTenantId: null,
            currentTenantRole: null,
            currentTenantPermissions: [],
          };
          set(newState);
          saveToStorageSafe(newState);
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

