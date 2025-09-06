import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import { ThemeMode, Language } from '../lib/types'
import { STORAGE_KEYS } from '../lib/constants'

/**
 * Interface cho UI Store State
 */
interface UIState {
  // State
  theme: ThemeMode
  language: Language
  sidebarCollapsed: boolean
  isLoading: boolean
  
  // Actions
  setTheme: (theme: ThemeMode) => void
  setLanguage: (language: Language) => void
  toggleSidebar: () => void
  setSidebarCollapsed: (collapsed: boolean) => void
  setLoading: (loading: boolean) => void
}

/**
 * Zustand store cho UI state management
 * Sử dụng persist để lưu preferences vào localStorage
 */
export const useUIStore = create<UIState>()(
  persist(
    (set) => ({
      // Initial state
      theme: 'system',
      language: 'vi',
      sidebarCollapsed: false,
      isLoading: false,

      /**
       * Set theme mode
       */
      setTheme: (theme: ThemeMode) => {
        set({ theme })
        
        // Apply theme to document
        if (theme === 'dark') {
          document.documentElement.classList.add('dark')
        } else if (theme === 'light') {
          document.documentElement.classList.remove('dark')
        } else {
          // System theme
          const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
          if (prefersDark) {
            document.documentElement.classList.add('dark')
          } else {
            document.documentElement.classList.remove('dark')
          }
        }
      },

      /**
       * Set language
       */
      setLanguage: (language: Language) => {
        set({ language })
        
        // Update document lang attribute
        document.documentElement.lang = language
      },

      /**
       * Toggle sidebar collapsed state
       */
      toggleSidebar: () => {
        set((state) => ({ sidebarCollapsed: !state.sidebarCollapsed }))
      },

      /**
       * Set sidebar collapsed state
       */
      setSidebarCollapsed: (collapsed: boolean) => {
        set({ sidebarCollapsed: collapsed })
      },

      /**
       * Set global loading state
       */
      setLoading: (loading: boolean) => {
        set({ isLoading: loading })
      },
    }),
    {
      name: 'zena-ui-preferences',
      partialize: (state) => ({
        theme: state.theme,
        language: state.language,
        sidebarCollapsed: state.sidebarCollapsed,
      }),
    }
  )
)