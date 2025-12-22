import React from 'react';
import { NavLink } from 'react-router-dom';
import { useAuthStore } from '../../features/auth/store';

interface NavItem {
  path: string;
  label: string;
  icon?: React.ReactNode;
  roles?: string[];
  viewPermission?: string; // View permission required to see this item (Round 9)
  managePermission?: string; // Manage permission (if user has this, they can also view)
}

const navItems: NavItem[] = [
  {
    path: '/app/dashboard',
    label: 'Dashboard',
    viewPermission: 'tenant.view_analytics',
  },
  {
    path: '/app/projects',
    label: 'Projects',
  },
  {
    path: '/app/tasks',
    label: 'Tasks',
  },
  {
    path: '/app/documents',
    label: 'Documents',
    viewPermission: 'tenant.view_documents',
    managePermission: 'tenant.manage_documents',
  },
  {
    path: '/app/reports',
    label: 'Reports',
    viewPermission: 'tenant.view_reports',
    managePermission: 'tenant.manage_reports',
  },
  {
    path: '/app/settings',
    label: 'Settings',
    viewPermission: 'tenant.view_settings',
    managePermission: 'tenant.manage_settings',
  },
  {
    path: '/app/tenant/members',
    label: 'Members',
    viewPermission: 'tenant.view_members',
    managePermission: 'tenant.manage_members',
  },
  {
    path: '/app/contracts',
    label: 'Hợp đồng',
    viewPermission: 'tenant.view_contracts',
    managePermission: 'tenant.manage_contracts',
  },
];

export const AppNavigator: React.FC = () => {
  const { user, hasTenantPermission } = useAuthStore();

  const filteredItems = navItems.filter((item) => {
    // Filter by roles if specified
    if (item.roles) {
      if (!user?.role) return false;
      if (!item.roles.includes(user.role)) return false;
    }

    // Filter by view permissions (Round 9)
    if (item.viewPermission || item.managePermission) {
      const canView = 
        (item.viewPermission && hasTenantPermission(item.viewPermission)) ||
        (item.managePermission && hasTenantPermission(item.managePermission));
      
      if (!canView) return false;
    }

    return true;
  });

  return (
    <nav className="border-b border-[var(--color-border-subtle)] bg-[var(--color-surface-card)]">
      <div className="flex items-center gap-1 px-4 lg:px-8">
        {filteredItems.map((item) => (
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
        ))}
      </div>
    </nav>
  );
};

