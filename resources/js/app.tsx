import React, { useState, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { HeaderShell } from '../src/components/ui/header/HeaderShell';
import { PrimaryNav } from '../src/components/ui/header/PrimaryNav';
import { UserMenu } from '../src/components/ui/header/UserMenu';
import { NotificationsBell } from '../src/components/ui/header/NotificationsBell';
import { SearchToggle } from '../src/components/ui/header/SearchToggle';
import { getMenuItems } from '../src/lib/menu/filterMenu';

interface HeaderConfig {
  user: any;
  tenant: any;
  menuItems: any[];
  notifications: any[];
  unreadCount: number;
  breadcrumbs: any[];
  logoutUrl: string;
  csrfToken: string;
}

  // Initialize header function
window.initHeader = async function(config: HeaderConfig) {
  console.log('Initializing header...', config);
  
  const mountEl = document.getElementById('header-mount');
  if (!mountEl) {
    console.error('Header mount element not found');
    return;
  }

  // Get data from config
  const userData = config.user;
  const tenantData = config.tenant;
  const notificationsData = config.notifications || [];
  const unreadCount = config.unreadCount || 0;
  const breadcrumbsData = config.breadcrumbs || [];
  const logoutUrl = config.logoutUrl;
  const csrfToken = config.csrfToken;

  console.log('Header config:', { userData, tenantData, notificationsData, unreadCount });

  // Load menu items
  let menuItems = [];
  if (userData && tenantData) {
    try {
      menuItems = await getMenuItems(userData, tenantData);
      console.log('Loaded menu items:', menuItems);
    } catch (error) {
      console.error('Failed to load menu items:', error);
      menuItems = [];
    }
  }

  // Logo component
  const Logo = () => (
    <div className="flex items-center space-x-2">
      <div className="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
        <i className="fas fa-cube text-white text-sm" aria-hidden="true" />
      </div>
      <span className="text-xl font-bold text-gray-900 dark:text-white">ZenaManage</span>
    </div>
  );

  // Header component with theme support
  const Header = () => {
    const [theme, setTheme] = useState<'light' | 'dark'>('light');
    const [currentUser, setCurrentUser] = useState(userData);

    // Transform user data to match UserMenu interface
    const userForMenu = userData ? {
      id: userData.id,
      name: userData.name,
      email: userData.email,
      avatar: userData.avatar,
      role: userData.role || 'user',
      tenant: tenantData,
    } : null;

    // Load theme from localStorage
    useEffect(() => {
      const savedTheme = localStorage.getItem('theme') || 'light';
      setTheme(savedTheme as 'light' | 'dark');
      document.documentElement.setAttribute('data-theme', savedTheme);
      document.documentElement.classList.toggle('dark', savedTheme === 'dark');
    }, []);

    // Handle theme toggle
    const handleThemeToggle = () => {
      const newTheme = theme === 'light' ? 'dark' : 'light';
      setTheme(newTheme);
      localStorage.setItem('theme', newTheme);
      document.documentElement.setAttribute('data-theme', newTheme);
      document.documentElement.classList.toggle('dark', newTheme === 'dark');
    };

    // Handle logout
    const handleLogout = () => {
      // Create a form to submit logout request
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = logoutUrl;
      
      // Add CSRF token
      const csrfInput = document.createElement('input');
      csrfInput.type = 'hidden';
      csrfInput.name = '_token';
      csrfInput.value = csrfToken;
      form.appendChild(csrfInput);
      
      // Submit form
      document.body.appendChild(form);
      form.submit();
    };

    // Handle search
    const handleSearch = async (query: string) => {
      try {
        // Call search API
        const response = await fetch(`/api/v1/app/search?q=${encodeURIComponent(query)}`, {
          headers: {
            'Authorization': `Bearer ${getAuthToken()}`,
            'Accept': 'application/json',
          },
        });
        
        if (response.ok) {
          const data = await response.json();
          return data.data || [];
        }
        
        return [];
      } catch (error) {
        console.error('Search failed:', error);
        return [];
      }
    };

    const handleNotificationClick = (notification: any) => {
      console.log('Notification clicked:', notification);
      // Navigate to notification action_url if available
      if (notification.action_url) {
        window.location.href = notification.action_url;
      }
    };

    const handleMarkAsRead = async (notificationId: string) => {
      try {
        await fetch(`/api/v1/app/notifications/${notificationId}/read`, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${getAuthToken()}`,
            'Accept': 'application/json',
          },
        });
      } catch (error) {
        console.error('Failed to mark notification as read:', error);
      }
    };

    return (
      <BrowserRouter>
        <HeaderShell
          theme={theme}
          size="md"
          sticky={true}
          condensedOnScroll={true}
          logo={<Logo />}
          primaryNav={
            <PrimaryNav
              items={menuItems}
              currentUser={currentUser}
              mobile={false}
            />
          }
          secondaryActions={
            <div className="flex items-center space-x-2">
              <button
                onClick={handleThemeToggle}
                className="header-action-btn"
                aria-label="Toggle theme"
                title={`Switch to ${theme === 'light' ? 'dark' : 'light'} mode`}
              >
                <i className={`fas fa-${theme === 'light' ? 'moon' : 'sun'} text-lg`} aria-hidden="true" />
              </button>
              <SearchToggle
                onSearch={handleSearch}
                placeholder="Search projects, tasks, users..."
              />
            </div>
          }
          userMenu={
            userForMenu && (
              <UserMenu
                user={userForMenu}
                onLogout={handleLogout}
                mobile={false}
              />
            )
          }
          notifications={
            <NotificationsBell
              notifications={notificationsData}
              unreadCount={unreadCount}
              onNotificationClick={handleNotificationClick}
              onMarkAsRead={handleMarkAsRead}
            />
          }
        />
      </BrowserRouter>
    );
  };

  // Mount header to DOM
  const root = createRoot(mountEl);
  root.render(<Header />);
};

// Helper function to get auth token
function getAuthToken(): string {
  // Get token from meta tag or cookie
  const csrfToken = document.querySelector('meta[name="csrf-token"]');
  if (csrfToken) {
    return csrfToken.getAttribute('content') || '';
  }
  return '';
}
