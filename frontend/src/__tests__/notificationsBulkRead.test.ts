/**
 * Notifications Bulk Read Tests
 * 
 * Round 260: Tests for bulk-read functionality
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { NotificationsPage } from '../features/app/notifications/NotificationsPage';
import { useNotificationsStore } from '../store/notifications';
import { notificationsApi } from '../features/app/api';
import toast from 'react-hot-toast';

// Mock dependencies
vi.mock('../store/notifications');
vi.mock('../features/app/api');
vi.mock('react-hot-toast');
vi.mock('../features/app/hooks', () => ({
  useNotifications: vi.fn(),
  useMarkAllNotificationsRead: vi.fn(() => ({
    mutate: vi.fn(),
    isPending: false,
  })),
  useMarkNotificationRead: vi.fn(() => ({
    mutate: vi.fn(),
  })),
}));

describe('Notifications Bulk Read', () => {
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
      title: 'Task 2 assigned',
      message: 'You have been assigned another task',
      entity_type: 'task',
      entity_id: 'task-2',
      is_read: false,
      metadata: null,
      created_at: '2024-01-01T01:00:00Z',
      updated_at: '2024-01-01T01:00:00Z',
    },
    {
      id: 'notif-3',
      tenant_id: 'tenant-1',
      user_id: 'user-1',
      module: 'documents',
      type: 'document.created',
      title: 'Document created',
      message: 'A new document was created',
      entity_type: 'document',
      entity_id: 'doc-1',
      is_read: true,
      metadata: null,
      created_at: '2024-01-01T02:00:00Z',
      updated_at: '2024-01-01T02:00:00Z',
    },
  ];

  const mockMarkAsRead = vi.fn();
  const mockMarkAllAsRead = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
    
    (useNotificationsStore as any).mockReturnValue({
      markAsRead: mockMarkAsRead,
      markAllAsRead: mockMarkAllAsRead,
    });

    (notificationsApi.markNotificationRead as any) = vi.fn().mockResolvedValue({ id: 'notif-1', is_read: true });
    (notificationsApi.markAllNotificationsRead as any) = vi.fn().mockResolvedValue({ count: 2 });
  });

  describe('Mark selected as read', () => {
    it('should mark selected notifications as read and update store', async () => {
      const { useNotifications } = await import('../features/app/hooks');
      (useNotifications as any).mockReturnValue({
        data: {
          data: mockNotifications,
          meta: {
            current_page: 1,
            per_page: 20,
            total: 3,
            last_page: 1,
            from: 1,
            to: 3,
            unread_count: 2,
          },
        },
        isLoading: false,
        error: null,
      });

      render(<NotificationsPage />);

      // Select first notification
      const checkboxes = screen.getAllByRole('checkbox');
      fireEvent.click(checkboxes[1]); // First notification checkbox (skip select-all)

      // Click "Mark selected as read" button
      const markSelectedButton = screen.getByText(/Mark selected as read/i);
      fireEvent.click(markSelectedButton);

      await waitFor(() => {
        expect(mockMarkAsRead).toHaveBeenCalledWith('notif-1', false);
        expect(notificationsApi.markNotificationRead).toHaveBeenCalledWith('notif-1');
      });

      expect(toast.success).toHaveBeenCalledWith(expect.stringContaining('1 notification'));
    });

    it('should mark multiple selected notifications as read', async () => {
      const { useNotifications } = await import('../features/app/hooks');
      (useNotifications as any).mockReturnValue({
        data: {
          data: mockNotifications,
          meta: {
            current_page: 1,
            per_page: 20,
            total: 3,
            last_page: 1,
            from: 1,
            to: 3,
            unread_count: 2,
          },
        },
        isLoading: false,
        error: null,
      });

      render(<NotificationsPage />);

      // Select multiple notifications
      const checkboxes = screen.getAllByRole('checkbox');
      fireEvent.click(checkboxes[1]); // First notification
      fireEvent.click(checkboxes[2]); // Second notification

      // Click "Mark selected as read" button
      const markSelectedButton = screen.getByText(/Mark selected as read \(2\)/i);
      fireEvent.click(markSelectedButton);

      await waitFor(() => {
        expect(mockMarkAsRead).toHaveBeenCalledWith('notif-1', false);
        expect(mockMarkAsRead).toHaveBeenCalledWith('notif-2', false);
        expect(notificationsApi.markNotificationRead).toHaveBeenCalledWith('notif-1');
        expect(notificationsApi.markNotificationRead).toHaveBeenCalledWith('notif-2');
      });

      expect(toast.success).toHaveBeenCalledWith(expect.stringContaining('2 notification'));
    });
  });

  describe('Mark all as read', () => {
    it('should mark all notifications as read and update store', async () => {
      const { useNotifications } = await import('../features/app/hooks');
      (useNotifications as any).mockReturnValue({
        data: {
          data: mockNotifications,
          meta: {
            current_page: 1,
            per_page: 20,
            total: 3,
            last_page: 1,
            from: 1,
            to: 3,
            unread_count: 2,
          },
        },
        isLoading: false,
        error: null,
      });

      render(<NotificationsPage />);

      // Click "Mark all as read" button
      const markAllButton = screen.getByText(/Mark all as read/i);
      fireEvent.click(markAllButton);

      await waitFor(() => {
        expect(mockMarkAllAsRead).toHaveBeenCalledWith(false);
        expect(notificationsApi.markAllNotificationsRead).toHaveBeenCalled();
      });

      expect(toast.success).toHaveBeenCalledWith('All notifications marked as read');
    });
  });

  describe('Select all', () => {
    it('should select all notifications when select-all is checked', async () => {
      const { useNotifications } = await import('../features/app/hooks');
      (useNotifications as any).mockReturnValue({
        data: {
          data: mockNotifications,
          meta: {
            current_page: 1,
            per_page: 20,
            total: 3,
            last_page: 1,
            from: 1,
            to: 3,
            unread_count: 2,
          },
        },
        isLoading: false,
        error: null,
      });

      render(<NotificationsPage />);

      // Click select-all checkbox
      const selectAllCheckbox = screen.getByText(/Select all/i).closest('label')?.querySelector('input[type="checkbox"]');
      if (selectAllCheckbox) {
        fireEvent.click(selectAllCheckbox);
      }

      // All notification checkboxes should be checked
      const checkboxes = screen.getAllByRole('checkbox');
      expect(checkboxes[1]).toBeChecked();
      expect(checkboxes[2]).toBeChecked();
      expect(checkboxes[3]).toBeChecked();
    });
  });
});
