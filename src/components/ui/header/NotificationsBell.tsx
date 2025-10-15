import React, { useState, useRef, useEffect, lazy, Suspense } from 'react';

// Lazy load the notifications overlay for better performance
const NotificationsOverlay = lazy(() => import('./NotificationsOverlay'));

export interface Notification {
  id: string;
  title: string;
  message: string;
  type: 'info' | 'success' | 'warning' | 'error';
  read: boolean;
  created_at: string;
  action_url?: string;
}

export interface NotificationsBellProps {
  notifications?: Notification[];
  unreadCount?: number;
  onMarkAsRead?: (notificationId: string) => void;
  onMarkAllAsRead?: () => void;
  onNotificationClick?: (notification: Notification) => void;
  className?: string;
}

export const NotificationsBell: React.FC<NotificationsBellProps> = ({
  notifications = [],
  unreadCount = 0,
  onMarkAsRead,
  onMarkAllAsRead,
  onNotificationClick,
  className = '',
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const bellRef = useRef<HTMLButtonElement>(null);
  const overlayRef = useRef<HTMLDivElement>(null);

  // Handle click outside to close
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (overlayRef.current && !overlayRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };

    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
    }

    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isOpen]);

  // Handle escape key
  useEffect(() => {
    const handleEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && isOpen) {
        setIsOpen(false);
        bellRef.current?.focus();
      }
    };

    document.addEventListener('keydown', handleEscape);
    return () => document.removeEventListener('keydown', handleEscape);
  }, [isOpen]);

  const handleBellClick = async () => {
    if (!isOpen) {
      setIsLoading(true);
      // Simulate loading notifications
      await new Promise(resolve => setTimeout(resolve, 300));
      setIsLoading(false);
    }
    setIsOpen(!isOpen);
  };

  const handleNotificationClick = (notification: Notification) => {
    if (!notification.read) {
      onMarkAsRead?.(notification.id);
    }
    onNotificationClick?.(notification);
    setIsOpen(false);
  };

  return (
    <div className={`relative ${className}`}>
      <button
        ref={bellRef}
        onClick={handleBellClick}
        className="header-action-btn relative"
        aria-expanded={isOpen}
        aria-haspopup="menu"
        aria-label={`Notifications${unreadCount > 0 ? `, ${unreadCount} unread` : ''}`}
      >
        <i className="fas fa-bell text-lg" aria-hidden="true" />
        
        {/* Unread Badge */}
        {unreadCount > 0 && (
          <span className="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-medium">
            {unreadCount > 99 ? '99+' : unreadCount}
          </span>
        )}

        {/* Loading Spinner */}
        {isLoading && (
          <span className="absolute inset-0 flex items-center justify-center">
            <i className="fas fa-spinner fa-spin text-sm" aria-hidden="true" />
          </span>
        )}
      </button>

      {/* Notifications Overlay */}
      {isOpen && (
        <div
          ref={overlayRef}
          className="absolute right-0 mt-2 w-80 bg-header-bg rounded-lg shadow-lg border border-header-border z-header-dropdown"
          role="menu"
          aria-label="Notifications"
        >
          <Suspense fallback={
            <div className="p-4 text-center">
              <i className="fas fa-spinner fa-spin text-header-fg-muted" aria-hidden="true" />
              <p className="text-sm text-header-fg-muted mt-2">Loading notifications...</p>
            </div>
          }>
            <NotificationsOverlay
              notifications={notifications}
              unreadCount={unreadCount}
              onMarkAsRead={onMarkAsRead}
              onMarkAllAsRead={onMarkAllAsRead}
              onNotificationClick={handleNotificationClick}
            />
          </Suspense>
        </div>
      )}
    </div>
  );
};

export default NotificationsBell;
