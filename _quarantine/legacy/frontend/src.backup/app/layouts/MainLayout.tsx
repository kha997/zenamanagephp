import React from 'react';
import { Outlet, useNavigate } from 'react-router-dom';
import { Button } from '../../shared/ui/button';
import { useThemeMode } from '../theme-context';
import { useI18n } from '../i18n-context';
import { useAuthStore } from '../../shared/auth/store';
import { PrimaryNavigator } from '../../components/layout/PrimaryNavigator';
import { AppNavigator } from '../../components/layout/AppNavigator';
import { USE_NEW_NAVIGATION } from '../../config/navigation';
import { NotificationBell } from '../../components/shared/NotificationBell';

const MainLayout: React.FC = () => {
  const { mode, toggleMode } = useThemeMode();
  const { t } = useI18n();
  const { logout, user } = useAuthStore();
  const navigate = useNavigate();

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  return (
    <div className="relative min-h-screen bg-[var(--color-surface-base)] text-[var(--color-text-primary)]">
      {/* Debug banner: shows theme and html.dark presence */}
      {process.env.NODE_ENV !== 'production' && (
        <div
          id="__debug-theme-banner"
          className="fixed bottom-4 right-4 z-[1000] rounded-md border border-[var(--color-border-subtle)] bg-[var(--color-surface-card)] px-3 py-2 text-xs text-[var(--color-text-secondary)] shadow-lg"
        >
          <script dangerouslySetInnerHTML={{__html: `
            (function(){
              function update(){
                var ls = localStorage.getItem('theme') || localStorage.getItem('zenamanage.theme') || 'unset';
                var hasDark = document.documentElement.classList.contains('dark');
                var el = document.getElementById('__debug-theme-banner');
                if(el){ el.setAttribute('data-theme', String(ls)); el.setAttribute('data-html-dark', String(hasDark));
                  el.innerText = 'theme=' + ls + ' | html.dark=' + hasDark; }
              }
              window.addEventListener('storage', update);
              var mo = new MutationObserver(update);
              mo.observe(document.documentElement, { attributes: true, attributeFilter: ['class','data-theme']});
              update();
            })();
          `}} />
        </div>
      )}
      <a
        href="#main-content"
        className="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 rounded-[var(--radius-md)] bg-[var(--color-semantic-primary-500)] px-3 py-2 text-sm font-medium text-[var(--color-semantic-primary-contrast)] shadow-lg"
      >
        Bỏ qua tới nội dung chính
      </a>

      <div className="flex flex-col min-h-screen">
        {/* Header và Navigator - Fixed khi scroll */}
        <div className="sticky top-0 z-50">
          <header className="border-b border-[var(--color-border-subtle)] bg-[var(--color-surface-card)]">
            <div className="flex items-center justify-between gap-4 py-4 px-4 lg:px-8">
            <div className="flex items-center gap-3">
              <h1 className="text-base font-semibold leading-tight">ZenaManage</h1>
            </div>
            <div className="flex items-center gap-4 flex-1 justify-end">
              <span className="text-sm text-[var(--color-text-secondary)] hidden md:block">
                Xin chào, <span className="font-medium text-[var(--color-text-primary)]">{user?.name || 'User'}</span>
              </span>
              <NotificationBell />
              <Button variant="ghost" size="sm" aria-label={t('common.toggleTheme', { defaultValue: 'Toggle theme' })} onClick={toggleMode}>
                {mode === 'light' ? t('common.darkMode', { defaultValue: 'Dark mode' }) : t('common.lightMode', { defaultValue: 'Light mode' })}
              </Button>
              <Button variant="outline" size="sm" onClick={handleLogout}>
                Logout
              </Button>
            </div>
          </div>
        </header>

        {/* Primary Navigator */}
        {USE_NEW_NAVIGATION ? <AppNavigator /> : <PrimaryNavigator />}
        </div>

        <main id="main-content" className="flex-1 overflow-y-auto py-8 px-4 lg:px-8">
          <div className="flex w-full flex-col gap-6 max-w-7xl mx-auto">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  );
};

export default MainLayout;

// Export useSidebar for backwards compatibility (if needed)
export const useSidebar = () => ({ collapsed: false });
