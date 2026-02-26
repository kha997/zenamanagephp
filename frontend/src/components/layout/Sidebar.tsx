import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import { cn } from '@/lib/utils';
import { useAuthStore } from '@/store/auth';
import {
  HomeIcon,
  FolderIcon,
  ClipboardDocumentListIcon,
  BellIcon,
  UserIcon,
  Cog6ToothIcon,
  ArrowRightOnRectangleIcon,
  DocumentTextIcon,
  BuildingOfficeIcon,
  WrenchScrewdriverIcon,
  CheckCircleIcon,
  ShoppingCartIcon,
  CurrencyDollarIcon,
  UserGroupIcon
} from '@/lib/heroicons';

interface SidebarProps {
  className?: string;
}

// Role-based navigation configuration
const getNavigationForRole = (userRoles: string[]) => {
  const baseNavigation = [
    { name: 'Dashboard', href: '/dashboard', icon: HomeIcon, roles: ['*'] },
    { name: 'Profile', href: '/profile', icon: UserIcon, roles: ['*'] },
    { name: 'Settings', href: '/settings/general', icon: Cog6ToothIcon, roles: ['*'] },
  ];

  const roleSpecificNavigation = [
    // Admin/SuperAdmin specific
    { name: 'Admin Dashboard', href: '/admin/dashboard', icon: BuildingOfficeIcon, roles: ['SuperAdmin', 'Admin'] },
    
    // PM specific
    { name: 'PM Dashboard', href: '/pm/dashboard', icon: FolderIcon, roles: ['PM'] },
    
    // Designer specific
    { name: 'Designer Dashboard', href: '/designer/dashboard', icon: DocumentTextIcon, roles: ['Designer'] },
    
    // Site Engineer specific
    { name: 'Site Engineer Dashboard', href: '/site-engineer/dashboard', icon: WrenchScrewdriverIcon, roles: ['SiteEngineer'] },
    
    // QC specific
    { name: 'QC Dashboard', href: '/qc/dashboard', icon: CheckCircleIcon, roles: ['QC'] },
    
    // Procurement specific
    { name: 'Procurement Dashboard', href: '/procurement/dashboard', icon: ShoppingCartIcon, roles: ['Procurement'] },
    
    // Finance specific
    { name: 'Finance Dashboard', href: '/finance/dashboard', icon: CurrencyDollarIcon, roles: ['Finance'] },
    
    // Client specific
    { name: 'Client Dashboard', href: '/client/dashboard', icon: UserGroupIcon, roles: ['Client'] },
    
    // Common features
    { name: 'Projects', href: '/projects', icon: FolderIcon, roles: ['PM', 'Designer', 'SiteEngineer', 'QC', 'SuperAdmin', 'Admin'] },
    { name: 'Tasks', href: '/tasks', icon: ClipboardDocumentListIcon, roles: ['PM', 'Designer', 'SiteEngineer', 'QC', 'SuperAdmin', 'Admin'] },
    { name: 'Change Requests', href: '/change-requests', icon: DocumentTextIcon, roles: ['PM', 'Designer', 'SiteEngineer', 'QC', 'SuperAdmin', 'Admin'] },
    { name: 'Interaction Logs', href: '/interaction-logs', icon: DocumentTextIcon, roles: ['PM', 'Designer', 'SiteEngineer', 'QC', 'SuperAdmin', 'Admin'] },
    { name: 'Templates', href: '/templates', icon: DocumentTextIcon, roles: ['PM', 'Designer', 'SuperAdmin', 'Admin'] },
    { name: 'Notifications', href: '/notifications', icon: BellIcon, roles: ['*'] },
  ];

  // Filter navigation based on user roles
  const allNavigation = [...baseNavigation, ...roleSpecificNavigation];
  
  return allNavigation.filter(item => 
    item.roles.includes('*') || 
    item.roles.some(role => userRoles.includes(role))
  );
};

export const Sidebar: React.FC<SidebarProps> = ({ className }) => {
  const location = useLocation();
  const { logout, user } = useAuthStore();

  const handleLogout = () => {
    logout();
  };

  // Get user roles for navigation filtering
  const userRoles = user?.roles?.map(role => role.name) || [];
  const navigation = getNavigationForRole(userRoles);

  return (
    <div className={cn('flex h-full w-64 flex-col bg-gray-900', className)}>
      {/* Logo */}
      <div className="flex h-16 shrink-0 items-center px-6">
        <h1 className="text-xl font-bold text-white">Z.E.N.A</h1>
      </div>

      {/* Navigation */}
      <nav className="flex flex-1 flex-col px-6 pb-4">
        <ul role="list" className="flex flex-1 flex-col gap-y-7">
          <li>
            <ul role="list" className="-mx-2 space-y-1">
              {navigation.map((item) => {
                const isActive = location.pathname === item.href;
                return (
                  <li key={item.name}>
                    <Link
                      to={item.href}
                      className={cn(
                        isActive
                          ? 'bg-gray-800 text-white'
                          : 'text-gray-400 hover:text-white hover:bg-gray-800',
                        'group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold'
                      )}
                    >
                      <item.icon className="h-6 w-6 shrink-0" aria-hidden="true" />
                      {item.name}
                    </Link>
                  </li>
                );
              })}
            </ul>
          </li>
          
          {/* Logout */}
          <li className="mt-auto">
            <button
              onClick={handleLogout}
              className="group -mx-2 flex w-full gap-x-3 rounded-md p-2 text-sm font-semibold leading-6 text-gray-400 hover:bg-gray-800 hover:text-white"
            >
              <ArrowRightOnRectangleIcon className="h-6 w-6 shrink-0" aria-hidden="true" />
              Logout
            </button>
          </li>
        </ul>
      </nav>
    </div>
  );
};

export default Sidebar;
