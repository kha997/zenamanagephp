import React, { useEffect, useMemo, useState } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { RouterProvider } from 'react-router-dom';
import { Toaster } from 'react-hot-toast';
import { router } from './router';
import { ThemeContext } from './theme-context';
import { I18nProvider } from './i18n-context';
import { applyTheme, resolveNextMode, type ColorMode } from '../shared/tokens';
import { initializeAuth } from '../shared/auth/store';
import { AuthProvider } from '../contexts/AuthContext';

const getInitialMode = (): ColorMode => {
  if (typeof window === 'undefined') {
    return 'light';
  }

  // Prefer Blade header/localStorage convention if present
  const bladeStored = window.localStorage.getItem('theme');
  if (bladeStored === 'light' || bladeStored === 'dark') {
    return bladeStored as ColorMode;
  }
  const stored = window.localStorage.getItem('zenamanage.theme');
  if (stored === 'light' || stored === 'dark') {
    return stored;
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
};

export const AppShell: React.FC = () => {
  const [mode, setMode] = useState<ColorMode>(() => getInitialMode());
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            retry: 1,
            staleTime: 60_000,
            refetchOnWindowFocus: false,
            refetchOnMount: false, // Disable auto-refetch on mount to reduce initial load
            refetchOnReconnect: false, // Disable auto-refetch on reconnect
            // Use requestIdleCallback for batch updates
            networkMode: 'online',
          },
          mutations: {
            // Batch mutation retries to avoid setTimeout violations
            retry: 1,
            networkMode: 'online',
          },
        },
      }),
  );

  useEffect(() => {
    applyTheme(mode);
    if (typeof window !== 'undefined') {
      // Keep both keys in sync so Blade and React stay consistent
      window.localStorage.setItem('zenamanage.theme', mode);
      window.localStorage.setItem('theme', mode);
    }
  }, [mode]);

  useEffect(() => {
    // Initialize auth state from localStorage or session
    initializeAuth().catch((error) => {
      console.error('Failed to initialize auth:', error);
    });
  }, []);

  // Sync when Blade header toggles theme in another tab/view
  useEffect(() => {
    if (typeof window === 'undefined') return;
    const onStorage = (e: StorageEvent) => {
      if (e.key === 'theme' || e.key === 'zenamanage.theme') {
        const next = (e.newValue === 'dark' ? 'dark' : 'light') as ColorMode;
        setMode(next);
      }
    };
    window.addEventListener('storage', onStorage);
    return () => window.removeEventListener('storage', onStorage);
  }, []);

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

  return (
    <AuthProvider>
      <I18nProvider locale="en">
        <ThemeContext.Provider value={contextValue}>
          <QueryClientProvider client={queryClient}>
            <div className="min-h-screen bg-[var(--color-surface-base)] text-[var(--color-text-primary)]">
              <RouterProvider 
                router={router} 
                future={{
                  v7_startTransition: true,
                }}
              />
              <Toaster
                position="top-right"
                toastOptions={{
                  duration: 3000,
                  // Reduce animation duration to minimize setTimeout overhead
                  style: {
                    background: 'var(--color-surface-card)',
                    color: 'var(--color-text-primary)',
                    border: '1px solid var(--color-border-subtle)',
                    boxShadow: '0 12px 24px rgba(15, 23, 42, 0.12)',
                  },
                }}
                // Batch DOM updates to reduce reflows
                containerStyle={{
                  pointerEvents: 'none',
                }}
                // Reduce animation overhead
                reverseOrder={false}
              />
            </div>
          </QueryClientProvider>
        </ThemeContext.Provider>
      </I18nProvider>
    </AuthProvider>
  );
};

export default AppShell;
