import React from 'react';
import { NotificationItem } from './NotificationItem';
import type { Notification } from '../api';

/**
 * NotificationList Component
 * 
 * Round 251: Notifications Center Phase 1
 * Round 260: Added multi-select support
 * 
 * Displays a list of notifications
 */
interface NotificationListProps {
  notifications: Notification[];
  isLoading?: boolean;
  selectedIds?: Set<string>;
  onSelectChange?: (id: string, selected: boolean) => void;
  onSelectAll?: (selected: boolean) => void;
}

export const NotificationList: React.FC<NotificationListProps> = ({ 
  notifications, 
  isLoading,
  selectedIds = new Set(),
  onSelectChange,
  onSelectAll,
}) => {
  if (isLoading) {
    return (
      <div className="space-y-4">
        {[1, 2, 3, 4, 5].map((i) => (
          <div key={i} className="p-4 border-b border-gray-200 dark:border-gray-700 animate-pulse">
            <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4 mb-2" />
            <div className="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/2" />
          </div>
        ))}
      </div>
    );
  }

  if (notifications.length === 0) {
    return (
      <div className="text-center py-12">
        <div className="text-gray-400 dark:text-gray-500 mb-2">
          <svg
            className="mx-auto h-12 w-12"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
            />
          </svg>
        </div>
        <p className="text-gray-500 dark:text-gray-400">No notifications yet</p>
        <p className="text-sm text-gray-400 dark:text-gray-500 mt-1">
          You're all caught up!
        </p>
      </div>
    );
  }

  const allSelected = notifications.length > 0 && notifications.every(n => selectedIds.has(n.id));
  const someSelected = notifications.some(n => selectedIds.has(n.id));

  return (
    <div className="divide-y divide-gray-200 dark:divide-gray-700">
      {/* Select all checkbox */}
      {onSelectAll && notifications.length > 0 && (
        <div className="p-2 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
          <label className="flex items-center gap-2 cursor-pointer">
            <input
              type="checkbox"
              checked={allSelected}
              ref={(input) => {
                if (input) input.indeterminate = someSelected && !allSelected;
              }}
              onChange={(e) => onSelectAll(e.target.checked)}
              className="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600"
            />
            <span className="text-sm text-gray-700 dark:text-gray-300">
              Select all ({notifications.length} notifications)
            </span>
          </label>
        </div>
      )}
      
      {notifications.map((notification) => (
        <NotificationItem 
          key={notification.id} 
          notification={notification}
          isSelected={selectedIds.has(notification.id)}
          onSelectChange={onSelectChange}
        />
      ))}
    </div>
  );
};
