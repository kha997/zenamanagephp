/**
 * Legacy auth store adapter - wraps canonical store from features/auth/store.ts
 * 
 * This file maintains backward compatibility for components importing from stores/authStore
 * All auth state is now managed by the canonical store in features/auth/store.ts
 * which persists to localStorage['zena-auth-storage']
 * 
 * Round 135: Unified auth store - no more duplicate auth-storage persistence
 */
import { useAuthStore as useCanonicalAuthStore } from '@/features/auth/store'
import type { User } from '../lib/types'

// Re-export canonical store as the default export
export const useAuthStore = useCanonicalAuthStore
export default useAuthStore

// Export types for backward compatibility
export type AuthState = {
  user: User | null
  isAuthenticated: boolean
  isLoading: boolean
  error: string | null
}

export type AuthActions = {
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
  refreshToken: () => Promise<void>
  updateProfile: (userData: Partial<User>) => Promise<void>
  clearError: () => void
  checkAuthStatus: () => Promise<void>
}

export type AuthStore = AuthState & AuthActions

// Legacy storage key (for reference only - not used for persistence)
export const authStorageKey = 'auth-storage'
