import { create } from 'zustand'
import { Notification } from '../lib/types'
import type { Notification as ApiNotification } from '../features/app/api'
import { apiClient } from '../lib/api/client'
import { API_ENDPOINTS } from '../lib/constants'
import { createNotificationChannel } from '../lib/crossTab/notificationChannel'

/**
 * Interface cho Notifications Store State
 * 
 * Round 257: Updated to support both legacy Notification type and API Notification type
 * Round 259: Added cross-tab sync support
 */
interface NotificationsState {
  // State
  notifications: Notification[]
  unreadCount: number
  isLoading: boolean
  error: string | null
  
  // Cross-tab sync context (Round 259)
  syncContext: {
    tenantId: string | null
    userId: string | null
  }
  
  // Actions
  fetchNotifications: () => Promise<void>
  markAsRead: (id: string, fromSync?: boolean) => Promise<void>
  markAllAsRead: (fromSync?: boolean) => Promise<void>
  addNotification: (notification: Notification | ApiNotification, fromSync?: boolean) => void
  incrementUnread: () => void
  removeNotification: (id: string) => void
  clearError: () => void
  
  // Cross-tab sync actions (Round 259)
  setSyncContext: (context: { tenantId: string | null; userId: string | null }) => void
  applyNotificationFromSync: (notification: ApiNotification) => void
  applyNotificationReadFromSync: (notificationId: string) => void
  applyBulkReadFromSync: (notificationIds: string[] | null) => void
}

/**
 * Get cross-tab channel instance (lazy initialization)
 */
let notificationChannel: ReturnType<typeof createNotificationChannel> | null = null;

function getChannel() {
  if (!notificationChannel) {
    notificationChannel = createNotificationChannel();
  }
  return notificationChannel;
}

/**
 * Broadcast a message to other tabs (only if context is set and not from sync)
 */
function broadcastMessage(
  message: Parameters<ReturnType<typeof createNotificationChannel>['postMessage']>[0],
  context: { tenantId: string | null; userId: string | null },
  fromSync: boolean
) {
  // Don't broadcast if this change came from sync (avoid loops)
  if (fromSync) {
    return;
  }
  
  // Don't broadcast if context is not set
  if (!context.tenantId || !context.userId) {
    return;
  }
  
  try {
    getChannel().postMessage(message);
  } catch (error) {
    console.error('[NotificationsStore] Error broadcasting message:', error);
  }
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
  syncContext: {
    tenantId: null,
    userId: null,
  },

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
   * 
   * Round 259: Added fromSync parameter to prevent broadcast loops
   */
  markAsRead: async (id: string, fromSync = false) => {
    try {
      // Only call API if not from sync (sync events are already applied on the source tab)
      if (!fromSync) {
        await apiClient.post(API_ENDPOINTS.NOTIFICATIONS.MARK_READ(id))
      }
      
      set((state) => {
        // Check if notification exists and is already read
        const notification = state.notifications.find(n => n.id === id);
        if (!notification) {
          return state; // No change
        }
        
        // If already read, no need to update or broadcast
        if (notification.read_at) {
          return state; // No change
        }
        
        const updatedNotifications = state.notifications.map(n => 
          n.id === id ? { ...n, read_at: new Date().toISOString() } : n
        )
        const unreadCount = updatedNotifications.filter(n => !n.read_at).length
        
        const newState = {
          notifications: updatedNotifications,
          unreadCount,
        };
        
        // Broadcast to other tabs (only if state changed and not from sync)
        broadcastMessage(
          {
            type: 'NOTIFICATION_READ',
            payload: {
              notificationId: id,
              tenantId: state.syncContext.tenantId || '',
              userId: state.syncContext.userId || '',
            },
          },
          state.syncContext,
          fromSync
        );
        
        return newState;
      })
    } catch (error: any) {
      set({
        error: error.message || 'Không thể đánh dấu đã đọc',
      })
    }
  },

  /**
   * Đánh dấu tất cả notifications đã đọc
   * 
   * Round 259: Added fromSync parameter to prevent broadcast loops
   */
  markAllAsRead: async (fromSync = false) => {
    try {
      // Only call API if not from sync
      if (!fromSync) {
        await apiClient.post(API_ENDPOINTS.NOTIFICATIONS.MARK_ALL_READ)
      }
      
      set((state) => {
        // Check if all are already read
        const allRead = state.notifications.every(n => n.read_at);
        if (allRead && state.unreadCount === 0) {
          return state; // No change
        }
        
        const updatedNotifications = state.notifications.map(n => ({
          ...n,
          read_at: n.read_at || new Date().toISOString(),
        }));
        
        const newState = {
          notifications: updatedNotifications,
          unreadCount: 0,
        };
        
        // Broadcast to other tabs (only if state changed and not from sync)
        broadcastMessage(
          {
            type: 'NOTIFICATIONS_BULK_READ',
            payload: {
              notificationIds: null, // null indicates "mark all read"
              tenantId: state.syncContext.tenantId || '',
              userId: state.syncContext.userId || '',
            },
          },
          state.syncContext,
          fromSync
        );
        
        return newState;
      })
    } catch (error: any) {
      set({
        error: error.message || 'Không thể đánh dấu tất cả đã đọc',
      })
    }
  },

  /**
   * Thêm notification mới (từ realtime)
   * 
   * Round 257: Updated to handle API Notification format and deduplication
   * Round 259: Added fromSync parameter to prevent broadcast loops
   * 
   * Deduplication Logic:
   * - Prevents duplicate notifications by ID (ULID from backend)
   * - Works when:
   *   1. User loads notification history via API (fetchNotifications)
   *   2. Same notification arrives via real-time broadcast
   *   3. Same notification arrives via cross-tab sync
   * - If API and realtime return same ID (ULID), dedupe prevents duplicate
   * - This ensures smooth integration between pull API and push real-time
   */
  addNotification: (notification: Notification | ApiNotification, fromSync = false) => {
    set((state) => {
      // Check if notification already exists (deduplication by id)
      // Backend uses ULID for notification IDs, ensuring uniqueness
      const exists = state.notifications.some(n => n.id === notification.id);
      if (exists) {
        console.warn('[NotificationsStore] Duplicate notification ignored (already in store):', notification.id);
        return state;
      }

      // Convert API Notification format to legacy format if needed
      let normalizedNotification: Notification;
      let apiNotification: ApiNotification | null = null;
      
      if ('is_read' in notification) {
        // This is an API Notification - convert to legacy format
        const apiNotif = notification as ApiNotification;
        apiNotification = apiNotif;
        normalizedNotification = {
          id: apiNotif.id,
          user_id: apiNotif.user_id,
          priority: 'normal' as const, // Default priority
          title: apiNotif.title,
          body: apiNotif.message || '', // Map message to body
          link_url: apiNotif.entity_type && apiNotif.entity_id 
            ? `/${apiNotif.entity_type}/${apiNotif.entity_id}` 
            : undefined,
          channel: 'inapp' as const, // Default channel
          read_at: apiNotif.is_read ? new Date().toISOString() : undefined,
          type: apiNotif.type,
          created_at: apiNotif.created_at,
          updated_at: apiNotif.updated_at || apiNotif.created_at,
        };
      } else {
        // This is already a legacy Notification
        normalizedNotification = notification as Notification;
      }

      // Determine if notification is unread
      const isUnread = !normalizedNotification.read_at;

      const newState = {
        notifications: [normalizedNotification, ...state.notifications],
        unreadCount: isUnread ? state.unreadCount + 1 : state.unreadCount,
      };
      
      // Broadcast to other tabs (only if not from sync and we have API notification format)
      if (apiNotification) {
        broadcastMessage(
          {
            type: 'NEW_NOTIFICATION',
            payload: {
              notification: apiNotification,
              tenantId: state.syncContext.tenantId || '',
              userId: state.syncContext.userId || '',
            },
          },
          state.syncContext,
          fromSync
        );
      }
      
      return newState;
    });
  },

  /**
   * Increment unread count
   * 
   * Round 257: Added for real-time notification handling
   */
  incrementUnread: () => {
    set((state) => ({
      unreadCount: state.unreadCount + 1,
    }));
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
  
  /**
   * Set sync context (tenantId and userId) for cross-tab broadcasting
   * 
   * Round 259: Cross-tab sync support
   */
  setSyncContext: (context: { tenantId: string | null; userId: string | null }) => {
    set({ syncContext: context });
  },
  
  /**
   * Apply notification from cross-tab sync (internal use)
   * 
   * Round 259: Cross-tab sync support
   */
  applyNotificationFromSync: (notification: ApiNotification) => {
    get().addNotification(notification, true); // fromSync = true
  },
  
  /**
   * Apply notification read from cross-tab sync (internal use)
   * 
   * Round 259: Cross-tab sync support
   */
  applyNotificationReadFromSync: (notificationId: string) => {
    get().markAsRead(notificationId, true); // fromSync = true
  },
  
  /**
   * Apply bulk read from cross-tab sync (internal use)
   * 
   * Round 259: Cross-tab sync support
   */
  applyBulkReadFromSync: (notificationIds: string[] | null) => {
    if (notificationIds === null) {
      // Mark all as read
      get().markAllAsRead(true); // fromSync = true
    } else {
      // Mark specific notifications as read
      // Note: We don't have a bulk mark API, so we'll mark them individually
      // But we'll do it synchronously to avoid multiple broadcasts
      notificationIds.forEach((id) => {
        get().markAsRead(id, true); // fromSync = true
      });
    }
  },
}))