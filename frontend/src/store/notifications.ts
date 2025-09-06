import { create } from 'zustand'
import { Notification } from '../lib/types'
import { apiClient } from '../lib/api/client'
import { API_ENDPOINTS } from '../lib/constants'

/**
 * Interface cho Notifications Store State
 */
interface NotificationsState {
  // State
  notifications: Notification[]
  unreadCount: number
  isLoading: boolean
  error: string | null
  
  // Actions
  fetchNotifications: () => Promise<void>
  markAsRead: (id: string) => Promise<void>
  markAllAsRead: () => Promise<void>
  addNotification: (notification: Notification) => void
  removeNotification: (id: string) => void
  clearError: () => void
}

/**
 * Zustand store cho notifications management
 */
export const useNotificationsStore = create<NotificationsState>((set, get) => ({
  // Initial state
  notifications: [],
  unreadCount: 0,
  isLoading: false,
  error: null,

  /**
   * Lấy danh sách notifications
   */
  fetchNotifications: async () => {
    set({ isLoading: true, error: null })
    
    try {
      const response = await apiClient.get<Notification[]>(
        API_ENDPOINTS.NOTIFICATIONS.LIST
      )
      
      if (response.status === 'success' && response.data) {
        const notifications = response.data
        const unreadCount = notifications.filter(n => !n.read_at).length
        
        set({
          notifications,
          unreadCount,
          isLoading: false,
        })
      }
    } catch (error: any) {
      set({
        isLoading: false,
        error: error.message || 'Không thể tải thông báo',
      })
    }
  },

  /**
   * Đánh dấu notification đã đọc
   */
  markAsRead: async (id: string) => {
    try {
      await apiClient.post(API_ENDPOINTS.NOTIFICATIONS.MARK_READ(id))
      
      set((state) => {
        const updatedNotifications = state.notifications.map(n => 
          n.id === id ? { ...n, read_at: new Date().toISOString() } : n
        )
        const unreadCount = updatedNotifications.filter(n => !n.read_at).length
        
        return {
          notifications: updatedNotifications,
          unreadCount,
        }
      })
    } catch (error: any) {
      set({
        error: error.message || 'Không thể đánh dấu đã đọc',
      })
    }
  },

  /**
   * Đánh dấu tất cả notifications đã đọc
   */
  markAllAsRead: async () => {
    try {
      await apiClient.post(API_ENDPOINTS.NOTIFICATIONS.MARK_ALL_READ)
      
      set((state) => ({
        notifications: state.notifications.map(n => ({
          ...n,
          read_at: n.read_at || new Date().toISOString(),
        })),
        unreadCount: 0,
      }))
    } catch (error: any) {
      set({
        error: error.message || 'Không thể đánh dấu tất cả đã đọc',
      })
    }
  },

  /**
   * Thêm notification mới (từ realtime)
   */
  addNotification: (notification: Notification) => {
    set((state) => ({
      notifications: [notification, ...state.notifications],
      unreadCount: state.unreadCount + 1,
    }))
  },

  /**
   * Xóa notification
   */
  removeNotification: (id: string) => {
    set((state) => {
      const notification = state.notifications.find(n => n.id === id)
      const wasUnread = notification && !notification.read_at
      
      return {
        notifications: state.notifications.filter(n => n.id !== id),
        unreadCount: wasUnread ? state.unreadCount - 1 : state.unreadCount,
      }
    })
  },

  /**
   * Clear error
   */
  clearError: () => {
    set({ error: null })
  },
}))