/**
 * Auto-Read Notifications Hook
 * 
 * Round 260: Smart auto-read for entity pages
 * 
 * Automatically marks notifications as read when user views an entity page for a specified duration.
 */

import { useEffect, useRef } from 'react';
import { useNotificationsStore } from '../store/notifications';
import { notificationsApi } from '../features/app/api';
import { useAuthStore } from '../features/auth/store';
import { useNotifications } from '../features/app/hooks';

export type AutoReadConfig = {
  module: string;
  entityType: string;
  entityId: string;
  delayMs?: number; // default 5000
};

/**
 * Hook to automatically mark notifications as read when viewing an entity page
 * 
 * @param config - Configuration for auto-read behavior
 * 
 * @example
 * ```tsx
 * useAutoReadNotificationsForEntity({
 *   module: 'tasks',
 *   entityType: 'task',
 *   entityId: taskId,
 *   delayMs: 5000, // optional, defaults to 5000
 * });
 * ```
 */
export function useAutoReadNotificationsForEntity(config: AutoReadConfig) {
  const { module, entityType, entityId, delayMs = 5000 } = config;
  const { isAuthenticated, user, selectedTenantId } = useAuthStore();
  const { markAsRead } = useNotificationsStore();
  // Use React Query to get notifications (has module/entity_type/entity_id fields)
  const { data: notificationsData } = useNotifications({ per_page: 100 });
  const notifications = notificationsData?.data || [];
  const timerRef = useRef<NodeJS.Timeout | null>(null);
  const processedIdsRef = useRef<Set<string>>(new Set());

  useEffect(() => {
    // Only operate when authenticated and context exists
    // Use user.tenant_id as fallback if selectedTenantId is not set
    const tenantId = selectedTenantId || user?.tenant_id;
    if (!isAuthenticated || !user || !tenantId) {
      return;
    }

    // Clear any existing timer
    if (timerRef.current) {
      clearTimeout(timerRef.current);
      timerRef.current = null;
    }

    // Reset processed IDs when entity changes
    processedIdsRef.current.clear();

    // Start timer
    timerRef.current = setTimeout(() => {
      // Find matching unread notifications
      const matchingNotifications = notifications.filter((n) => {
        // Skip if already processed in this session
        if (processedIdsRef.current.has(n.id)) {
          return false;
        }

        // Match module, entity_type, and entity_id
        // Note: API Notification type has is_read (boolean), not read_at
        const matchesModule = n.module === module;
        const matchesEntityType = n.entity_type === entityType;
        const matchesEntityId = n.entity_id === entityId;
        const isUnread = !n.is_read;

        return matchesModule && matchesEntityType && matchesEntityId && isUnread;
      });

      // Mark each matching notification as read
      if (matchingNotifications.length > 0) {
        matchingNotifications.forEach(async (notification) => {
          // Mark as processed to avoid duplicate API calls
          processedIdsRef.current.add(notification.id);

          try {
            // Update store (will trigger cross-tab sync)
            await markAsRead(notification.id, false);
            // Call API to persist on backend
            await notificationsApi.markNotificationRead(notification.id);
          } catch (error) {
            console.error(
              `[useAutoReadNotificationsForEntity] Failed to mark notification ${notification.id} as read:`,
              error
            );
            // Remove from processed set on error so it can be retried
            processedIdsRef.current.delete(notification.id);
          }
        });
      }
    }, delayMs);

    // Cleanup on unmount or when dependencies change
    return () => {
      if (timerRef.current) {
        clearTimeout(timerRef.current);
        timerRef.current = null;
      }
    };
  }, [isAuthenticated, user, selectedTenantId, module, entityType, entityId, delayMs, notificationsData, markAsRead]);
}
