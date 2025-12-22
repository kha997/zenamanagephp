/**
 * Legacy auth store adapter - wraps canonical store from features/auth/store.ts
 * 
 * This file maintains backward compatibility for components importing from shared/auth/store
 * All auth state is now managed by the canonical store in features/auth/store.ts
 * which persists to localStorage['zena-auth-storage']
 * 
 * Round 135: Unified auth store - no more duplicate auth-storage persistence
 * Round 136: Deferred initialization - no module-level side effects, init via initSharedAuthAdapter()
 */
import { useAuthStore as useCanonicalAuthStore } from '@/features/auth/store';
import { create } from 'zustand';
import { apiClient } from '../api/client';
import type { User } from './types';

export interface RoleInfo {
  id: string;
  name: string;
  scope?: string;
  description?: string;
  permissions: string[];
  created_at?: string;
  updated_at?: string;
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

/**
 * Adapter store that wraps canonical store and provides legacy API
 * This maintains backward compatibility for components expecting token, setToken, clearAuth, etc.
 * 
 * Round 136: Store creation is now side-effect free. Subscription is set up via initSharedAuthAdapter()
 */
export const useAuthStore = create<AuthStore>()((set, get) => {
  // Sync function to update adapter state from canonical store
  // This is called from initSharedAuthAdapter() and actions, not at module init
  const syncFromCanonical = () => {
    try {
      if (typeof window === 'undefined') return;
      const canonical = useCanonicalAuthStore.getState();
      const token = localStorage.getItem('auth_token');
      set({
        user: canonical.user,
        token,
        isAuthenticated: canonical.isAuthenticated,
        isLoading: canonical.isLoading,
        error: canonical.error,
      });
    } catch (error) {
      if (typeof window !== 'undefined') {
        if (!(window as any).__e2e_logs) {
          (window as any).__e2e_logs = [];
        }
        (window as any).__e2e_logs.push({
          scope: 'shared-auth-adapter',
          event: 'sync-error',
          error: String(error),
        });
      }
    }
  };

  return {
    // Initial state (will be synced from canonical store via initSharedAuthAdapter)
    user: null,
    token: typeof window !== 'undefined' ? localStorage.getItem('auth_token') : null,
    isAuthenticated: false,
    isLoading: false,
    error: null,

    // Actions - delegate to canonical store or provide wrappers
    login: async (email: string, password: string) => {
      const canonical = useCanonicalAuthStore.getState();
      await canonical.login({ email, password });
      syncFromCanonical();
    },

    logout: async () => {
      const canonical = useCanonicalAuthStore.getState();
      await canonical.logout();
      syncFromCanonical();
    },

    setUser: (user: User) => {
      const canonical = useCanonicalAuthStore.getState();
      canonical.setUser(user);
      syncFromCanonical();
    },

    setToken: (token: string) => {
      try {
        if (typeof window !== 'undefined') {
          localStorage.setItem('auth_token', token);
          apiClient.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        }
        // Trigger checkAuth to sync state
        const canonical = useCanonicalAuthStore.getState();
        canonical.checkAuth().then(() => syncFromCanonical());
      } catch (error) {
        if (typeof window !== 'undefined') {
          if (!(window as any).__e2e_logs) {
            (window as any).__e2e_logs = [];
          }
          (window as any).__e2e_logs.push({
            scope: 'shared-auth-adapter',
            event: 'setToken-error',
            error: String(error),
          });
        }
      }
    },

    setError: (error: string | null) => {
      const canonical = useCanonicalAuthStore.getState();
      canonical.clearError();
      // Note: canonical store doesn't have setError, only clearError
      // This is a limitation but shouldn't break existing code
      syncFromCanonical();
    },

    setLoading: (loading: boolean) => {
      // Canonical store manages loading internally, so this is a no-op
      // But we keep it for API compatibility
    },

    clearAuth: () => {
      try {
        if (typeof window !== 'undefined') {
          localStorage.removeItem('auth_token');
          delete apiClient.defaults.headers.common['Authorization'];
        }
        const canonical = useCanonicalAuthStore.getState();
        canonical.logout().then(() => syncFromCanonical());
      } catch (error) {
        if (typeof window !== 'undefined') {
          if (!(window as any).__e2e_logs) {
            (window as any).__e2e_logs = [];
          }
          (window as any).__e2e_logs.push({
            scope: 'shared-auth-adapter',
            event: 'clearAuth-error',
            error: String(error),
          });
        }
      }
    },

    refreshUser: async () => {
      const canonical = useCanonicalAuthStore.getState();
      await canonical.checkAuth();
      syncFromCanonical();
    },
  };
});

// Round 136: Deferred initialization - no module-level side effects
let sharedAdapterInitialized = false;
let unsubscribeFromCanonical: (() => void) | null = null;

/**
 * Sync function that can be called from initSharedAuthAdapter
 * Accesses the store's internal sync function
 */
function syncFromCanonicalState() {
  try {
    if (typeof window === 'undefined') return;
    const store = useAuthStore.getState();
    const canonical = useCanonicalAuthStore.getState();
    const token = localStorage.getItem('auth_token');
    useAuthStore.setState({
      user: canonical.user,
      token,
      isAuthenticated: canonical.isAuthenticated,
      isLoading: canonical.isLoading,
      error: canonical.error,
    });
  } catch (error) {
    if (typeof window !== 'undefined') {
      if (!(window as any).__e2e_logs) {
        (window as any).__e2e_logs = [];
      }
      (window as any).__e2e_logs.push({
        scope: 'shared-auth-adapter',
        event: 'sync-error',
        error: String(error),
      });
    }
  }
}

/**
 * Initialize the shared auth adapter
 * Sets up subscription to canonical store and performs initial sync
 * Must be called from React lifecycle (e.g., AppShell useEffect)
 * 
 * Round 136: Fail-soft - never throws, logs errors to window.__e2e_logs
 */
export function initSharedAuthAdapter() {
  if (sharedAdapterInitialized) return;
  
  try {
    sharedAdapterInitialized = true;
    
    if (typeof window === 'undefined') {
      return;
    }

    // Sync initial state
    syncFromCanonicalState();

    // Subscribe to canonical store changes
    unsubscribeFromCanonical = useCanonicalAuthStore.subscribe((state) => {
      syncFromCanonicalState();
    });

    // Log successful initialization
    if (!(window as any).__e2e_logs) {
      (window as any).__e2e_logs = [];
    }
    (window as any).__e2e_logs.push({
      scope: 'shared-auth-adapter',
      event: 'init-ok',
    });
  } catch (error) {
    // Fail-soft: log error but don't throw
    if (typeof window !== 'undefined') {
      if (!(window as any).__e2e_logs) {
        (window as any).__e2e_logs = [];
      }
      (window as any).__e2e_logs.push({
        scope: 'shared-auth-adapter',
        event: 'init-error',
        error: String(error),
      });
    }
    // Reset flag so retry is possible
    sharedAdapterInitialized = false;
  }
}

/**
 * Teardown the shared auth adapter
 * Unsubscribes from canonical store
 */
export function teardownSharedAuthAdapter() {
  if (unsubscribeFromCanonical) {
    unsubscribeFromCanonical();
    unsubscribeFromCanonical = null;
  }
  sharedAdapterInitialized = false;
}

// Initialize auth state from localStorage on app start
// Round 136: Now also initializes shared auth adapter for backward compatibility
export const initializeAuth = () => {
  if (typeof window === 'undefined') return;
  
  // Round 136: Initialize shared adapter (idempotent)
  initSharedAuthAdapter();
  
  const token = localStorage.getItem('auth_token');
  if (token) {
    apiClient.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    // Trigger checkAuth to sync canonical store
    const canonical = useCanonicalAuthStore.getState();
    canonical.checkAuth();
  }
};
