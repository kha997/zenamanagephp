import React, { useEffect, useMemo, useRef, useState } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { RouterProvider } from 'react-router-dom';
import { Toaster } from 'react-hot-toast';
import { router } from './router';
import { ThemeContext } from './theme-context';
import { I18nProvider } from './i18n-context';
import { applyTheme, resolveNextMode, type ColorMode } from '../shared/tokens';
import { AuthProvider } from '../contexts/AuthContext';
import { useAuthStore } from '../features/auth/store';
import { initSharedAuthAdapter } from '../shared/auth/store';
import { ToastProvider } from '../shared/ui/toast';

const getInitialMode = (): ColorMode => {
  if (typeof window === 'undefined') {
    return 'light';
  }

  const stored = window.localStorage.getItem('zenamanage.theme');
  if (stored === 'light' || stored === 'dark') {
    return stored;
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
};

export const AppShell: React.FC = () => {
  // Round 136: Initialize E2E logs array immediately (before any async operations)
  if (typeof window !== 'undefined') {
    if (!(window as any).__e2e_logs) {
      (window as any).__e2e_logs = [];
    }
    // Log component render (runs on every render, but that's ok for debugging)
    (window as any).__e2e_logs.push('[AppShell] component rendered');
  }

  const didBoot = useRef(false);

  // Round 140: E2E hook - set up event listener early (separate from boot logic)
  useEffect(() => {
    if (typeof window === 'undefined') return;

    const handleForceCheckAuth = () => {
      try {
        const w = window as any;
        const logs = (w.__e2e_logs = w.__e2e_logs ?? []);
        logs.push({
          scope: 'app-shell',
          event: 'force-check-auth',
        });
        useAuthStore.getState().checkAuth().catch((error) => {
          const errorLogs = (w.__e2e_logs = w.__e2e_logs ?? []);
          errorLogs.push({
            scope: 'app-shell',
            event: 'force-check-auth-error',
            error: String(error),
          });
          console.error('[AppShell] force-check-auth error:', error);
        });
      } catch (error) {
        const w = window as any;
        const logs = (w.__e2e_logs = w.__e2e_logs ?? []);
        logs.push({
          scope: 'app-shell',
          event: 'force-check-auth-error',
          error: String(error),
        });
        console.error('[AppShell] force-check-auth handler error:', error);
      }
    };

    window.addEventListener('zena:e2e:check-auth', handleForceCheckAuth);

    return () => {
      window.removeEventListener('zena:e2e:check-auth', handleForceCheckAuth);
    };
  }, []);

  // Round 136: Initialize shared auth adapter and boot effect
  useEffect(() => {
    if (didBoot.current) return;
    didBoot.current = true;

    // Round 155: Ensure E2E logs array exists and log mount event with timestamp
    if (typeof window !== 'undefined') {
      if (!(window as any).__e2e_logs) {
        (window as any).__e2e_logs = [];
      }
      // Round 155: Log app-shell mounted event with timestamp
      (window as any).__e2e_logs.push({
        scope: 'app-shell',
        event: 'mounted',
        url: window.location.href,
        ts: Date.now(),
      });
    }

    // Round 136: Initialize shared auth adapter (deferred from module-level)
    initSharedAuthAdapter();

    // Round 154: Always call checkAuth() after mount, regardless of token presence
    // This allows Sanctum cookie-based auth to work after UI login
    const token = typeof window !== 'undefined' ? localStorage.getItem('auth_token') : null;
    const tokenPresent = !!token;
    if (typeof window !== 'undefined') {
      (window as any).__e2e_logs.push({
        scope: 'app-shell',
        event: 'boot',
        tokenPresent,
        url: window.location.href,
        ts: Date.now(),
      });

      // Round 154: Unconditionally call checkAuth() - it will use cookies if no token
      (window as any).__e2e_logs.push({
        scope: 'app-shell',
        event: 'calling-check-auth',
        reason: 'boot-after-mount',
        ts: Date.now(),
      });
      // Round 134: Log ME request starting for E2E visibility
      (window as any).__e2e_logs.push({
        scope: 'app-shell',
        event: 'me-request-starting',
        ts: Date.now(),
      });
    }
    
    const { checkAuth } = useAuthStore.getState();
    checkAuth().catch((error) => {
      if (typeof window !== 'undefined') {
        (window as any).__e2e_logs.push({
          scope: 'app-shell',
          event: 'check-auth-error',
          error: String(error),
          ts: Date.now(),
        });
      }
      console.error('[AppShell] boot checkAuth failed:', error);
    });
  }, []);

  const [mode, setMode] = useState<ColorMode>(() => getInitialMode());
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            retry: 1,
            staleTime: 60_000,
            refetchOnWindowFocus: false,
          },
        },
      }),
  );

  useEffect(() => {
    applyTheme(mode);
    if (typeof window !== 'undefined') {
      window.localStorage.setItem('zenamanage.theme', mode);
    }
  }, [mode]);

  useEffect(() => {
    if (typeof window === 'undefined') {
      return undefined;
    }

    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const handler = (event: MediaQueryListEvent) => {
      const stored = window.localStorage.getItem('zenamanage.theme');
      if (!stored) {
        setMode(event.matches ? 'dark' : 'light');
      }
    };

    media.addEventListener('change', handler);
    return () => media.removeEventListener('change', handler);
  }, []);

  const contextValue = useMemo(
    () => ({
      mode,
      setMode,
      toggleMode: () => setMode((prev) => resolveNextMode(prev)),
    }),
    [mode],
  );

  // Round 155: Add data-testid marker for E2E testing
  // Round 258: Add ToastProvider for notification toasts
  return (
    <div data-testid="app-shell-root">
      <AuthProvider>
        <I18nProvider locale="en">
          <ThemeContext.Provider value={contextValue}>
            <QueryClientProvider client={queryClient}>
              <ToastProvider>
                <div className="min-h-screen bg-[var(--color-surface-base)] text-[var(--color-text-primary)]">
                  <RouterProvider router={router} />
                  <Toaster
                    position="top-right"
                    toastOptions={{
                      duration: 3500,
                      style: {
                        background: 'var(--color-surface-card)',
                        color: 'var(--color-text-primary)',
                        border: '1px solid var(--color-border-subtle)',
                        boxShadow: '0 12px 24px rgba(15, 23, 42, 0.12)',
                      },
                    }}
                  />
                </div>
              </ToastProvider>
            </QueryClientProvider>
          </ThemeContext.Provider>
        </I18nProvider>
      </AuthProvider>
    </div>
  );
};

export default AppShell;
