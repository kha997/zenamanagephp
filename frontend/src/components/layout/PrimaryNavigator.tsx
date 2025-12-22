import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAuthStore } from '@/store/auth';
import { cn } from '@/lib/utils';
import {
  HomeIcon,
  FolderIcon,
  ClipboardDocumentListIcon,
  DocumentTextIcon,
  ExclamationTriangleIcon,
  BuildingOfficeIcon,
  WrenchScrewdriverIcon,
  CheckCircleIcon,
  ShoppingCartIcon,
  CurrencyDollarIcon,
  UserGroupIcon,
  Cog6ToothIcon,
  ClockIcon
} from '@heroicons/react/24/outline';

interface NavItem {
  name: string;
  href: string;
  icon: React.ComponentType<{ className?: string }>;
  roles: string[];
}

interface PrimaryNavigatorProps {
  className?: string;
}

// Role-based navigation configuration
const getNavigationForRole = (userRoles: string[]): NavItem[] => {
  const baseNavigation: NavItem[] = [
    { name: 'Dashboard', href: '/app/dashboard', icon: HomeIcon, roles: ['*'] },
    { name: 'Projects', href: '/app/projects', icon: FolderIcon, roles: ['*'] },
    { name: 'Tasks', href: '/app/tasks', icon: ClipboardDocumentListIcon, roles: ['*'] },
    { name: 'Activity', href: '/app/activity', icon: ClockIcon, roles: ['*'] },
  ];

  const roleSpecificNavigation: NavItem[] = [
    // Admin/SuperAdmin specific
    { name: 'Tenants', href: '/app/tenants', icon: BuildingOfficeIcon, roles: ['SuperAdmin', 'Admin'] },
    
    // Common features
    { name: 'Templates', href: '/app/templates', icon: DocumentTextIcon, roles: ['PM', 'Designer', 'SuperAdmin', 'Admin'] },
    { name: 'Change Requests', href: '/app/change-requests', icon: ExclamationTriangleIcon, roles: ['PM', 'SuperAdmin', 'Admin'] },
    { name: 'Users', href: '/app/users', icon: UserGroupIcon, roles: ['SuperAdmin', 'Admin'] },
    { name: 'Settings', href: '/app/settings', icon: Cog6ToothIcon, roles: ['*'] },
  ];

  // Filter navigation based on user roles
  const allNavigation = [...baseNavigation, ...roleSpecificNavigation];
  
  return allNavigation.filter(item => 
    item.roles.includes('*') || 
    item.roles.some(role => userRoles.includes(role))
  );
};

export const PrimaryNavigator: React.FC<PrimaryNavigatorProps> = ({ className }) => {
  const location = useLocation();
  const { user } = useAuthStore();

  // Get user roles for navigation filtering
  const userRoles = user?.roles?.map(role => role.name) || [];
  const navigation = getNavigationForRole(userRoles);

  return (
    <nav 
      className={cn(
        'bg-white border-b border-gray-200 shadow-sm sticky top-16 z-40 h-12',
        className
      )}
      data-testid="primary-navigator"
      data-source="react"
      aria-label="Primary navigation"
    >
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center overflow-x-auto">
          {navigation.map((item) => {
            const isActive = location.pathname.startsWith(item.href);
            return (
              <Link
                key={item.name}
                to={item.href}
                className={cn(
                  'flex items-center gap-2 px-4 py-3 text-sm font-medium transition-colors whitespace-nowrap',
                  isActive
                    ? 'border-b-2 border-blue-600 text-blue-600 bg-blue-50'
                    : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'
                )}
              >
                <item.icon className="h-5 w-5" aria-hidden="true" />
                <span className="hidden sm:inline">{item.name}</span>
              </Link>
            );
          })}
        </div>
      </div>
    </nav>
  );
};

export default PrimaryNavigator;

