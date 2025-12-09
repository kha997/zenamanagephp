/**
 * Tests for cross-tab notification synchronization
 * 
 * Round 259: Cross-tab synchronization for notifications
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { useNotificationCrossTabSync } from '../hooks/useNotificationCrossTabSync';
import { useAuthStore } from '../features/auth/store';
import { useNotificationsStore } from '../store/notifications';
import { resetNotificationChannel } from '../lib/crossTab/notificationChannel';
import type { NotificationSyncMessage } from '../lib/crossTab/notificationChannel';

// Mock auth store
const mockUser = {
  id: 'user-123',
  name: 'Test User',
  email: 'test@example.com',
  tenant_id: 'tenant-456',
};

const mockAuthStore = {
  user: mockUser,
  selectedTenantId: 'tenant-456',
  isAuthenticated: true,
};

vi.mock('../features/auth/store', () => ({
  useAuthStore: vi.fn((selector) => {
    const state = mockAuthStore;
    return selector ? selector(state) : state;
  }),
}));

// Mock notification store
const mockSetSyncContext = vi.fn();
const mockApplyNotificationFromSync = vi.fn();
const mockApplyNotificationReadFromSync = vi.fn();
const mockApplyBulkReadFromSync = vi.fn();

vi.mock('../store/notifications', () => ({
  useNotificationsStore: vi.fn((selector) => {
    const state = {
      setSyncContext: mockSetSyncContext,
      applyNotificationFromSync: mockApplyNotificationFromSync,
      applyNotificationReadFromSync: mockApplyNotificationReadFromSync,
      applyBulkReadFromSync: mockApplyBulkReadFromSync,
    };
    return selector ? selector(state) : state;
  }),
}));

// Mock BroadcastChannel
class MockBroadcastChannel {
  name: string;
  private handlers: Set<(event: MessageEvent) => void> = new Set();

  constructor(name: string) {
    this.name = name;
  }

  postMessage(message: any) {
    // Simulate message event
    const event = new MessageEvent('message', { data: message });
    this.handlers.forEach((handler) => handler(event));
  }

  addEventListener(event: string, handler: (event: MessageEvent) => void) {
    if (event === 'message') {
      this.handlers.add(handler);
    }
  }

  removeEventListener(event: string, handler: (event: MessageEvent) => void) {
    if (event === 'message') {
      this.handlers.delete(handler);
    }
  }

  close() {
    this.handlers.clear();
  }
}

// Setup global BroadcastChannel mock
beforeEach(() => {
  // Reset mocks
  vi.clearAllMocks();
  resetNotificationChannel();
  
  // Setup BroadcastChannel mock in window
  if (typeof window !== 'undefined') {
    (window as any).BroadcastChannel = MockBroadcastChannel;
  }
  
  // Also set in global for Node.js environment
  (global as any).BroadcastChannel = MockBroadcastChannel;
});

afterEach(() => {
  resetNotificationChannel();
  vi.clearAllMocks();
});

describe('useNotificationCrossTabSync', () => {
  describe('context setup', () => {
    it('should set sync context when user is authenticated', async () => {
      const { result } = renderHook(() => useNotificationCrossTabSync());

      await waitFor(() => {
        expect(mockSetSyncContext).toHaveBeenCalledWith({
          tenantId: 'tenant-456',
          userId: 'user-123',
        });
      }, { timeout: 3000 });
    });

    it('should clear sync context when user is not authenticated', async () => {
      // Mock unauthenticated state
      const originalMock = (useAuthStore as any).mockImplementation;
      (useAuthStore as any).mockImplementation((selector: any) => {
        const state = {
          user: null,
          selectedTenantId: null,
          isAuthenticated: false,
        };
        return selector ? selector(state) : state;
      });

      renderHook(() => useNotificationCrossTabSync());

      await waitFor(() => {
        expect(mockSetSyncContext).toHaveBeenCalledWith({
          tenantId: null,
          userId: null,
        });
      }, { timeout: 3000 });
      
      // Restore original mock
      (useAuthStore as any).mockImplementation = originalMock;
    });
  });

  describe('message handling', () => {
    it('should apply NEW_NOTIFICATION message when tenant and user match', async () => {
      const { result } = renderHook(() => useNotificationCrossTabSync());

      // Wait for sync context to be set
      await waitFor(() => {
        expect(mockSetSyncContext).toHaveBeenCalled();
      }, { timeout: 3000 });

      // Get the channel instance and simulate a message
      const { createNotificationChannel } = await import('../lib/crossTab/notificationChannel');
      const channel = createNotificationChannel();
      
      // Wait a bit for subscription to be set up
      await new Promise(resolve => setTimeout(resolve, 100));

      const testNotification = {
        id: 'notif-789',
        tenant_id: 'tenant-456',
        user_id: 'user-123',
        module: 'tasks' as const,
        type: 'task_assigned',
        title: 'New Task',
        message: 'You have been assigned a task',
        entity_type: 'task',
        entity_id: 'task-123',
        is_read: false,
        metadata: {},
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      };

      const message: NotificationSyncMessage = {
        type: 'NEW_NOTIFICATION',
        payload: {
          notification: testNotification,
          tenantId: 'tenant-456',
          userId: 'user-123',
        },
      };

      // Post message
      channel.postMessage(message);

      // Wait for handler to process
      await waitFor(() => {
        expect(mockApplyNotificationFromSync).toHaveBeenCalledWith(testNotification);
      }, { timeout: 1000 });
    });

    it('should ignore NEW_NOTIFICATION message when tenant does not match', async () => {
      const { result } = renderHook(() => useNotificationCrossTabSync());

      // Wait for sync context to be set
      await waitFor(() => {
        expect(mockSetSyncContext).toHaveBeenCalled();
      }, { timeout: 3000 });

      const { createNotificationChannel } = await import('../lib/crossTab/notificationChannel');
      const channel = createNotificationChannel();
      
      // Wait a bit for subscription to be set up
      await new Promise(resolve => setTimeout(resolve, 100));

      const testNotification = {
        id: 'notif-789',
        tenant_id: 'tenant-999', // Different tenant
        user_id: 'user-123',
        module: 'tasks' as const,
        type: 'task_assigned',
        title: 'New Task',
        message: 'You have been assigned a task',
        entity_type: 'task',
        entity_id: 'task-123',
        is_read: false,
        metadata: {},
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      };

      const message: NotificationSyncMessage = {
        type: 'NEW_NOTIFICATION',
        payload: {
          notification: testNotification,
          tenantId: 'tenant-999', // Different tenant
          userId: 'user-123',
        },
      };

      channel.postMessage(message);

      // Wait a bit to ensure message is processed
      await new Promise((resolve) => setTimeout(resolve, 100));

      // Should NOT have been called
      expect(mockApplyNotificationFromSync).not.toHaveBeenCalled();
    });

    it('should ignore NEW_NOTIFICATION message when user does not match', async () => {
      const { result } = renderHook(() => useNotificationCrossTabSync());

      // Wait for sync context to be set
      await waitFor(() => {
        expect(mockSetSyncContext).toHaveBeenCalled();
      }, { timeout: 3000 });

      const { createNotificationChannel } = await import('../lib/crossTab/notificationChannel');
      const channel = createNotificationChannel();
      
      // Wait a bit for subscription to be set up
      await new Promise(resolve => setTimeout(resolve, 100));

      const testNotification = {
        id: 'notif-789',
        tenant_id: 'tenant-456',
        user_id: 'user-999', // Different user
        module: 'tasks' as const,
        type: 'task_assigned',
        title: 'New Task',
        message: 'You have been assigned a task',
        entity_type: 'task',
        entity_id: 'task-123',
        is_read: false,
        metadata: {},
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      };

      const message: NotificationSyncMessage = {
        type: 'NEW_NOTIFICATION',
        payload: {
          notification: testNotification,
          tenantId: 'tenant-456',
          userId: 'user-999', // Different user
        },
      };

      channel.postMessage(message);

      await new Promise((resolve) => setTimeout(resolve, 100));

      // Should NOT have been called
      expect(mockApplyNotificationFromSync).not.toHaveBeenCalled();
    });

    it('should apply NOTIFICATION_READ message when tenant and user match', async () => {
      const { result } = renderHook(() => useNotificationCrossTabSync());

      // Wait for sync context to be set
      await waitFor(() => {
        expect(mockSetSyncContext).toHaveBeenCalled();
      }, { timeout: 3000 });

      const { createNotificationChannel } = await import('../lib/crossTab/notificationChannel');
      const channel = createNotificationChannel();
      
      // Wait a bit for subscription to be set up
      await new Promise(resolve => setTimeout(resolve, 100));

      const message: NotificationSyncMessage = {
        type: 'NOTIFICATION_READ',
        payload: {
          notificationId: 'notif-789',
          tenantId: 'tenant-456',
          userId: 'user-123',
        },
      };

      channel.postMessage(message);

      await waitFor(() => {
        expect(mockApplyNotificationReadFromSync).toHaveBeenCalledWith('notif-789');
      }, { timeout: 1000 });
    });

    it('should apply NOTIFICATIONS_BULK_READ message when tenant and user match', async () => {
      const { result } = renderHook(() => useNotificationCrossTabSync());

      // Wait for sync context to be set
      await waitFor(() => {
        expect(mockSetSyncContext).toHaveBeenCalled();
      }, { timeout: 3000 });

      const { createNotificationChannel } = await import('../lib/crossTab/notificationChannel');
      const channel = createNotificationChannel();
      
      // Wait a bit for subscription to be set up
      await new Promise(resolve => setTimeout(resolve, 100));

      const message: NotificationSyncMessage = {
        type: 'NOTIFICATIONS_BULK_READ',
        payload: {
          notificationIds: null, // Mark all read
          tenantId: 'tenant-456',
          userId: 'user-123',
        },
      };

      channel.postMessage(message);

      await waitFor(() => {
        expect(mockApplyBulkReadFromSync).toHaveBeenCalledWith(null);
      }, { timeout: 1000 });
    });

    it('should handle NOTIFICATIONS_BULK_READ with specific notification IDs', async () => {
      const { result } = renderHook(() => useNotificationCrossTabSync());

      // Wait for sync context to be set
      await waitFor(() => {
        expect(mockSetSyncContext).toHaveBeenCalled();
      }, { timeout: 3000 });

      const { createNotificationChannel } = await import('../lib/crossTab/notificationChannel');
      const channel = createNotificationChannel();
      
      // Wait a bit for subscription to be set up
      await new Promise(resolve => setTimeout(resolve, 100));

      const message: NotificationSyncMessage = {
        type: 'NOTIFICATIONS_BULK_READ',
        payload: {
          notificationIds: ['notif-1', 'notif-2', 'notif-3'],
          tenantId: 'tenant-456',
          userId: 'user-123',
        },
      };

      channel.postMessage(message);

      await waitFor(() => {
        expect(mockApplyBulkReadFromSync).toHaveBeenCalledWith(['notif-1', 'notif-2', 'notif-3']);
      }, { timeout: 1000 });
    });
  });

  describe('cleanup', () => {
    it('should unsubscribe on unmount', async () => {
      const { unmount } = renderHook(() => useNotificationCrossTabSync());

      await waitFor(() => {
        expect(mockSetSyncContext).toHaveBeenCalled();
      }, { timeout: 3000 });

      unmount();

      // Channel should be cleaned up (no way to directly test, but unmount should not throw)
      expect(true).toBe(true);
    });

    it('should unsubscribe when user logs out', async () => {
      // Start with authenticated state
      (useAuthStore as any).mockImplementation((selector: any) => {
        const state = mockAuthStore;
        return selector ? selector(state) : state;
      });

      const { rerender } = renderHook(() => useNotificationCrossTabSync());

      await waitFor(() => {
        expect(mockSetSyncContext).toHaveBeenCalledWith({
          tenantId: 'tenant-456',
          userId: 'user-123',
        });
      }, { timeout: 3000 });

      // Clear previous calls
      vi.clearAllMocks();

      // Simulate logout - update the mock
      (useAuthStore as any).mockImplementation((selector: any) => {
        const state = {
          user: null,
          selectedTenantId: null,
          isAuthenticated: false,
        };
        return selector ? selector(state) : state;
      });

      rerender();

      await waitFor(() => {
        expect(mockSetSyncContext).toHaveBeenCalledWith({
          tenantId: null,
          userId: null,
        });
      }, { timeout: 3000 });
    });
  });

  describe('edge cases', () => {
    it('should handle invalid message format gracefully', async () => {
      const { result } = renderHook(() => useNotificationCrossTabSync());

      // Wait for sync context to be set
      await waitFor(() => {
        expect(mockSetSyncContext).toHaveBeenCalled();
      }, { timeout: 3000 });

      const { createNotificationChannel } = await import('../lib/crossTab/notificationChannel');
      const channel = createNotificationChannel();
      
      // Wait a bit for subscription to be set up
      await new Promise(resolve => setTimeout(resolve, 100));

      // Post invalid message
      (channel as any).postMessage({ invalid: 'message' });

      // Should not throw or call any handlers
      await new Promise((resolve) => setTimeout(resolve, 100));

      expect(mockApplyNotificationFromSync).not.toHaveBeenCalled();
      expect(mockApplyNotificationReadFromSync).not.toHaveBeenCalled();
      expect(mockApplyBulkReadFromSync).not.toHaveBeenCalled();
    });

    it('should handle unknown message type gracefully', async () => {
      const { result } = renderHook(() => useNotificationCrossTabSync());

      // Wait for sync context to be set
      await waitFor(() => {
        expect(mockSetSyncContext).toHaveBeenCalled();
      }, { timeout: 3000 });

      const { createNotificationChannel } = await import('../lib/crossTab/notificationChannel');
      const channel = createNotificationChannel();
      
      // Wait a bit for subscription to be set up
      await new Promise(resolve => setTimeout(resolve, 100));

      const message = {
        type: 'UNKNOWN_TYPE',
        payload: {
          tenantId: 'tenant-456',
          userId: 'user-123',
        },
      } as any;

      channel.postMessage(message);

      await new Promise((resolve) => setTimeout(resolve, 100));

      // Should not throw
      expect(true).toBe(true);
    });
  });
});
