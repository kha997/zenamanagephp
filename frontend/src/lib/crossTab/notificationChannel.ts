/**
 * Cross-tab Notification Sync Channel
 * 
 * Round 259: Cross-tab synchronization for notifications
 * 
 * Provides a BroadcastChannel abstraction for synchronizing notification state
 * across multiple browser tabs/windows for the same user and tenant.
 * 
 * Uses BroadcastChannel API when available, with SSR-safe fallback.
 */

import type { Notification } from '../../features/app/api';

/**
 * Message types for cross-tab notification synchronization
 */
export type NotificationSyncMessage =
  | {
      type: 'NEW_NOTIFICATION';
      payload: {
        notification: Notification;
        tenantId: string;
        userId: string;
      };
    }
  | {
      type: 'NOTIFICATION_READ';
      payload: {
        notificationId: string;
        tenantId: string;
        userId: string;
      };
    }
  | {
      type: 'NOTIFICATIONS_BULK_READ';
      payload: {
        notificationIds: string[] | null; // null indicates "mark all read"
        tenantId: string;
        userId: string;
      };
    };

/**
 * Cross-tab notification channel interface
 */
export interface NotificationCrossTabChannel {
  /**
   * Post a message to other tabs
   */
  postMessage(msg: NotificationSyncMessage): void;
  
  /**
   * Subscribe to messages from other tabs
   * @returns Unsubscribe function
   */
  subscribe(handler: (msg: NotificationSyncMessage) => void): () => void;
  
  /**
   * Close the channel (cleanup)
   */
  close(): void;
}

/**
 * BroadcastChannel-based implementation
 */
class BroadcastChannelImpl implements NotificationCrossTabChannel {
  private channel: BroadcastChannel;
  private handlers: Set<(msg: NotificationSyncMessage) => void> = new Set();

  constructor(channelName: string) {
    this.channel = new BroadcastChannel(channelName);
    
    // Listen for messages from other tabs
    this.channel.addEventListener('message', (event: MessageEvent) => {
      try {
        const msg = event.data as NotificationSyncMessage;
        // Validate message structure
        if (msg && msg.type && msg.payload) {
          // Notify all subscribers
          this.handlers.forEach((handler) => {
            try {
              handler(msg);
            } catch (error) {
              console.error('[NotificationCrossTabChannel] Handler error:', error);
            }
          });
        }
      } catch (error) {
        console.error('[NotificationCrossTabChannel] Error processing message:', error);
      }
    });
  }

  postMessage(msg: NotificationSyncMessage): void {
    try {
      this.channel.postMessage(msg);
    } catch (error) {
      console.error('[NotificationCrossTabChannel] Error posting message:', error);
    }
  }

  subscribe(handler: (msg: NotificationSyncMessage) => void): () => void {
    this.handlers.add(handler);
    
    // Return unsubscribe function
    return () => {
      this.handlers.delete(handler);
    };
  }

  close(): void {
    this.handlers.clear();
    this.channel.close();
  }
}

/**
 * No-op implementation for environments without BroadcastChannel support
 * (SSR, older browsers, etc.)
 */
class NoOpChannelImpl implements NotificationCrossTabChannel {
  postMessage(_msg: NotificationSyncMessage): void {
    // No-op: cross-tab sync not available
  }

  subscribe(_handler: (msg: NotificationSyncMessage) => void): () => void {
    // Return no-op unsubscribe
    return () => {};
  }

  close(): void {
    // No-op
  }
}

/**
 * Channel name for notification synchronization
 */
const CHANNEL_NAME = 'zena-notifications';

/**
 * Singleton channel instance
 * Created lazily on first access
 */
let channelInstance: NotificationCrossTabChannel | null = null;

/**
 * Create or get the notification cross-tab channel
 * 
 * @returns NotificationCrossTabChannel instance
 */
export function createNotificationChannel(): NotificationCrossTabChannel {
  // Return existing instance if available
  if (channelInstance) {
    return channelInstance;
  }

  // SSR safety: only create channel in browser environment
  if (typeof window === 'undefined') {
    channelInstance = new NoOpChannelImpl();
    return channelInstance;
  }

  // Check if BroadcastChannel is available
  if (typeof BroadcastChannel !== 'undefined') {
    channelInstance = new BroadcastChannelImpl(CHANNEL_NAME);
  } else {
    // Fallback to no-op for browsers without BroadcastChannel support
    console.warn('[NotificationCrossTabChannel] BroadcastChannel not available. Cross-tab sync disabled.');
    channelInstance = new NoOpChannelImpl();
  }

  return channelInstance;
}

/**
 * Get the current channel instance (if initialized)
 * 
 * @returns NotificationCrossTabChannel instance or null
 */
export function getNotificationChannel(): NotificationCrossTabChannel | null {
  return channelInstance;
}

/**
 * Close and reset the channel instance (for testing/cleanup)
 */
export function resetNotificationChannel(): void {
  if (channelInstance) {
    channelInstance.close();
    channelInstance = null;
  }
}
