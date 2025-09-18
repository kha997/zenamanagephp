import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import { User } from '../services/authService'
import { authService } from '../services/authService'
import { ApiError } from '../services/api'
import toast from 'react-hot-toast'

interface AuthState {
  user: User | null
  isLoading: boolean
  isAuthenticated: boolean
  login: (email: string, password: string) => Promise<void>
  register: (data: any) => Promise<void>
  logout: () => void
  checkAuth: () => Promise<void>
  updateUser: (user: User) => void
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      user: null,
      isLoading: false,
      isAuthenticated: false,

      login: async (email: string, password: string) => {
        set({ isLoading: true })
        try {
          const response = await authService.login({ email, password })
          set({
            user: response.user,
            isAuthenticated: true,
            isLoading: false,
          })
          toast.success('Login successful!')
        } catch (error) {
          set({ isLoading: false })
          const errorMessage = error instanceof ApiError ? error.message : 'Login failed'
          toast.error(errorMessage)
          throw error
        }
      },

      register: async (data: any) => {
        set({ isLoading: true })
        try {
          const response = await authService.register(data)
          set({
            user: response.user,
            isAuthenticated: true,
            isLoading: false,
          })
          toast.success('Registration successful!')
        } catch (error) {
          set({ isLoading: false })
          const errorMessage = error instanceof ApiError ? error.message : 'Registration failed'
          toast.error(errorMessage)
          throw error
        }
      },

      logout: async () => {
        try {
          await authService.logout()
        } catch (error) {
          console.error('Logout error:', error)
        } finally {
          set({
            user: null,
            isAuthenticated: false,
            isLoading: false,
          })
          toast.success('Logged out successfully!')
        }
      },

      checkAuth: async () => {
        if (!authService.isAuthenticated()) {
          set({ isLoading: false })
          return
        }

        set({ isLoading: true })
        try {
          const user = await authService.getCurrentUser()
          set({
            user,
            isAuthenticated: true,
            isLoading: false,
          })
        } catch (error) {
          console.error('Auth check error:', error)
          authService.clearStoredData()
          set({
            user: null,
            isAuthenticated: false,
            isLoading: false,
          })
        }
      },

      updateUser: (user: User) => {
        set({ user })
      },
    }),
    {
      name: 'auth-storage',
      partialize: (state) => ({
        user: state.user,
        isAuthenticated: state.isAuthenticated,
      }),
    }
  )
)
