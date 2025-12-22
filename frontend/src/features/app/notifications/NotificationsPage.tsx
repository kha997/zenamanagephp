import React, { useState, useCallback } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Select, type SelectOption } from '../../../components/ui/primitives/Select';
import { NotificationList } from './NotificationList';
import { useNotifications, useMarkAllNotificationsRead, useMarkNotificationRead } from '../hooks';
import { notificationsApi } from '../api';
import { useNotificationsStore } from '../../../store/notifications';
import toast from 'react-hot-toast';

/**
 * NotificationsPage Component
 * 
 * Round 251: Notifications Center Phase 1
 * Round 260: Added bulk-read and multi-select functionality
 * 
 * Main page for viewing and managing notifications
 */
export const NotificationsPage: React.FC = () => {
  const [moduleFilter, setModuleFilter] = useState<'tasks' | 'documents' | 'cost' | 'rbac' | 'system' | undefined>(undefined);
  const [readFilter, setReadFilter] = useState<boolean | undefined>(undefined);
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [page, setPage] = useState<number>(1);
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
  const perPage = 20;

  const { data, isLoading, error } = useNotifications({
    page,
    per_page: perPage,
    module: moduleFilter,
    is_read: readFilter,
    search: searchQuery || undefined,
  });

  const markAllReadMutation = useMarkAllNotificationsRead();
  const markReadMutation = useMarkNotificationRead();
  const { markAsRead, markAllAsRead } = useNotificationsStore();

  // Reset selection when filters change
  React.useEffect(() => {
    setSelectedIds(new Set());
  }, [moduleFilter, readFilter, searchQuery, page]);

  // Module filter options
  const moduleOptions: SelectOption[] = [
    { value: '', label: 'All Modules' },
    { value: 'tasks', label: 'Tasks' },
    { value: 'documents', label: 'Documents' },
    { value: 'cost', label: 'Cost' },
    { value: 'rbac', label: 'RBAC' },
    { value: 'system', label: 'System' },
  ];

  // Read filter options
  const readFilterOptions: SelectOption[] = [
    { value: '', label: 'All' },
    { value: 'false', label: 'Unread' },
    { value: 'true', label: 'Read' },
  ];

  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setSearchQuery(e.target.value);
    setPage(1);
  };

  const handleModuleChange = (value: string) => {
    setModuleFilter(value ? (value as typeof moduleFilter) : undefined);
    setPage(1);
  };

  const handleReadFilterChange = (value: string) => {
    setReadFilter(value ? value === 'true' : undefined);
    setPage(1);
  };

  const handleMarkAllRead = async () => {
    try {
      // Store's markAllAsRead handles API call, store update, and cross-tab sync
      await markAllAsRead(false);
      // Invalidate queries to refresh the list
      markAllReadMutation.mutate();
      toast.success('All notifications marked as read');
    } catch (error) {
      console.error('[NotificationsPage] Failed to mark all as read:', error);
      toast.error('Failed to mark all notifications as read. Please refresh and try again.');
    }
  };

  const handleMarkSelectedAsRead = async () => {
    if (selectedIds.size === 0) return;

    try {
      const selectedArray = Array.from(selectedIds);
      
      // Optimistically update store for each selected notification
      for (const id of selectedArray) {
        await markAsRead(id, false);
        // Call API for each notification
        try {
          await notificationsApi.markNotificationRead(id);
        } catch (error) {
          console.error(`[NotificationsPage] Failed to mark notification ${id} as read:`, error);
        }
      }

      // Clear selection
      setSelectedIds(new Set());
      toast.success(`${selectedArray.length} notification(s) marked as read`);
    } catch (error) {
      console.error('[NotificationsPage] Failed to mark selected as read:', error);
      toast.error('Failed to mark selected notifications as read. Please try again.');
    }
  };

  const handleSelectChange = useCallback((id: string, selected: boolean) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (selected) {
        next.add(id);
      } else {
        next.delete(id);
      }
      return next;
    });
  }, []);

  const handleSelectAll = useCallback((selected: boolean) => {
    if (selected) {
      const allIds = new Set(data?.data.map(n => n.id) || []);
      setSelectedIds(allIds);
    } else {
      setSelectedIds(new Set());
    }
  }, [data?.data]);

  const unreadCount = data?.meta.unread_count ?? 0;
  const totalCount = data?.meta.total ?? 0;

  if (error) {
    return (
      <div className="container mx-auto p-6">
        <Card>
          <CardContent className="p-6">
            <div className="text-center text-red-600 dark:text-red-400">
              Failed to load notifications. Please try again.
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="container mx-auto p-6">
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <CardTitle>Notifications</CardTitle>
            <div className="flex items-center gap-3">
              {selectedIds.size > 0 && (
                <button
                  onClick={handleMarkSelectedAsRead}
                  className="text-sm text-blue-600 dark:text-blue-400 hover:underline disabled:opacity-50 px-3 py-1 border border-blue-600 dark:border-blue-400 rounded hover:bg-blue-50 dark:hover:bg-blue-900/20"
                >
                  Mark selected as read ({selectedIds.size})
                </button>
              )}
              {unreadCount > 0 && (
                <button
                  onClick={handleMarkAllRead}
                  disabled={markAllReadMutation.isPending}
                  className="text-sm text-blue-600 dark:text-blue-400 hover:underline disabled:opacity-50"
                >
                  {markAllReadMutation.isPending ? 'Marking...' : 'Mark all as read'}
                </button>
              )}
            </div>
          </div>
          {unreadCount > 0 && (
            <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
              {unreadCount} unread {unreadCount === 1 ? 'notification' : 'notifications'}
            </p>
          )}
        </CardHeader>
        <CardContent>
          {/* Filters */}
          <div className="mb-6 space-y-4 md:space-y-0 md:flex md:gap-4">
            {/* Search */}
            <div className="flex-1">
              <input
                type="text"
                placeholder="Search notifications..."
                value={searchQuery}
                onChange={handleSearchChange}
                className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            {/* Module filter */}
            <div className="md:w-48">
              <Select
                value={moduleFilter || ''}
                onChange={handleModuleChange}
                options={moduleOptions}
                placeholder="Filter by module"
              />
            </div>

            {/* Read filter */}
            <div className="md:w-32">
              <Select
                value={readFilter !== undefined ? readFilter.toString() : ''}
                onChange={handleReadFilterChange}
                options={readFilterOptions}
                placeholder="Filter by status"
              />
            </div>
          </div>

          {/* Notification list */}
          <NotificationList
            notifications={data?.data ?? []}
            isLoading={isLoading}
            selectedIds={selectedIds}
            onSelectChange={handleSelectChange}
            onSelectAll={handleSelectAll}
          />

          {/* Pagination */}
          {data && data.meta.total > perPage && (
            <div className="mt-6 flex items-center justify-between">
              <div className="text-sm text-gray-600 dark:text-gray-400">
                Showing {data.meta.from} to {data.meta.to} of {data.meta.total} notifications
              </div>
              <div className="flex gap-2">
                <button
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  disabled={page === 1 || isLoading}
                  className="px-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-800"
                >
                  Previous
                </button>
                <button
                  onClick={() => setPage((p) => p + 1)}
                  disabled={page >= data.meta.last_page || isLoading}
                  className="px-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-800"
                >
                  Next
                </button>
              </div>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
};
