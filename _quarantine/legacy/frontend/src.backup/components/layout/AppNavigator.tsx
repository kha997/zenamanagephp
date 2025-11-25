import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAuthStore } from '../../shared/auth/store';
import { cn } from '../../shared/ui/utils';

interface NavItem {
  name: string;
  href: string;
  roles: string[];
}

interface AppNavigatorProps {
  className?: string;
}

// Role-based navigation configuration
const getNavigationForRole = (userRoles: string[]): NavItem[] => {
  const baseNavigation: NavItem[] = [
    { name: 'Dashboard', href: '/app/dashboard', roles: ['*'] },
    { name: 'Projects', href: '/app/projects', roles: ['*'] },
    { name: 'Tasks', href: '/app/tasks', roles: ['*'] },
  ];

  const roleSpecificNavigation: NavItem[] = [
    // Admin/SuperAdmin specific
    { name: 'Tenants', href: '/app/tenants', roles: ['SuperAdmin', 'Admin'] },
    
    // Common features
    { name: 'Templates', href: '/app/templates', roles: ['PM', 'Designer', 'SuperAdmin', 'Admin'] },
    { name: 'Change Requests', href: '/app/change-requests', roles: ['PM', 'SuperAdmin', 'Admin'] },
    { name: 'Quotes', href: '/app/quotes', roles: ['PM', 'SuperAdmin', 'Admin'] },
    { name: 'Clients', href: '/app/clients', roles: ['PM', 'SuperAdmin', 'Admin'] },
    { name: 'Users', href: '/app/users', roles: ['SuperAdmin', 'Admin'] },
    { name: 'Settings', href: '/app/settings', roles: ['*'] },
  ];

  // Filter navigation based on user roles
  const allNavigation = [...baseNavigation, ...roleSpecificNavigation];
  
  return allNavigation.filter(item => 
    item.roles.includes('*') || 
    item.roles.some(role => userRoles.includes(role))
  );
};

export const AppNavigator: React.FC<AppNavigatorProps> = ({ className }) => {
  const location = useLocation();
  const { user } = useAuthStore();

  // Get user roles for navigation filtering
  const userRoles = user?.roles?.map(role => role.name) || [];
  const navigation = getNavigationForRole(userRoles);

  return (
    <nav 
      className={cn(
        'bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 shadow-sm sticky top-16 z-40 h-12',
        className
      )}
      data-testid="primary-navigator"
      data-source="react-new"
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
                  'flex items-center px-4 py-3 text-sm font-medium transition-colors whitespace-nowrap relative',
                  isActive
                    ? 'text-blue-600 dark:text-blue-400 font-semibold'
                    : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-800'
                )}
                aria-current={isActive ? 'page' : 'false'}
              >
                <span>{item.name}</span>
                {isActive && (
                  <span className="absolute bottom-0 left-0 right-0 h-0.5 bg-blue-600 dark:bg-blue-400" aria-hidden="true" />
                )}
              </Link>
            );
          })}
        </div>
      </div>
    </nav>
  );
};

export default AppNavigator;

