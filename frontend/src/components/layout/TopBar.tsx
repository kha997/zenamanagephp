import React from 'react';
import { Bell, Search, User, Settings, LogOut } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/Button';
import { useAuth } from '@/hooks/useAuth';
import { useNotificationsStore } from '@/store/notifications';

/**
 * TopBar component - Header navigation bar cho admin layout
 * Hiển thị search, notifications, user menu và logout
 * 
 * Round 251: Added notifications bell icon with unread count
 * Round 257: Updated to use notification store for real-time unread count
 */
export function TopBar() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  
  // Get unread count from notification store (reactively updates with real-time notifications)
  const unreadCount = useNotificationsStore((state) => state.unreadCount);

  const handleLogout = () => {
    logout();
  };

  const handleNotificationsClick = () => {
    navigate('/app/notifications');
  };

  return (
    <header className="sticky top-0 z-50 bg-white shadow-sm border-b border-gray-200 px-6 py-4">
      <div className="flex items-center justify-between">
        {/* Left side - Search */}
        <div className="flex items-center flex-1 max-w-md">
          <div className="relative w-full">
            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
            <input
              type="text"
              placeholder="Tìm kiếm..."
              className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
        </div>

        {/* Right side - Notifications and User Menu */}
        <div className="flex items-center space-x-4">
          {/* Notifications - Round 251 */}
          <Button 
            variant="ghost" 
            size="sm" 
            className="relative"
            onClick={handleNotificationsClick}
          >
            <Bell className="h-5 w-5" />
            {unreadCount > 0 && (
              <span className="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-semibold">
                {unreadCount > 99 ? '99+' : unreadCount}
              </span>
            )}
          </Button>

          {/* User Menu */}
          <div className="flex items-center space-x-3">
            <div className="text-right">
              <p className="text-sm font-medium text-gray-900">{user?.name || 'User'}</p>
              <p className="text-xs text-gray-500">{user?.email || 'user@example.com'}</p>
            </div>
            
            <div className="flex items-center space-x-2">
              <Button variant="ghost" size="sm">
                <User className="h-4 w-4" />
              </Button>
              
              <Button variant="ghost" size="sm">
                <Settings className="h-4 w-4" />
              </Button>
              
              <Button 
                variant="ghost" 
                size="sm" 
                onClick={handleLogout}
                className="text-red-600 hover:text-red-700 hover:bg-red-50"
              >
                <LogOut className="h-4 w-4" />
              </Button>
            </div>
          </div>
        </div>
      </div>
    </header>
  );
}