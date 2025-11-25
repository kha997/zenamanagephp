import { create } from 'zustand'
import { Notification } from '../lib/types'
import { apiClient } from '../shared/api/client'
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
      const response = await apiClient.get(
        API_ENDPOINTS.NOTIFICATIONS.LIST
      )
      
      // apiClient.get() returns axios response, so response.data is the API response
      // API response format: { success: true, data: { items: [], unread_count: 0 } } (stub)
      // or { success: true, data: [] } (real API)
      const apiResponse = response.data
      
      console.log('Notification API Response:', apiResponse)
      
      let notifications: Notification[] = []
      let unreadCount = 0
      
      if (apiResponse) {
        // Handle stub format: { success: true, data: { items: [], unread_count: 0 } }
        if (apiResponse.data && typeof apiResponse.data === 'object' && !Array.isArray(apiResponse.data)) {
          if (Array.isArray(apiResponse.data.items)) {
            notifications = apiResponse.data.items
            unreadCount = apiResponse.data.unread_count ?? notifications.filter(n => !n.read_at).length
            console.log('Parsed notifications (stub format):', { notifications, unreadCount })
          }
        }
        // Handle real format: { success: true, data: [] }
        else if (Array.isArray(apiResponse.data)) {
          notifications = apiResponse.data
          unreadCount = notifications.filter(n => !n.read_at).length
          console.log('Parsed notifications (array format):', { notifications, unreadCount })
        }
        // Fallback: if data is directly an array
        else if (Array.isArray(apiResponse)) {
          notifications = apiResponse
          unreadCount = notifications.filter(n => !n.read_at).length
          console.log('Parsed notifications (direct array):', { notifications, unreadCount })
        } else {
          console.warn('Unexpected API response format:', apiResponse)
        }
      }
      
      set({
        notifications,
        unreadCount,
        isLoading: false,
      })
    } catch (error: any) {
      console.error('Failed to fetch notifications:', error)
      set({
        isLoading: false,
        error: error.message || 'Không thể tải thông báo',
        notifications: [],
        unreadCount: 0,
      })
    }
  },

  /**
   * Đánh dấu notification đã đọc
   */
  markAsRead: async (id: string) => {
    try {
      await apiClient.put(API_ENDPOINTS.NOTIFICATIONS.MARK_READ(id))
      
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
      console.error('Failed to mark notification as read:', error)
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
      await apiClient.put(API_ENDPOINTS.NOTIFICATIONS.MARK_ALL_READ)
      
      set((state) => ({
        notifications: state.notifications.map(n => ({
          ...n,
          read_at: n.read_at || new Date().toISOString(),
        })),
        unreadCount: 0,
      }))
    } catch (error: any) {
      console.error('Failed to mark all notifications as read:', error)
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