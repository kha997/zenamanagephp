import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { HeaderShell } from '../src/components/ui/header/HeaderShell';
import { PrimaryNav } from '../src/components/ui/header/PrimaryNav';
import { UserMenu } from '../src/components/ui/header/UserMenu';
import { NotificationsBell } from '../src/components/ui/header/NotificationsBell';
import { SearchToggle } from '../src/components/ui/header/SearchToggle';
import { getMenuItems } from '../src/lib/menu/filterMenu';

// Get user data from Laravel
const userData = window.Laravel?.user || {
  id: '1',
  name: 'Test User',
  email: 'test@example.com',
  roles: ['admin'],
  tenant_id: 'tenant1',
  tenant: {
    id: 'tenant1',
    name: 'Test Tenant',
  },
};

// Get menu items
const menuItems = await getMenuItems(userData, userData.tenant);

// Logo component
const Logo = () => (
  <div className="flex items-center space-x-2">
    <div className="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
      <i className="fas fa-cube text-white text-sm" aria-hidden="true" />
    </div>
    <span className="text-xl font-bold text-gray-900">ZenaManage</span>
  </div>
);

// Header component
const Header = () => {
  const handleLogout = () => {
    // Handle logout
    window.location.href = '/logout';
  };

  const handleSearch = async () => {
    // Mock search implementation
    return [
      {
        id: '1',
        title: 'Test Result',
        description: 'This is a test search result',
        type: 'project' as const,
        url: '/app/projects/1',
      },
    ];
  };

  const handleNotificationClick = (notification: any) => {
    console.log('Notification clicked:', notification);
  };

  return (
    <HeaderShell
      theme="light"
      size="md"
      sticky={true}
      condensedOnScroll={true}
      logo={<Logo />}
      primaryNav={
        <PrimaryNav
          items={menuItems}
          currentUser={userData}
          mobile={false}
        />
      }
      secondaryActions={
        <div className="flex items-center space-x-2">
          <SearchToggle
            onSearch={handleSearch}
            placeholder="Search projects, tasks, users..."
          />
        </div>
      }
      userMenu={
        <UserMenu
          user={userData}
          onLogout={handleLogout}
          mobile={false}
        />
      }
      notifications={
        <NotificationsBell
          notifications={[]}
          unreadCount={0}
          onNotificationClick={handleNotificationClick}
        />
      }
    />
  );
};

// Mount header to DOM
const headerMount = document.getElementById('header-mount');
if (headerMount) {
  const root = createRoot(headerMount);
  root.render(
    <BrowserRouter>
      <Header />
    </BrowserRouter>
  );
}

// Export for potential use in other components
export { Header, Logo };
