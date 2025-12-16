import React from 'react';
import { useNavigate } from 'react-router-dom';
import { formatDistanceToNow } from 'date-fns';
import type { Notification } from '../api';
import { useMarkNotificationRead } from '../hooks';
import { resolveNotificationRoute } from '../../../lib/notifications/navigation';
import { notificationsApi } from '../api';

/**
 * NotificationItem Component
 * 
 * Round 251: Notifications Center Phase 1
 * Round 258: Deep-link navigation on click
 * Round 260: Added multi-select checkbox support
 * 
 * Displays a single notification item (Slack/Discord style)
 */
interface NotificationItemProps {
  notification: Notification;
  isSelected?: boolean;
  onSelectChange?: (id: string, selected: boolean) => void;
}

export const NotificationItem: React.FC<NotificationItemProps> = ({ 
  notification,
  isSelected = false,
  onSelectChange,
}) => {
  const markReadMutation = useMarkNotificationRead();
  const navigate = useNavigate();

  const handleClick = async () => {
    // Round 258: Resolve route and navigate
    const route = resolveNotificationRoute(notification);
    
    if (route) {
      // Navigate to the resolved route
      navigate(route.path);
    }
    
    // Mark as read if not already read
    if (!notification.is_read) {
      try {
        await notificationsApi.markNotificationRead(notification.id);
        // The mutation will handle the store update via query invalidation
        markReadMutation.mutate(notification.id);
      } catch (error) {
        console.error('[NotificationItem] Failed to mark notification as read:', error);
        // Still try the mutation as fallback
        markReadMutation.mutate(notification.id);
      }
    }
  };

  const formatRelativeTime = (timestamp: string): string => {
    try {
      return formatDistanceToNow(new Date(timestamp), { addSuffix: true });
    } catch {
      return timestamp;
    }
  };

  const getModuleBadgeColor = (module: string | null): string => {
    switch (module) {
      case 'tasks':
        return 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300';
      case 'documents':
        return 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300';
      case 'cost':
        return 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300';
      case 'rbac':
        return 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300';
      case 'system':
        return 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
      default:
        return 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
    }
  };

  const handleCheckboxChange = (e: React.MouseEvent) => {
    e.stopPropagation();
    if (onSelectChange) {
      onSelectChange(notification.id, !isSelected);
    }
  };

  return (
    <div
      className={`
        p-4 border-b border-gray-200 dark:border-gray-700 cursor-pointer
        hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors
        ${!notification.is_read ? 'bg-blue-50 dark:bg-blue-900/20 font-semibold' : ''}
        ${isSelected ? 'bg-blue-100 dark:bg-blue-900/30' : ''}
      `}
      onClick={handleClick}
    >
      <div className="flex items-start gap-3">
        {/* Checkbox for multi-select */}
        {onSelectChange && (
          <input
            type="checkbox"
            checked={isSelected}
            onChange={handleCheckboxChange}
            onClick={handleCheckboxChange}
            className="w-4 h-4 mt-2 text-blue-600 border-gray-300 rounded focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 flex-shrink-0"
          />
        )}
        
        {/* Unread indicator */}
        {!notification.is_read && !onSelectChange && (
          <div className="w-2 h-2 rounded-full bg-blue-500 mt-2 flex-shrink-0" />
        )}
        
        <div className="flex-1 min-w-0">
          {/* Header: Module badge + timestamp */}
          <div className="flex items-center justify-between mb-1">
            {notification.module && (
              <span className={`text-xs px-2 py-0.5 rounded ${getModuleBadgeColor(notification.module)}`}>
                {notification.module}
              </span>
            )}
            <span className="text-xs text-gray-500 dark:text-gray-400">
              {formatRelativeTime(notification.created_at)}
            </span>
          </div>

          {/* Title */}
          <h3 className={`text-sm mb-1 ${!notification.is_read ? 'font-semibold' : 'font-normal'}`}>
            {notification.title}
          </h3>

          {/* Message */}
          {notification.message && (
            <p className="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
              {notification.message}
            </p>
          )}
        </div>
      </div>
    </div>
  );
};
