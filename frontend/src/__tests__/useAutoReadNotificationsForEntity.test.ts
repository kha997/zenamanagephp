/**
 * Auto-Read Notifications Hook Tests
 * 
 * Round 260: Tests for auto-read functionality
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { useAutoReadNotificationsForEntity } from '../hooks/useAutoReadNotificationsForEntity';
import { useNotificationsStore } from '../store/notifications';
import { notificationsApi } from '../features/app/api';
import { useAuthStore } from '../features/auth/store';

// Mock dependencies
vi.mock('../store/notifications');
vi.mock('../features/app/api');
vi.mock('../features/auth/store');
vi.mock('../features/app/hooks', () => ({
  useNotifications: vi.fn(),
}));

describe('useAutoReadNotificationsForEntity', () => {
  const mockNotifications = [
    {
      id: 'notif-1',
      tenant_id: 'tenant-1',
      user_id: 'user-1',
      module: 'tasks',
      type: 'task.assigned',
      title: 'Task 1 assigned',
      message: 'You have been assigned a task',
      entity_type: 'task',
      entity_id: 'task-1',
      is_read: false,
      metadata: null,
      created_at: '2024-01-01T00:00:00Z',
      updated_at: '2024-01-01T00:00:00Z',
    },
    {
      id: 'notif-2',
      tenant_id: 'tenant-1',
      user_id: 'user-1',
      module: 'tasks',
      type: 'task.assigned',
      title: 'Task 1 assigned again',
      message: 'You have been assigned the same task',
      entity_type: 'task',
      entity_id: 'task-1',
      is_read: false,
      metadata: null,
      created_at: '2024-01-01T01:00:00Z',
      updated_at: '2024-01-01T01:00:00Z',
    },
    {
      id: 'notif-3',
      tenant_id: 'tenant-1',
      user_id: 'user-1',
      module: 'tasks',
      type: 'task.assigned',
      title: 'Task 2 assigned',
      message: 'You have been assigned another task',
      entity_type: 'task',
      entity_id: 'task-2',
      is_read: false,
      metadata: null,
      created_at: '2024-01-01T02:00:00Z',
      updated_at: '2024-01-01T02:00:00Z',
    },
    {
      id: 'notif-4',
      tenant_id: 'tenant-1',
      user_id: 'user-1',
      module: 'tasks',
      type: 'task.assigned',
      title: 'Task 1 assigned (read)',
      message: 'This is already read',
      entity_type: 'task',
      entity_id: 'task-1',
      is_read: true,
      metadata: null,
      created_at: '2024-01-01T03:00:00Z',
      updated_at: '2024-01-01T03:00:00Z',
    },
  ];

  const mockMarkAsRead = vi.fn().mockResolvedValue(undefined);
  const mockUser = { id: 'user-1', tenant_id: 'tenant-1' };

  beforeEach(() => {
    vi.useFakeTimers();
    vi.clearAllMocks();

    (useAuthStore as any).mockReturnValue({
      isAuthenticated: true,
      user: mockUser,
      selectedTenantId: 'tenant-1',
    });

    (useNotificationsStore as any).mockReturnValue({
      markAsRead: mockMarkAsRead,
    });

    (notificationsApi.markNotificationRead as any) = vi.fn().mockResolvedValue({ id: 'notif-1', is_read: true });
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('should mark matching notifications as read after delay', async () => {
    const { useNotifications } = await import('../features/app/hooks');
    (useNotifications as any).mockReturnValue({
      data: {
        data: mockNotifications,
        meta: {
          current_page: 1,
          per_page: 100,
          total: 4,
          last_page: 1,
          from: 1,
          to: 4,
          unread_count: 3,
        },
      },
      isLoading: false,
      error: null,
    });

    const { result } = renderHook(() =>
      useAutoReadNotificationsForEntity({
        module: 'tasks',
        entityType: 'task',
        entityId: 'task-1',
        delayMs: 10, // Short delay for testing
      })
    );

    // Advance timer past delay
    vi.advanceTimersByTime(10);

    await waitFor(() => {
      // Should mark notif-1 and notif-2 (both match task-1 and are unread)
      expect(mockMarkAsRead).toHaveBeenCalledWith('notif-1', false);
      expect(mockMarkAsRead).toHaveBeenCalledWith('notif-2', false);
      // Should NOT mark notif-3 (different entity_id) or notif-4 (already read)
      expect(mockMarkAsRead).not.toHaveBeenCalledWith('notif-3', expect.anything());
      expect(mockMarkAsRead).not.toHaveBeenCalledWith('notif-4', expect.anything());
      
      // API should be called for each matching notification
      expect(notificationsApi.markNotificationRead).toHaveBeenCalledWith('notif-1');
      expect(notificationsApi.markNotificationRead).toHaveBeenCalledWith('notif-2');
    });
  });

  it('should not run when unmounted before delay', async () => {
    const { useNotifications } = await import('../features/app/hooks');
    (useNotifications as any).mockReturnValue({
      data: {
        data: mockNotifications,
        meta: {
          current_page: 1,
          per_page: 100,
          total: 4,
          last_page: 1,
          from: 1,
          to: 4,
          unread_count: 3,
        },
      },
      isLoading: false,
      error: null,
    });

    const { unmount } = renderHook(() =>
      useAutoReadNotificationsForEntity({
        module: 'tasks',
        entityType: 'task',
        entityId: 'task-1',
        delayMs: 1000,
      })
    );

    // Unmount before delay
    unmount();

    // Advance timer past delay
    vi.advanceTimersByTime(1000);

    // Should not have called markAsRead
    expect(mockMarkAsRead).not.toHaveBeenCalled();
    expect(notificationsApi.markNotificationRead).not.toHaveBeenCalled();
  });

  it('should ignore already read notifications', async () => {
    const { useNotifications } = await import('../features/app/hooks');
    (useNotifications as any).mockReturnValue({
      data: {
        data: [
          {
            id: 'notif-1',
            module: 'tasks',
            entity_type: 'task',
            entity_id: 'task-1',
            is_read: true, // Already read
          },
        ],
        meta: {
          current_page: 1,
          per_page: 100,
          total: 1,
          last_page: 1,
          from: 1,
          to: 1,
          unread_count: 0,
        },
      },
      isLoading: false,
      error: null,
    });

    renderHook(() =>
      useAutoReadNotificationsForEntity({
        module: 'tasks',
        entityType: 'task',
        entityId: 'task-1',
        delayMs: 10,
      })
    );

    vi.advanceTimersByTime(10);

    await waitFor(() => {
      // Should not mark already read notifications
      expect(mockMarkAsRead).not.toHaveBeenCalled();
      expect(notificationsApi.markNotificationRead).not.toHaveBeenCalled();
    });
  });

  it('should not run when user is not authenticated', async () => {
    (useAuthStore as any).mockReturnValue({
      isAuthenticated: false,
      user: null,
      selectedTenantId: null,
    });

    const { useNotifications } = await import('../features/app/hooks');
    (useNotifications as any).mockReturnValue({
      data: {
        data: mockNotifications,
        meta: {
          current_page: 1,
          per_page: 100,
          total: 4,
          last_page: 1,
          from: 1,
          to: 4,
          unread_count: 3,
        },
      },
      isLoading: false,
      error: null,
    });

    renderHook(() =>
      useAutoReadNotificationsForEntity({
        module: 'tasks',
        entityType: 'task',
        entityId: 'task-1',
        delayMs: 10,
      })
    );

    vi.advanceTimersByTime(10);

    // Should not have called markAsRead
    expect(mockMarkAsRead).not.toHaveBeenCalled();
    expect(notificationsApi.markNotificationRead).not.toHaveBeenCalled();
  });

  it('should dedupe notifications to avoid duplicate API calls', async () => {
    const { useNotifications } = await import('../features/app/hooks');
    (useNotifications as any).mockReturnValue({
      data: {
        data: mockNotifications,
        meta: {
          current_page: 1,
          per_page: 100,
          total: 4,
          last_page: 1,
          from: 1,
          to: 4,
          unread_count: 3,
        },
      },
      isLoading: false,
      error: null,
    });

    const { rerender } = renderHook(() =>
      useAutoReadNotificationsForEntity({
        module: 'tasks',
        entityType: 'task',
        entityId: 'task-1',
        delayMs: 10,
      })
    );

    // First run
    vi.advanceTimersByTime(10);
    await waitFor(() => {
      expect(mockMarkAsRead).toHaveBeenCalledTimes(2); // notif-1 and notif-2
    });

    // Rerender with same config (should not process again)
    rerender();
    vi.advanceTimersByTime(10);
    
    // Should not call again for same notifications
    await waitFor(() => {
      // Total calls should still be 2 (not 4)
      expect(mockMarkAsRead).toHaveBeenCalledTimes(2);
    });
  });
});
