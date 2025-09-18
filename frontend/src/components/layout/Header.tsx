import React from 'react';
import { useAuthStore } from '@/store/auth';
import { BellIcon, UserIcon, Cog6ToothIcon } from '@heroicons/react/24/outline';
import { cn } from '@/lib/utils';

interface HeaderProps {
  className?: string;
}

export const Header: React.FC<HeaderProps> = ({ className }) => {
  const { user } = useAuthStore();

  return (
    <header className={cn('bg-white shadow-sm border-b border-gray-200', className)}>
      <div className="flex h-16 items-center justify-between px-6">
        {/* Left side - could add breadcrumbs or page title here */}
        <div className="flex items-center">
          <h2 className="text-lg font-semibold text-gray-900">
            Welcome back, {user?.name || 'User'}
          </h2>
        </div>

        {/* Right side - user menu and notifications */}
        <div className="flex items-center space-x-4">
          {/* Notifications */}
          <button className="relative p-2 text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded-md">
            <BellIcon className="h-6 w-6" />
            {/* Notification badge */}
            <span className="absolute -top-1 -right-1 h-4 w-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center">
              3
            </span>
          </button>

          {/* Settings */}
          <button className="p-2 text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded-md">
            <Cog6ToothIcon className="h-6 w-6" />
          </button>

          {/* User profile */}
          <div className="flex items-center space-x-3">
            <div className="flex-shrink-0">
              {user?.avatar ? (
                <img
                  className="h-8 w-8 rounded-full"
                  src={user.avatar}
                  alt={user.name}
                />
              ) : (
                <div className="h-8 w-8 rounded-full bg-gray-300 flex items-center justify-center">
                  <UserIcon className="h-5 w-5 text-gray-600" />
                </div>
              )}
            </div>
            <div className="hidden md:block">
              <div className="text-sm font-medium text-gray-900">{user?.name}</div>
              <div className="text-xs text-gray-500">
                {user?.roles?.map(role => role.name).join(', ') || 'No roles'}
              </div>
            </div>
          </div>
        </div>
      </div>
    </header>
  );
};

export default Header;
