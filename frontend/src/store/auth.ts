import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import { AuthService } from '../lib/api/auth.service'
import { User, LoginCredentials } from '../lib/types'
import { setToken, removeToken, getUserProfile, setUserProfile } from '../lib/utils/auth'

interface AuthState {
  user: User | null
  isAuthenticated: boolean
  isLoading: boolean
  error: string | null
}

interface AuthActions {
  login: (credentials: LoginCredentials) => Promise<void>
  logout: () => Promise<void>
  refreshToken: () => Promise<void>
  updateProfile: (userData: Partial<User>) => Promise<void>
  clearError: () => void
  checkAuthStatus: () => Promise<void>
}

type AuthStore = AuthState & AuthActions

export const useAuthStore = create<AuthStore>()(persist(
    (set, get) => ({
      // State
      user: getUserProfile(),
      isAuthenticated: !!getUserProfile(),
      isLoading: false,
      error: null,

      // Actions
      login: async (credentials: LoginCredentials) => {
        try {
          set({ isLoading: true, error: null })
          
          const authResponse = await AuthService.login(credentials)
          
          // Lưu token và user info
          setToken(authResponse.token)
          setUserProfile(authResponse.user)
          
          set({
            user: authResponse.user,
            isAuthenticated: true,
            isLoading: false,
            error: null
          })
        } catch (error: any) {
          set({
            user: null,
            isAuthenticated: false,
            isLoading: false,
            error: error.message || 'Đăng nhập thất bại'
          })
          throw error
        }
      },

      logout: async () => {
        try {
          await AuthService.logout()
        } catch (error) {
          console.error('Logout error:', error)
        } finally {
          // Xóa token và user info
          removeToken()
          
          set({
            user: null,
            isAuthenticated: false,
            error: null
          })
        }
      },

      refreshToken: async () => {
        try {
          const authResponse = await AuthService.refreshToken()
          
          setToken(authResponse.token)
          setUserProfile(authResponse.user)
          
          set({
            user: authResponse.user,
            isAuthenticated: true,
            error: null
          })
        } catch (error: any) {
          // Refresh token thất bại, logout user
          get().logout()
          throw error
        }
      },

      updateProfile: async (userData: Partial<User>) => {
        try {
          set({ isLoading: true, error: null })
          
          const updatedUser = await AuthService.updateProfile(userData)
          setUserProfile(updatedUser)
          
          set({
            user: updatedUser,
            isLoading: false,
            error: null
          })
        } catch (error: any) {
          set({
            isLoading: false,
            error: error.message || 'Cập nhật profile thất bại'
          })
          throw error
        }
      },

      clearError: () => {
        set({ error: null })
      },

      checkAuthStatus: async () => {
        const storedUser = getUserProfile()
        if (!storedUser) {
          set({ isAuthenticated: false, user: null })
          return
        }

        try {
          const currentUser = await AuthService.getProfile()
          setUserProfile(currentUser)
          
          set({
            user: currentUser,
            isAuthenticated: true,
            error: null
          })
        } catch (error) {
          // Token không hợp lệ, logout user
          get().logout()
        }
      }
    }),
    {
      name: 'auth-storage',
      partialize: (state) => ({
        user: state.user,
        isAuthenticated: state.isAuthenticated
      })
    }
  )
)

export default useAuthStore