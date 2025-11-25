import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { buildCssVars, type ColorMode } from '../tokens/colors';
import { buildTypographyCssVars } from '../tokens/typography';

type Theme = ColorMode;

type ThemeContextValue = {
  theme: Theme;
  setTheme: (t: Theme) => void;
  toggleTheme: () => void;
};

const THEME_STORAGE_KEY = 'ui.theme';

const ThemeContext = createContext<ThemeContextValue | undefined>(undefined);

function applyCssVars(mode: Theme) {
  const vars = buildCssVars(mode);
  const typographyVars = buildTypographyCssVars();
  const root = document.documentElement;
  root.setAttribute('data-theme', mode);
  Object.entries(vars).forEach(([k, v]) => root.style.setProperty(k, String(v)));
  Object.entries(typographyVars).forEach(([k, v]) => root.style.setProperty(k, String(v)));
}

function detectInitialTheme(): Theme {
  try {
    const stored = localStorage.getItem(THEME_STORAGE_KEY) as Theme | null;
    if (stored === 'light' || stored === 'dark') return stored;
  } catch {}
  if (typeof window !== 'undefined' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
    return 'dark';
  }
  return 'light';
}

export const ThemeProvider: React.FC<React.PropsWithChildren<{}>> = ({ children }) => {
  const [theme, _setTheme] = useState<Theme>(() => detectInitialTheme());

  useEffect(() => {
    applyCssVars(theme);
    try { localStorage.setItem(THEME_STORAGE_KEY, theme); } catch {}
  }, [theme]);

  useEffect(() => {
    const mq = window.matchMedia?.('(prefers-color-scheme: dark)');
    if (!mq) return;
    const handler = (e: MediaQueryListEvent) => {
      const stored = (() => { try { return localStorage.getItem(THEME_STORAGE_KEY); } catch { return null; } })();
      if (stored === 'light' || stored === 'dark') return; // user override exists
      _setTheme(e.matches ? 'dark' : 'light');
    };
    mq.addEventListener?.('change', handler);
    return () => mq.removeEventListener?.('change', handler);
  }, []);

  const setTheme = useCallback((t: Theme) => {
    _setTheme(t);
    try { localStorage.setItem(THEME_STORAGE_KEY, t); } catch {}
  }, []);

  const toggleTheme = useCallback(() => {
    setTheme(prev => (prev === 'light' ? 'dark' : 'light'));
  }, [setTheme]);

  const value = useMemo(() => ({ theme, setTheme, toggleTheme }), [theme, setTheme, toggleTheme]);

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
};

export function useTheme(): ThemeContextValue {
  const ctx = useContext(ThemeContext);
  if (!ctx) throw new Error('useTheme must be used within ThemeProvider');
  return ctx;
}

