import React from 'react';
import { Notification } from './NotificationsBell';

export interface NotificationsOverlayProps {
  notifications: Notification[];
  unreadCount: number;
  onMarkAsRead?: (notificationId: string) => void;
  onMarkAllAsRead?: () => void;
  onNotificationClick?: (notification: Notification) => void;
}

export const NotificationsOverlay: React.FC<NotificationsOverlayProps> = ({
  notifications,
  unreadCount,
  onMarkAsRead,
  onMarkAllAsRead,
  onNotificationClick,
}) => {
  const getNotificationIcon = (type: Notification['type']) => {
    switch (type) {
      case 'success':
        return 'fas fa-check-circle text-green-500';
      case 'warning':
        return 'fas fa-exclamation-triangle text-yellow-500';
      case 'error':
        return 'fas fa-times-circle text-red-500';
      default:
        return 'fas fa-info-circle text-blue-500';
    }
  };

  const formatTimeAgo = (createdAt: string) => {
    const now = new Date();
    const created = new Date(createdAt);
    const diffInMinutes = Math.floor((now.getTime() - created.getTime()) / (1000 * 60));

    if (diffInMinutes < 1) return 'Just now';
    if (diffInMinutes < 60) return `${diffInMinutes}m ago`;
    if (diffInMinutes < 1440) return `${Math.floor(diffInMinutes / 60)}h ago`;
    return `${Math.floor(diffInMinutes / 1440)}d ago`;
  };

  const handleNotificationClick = (notification: Notification) => {
    if (!notification.read) {
      onMarkAsRead?.(notification.id);
    }
    onNotificationClick?.(notification);
  };

  return (
    <div className="w-80 max-h-96 overflow-hidden">
      {/* Header */}
      <div className="px-4 py-3 border-b border-header-border bg-header-bg-hover">
        <div className="flex items-center justify-between">
          <h3 className="text-sm font-medium text-header-fg">Notifications</h3>
          {unreadCount > 0 && (
            <button
              onClick={onMarkAllAsRead}
              className="text-xs text-nav-active hover:text-nav-hover transition-colors"
            >
              Mark all as read
            </button>
          )}
        </div>
      </div>

      {/* Notifications List */}
      <div className="max-h-80 overflow-y-auto">
        {notifications.length === 0 ? (
          <div className="p-4 text-center">
            <div className="w-12 h-12 mx-auto mb-3 bg-gray-100 rounded-full flex items-center justify-center">
              <i className="fas fa-bell text-gray-400" aria-hidden="true" />
            </div>
            <p className="text-sm text-header-fg-muted">No notifications yet</p>
            <p className="text-xs text-header-fg-muted mt-1">
              We'll notify you when something important happens
            </p>
          </div>
        ) : (
          <div className="divide-y divide-header-border">
            {notifications.map((notification) => (
              <div
                key={notification.id}
                className={`p-4 hover:bg-header-bg-hover transition-colors cursor-pointer ${
                  !notification.read ? 'bg-blue-50' : ''
                }`}
                onClick={() => handleNotificationClick(notification)}
              >
                <div className="flex items-start space-x-3">
                  {/* Icon */}
                  <div className="flex-shrink-0 mt-0.5">
                    <i className={`${getNotificationIcon(notification.type)} text-sm`} aria-hidden="true" />
                  </div>

                  {/* Content */}
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between">
                      <p className={`text-sm font-medium ${
                        !notification.read ? 'text-header-fg' : 'text-header-fg-muted'
                      }`}>
                        {notification.title}
                      </p>
                      {!notification.read && (
                        <div className="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0" />
                      )}
                    </div>
                    <p className={`text-xs mt-1 ${
                      !notification.read ? 'text-header-fg-muted' : 'text-header-fg-muted'
                    }`}>
                      {notification.message}
                    </p>
                    <p className="text-xs text-header-fg-muted mt-2">
                      {formatTimeAgo(notification.created_at)}
                    </p>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Footer */}
      {notifications.length > 0 && (
        <div className="px-4 py-3 border-t border-header-border bg-header-bg-hover">
          <button
            onClick={() => window.location.href = '/app/notifications'}
            className="w-full text-center text-sm text-nav-active hover:text-nav-hover transition-colors"
          >
            View all notifications
          </button>
        </div>
      )}
    </div>
  );
};

export default NotificationsOverlay;
