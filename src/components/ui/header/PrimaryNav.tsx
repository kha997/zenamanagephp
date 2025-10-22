import React from 'react';
import { Link, useLocation } from 'react-router-dom';

export interface NavItem {
  id: string;
  label: string;
  icon?: string;
  to: string;
  roles?: string[];
  tenants?: string[];
  children?: NavItem[];
  badge?: {
    text: string;
    variant?: 'default' | 'success' | 'warning' | 'error';
  };
}

export interface PrimaryNavProps {
  items: NavItem[];
  currentUser?: {
    id: string;
    roles: string[];
    tenant_id: string;
  };
  className?: string;
  mobile?: boolean;
}

export const PrimaryNav: React.FC<PrimaryNavProps> = ({
  items,
  currentUser,
  className = '',
  mobile = false,
}) => {
  const location = useLocation();

  const isActive = (item: NavItem): boolean => {
    if (item.to === '/') {
      return location.pathname === '/';
    }
    return location.pathname.startsWith(item.to);
  };

  const hasPermission = (item: NavItem): boolean => {
    if (!currentUser) return false;
    
    const hasRolePermission = !item.roles || item.roles.length === 0 || item.roles.includes('*') || item.roles.some(role => currentUser.roles.includes(role));
    const hasTenantPermission = !item.tenants || item.tenants.length === 0 || item.tenants.includes('*') || item.tenants.includes(currentUser.tenant_id);
    
    return hasRolePermission && hasTenantPermission;
  };

  const renderNavItem = (item: NavItem) => {
    if (!hasPermission(item)) {
      return null;
    }

    const active = isActive(item);
    const baseClasses = mobile 
      ? 'block px-4 py-3 text-header-fg hover:text-nav-hover hover:bg-header-bg-hover rounded-lg transition-colors duration-200'
      : 'header-nav-item';

    return (
      <div key={item.id} className="relative">
        <Link
          to={item.to}
          className={`${baseClasses} ${active ? 'active' : ''}`}
          aria-current={active ? 'page' : undefined}
        >
          <div className="flex items-center space-x-2">
            {item.icon && (
              <i className={`fas fa-${item.icon} text-sm`} aria-hidden="true" />
            )}
            <span>{item.label}</span>
            {item.badge && (
              <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${
                item.badge.variant === 'success' ? 'bg-green-100 text-green-800' :
                item.badge.variant === 'warning' ? 'bg-yellow-100 text-yellow-800' :
                item.badge.variant === 'error' ? 'bg-red-100 text-red-800' :
                'bg-gray-100 text-gray-800'
              }`}>
                {item.badge.text}
              </span>
            )}
          </div>
        </Link>
        
        {/* Render children if any */}
        {item.children && item.children.length > 0 && (
          <div className="ml-4 mt-2 space-y-1">
            {item.children.map(renderNavItem)}
          </div>
        )}
      </div>
    );
  };

  const navClasses = mobile 
    ? `space-y-1 ${className}`
    : `flex items-center space-x-8 ${className}`;

  return (
    <div className={navClasses}>
      {items.map(renderNavItem)}
    </div>
  );
};

export default PrimaryNav;
