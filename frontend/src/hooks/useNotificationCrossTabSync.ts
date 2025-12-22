/**
 * useNotificationCrossTabSync Hook
 * 
 * Round 259: Cross-tab synchronization for notifications
 * 
 * Listens for cross-tab notification sync messages and applies them to the local store.
 * Only processes messages that match the current user and tenant context.
 * 
 * Features:
 * - Auto-subscribes when user is authenticated
 * - Auto-unsubscribes on logout or tenant change
 * - Filters messages by tenantId and userId to prevent cross-user/tenant sync
 * - Applies state changes without re-broadcasting (prevents infinite loops)
 */

import { useEffect, useRef } from 'react';
import { createNotificationChannel } from '../lib/crossTab/notificationChannel';
import { useAuthStore } from '../features/auth/store';
import { useNotificationsStore } from '../store/notifications';
import type { NotificationSyncMessage } from '../lib/crossTab/notificationChannel';

/**
 * Hook to listen for cross-tab notification sync messages
 * 
 * @returns Object with sync status (for debugging)
 */
export function useNotificationCrossTabSync() {
  const { user, selectedTenantId, isAuthenticated } = useAuthStore();
  const {
    applyNotificationFromSync,
    applyNotificationReadFromSync,
    applyBulkReadFromSync,
    setSyncContext,
  } = useNotificationsStore();
  
  // Track unsubscribe function
  const unsubscribeRef = useRef<(() => void) | null>(null);
  const channelRef = useRef<ReturnType<typeof createNotificationChannel> | null>(null);

  // Update sync context when user/tenant changes
  useEffect(() => {
    if (isAuthenticated && user && selectedTenantId) {
      const tenantId = String(selectedTenantId);
      const userId = String(user.id);
      
      setSyncContext({
        tenantId,
        userId,
      });
    } else {
      setSyncContext({
        tenantId: null,
        userId: null,
      });
    }
  }, [isAuthenticated, user, selectedTenantId, setSyncContext]);

  // Subscribe to cross-tab messages
  useEffect(() => {
    // Only subscribe if user is authenticated and has tenant/user IDs
    if (!isAuthenticated || !user || !selectedTenantId || !user.id) {
      // Cleanup if we were previously subscribed
      if (unsubscribeRef.current) {
        unsubscribeRef.current();
        unsubscribeRef.current = null;
      }
      if (channelRef.current) {
        channelRef.current = null;
      }
      return;
    }

    const tenantId = String(selectedTenantId);
    const userId = String(user.id);

    // Get or create channel
    const channel = createNotificationChannel();
    channelRef.current = channel;

    // Subscribe to messages
    const unsubscribe = channel.subscribe((msg: NotificationSyncMessage) => {
      // Validate message structure
      if (!msg || !msg.type || !msg.payload) {
        console.warn('[useNotificationCrossTabSync] Invalid message format:', msg);
        return;
      }

      // Filter by tenantId and userId to prevent cross-user/tenant sync
      if (msg.payload.tenantId !== tenantId || msg.payload.userId !== userId) {
        // Message is for a different user/tenant, ignore it
        return;
      }

      // Apply the state change based on message type
      try {
        switch (msg.type) {
          case 'NEW_NOTIFICATION':
            // Apply notification from sync (will not re-broadcast)
            applyNotificationFromSync(msg.payload.notification);
            console.log('[useNotificationCrossTabSync] Applied NEW_NOTIFICATION from sync:', msg.payload.notification.id);
            break;

          case 'NOTIFICATION_READ':
            // Apply read status from sync (will not re-broadcast)
            applyNotificationReadFromSync(msg.payload.notificationId);
            console.log('[useNotificationCrossTabSync] Applied NOTIFICATION_READ from sync:', msg.payload.notificationId);
            break;

          case 'NOTIFICATIONS_BULK_READ':
            // Apply bulk read from sync (will not re-broadcast)
            applyBulkReadFromSync(msg.payload.notificationIds);
            console.log('[useNotificationCrossTabSync] Applied NOTIFICATIONS_BULK_READ from sync');
            break;

          default:
            console.warn('[useNotificationCrossTabSync] Unknown message type:', msg.type);
        }
      } catch (error) {
        console.error('[useNotificationCrossTabSync] Error applying sync message:', error);
      }
    });

    unsubscribeRef.current = unsubscribe;

    // Cleanup function
    return () => {
      if (unsubscribeRef.current) {
        unsubscribeRef.current();
        unsubscribeRef.current = null;
      }
      channelRef.current = null;
    };
  }, [isAuthenticated, user, selectedTenantId, applyNotificationFromSync, applyNotificationReadFromSync, applyBulkReadFromSync]);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      if (unsubscribeRef.current) {
        unsubscribeRef.current();
        unsubscribeRef.current = null;
      }
      channelRef.current = null;
    };
  }, []);

  // Return sync status
  const isSyncing = isAuthenticated && !!user && !!selectedTenantId && !!unsubscribeRef.current;
  
  return {
    isSyncing,
  };
}
