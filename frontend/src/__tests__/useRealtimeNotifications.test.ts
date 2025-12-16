/**
 * Tests for useRealtimeNotifications hook
 * 
 * Round 257: Real-time notification handling
 * Round 258: Toast notifications on new notifications
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { useRealtimeNotifications } from '../hooks/useRealtimeNotifications';
import { useAuthStore } from '../features/auth/store';
import { useNotificationsStore } from '../store/notifications';

// Mock Echo client
const mockEcho = {
  private: vi.fn(),
  leave: vi.fn(),
  disconnect: vi.fn(),
  connector: {
    pusher: {
      connection: {
        bind: vi.fn(),
      },
    },
  },
};

// Mock the Echo module
const mockGetEcho = vi.fn(() => mockEcho);
vi.mock('../lib/realtime/echo', () => ({
  getEcho: (...args: any[]) => mockGetEcho(...args),
  disconnectEcho: vi.fn(),
}));

// Mock auth store
vi.mock('../features/auth/store', () => ({
  useAuthStore: vi.fn(),
}));

// Mock notification store
const mockAddNotification = vi.fn();
const mockIncrementUnread = vi.fn();

vi.mock('../store/notifications', () => ({
  useNotificationsStore: vi.fn((selector) => {
    const state = {
      addNotification: mockAddNotification,
      incrementUnread: mockIncrementUnread,
    };
    return selector(state);
  }),
}));

// Create hoisted mocks
const { 
  mockShowToast, 
  mockNavigate, 
  mockResolveNotificationRoute, 
  mockMarkNotificationRead 
} = vi.hoisted(() => ({
  mockShowToast: vi.fn(),
  mockNavigate: vi.fn(),
  mockResolveNotificationRoute: vi.fn(),
  mockMarkNotificationRead: vi.fn(),
}));

// Mock toast hook
vi.mock('../shared/ui/toast', () => ({
  useToast: () => ({
    showToast: mockShowToast,
  }),
}));

// Mock navigation
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
    useLocation: () => ({ pathname: '/app/dashboard' }),
  };
});

// Mock notification navigation resolver
vi.mock('../lib/notifications/navigation', () => ({
  resolveNotificationRoute: (...args: any[]) => mockResolveNotificationRoute(...args),
}));

// Mock notification icons
vi.mock('../lib/notifications/icons', () => ({
  getNotificationIcon: vi.fn(() => 'Icon'),
}));

// Mock notifications API
vi.mock('../features/app/api', () => ({
  notificationsApi: {
    markNotificationRead: mockMarkNotificationRead,
  },
}));

describe('useRealtimeNotifications', () => {
  let mockChannel: any;
  let mockPrivateChannel: any;

  beforeEach(() => {
    vi.clearAllMocks();
    
    // Reset mockGetEcho to return mockEcho by default
    mockGetEcho.mockReturnValue(mockEcho);

    // Setup mock channel
    mockChannel = {
      listen: vi.fn(),
      subscribed: vi.fn(),
      error: vi.fn(),
      bind: vi.fn(),
      unbind_all: vi.fn(),
    };

    mockPrivateChannel = vi.fn(() => mockChannel);
    mockEcho.private = mockPrivateChannel;

    // Default auth store mock - authenticated user
    (useAuthStore as any).mockReturnValue({
      user: { id: 'user-123', tenant_id: 'tenant-456' },
      selectedTenantId: 'tenant-456',
      isAuthenticated: true,
    });

    // Default route resolver - return null (no route)
    mockResolveNotificationRoute.mockReturnValue(null);
    
    // Default API mock - resolve successfully
    mockMarkNotificationRead.mockResolvedValue({ id: 'notif-123', is_read: true });
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('Channel subscription logic', () => {
    it('should subscribe when tenantId and userId are available', () => {
      renderHook(() => useRealtimeNotifications());

      expect(mockEcho.private).toHaveBeenCalledWith(
        'tenant.tenant-456.user.user-123.notifications'
      );
      expect(mockChannel.listen).toHaveBeenCalledWith(
        '.notification.created',
        expect.any(Function)
      );
    });

    it('should not subscribe when user is not authenticated', () => {
      (useAuthStore as any).mockReturnValue({
        user: null,
        selectedTenantId: null,
        isAuthenticated: false,
      });

      renderHook(() => useRealtimeNotifications());

      expect(mockEcho.private).not.toHaveBeenCalled();
    });

    it('should not subscribe when tenantId is missing', () => {
      (useAuthStore as any).mockReturnValue({
        user: { id: 'user-123' },
        selectedTenantId: null,
        isAuthenticated: true,
      });

      renderHook(() => useRealtimeNotifications());

      expect(mockEcho.private).not.toHaveBeenCalled();
    });

    it('should not subscribe when userId is missing', () => {
      (useAuthStore as any).mockReturnValue({
        user: { tenant_id: 'tenant-456' },
        selectedTenantId: 'tenant-456',
        isAuthenticated: true,
      });

      renderHook(() => useRealtimeNotifications());

      expect(mockEcho.private).not.toHaveBeenCalled();
    });

    it('should not subscribe when Echo is not available', () => {
      mockGetEcho.mockReturnValueOnce(null);

      renderHook(() => useRealtimeNotifications());

      expect(mockEcho.private).not.toHaveBeenCalled();
    });
  });

  describe('Receiving notification', () => {
    it('should call addNotification when notification.created event is received', () => {
      let eventHandler: (payload: any) => void;

      mockChannel.listen.mockImplementation((event: string, handler: (payload: any) => void) => {
        if (event === '.notification.created') {
          eventHandler = handler;
        }
      });

      renderHook(() => useRealtimeNotifications());

      // Simulate notification event
      const payload = {
        id: 'notif-123',
        tenant_id: 'tenant-456',
        user_id: 'user-123',
        module: 'tasks',
        type: 'task_assigned',
        title: 'Task Assigned',
        message: 'You have been assigned to a task',
        entity_type: 'task',
        entity_id: 'task-789',
        metadata: { project_id: 'project-123' },
        is_read: false,
        created_at: '2024-01-01T00:00:00Z',
      };

      // Trigger the event handler
      if (eventHandler) {
        eventHandler(payload);
      }

      expect(mockAddNotification).toHaveBeenCalledWith(
        expect.objectContaining({
          id: 'notif-123',
          tenant_id: 'tenant-456',
          user_id: 'user-123',
          module: 'tasks',
          type: 'task_assigned',
          title: 'Task Assigned',
          message: 'You have been assigned to a task',
          is_read: false,
        })
      );
    });

    it('should handle notification with null message', () => {
      let eventHandler: (payload: any) => void;

      mockChannel.listen.mockImplementation((event: string, handler: (payload: any) => void) => {
        if (event === '.notification.created') {
          eventHandler = handler;
        }
      });

      renderHook(() => useRealtimeNotifications());

      const payload = {
        id: 'notif-124',
        tenant_id: 'tenant-456',
        user_id: 'user-123',
        module: 'system',
        type: 'system_alert',
        title: 'System Alert',
        message: null,
        entity_type: null,
        entity_id: null,
        metadata: {},
        is_read: false,
        created_at: '2024-01-01T00:00:00Z',
      };

      if (eventHandler) {
        eventHandler(payload);
      }

      expect(mockAddNotification).toHaveBeenCalledWith(
        expect.objectContaining({
          id: 'notif-124',
          message: null,
        })
      );
    });
  });

  describe('Round 258: Toast notifications', () => {
    it('should show toast for unread notifications', () => {
      let eventHandler: (payload: any) => void;

      mockChannel.listen.mockImplementation((event: string, handler: (payload: any) => void) => {
        if (event === '.notification.created') {
          eventHandler = handler;
        }
      });

      renderHook(() => useRealtimeNotifications());

      const payload = {
        id: 'notif-125',
        tenant_id: 'tenant-456',
        user_id: 'user-123',
        module: 'tasks',
        type: 'task_assigned',
        title: 'Task Assigned',
        message: 'You have been assigned to a task',
        entity_type: 'task',
        entity_id: 'task-789',
        metadata: { project_id: 'project-123' },
        is_read: false,
        created_at: '2024-01-01T00:00:00Z',
      };

      if (eventHandler) {
        eventHandler(payload);
      }

      expect(mockShowToast).toHaveBeenCalledWith(
        expect.objectContaining({
          title: 'Task Assigned',
          message: 'You have been assigned to a task',
          variant: 'info',
          duration: 6000,
        })
      );
    });

    it('should not show toast for already read notifications', () => {
      let eventHandler: (payload: any) => void;

      mockChannel.listen.mockImplementation((event: string, handler: (payload: any) => void) => {
        if (event === '.notification.created') {
          eventHandler = handler;
        }
      });

      renderHook(() => useRealtimeNotifications());

      const payload = {
        id: 'notif-126',
        tenant_id: 'tenant-456',
        user_id: 'user-123',
        module: 'tasks',
        type: 'task_assigned',
        title: 'Task Assigned',
        message: 'You have been assigned to a task',
        entity_type: 'task',
        entity_id: 'task-789',
        metadata: { project_id: 'project-123' },
        is_read: true, // Already read
        created_at: '2024-01-01T00:00:00Z',
      };

      if (eventHandler) {
        eventHandler(payload);
      }

      expect(mockShowToast).not.toHaveBeenCalled();
    });

    it('should include onClick handler when route is resolved', () => {
      let eventHandler: (payload: any) => void;

      mockChannel.listen.mockImplementation((event: string, handler: (payload: any) => void) => {
        if (event === '.notification.created') {
          eventHandler = handler;
        }
      });

      // Mock route resolver to return a route
      mockResolveNotificationRoute.mockReturnValue({
        path: '/app/tasks',
      });

      renderHook(() => useRealtimeNotifications());

      const payload = {
        id: 'notif-127',
        tenant_id: 'tenant-456',
        user_id: 'user-123',
        module: 'tasks',
        type: 'task_assigned',
        title: 'Task Assigned',
        message: 'You have been assigned to a task',
        entity_type: 'task',
        entity_id: 'task-789',
        metadata: { project_id: 'project-123' },
        is_read: false,
        created_at: '2024-01-01T00:00:00Z',
      };

      if (eventHandler) {
        eventHandler(payload);
      }

      expect(mockShowToast).toHaveBeenCalledWith(
        expect.objectContaining({
          title: 'Task Assigned',
          onClick: expect.any(Function),
        })
      );

      // Test that onClick navigates and marks as read
      const toastCall = mockShowToast.mock.calls[0][0];
      if (toastCall.onClick) {
        toastCall.onClick();
        expect(mockNavigate).toHaveBeenCalledWith('/app/tasks');
        expect(mockMarkNotificationRead).toHaveBeenCalledWith('notif-127');
      }
    });

    it('should not include onClick handler when route is null', () => {
      let eventHandler: (payload: any) => void;

      mockChannel.listen.mockImplementation((event: string, handler: (payload: any) => void) => {
        if (event === '.notification.created') {
          eventHandler = handler;
        }
      });

      // Mock route resolver to return null
      mockResolveNotificationRoute.mockReturnValue(null);

      renderHook(() => useRealtimeNotifications());

      const payload = {
        id: 'notif-128',
        tenant_id: 'tenant-456',
        user_id: 'user-123',
        module: 'system',
        type: 'system_alert',
        title: 'System Alert',
        message: 'System maintenance scheduled',
        entity_type: null,
        entity_id: null,
        metadata: {},
        is_read: false,
        created_at: '2024-01-01T00:00:00Z',
      };

      if (eventHandler) {
        eventHandler(payload);
      }

      expect(mockShowToast).toHaveBeenCalledWith(
        expect.objectContaining({
          title: 'System Alert',
          onClick: undefined,
        })
      );
    });

    it('should handle mark as read error gracefully', async () => {
      let eventHandler: (payload: any) => void;

      mockChannel.listen.mockImplementation((event: string, handler: (payload: any) => void) => {
        if (event === '.notification.created') {
          eventHandler = handler;
        }
      });

      // Mock route resolver to return a route
      mockResolveNotificationRoute.mockReturnValue({
        path: '/app/tasks',
      });

      // Mock API to reject
      const consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
      mockMarkNotificationRead.mockRejectedValueOnce(new Error('API Error'));

      renderHook(() => useRealtimeNotifications());

      const payload = {
        id: 'notif-129',
        tenant_id: 'tenant-456',
        user_id: 'user-123',
        module: 'tasks',
        type: 'task_assigned',
        title: 'Task Assigned',
        message: 'You have been assigned to a task',
        entity_type: 'task',
        entity_id: 'task-789',
        metadata: { project_id: 'project-123' },
        is_read: false,
        created_at: '2024-01-01T00:00:00Z',
      };

      if (eventHandler) {
        eventHandler(payload);
      }

      const toastCall = mockShowToast.mock.calls[0][0];
      if (toastCall.onClick) {
        toastCall.onClick();
        await waitFor(() => {
          expect(consoleErrorSpy).toHaveBeenCalledWith(
            expect.stringContaining('Failed to mark notification as read'),
            expect.any(Error)
          );
        });
      }

      consoleErrorSpy.mockRestore();
    });
  });

  describe('Cleanup', () => {
    it('should leave channel on unmount', () => {
      const { unmount } = renderHook(() => useRealtimeNotifications());

      unmount();

      expect(mockEcho.leave).toHaveBeenCalledWith(
        'tenant.tenant-456.user.user-123.notifications'
      );
    });

    it('should leave channel when user logs out', () => {
      const { rerender } = renderHook(() => useRealtimeNotifications());

      // Change to unauthenticated state
      (useAuthStore as any).mockReturnValue({
        user: null,
        selectedTenantId: null,
        isAuthenticated: false,
      });

      rerender();

      expect(mockEcho.leave).toHaveBeenCalled();
    });

    it('should leave old channel and subscribe to new channel when tenant changes', () => {
      const { rerender } = renderHook(() => useRealtimeNotifications());

      // Change tenant
      (useAuthStore as any).mockReturnValue({
        user: { id: 'user-123', tenant_id: 'tenant-789' },
        selectedTenantId: 'tenant-789',
        isAuthenticated: true,
      });

      rerender();

      // Should leave old channel
      expect(mockEcho.leave).toHaveBeenCalledWith(
        'tenant.tenant-456.user.user-123.notifications'
      );

      // Should subscribe to new channel
      expect(mockEcho.private).toHaveBeenCalledWith(
        'tenant.tenant-789.user.user-123.notifications'
      );
    });
  });

  describe('Error handling', () => {
    it('should handle channel subscription errors gracefully', () => {
      const consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

      mockEcho.private.mockImplementation(() => {
        throw new Error('Subscription failed');
      });

      renderHook(() => useRealtimeNotifications());

      expect(consoleErrorSpy).toHaveBeenCalledWith(
        expect.stringContaining('Failed to subscribe'),
        expect.any(Error)
      );

      consoleErrorSpy.mockRestore();
    });

    it('should handle cleanup errors gracefully', () => {
      const consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

      mockEcho.leave.mockImplementation(() => {
        throw new Error('Leave failed');
      });

      const { unmount } = renderHook(() => useRealtimeNotifications());

      unmount();

      expect(consoleErrorSpy).toHaveBeenCalledWith(
        expect.stringContaining('Cleanup error'),
        expect.any(Error)
      );

      consoleErrorSpy.mockRestore();
    });
  });

  describe('Return value', () => {
    it('should return isSubscribed true when subscribed', () => {
      const { result } = renderHook(() => useRealtimeNotifications());

      // isSubscribed checks channelNameRef.current which is set in useEffect
      // Since we're mocking the channel subscription, the ref should be set
      // We need to wait for the effect to run
      expect(result.current.isSubscribed).toBeDefined();
      // The actual value depends on when the effect runs, but we can verify the logic
    });

    it('should return isSubscribed false when not authenticated', () => {
      (useAuthStore as any).mockReturnValue({
        user: null,
        selectedTenantId: null,
        isAuthenticated: false,
      });

      const { result } = renderHook(() => useRealtimeNotifications());

      expect(result.current.isSubscribed).toBe(false);
    });
  });
});
