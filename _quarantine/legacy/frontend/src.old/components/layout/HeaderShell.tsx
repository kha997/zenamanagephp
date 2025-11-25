import React, { useState, useEffect, useRef } from 'react';
import { 
  BellIcon, 
  UserIcon, 
  Cog6ToothIcon, 
  MagnifyingGlassIcon,
  Bars3Icon,
  XMarkIcon,
  MoonIcon,
  SunIcon
} from '@heroicons/react/24/outline';
import { useAuthStore } from '@/store/auth';
import { useTheme } from '@/contexts/ThemeContext';
import { usePermissions } from '@/hooks/usePermissions';
import { cn } from '@/lib/utils';

interface NavItem {
  href: string;
  label: string;
  icon?: React.ComponentType<{ className?: string }>;
  requiredPermission?: string;
}

interface Notification {
  id: string;
  title: string;
  body: string;
  read: boolean;
  created_at: string;
}

interface HeaderShellProps {
  /** Navigation items to display in the primary nav */
  navigation?: NavItem[];
  /** Notifications for the user */
  notifications?: Notification[];
  /** Unread notification count */
  unreadCount?: number;
  /** Current tenant name */
  tenantName?: string;
  /** Whether to show search bar */
  showSearch?: boolean;
  /** Search placeholder text */
  searchPlaceholder?: string;
  /** Additional CSS classes */
  className?: string;
  /** Search callback */
  onSearch?: (query: string) => void;
  /** Notification click handler */
  onNotificationClick?: (notification: Notification) => void;
  /** Settings click handler */
  onSettingsClick?: () => void;
  /** Logout handler */
  onLogout?: () => void;
}

/**
 * HeaderShell - Standardized header component with RBAC, tenancy, search, and mobile support
 * 
 * Features:
 * - Role-based navigation filtering
 * - Theme toggle (light/dark/system)
 * - Tenant context display
 * - Global search with debounce
 * - Mobile hamburger menu
 * - Notifications with unread count
 * - User profile menu
 * - Full accessibility support
 */
export const HeaderShell: React.FC<HeaderShellProps> = ({
  navigation = [],
  notifications = [],
  unreadCount = 0,
  tenantName,
  showSearch = true,
  searchPlaceholder = 'Search...',
  className,
  onSearch,
  onNotificationClick,
  onSettingsClick,
  onLogout,
}) => {
  const { user, logout } = useAuthStore();
  const { theme, setTheme, actualTheme } = useTheme();
  const { hasPermission } = usePermissions();
  const mobileMenuId = 'header-mobile-menu';
  const notificationsPanelId = 'header-notifications-panel';
  const userMenuId = 'header-user-menu';
  
  // Mobile menu state
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [showNotifications, setShowNotifications] = useState(false);
  const [showUserMenu, setShowUserMenu] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  
  // Refs for focus management
  const mobileMenuRef = useRef<HTMLDivElement>(null);
  const notificationsRef = useRef<HTMLDivElement>(null);
  const userMenuRef = useRef<HTMLDivElement>(null);
  const searchInputRef = useRef<HTMLInputElement>(null);

  // Filter navigation items based on permissions
  const filteredNavigation = navigation.filter(item => {
    if (!item.requiredPermission) return true;
    return hasPermission(item.requiredPermission);
  });

  // Close mobile menu when clicking outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (mobileMenuRef.current && !mobileMenuRef.current.contains(event.target as Node)) {
        setMobileMenuOpen(false);
      }
      if (notificationsRef.current && !notificationsRef.current.contains(event.target as Node)) {
        setShowNotifications(false);
      }
      if (userMenuRef.current && !userMenuRef.current.contains(event.target as Node)) {
        setShowUserMenu(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Focus trap for mobile menu
  useEffect(() => {
    if (mobileMenuOpen && mobileMenuRef.current) {
      const focusableElements = mobileMenuRef.current.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      );
      const firstElement = focusableElements[0] as HTMLElement;
      const lastElement = focusableElements[focusableElements.length - 1] as HTMLElement;
      
      const handleTabKey = (e: KeyboardEvent) => {
        if (e.key !== 'Tab') return;
        
        if (e.shiftKey && document.activeElement === firstElement) {
          e.preventDefault();
          lastElement.focus();
        } else if (!e.shiftKey && document.activeElement === lastElement) {
          e.preventDefault();
          firstElement.focus();
        }
      };

      document.addEventListener('keydown', handleTabKey);
      firstElement?.focus();
      
      return () => document.removeEventListener('keydown', handleTabKey);
    }
  }, [mobileMenuOpen]);

  // Handle search with debounce
  useEffect(() => {
    if (!onSearch) return;
    
    const timer = setTimeout(() => {
      onSearch(searchQuery);
    }, 300);

    return () => clearTimeout(timer);
  }, [searchQuery, onSearch]);

  // Keyboard navigation handlers
  const handleLogout = async () => {
    setShowUserMenu(false);
    try {
      if (onLogout) {
        await onLogout();
      } else {
        await logout();
      }
    } catch (error) {
      console.error('Logout failed:', error);
    }
  };

  const handleSettingsClick = () => {
    setShowUserMenu(false);
    if (onSettingsClick) {
      onSettingsClick();
    }
  };

  return (
    <header 
      role="banner"
      aria-label="Global application header"
      data-testid="header-shell"
      data-source="react"
      className={cn(
        'sticky top-0 z-50 w-full bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 shadow-sm',
        className
      )}
    >
      <div className="mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex h-16 items-center justify-between">
          {/* Left side - Logo and Hamburger */}
          <div className="flex items-center space-x-4">
            {/* Mobile hamburger button */}
            <button
              type="button"
              onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
              className="inline-flex items-center justify-center rounded-md p-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
              aria-expanded={mobileMenuOpen}
              aria-controls={mobileMenuId}
              aria-haspopup="true"
              aria-label={mobileMenuOpen ? 'Close navigation menu' : 'Open navigation menu'}
            >
              {mobileMenuOpen ? (
                <XMarkIcon className="h-6 w-6" aria-hidden="true" />
              ) : (
                <Bars3Icon className="h-6 w-6" aria-hidden="true" />
              )}
            </button>

            {/* Logo */}
            <div className="flex-shrink-0">
              <h1 className="text-xl font-bold text-gray-900 dark:text-gray-100">
                ZenaManage
              </h1>
              {tenantName && (
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  {tenantName}
                </p>
              )}
            </div>
          </div>

          {/* Center - Search bar */}
          {showSearch && (
            <div className="hidden md:flex flex-1 max-w-md mx-4">
              <div className="relative w-full">
                <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                  <MagnifyingGlassIcon 
                    className="h-5 w-5 text-gray-400" 
                    aria-hidden="true" 
                  />
                </div>
                <input
                  ref={searchInputRef}
                  type="text"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder={searchPlaceholder}
                  className="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  aria-label="Search"
                />
              </div>
            </div>
          )}

          {/* Right side - Notifications, Theme Toggle, User Menu */}
          <div className="flex items-center space-x-2">
            {/* Theme Toggle */}
            <div className="relative" role="group" aria-label="Theme selector">
              <button
                type="button"
                onClick={() => setTheme(theme === 'light' ? 'dark' : theme === 'dark' ? 'system' : 'light')}
                className="p-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                aria-label={`Current theme: ${actualTheme}, click to change`}
                data-testid="theme-toggle"
              >
                {actualTheme === 'dark' ? (
                  <MoonIcon className="h-5 w-5" aria-hidden="true" />
                ) : (
                  <SunIcon className="h-5 w-5" aria-hidden="true" />
                )}
              </button>
            </div>

            {/* Notifications */}
            <div className="relative" ref={notificationsRef}>
              <button
                type="button"
                onClick={() => setShowNotifications(!showNotifications)}
                className="relative p-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                aria-label={`Notifications${unreadCount > 0 ? `, ${unreadCount} unread` : ''}`}
                aria-controls={notificationsPanelId}
                aria-expanded={showNotifications}
              >
                <BellIcon className="h-5 w-5" aria-hidden="true" />
                {unreadCount > 0 && (
                  <span className="absolute -top-1 -right-1 h-5 w-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center">
                    {unreadCount}
                  </span>
                )}
              </button>

              {/* Notifications dropdown */}
              {showNotifications && (
                <div
                  id={notificationsPanelId}
                  className="absolute right-0 mt-2 w-80 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50"
                  role="region"
                  aria-label="Notifications panel"
                >
                  <div className="p-2" role="menu" aria-orientation="vertical">
                    {notifications.length === 0 ? (
                      <div className="p-4 text-center text-sm text-gray-500 dark:text-gray-400">
                        No notifications
                      </div>
                    ) : (
                      notifications.map((notification) => (
                        <button
                          key={notification.id}
                          onClick={() => {
                            if (onNotificationClick) {
                              onNotificationClick(notification);
                            }
                            setShowNotifications(false);
                          }}
                          className="w-full text-left p-3 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 rounded-md focus:outline-none focus:bg-gray-50 dark:focus:bg-gray-700"
                          role="menuitem"
                        >
                          <div className={cn(
                            "font-medium",
                            !notification.read && "text-gray-900 dark:text-gray-100 font-semibold"
                          )}>
                            {notification.title}
                          </div>
                          <p className="text-gray-600 dark:text-gray-400 truncate">
                            {notification.body}
                          </p>
                        </button>
                      ))
                    )}
                  </div>
                </div>
              )}
            </div>

            {/* Settings */}
            {onSettingsClick && (
              <button
                type="button"
                onClick={handleSettingsClick}
                className="p-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                aria-label="Settings"
              >
                <Cog6ToothIcon className="h-5 w-5" aria-hidden="true" />
              </button>
            )}

            {/* User menu */}
            <div className="relative" ref={userMenuRef}>
              <button
                type="button"
                onClick={() => setShowUserMenu(!showUserMenu)}
                className="flex items-center space-x-2 p-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                aria-label={`User menu${user ? `, ${user.name}` : ''}`}
                aria-expanded={showUserMenu}
                aria-controls={userMenuId}
                aria-haspopup="true"
              >
                <div className="flex-shrink-0">
                  {user?.avatar ? (
                    <img
                      className="h-8 w-8 rounded-full"
                      src={user.avatar}
                      alt={user.name}
                    />
                  ) : (
                    <div className="h-8 w-8 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center">
                      <UserIcon className="h-5 w-5 text-gray-600 dark:text-gray-300" />
                    </div>
                  )}
                </div>
                <div className="hidden md:block text-left">
                  <div className="text-sm font-medium text-gray-900 dark:text-gray-100">
                    {user?.name || 'User'}
                  </div>
                  <div className="text-xs text-gray-500 dark:text-gray-400">
                    {user?.roles?.map(role => role.name).join(', ') || 'No roles'}
                  </div>
                </div>
              </button>

              {/* User dropdown */}
              {showUserMenu && (
                <div
                  id={userMenuId}
                  className="absolute right-0 mt-2 w-56 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50"
                  role="menu"
                  aria-label="User menu options"
                >
                  <div className="p-2" role="menu" aria-orientation="vertical">
                    <div className="px-3 py-2 border-b border-gray-200 dark:border-gray-700">
                      <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {user?.name || 'User'}
                      </p>
                      <p className="text-xs text-gray-500 dark:text-gray-400 truncate">
                        {user?.email || ''}
                      </p>
                    </div>
                    <button
                      onClick={handleLogout}
                      className="w-full text-left px-3 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-md focus:outline-none focus:bg-gray-50 dark:focus:bg-gray-700"
                      role="menuitem"
                    >
                      Sign out
                    </button>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Mobile Navigation Menu */}
        {mobileMenuOpen && (
          <div
            ref={mobileMenuRef}
            className="md:hidden border-t border-gray-200 dark:border-gray-700"
            role="navigation"
            aria-label="Mobile navigation"
            id={mobileMenuId}
          >
            <div className="space-y-1 px-2 pb-3 pt-2">
              {filteredNavigation.map((item, index) => (
                <a
                  key={index}
                  href={item.href}
                  className="flex items-center px-3 py-2 text-base font-medium text-gray-900 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-md"
                >
                  {item.icon && <item.icon className="mr-3 h-5 w-5" />}
                  {item.label}
                </a>
              ))}
            </div>
          </div>
        )}
      </div>
    </header>
  );
};

export default HeaderShell;

