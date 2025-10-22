import React, { useState } from 'react';
import { NavLink, Outlet } from 'react-router-dom';
import { Button } from '../../shared/ui/button';
import { Badge } from '../../shared/ui/badge';
import { cn } from '../../shared/ui/utils';
import { useThemeMode } from '../theme-context';
import { useI18n } from '../i18n-context';

const navItems = [
  { to: '/app/dashboard', label: 'Dashboard', description: 'Widgets & KPIs' },
  { to: '/app/alerts', label: 'Alerts', description: 'Trung tâm cảnh báo' },
  { to: '/app/preferences', label: 'Preferences', description: 'Giao diện & bố cục' },
];

const MainLayout: React.FC = () => {
  const { mode, toggleMode } = useThemeMode();
  const { t } = useI18n();
  const [mobileOpen, setMobileOpen] = useState(false);

  return (
    <div className="relative min-h-screen bg-[var(--color-surface-base)] text-[var(--color-text-primary)] lg:grid lg:grid-cols-[260px_1fr]">
      <a
        href="#main-content"
        className="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 rounded-[var(--radius-md)] bg-[var(--color-semantic-primary-500)] px-3 py-2 text-sm font-medium text-[var(--color-semantic-primary-contrast)] shadow-lg"
      >
        Bỏ qua tới nội dung chính
      </a>

      {mobileOpen && (
        <button
          type="button"
          aria-label="Đóng menu"
          className="fixed inset-0 z-40 bg-black/40 lg:hidden"
          onClick={() => setMobileOpen(false)}
        />
      )}

      <aside
        className={cn(
          'z-50 flex h-full flex-col gap-6 border-r border-[var(--color-border-subtle)] bg-[var(--color-surface-card)] p-6 transition-transform lg:static lg:translate-x-0',
          mobileOpen ? 'fixed inset-y-0 left-0 w-64 translate-x-0 shadow-xl' : '-translate-x-full lg:flex',
        )}
        aria-label="Điều hướng ứng dụng"
      >
        <div className="flex items-center justify-between gap-2">
          <span className="text-lg font-semibold tracking-tight">ZenaManage</span>
          <Badge tone="success">{t('common.tenant', { defaultValue: 'Tenant' })}</Badge>
        </div>
        <nav className="flex flex-col gap-2 text-sm font-medium">
          {navItems.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              className={({ isActive }) =>
                cn(
                  'rounded-[var(--radius-md)] px-4 py-3 transition-colors',
                  isActive
                    ? 'bg-[var(--color-semantic-primary-50)] text-[var(--color-semantic-primary-700)]'
                    : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-muted)] hover:text-[var(--color-text-primary)]',
                )
              }
              onClick={() => setMobileOpen(false)}
            >
              <span className="block">{item.label}</span>
              <span className="text-xs font-normal text-[var(--color-text-muted)]">{item.description}</span>
            </NavLink>
          ))}
        </nav>
        <div className="mt-auto hidden flex-col gap-2 text-xs text-[var(--color-text-muted)] lg:flex">
          <span>Frontend v1 (draft)</span>
          <span>Perf budget: API &lt; 300ms, LCP &lt; 2.5s</span>
        </div>
        <Button variant="ghost" size="sm" className="mt-auto lg:hidden" onClick={() => setMobileOpen(false)}>
          Đóng
        </Button>
      </aside>

      <div className="flex min-h-screen flex-col">
        <header className="border-b border-[var(--color-border-subtle)] bg-[var(--color-surface-card)]">
          <div className="flex items-center justify-between gap-4 px-4 py-4 lg:px-8">
            <div className="flex items-center gap-3">
              <Badge tone="info">Preview</Badge>
              <div className="space-y-0.5">
                <h1 className="text-base font-semibold leading-tight">Frontend v1</h1>
                <p className="text-sm text-[var(--color-text-muted)]">
                  Chuẩn hoá AppShell, dashboard widgets, alerts center, preferences
                </p>
              </div>
            </div>
            <div className="flex items-center gap-2">
              <Button variant="ghost" size="sm" aria-label={t('common.toggleTheme', { defaultValue: 'Toggle theme' })} onClick={toggleMode}>
                {mode === 'light' ? t('common.darkMode', { defaultValue: 'Dark mode' }) : t('common.lightMode', { defaultValue: 'Light mode' })}
              </Button>
              <Button variant="primary" size="sm">
                Đồng bộ bố cục
              </Button>
              <Button
                variant="ghost"
                size="sm"
                className="lg:hidden"
                aria-label="Mở menu điều hướng"
                onClick={() => setMobileOpen(true)}
              >
                Menu
              </Button>
            </div>
          </div>
        </header>

        <main id="main-content" className="flex-1 overflow-y-auto px-4 py-8 lg:px-8">
          <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  );
};

export default MainLayout;
