import React, { useMemo } from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import { useAuthStore } from '../../features/auth/store';

interface NavItem {
  path: string;
  label: string;
  isBladeRoute?: boolean; // If true, use absolute link to Blade backend
}

// Get admin base URL from environment or use current origin
const getAdminBaseUrl = (): string => {
  // In production with single-origin, use same origin
  // In dev, can use env var or default to Laravel backend
  const adminBaseUrl = import.meta.env.VITE_ADMIN_BASE_URL || window.location.origin;
  return adminBaseUrl;
};

const navItems: NavItem[] = [
  {
    path: '/admin/dashboard',
    label: 'Dashboard',
    isBladeRoute: false, // React route
  },
  {
    path: '/admin/users',
    label: 'Users',
    isBladeRoute: false, // React route
  },
  {
    path: '/admin/members',
    label: 'Members',
    isBladeRoute: true, // Blade route
  },
  {
    path: '/admin/roles',
    label: 'Roles',
    isBladeRoute: true, // Blade route
  },
  {
    path: '/admin/tenants',
    label: 'Tenants',
    isBladeRoute: true, // Blade route
  },
];

export const AdminNavigator: React.FC = () => {
  const location = useLocation();
  const adminBaseUrl = getAdminBaseUrl();
  const { hasTenantPermission } = useAuthStore();

  // Filter nav items based on tenant permissions
  const visibleNavItems = useMemo(() => {
    return navItems.filter((item) => {
      // Members link requires tenant.manage_members permission
      if (item.path === '/admin/members') {
        return hasTenantPermission('tenant.manage_members');
      }
      // Other items are always visible (or can be gated later)
      return true;
    });
  }, [hasTenantPermission]);

  return (
    <nav className="border-b border-[var(--color-border-subtle)] bg-[var(--color-surface-card)]">
      <div className="flex items-center gap-1 px-4 lg:px-8">
        {visibleNavItems.map((item) => {
          // For Blade routes, use absolute link
          if (item.isBladeRoute) {
            const href = `${adminBaseUrl}${item.path}`;
            const isActive = location.pathname === item.path || 
                           (item.path !== '/admin/dashboard' && location.pathname.startsWith(item.path));
            
            return (
              <a
                key={item.path}
                href={href}
                className={`px-4 py-2 text-sm font-medium transition-colors ${
                  isActive
                    ? 'border-b-2 border-blue-500 text-blue-600'
                    : 'text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]'
                }`}
              >
                {item.label}
              </a>
            );
          }

          // For React routes, use NavLink
          return (
            <NavLink
              key={item.path}
              to={item.path}
              className={({ isActive }) =>
                `px-4 py-2 text-sm font-medium transition-colors ${
                  isActive
                    ? 'border-b-2 border-blue-500 text-blue-600'
                    : 'text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]'
                }`
              }
            >
              {item.label}
            </NavLink>
          );
        })}
      </div>
    </nav>
  );
};

