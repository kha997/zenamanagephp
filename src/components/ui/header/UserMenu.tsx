import React, { useState, useRef, useEffect } from 'react';
import { Link } from 'react-router-dom';

export interface User {
  id: string;
  name: string;
  email: string;
  avatar?: string;
  role: string;
  tenant?: {
    id: string;
    name: string;
  };
}

export interface UserMenuProps {
  user: User;
  onLogout?: () => void;
  className?: string;
  mobile?: boolean;
}

export const UserMenu: React.FC<UserMenuProps> = ({
  user,
  onLogout,
  className = '',
  mobile = false,
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);
  const buttonRef = useRef<HTMLButtonElement>(null);

  // Handle click outside to close menu
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
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

  // Handle keyboard navigation
  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent) => {
      if (!isOpen) return;

      switch (event.key) {
        case 'Escape':
          setIsOpen(false);
          buttonRef.current?.focus();
          break;
        case 'ArrowDown':
          event.preventDefault();
          const firstItem = menuRef.current?.querySelector('[role="menuitem"]') as HTMLElement;
          firstItem?.focus();
          break;
        case 'ArrowUp':
          event.preventDefault();
          const lastItem = menuRef.current?.querySelector('[role="menuitem"]:last-child') as HTMLElement;
          lastItem?.focus();
          break;
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [isOpen]);

  const handleMenuKeyDown = (event: React.KeyboardEvent) => {
    const items = Array.from(menuRef.current?.querySelectorAll('[role="menuitem"]') || []);
    const currentIndex = items.indexOf(event.target as HTMLElement);

    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault();
        const nextIndex = (currentIndex + 1) % items.length;
        (items[nextIndex] as HTMLElement).focus();
        break;
      case 'ArrowUp':
        event.preventDefault();
        const prevIndex = currentIndex === 0 ? items.length - 1 : currentIndex - 1;
        (items[prevIndex] as HTMLElement).focus();
        break;
      case 'Home':
        event.preventDefault();
        (items[0] as HTMLElement).focus();
        break;
      case 'End':
        event.preventDefault();
        (items[items.length - 1] as HTMLElement).focus();
        break;
    }
  };

  const getInitials = (name: string): string => {
    return name
      .split(' ')
      .map(word => word.charAt(0))
      .join('')
      .toUpperCase()
      .slice(0, 2);
  };

  const menuItems = [
    {
      label: 'Profile',
      icon: 'user',
      to: '/app/profile',
      role: 'menuitem',
    },
    {
      label: 'Settings',
      icon: 'cog',
      to: '/app/settings',
      role: 'menuitem',
    },
    {
      label: 'Switch Tenant',
      icon: 'building',
      to: '/app/tenants',
      role: 'menuitem',
      condition: user.tenant,
    },
    {
      label: 'Help & Support',
      icon: 'question-circle',
      to: '/app/help',
      role: 'menuitem',
    },
    {
      label: 'Logout',
      icon: 'sign-out-alt',
      onClick: onLogout,
      role: 'menuitem',
      className: 'border-t border-header-border',
    },
  ].filter(item => !item.condition || item.condition);

  if (mobile) {
    return (
      <div className={`space-y-2 ${className}`}>
        {/* User Info */}
        <div className="flex items-center space-x-3 p-3 bg-header-bg-hover rounded-lg">
          <div className="header-user-avatar">
            {user.avatar ? (
              <img
                src={user.avatar}
                alt={user.name}
                className="w-full h-full rounded-full object-cover"
              />
            ) : (
              <span>{getInitials(user.name)}</span>
            )}
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-medium text-header-fg truncate">
              {user.name}
            </p>
            <p className="text-xs text-header-fg-muted truncate">
              {user.email}
            </p>
            {user.tenant && (
              <p className="text-xs text-header-fg-muted truncate">
                {user.tenant.name}
              </p>
            )}
          </div>
        </div>

        {/* Menu Items */}
        <div className="space-y-1">
          {menuItems.map((item, index) => (
            <div key={index}>
              {item.to ? (
                <Link
                  to={item.to}
                  className="flex items-center space-x-3 px-3 py-2 text-header-fg hover:text-nav-hover hover:bg-header-bg-hover rounded-lg transition-colors duration-200"
                  role={item.role}
                  tabIndex={0}
                >
                  <i className={`fas fa-${item.icon} w-4 text-center`} aria-hidden="true" />
                  <span>{item.label}</span>
                </Link>
              ) : (
                <button
                  onClick={item.onClick}
                  className={`flex items-center space-x-3 px-3 py-2 text-header-fg hover:text-nav-hover hover:bg-header-bg-hover rounded-lg transition-colors duration-200 w-full text-left ${item.className || ''}`}
                  role={item.role}
                  tabIndex={0}
                >
                  <i className={`fas fa-${item.icon} w-4 text-center`} aria-hidden="true" />
                  <span>{item.label}</span>
                </button>
              )}
            </div>
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className={`header-user-menu ${className}`}>
      <button
        ref={buttonRef}
        onClick={() => setIsOpen(!isOpen)}
        className="header-user-avatar"
        aria-expanded={isOpen}
        aria-haspopup="menu"
        aria-label="User menu"
      >
        {user.avatar ? (
          <img
            src={user.avatar}
            alt={user.name}
            className="w-full h-full rounded-full object-cover"
          />
        ) : (
          <span>{getInitials(user.name)}</span>
        )}
      </button>

      {isOpen && (
        <div
          ref={menuRef}
          className="header-dropdown"
          role="menu"
          aria-label="User menu"
          onKeyDown={handleMenuKeyDown}
        >
          {/* User Info */}
          <div className="px-4 py-3 border-b border-header-border">
            <p className="text-sm font-medium text-header-fg truncate">
              {user.name}
            </p>
            <p className="text-xs text-header-fg-muted truncate">
              {user.email}
            </p>
            {user.tenant && (
              <p className="text-xs text-header-fg-muted truncate">
                {user.tenant.name}
              </p>
            )}
          </div>

          {/* Menu Items */}
          {menuItems.map((item, index) => (
            <div key={index}>
              {item.to ? (
                <Link
                  to={item.to}
                  className={`header-dropdown-item ${item.className || ''}`}
                  role={item.role}
                  tabIndex={0}
                  onClick={() => setIsOpen(false)}
                >
                  <i className={`fas fa-${item.icon} mr-3`} aria-hidden="true" />
                  {item.label}
                </Link>
              ) : (
                <button
                  onClick={() => {
                    item.onClick?.();
                    setIsOpen(false);
                  }}
                  className={`header-dropdown-item ${item.className || ''}`}
                  role={item.role}
                  tabIndex={0}
                >
                  <i className={`fas fa-${item.icon} mr-3`} aria-hidden="true" />
                  {item.label}
                </button>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default UserMenu;
