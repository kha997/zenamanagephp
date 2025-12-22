import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import { cn } from '@/lib/utils';
import {
  HomeIcon,
  UsersIcon,
  FolderIcon,
  ShieldCheckIcon,
  Cog6ToothIcon,
} from '@heroicons/react/24/outline';

interface AdminSidebarProps {
  className?: string;
}

const adminNavigation = [
  { name: 'Dashboard', href: '/admin/dashboard', icon: HomeIcon },
  { name: 'Users', href: '/admin/users', icon: UsersIcon },
  { name: 'Roles & Permissions', href: '/admin/roles', icon: ShieldCheckIcon },
  { name: 'Tenants', href: '/admin/tenants', icon: FolderIcon },
  { name: 'System Settings', href: '/admin/settings', icon: Cog6ToothIcon },
];

export const AdminSidebar: React.FC<AdminSidebarProps> = ({ className }) => {
  const location = useLocation();

  return (
    <div className={cn('flex h-full w-64 flex-col bg-red-900', className)}>
      {/* Logo */}
      <div className="flex h-16 shrink-0 items-center px-6">
        <h1 className="text-xl font-bold text-white">Z.E.N.A Admin</h1>
      </div>

      {/* Navigation */}
      <nav className="flex flex-1 flex-col px-6 pb-4">
        <ul role="list" className="flex flex-1 flex-col gap-y-7">
          <li>
            <ul role="list" className="-mx-2 space-y-1">
              {adminNavigation.map((item) => {
                const isActive = location.pathname === item.href;
                return (
                  <li key={item.name}>
                    <Link
                      to={item.href}
                      className={cn(
                        isActive
                          ? 'bg-red-800 text-white'
                          : 'text-red-200 hover:text-white hover:bg-red-800',
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
        </ul>
      </nav>
    </div>
  );
};

export default AdminSidebar;
