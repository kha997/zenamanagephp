/**
 * useRealtimeNotifications Hook
 * 
 * Round 257: Real-time notification handling
 * Round 258: Toast notifications on new notifications
 * 
 * Subscribes to real-time notification channel and updates the notification store
 * when new notifications are broadcast from the backend.
 * 
 * Channel: tenant.{tenantId}.user.{userId}.notifications (PRIVATE)
 * Event: .notification.created
 * 
 * Features:
 * - Auto-subscribes when user is authenticated
 * - Auto-unsubscribes on logout or tenant change
 * - Handles duplicate notifications (id-based deduplication)
 * - Updates notification store and unread count
 * - Shows toast notification for new unread notifications
 */

import { useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { getEcho, disconnectEcho } from '../lib/realtime/echo';
import { useAuthStore } from '../features/auth/store';
import { useNotificationsStore } from '../store/notifications';
import { useToast } from '../shared/ui/toast';
import { resolveNotificationRoute } from '../lib/notifications/navigation';
import { getNotificationIcon } from '../lib/notifications/icons';
import { notificationsApi } from '../features/app/api';
import type { Notification } from '../features/app/api';

/**
 * Notification payload from Laravel broadcasting
 * Matches the backend event payload structure
 */
interface NotificationCreatedPayload {
  id: string;
  tenant_id: string;
  user_id: string;
  module: string;
  type: string;
  title: string;
  message: string;
  entity_type: string | null;
  entity_id: string | null;
  metadata: Record<string, any>;
  is_read: boolean;
  created_at: string; // ISO8601
}

/**
 * Hook to subscribe to real-time notifications
 * 
 * @returns Object with connection status (for debugging)
 */
export function useRealtimeNotifications() {
  const { user, selectedTenantId, isAuthenticated } = useAuthStore();
  const addNotification = useNotificationsStore((state) => state.addNotification);
  const { showToast } = useToast();
  const navigate = useNavigate();
  
  // Track current channel name to handle cleanup properly
  const channelNameRef = useRef<string | null>(null);
  const echoRef = useRef<ReturnType<typeof getEcho> | null>(null);

  useEffect(() => {
    // Only subscribe if user is authenticated and has tenant/user IDs
    if (!isAuthenticated || !user || !selectedTenantId || !user.id) {
      // Cleanup if we were previously subscribed
      if (channelNameRef.current) {
        const echo = echoRef.current;
        if (echo) {
          try {
            echo.leave(channelNameRef.current);
          } catch (error) {
            console.error('[useRealtimeNotifications] Error leaving channel:', error);
          }
        }
        channelNameRef.current = null;
        echoRef.current = null;
      }
      return;
    }

    // Get Echo instance
    const echo = getEcho();
    if (!echo) {
      console.warn('[useRealtimeNotifications] Echo not available. Real-time notifications disabled.');
      return;
    }

    echoRef.current = echo;

    // Build channel name: tenant.{tenantId}.user.{userId}.notifications
    // Note: Laravel Echo automatically adds 'private-' prefix for private channels
    const tenantId = String(selectedTenantId);
    const userId = String(user.id);
    const channelName = `tenant.${tenantId}.user.${userId}.notifications`;
    channelNameRef.current = channelName;

    try {
      // Subscribe to private channel
      const channel = echo.private(channelName);

      // Listen for notification.created event
      // Note: Laravel Echo automatically prefixes with a dot for private channels
      channel.listen('.notification.created', (payload: NotificationCreatedPayload) => {
        console.log('[useRealtimeNotifications] Received notification:', payload);

        // Convert payload to Notification type expected by store
        // The store expects the API Notification format
        const notification: Notification = {
          id: payload.id,
          tenant_id: payload.tenant_id,
          user_id: payload.user_id,
          module: payload.module as Notification['module'],
          type: payload.type,
          title: payload.title,
          message: payload.message,
          entity_type: payload.entity_type,
          entity_id: payload.entity_id,
          is_read: payload.is_read,
          metadata: payload.metadata,
          created_at: payload.created_at,
          updated_at: payload.created_at, // Use created_at as fallback for updated_at
        };

        // Add notification to store
        // Store handles deduplication, prepending, and unread count increment
        addNotification(notification);

        // Round 258: Show toast for new unread notifications
        if (!notification.is_read) {
          // Resolve route for navigation
          const route = resolveNotificationRoute(notification);
          
          // Get icon for the notification module
          const icon = getNotificationIcon(notification.module);
          
          // Show toast with navigation capability
          showToast({
            title: notification.title,
            message: notification.message || undefined,
            variant: 'info',
            icon: icon,
            onClick: route
              ? () => {
                  // Navigate to the resolved route
                  navigate(route.path);
                  // Mark notification as read when clicked
                  notificationsApi.markNotificationRead(notification.id).catch((error) => {
                    console.error('[useRealtimeNotifications] Failed to mark notification as read:', error);
                  });
                }
              : undefined,
            duration: 6000, // 6 seconds default
          });
        }
      });

      // Handle subscription success
      channel.subscribed(() => {
        console.log('[useRealtimeNotifications] Subscribed to channel:', channelName);
      });

      // Handle subscription errors
      channel.error((error: any) => {
        console.error('[useRealtimeNotifications] Channel subscription error:', error);
      });

      // Cleanup function
      return () => {
        try {
          console.log('[useRealtimeNotifications] Unsubscribing from channel:', channelName);
          // Echo.leave() expects the channel name without 'private-' prefix
          echo.leave(channelName);
          channelNameRef.current = null;
          echoRef.current = null;
        } catch (error) {
          console.error('[useRealtimeNotifications] Error during cleanup:', error);
        }
      };
    } catch (error) {
      console.error('[useRealtimeNotifications] Failed to subscribe to channel:', error);
      channelNameRef.current = null;
      echoRef.current = null;
    }
  }, [isAuthenticated, user, selectedTenantId, addNotification, showToast, navigate]);

  // Cleanup on unmount or logout
  useEffect(() => {
    return () => {
      if (channelNameRef.current && echoRef.current) {
        try {
          echoRef.current.leave(channelNameRef.current);
        } catch (error) {
          console.error('[useRealtimeNotifications] Cleanup error:', error);
        }
      }
    };
  }, []);

  // Return subscription status
  // Note: channelNameRef is set asynchronously in useEffect, so we check if channel exists
  const isSubscribed = isAuthenticated && !!user && !!selectedTenantId && !!channelNameRef.current;
  
  return {
    isSubscribed,
  };
}
