import { useState, useEffect, useRef } from 'react';
import { BellIcon } from '@heroicons/react/24/outline';
import { useNotificationsStore } from '../../store/notifications';
import { useNavigate } from 'react-router-dom';

export function NotificationBell() {
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);
  const navigate = useNavigate();
  
  const { 
    notifications, 
    unreadCount, 
    isLoading, 
    error,
    fetchNotifications,
    markAsRead 
  } = useNotificationsStore();

  // Fetch notifications on mount
  useEffect(() => {
    fetchNotifications();
  }, [fetchNotifications]);

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };

    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => document.removeEventListener('mousedown', handleClickOutside);
    }
  }, [isOpen]);

  const handleNotificationClick = async (notification: any) => {
    if (!notification.read_at) {
      await markAsRead(notification.id);
    }
    setIsOpen(false);
    if (notification.link_url) {
      navigate(notification.link_url);
    }
  };

  return (
    <div className="relative" ref={dropdownRef}>
      <button
        type="button"
        onClick={() => {
          setIsOpen(!isOpen);
          if (!isOpen) {
            fetchNotifications();
          }
        }}
        className="relative p-2 text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] hover:bg-[var(--color-surface-hover)] rounded-md focus:outline-none focus:ring-2 focus:ring-[var(--color-semantic-primary-500)] transition-colors"
        aria-label={`Notifications${unreadCount > 0 ? `, ${unreadCount} unread` : ''}`}
        aria-expanded={isOpen}
        aria-haspopup="menu"
      >
        <BellIcon className="h-5 w-5" aria-hidden="true" />
        {unreadCount > 0 && (
          <span className="absolute -top-1 -right-1 h-5 w-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center font-medium">
            {unreadCount > 99 ? '99+' : unreadCount}
          </span>
        )}
      </button>

      {/* Dropdown */}
      {isOpen && (
        <div
          className="absolute right-0 mt-2 w-80 bg-[var(--color-surface-card)] rounded-lg shadow-lg border border-[var(--color-border-subtle)] z-50 max-h-96 overflow-hidden flex flex-col"
          role="menu"
          aria-label="Notifications panel"
        >
          {/* Header */}
          <div className="px-4 py-3 border-b border-[var(--color-border-subtle)] flex items-center justify-between">
            <h3 className="text-sm font-semibold text-[var(--color-text-primary)]">
              Notifications
            </h3>
            {unreadCount > 0 && (
              <span className="text-xs text-[var(--color-text-secondary)]">
                {unreadCount} unread
              </span>
            )}
          </div>

          {/* Content */}
          <div className="overflow-y-auto flex-1">
            {error ? (
              <div className="px-4 py-8 text-center">
                <p className="text-sm text-red-500 mb-2">Error loading notifications</p>
                <p className="text-xs text-[var(--color-text-muted)]">{error}</p>
                <button
                  onClick={() => fetchNotifications()}
                  className="mt-2 text-xs text-[var(--color-semantic-primary-500)] hover:underline"
                >
                  Retry
                </button>
              </div>
            ) : isLoading ? (
              <div className="px-4 py-8 text-center">
                <p className="text-sm text-[var(--color-text-secondary)]">Loading...</p>
              </div>
            ) : notifications.length === 0 ? (
              <div className="px-4 py-8 text-center">
                <BellIcon className="h-12 w-12 mx-auto text-[var(--color-text-muted)] mb-2" />
                <p className="text-sm text-[var(--color-text-secondary)]">No notifications</p>
              </div>
            ) : (
              <div className="py-2">
                {notifications.slice(0, 10).map((notification) => (
                  <button
                    key={notification.id}
                    onClick={() => handleNotificationClick(notification)}
                    className="w-full text-left px-4 py-3 hover:bg-[var(--color-surface-hover)] border-b border-[var(--color-border-subtle)] last:border-b-0 transition-colors focus:outline-none focus:bg-[var(--color-surface-hover)]"
                    role="menuitem"
                  >
                    <div className="flex items-start gap-3">
                      <div className="flex-1 min-w-0">
                        <p className={`text-sm ${!notification.read_at ? 'font-semibold text-[var(--color-text-primary)]' : 'text-[var(--color-text-secondary)]'}`}>
                          {notification.title || notification.body || 'Notification'}
                        </p>
                        {notification.body && notification.title && (
                          <p className="text-xs text-[var(--color-text-muted)] mt-1 truncate">
                            {notification.body}
                          </p>
                        )}
                        {notification.created_at && (
                          <p className="text-xs text-[var(--color-text-muted)] mt-1">
                            {new Date(notification.created_at).toLocaleDateString()}
                          </p>
                        )}
                      </div>
                      {!notification.read_at && (
                        <div className="flex-shrink-0">
                          <span className="w-2 h-2 bg-blue-500 rounded-full block mt-2" />
                        </div>
                      )}
                    </div>
                  </button>
                ))}
              </div>
            )}
          </div>

          {/* Footer */}
          {notifications.length > 0 && (
            <div className="px-4 py-2 border-t border-[var(--color-border-subtle)]">
              <button
                onClick={() => {
                  setIsOpen(false);
                  navigate('/app/notifications');
                }}
                className="text-sm text-[var(--color-semantic-primary-500)] hover:text-[var(--color-semantic-primary-600)] font-medium w-full text-center"
              >
                View all notifications
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

