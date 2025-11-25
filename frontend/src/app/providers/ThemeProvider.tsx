import React, { createContext, useContext, useEffect, useState, useMemo } from 'react';

type ColorMode = 'light' | 'dark';

interface ThemeContextType {
  mode: ColorMode;
  setMode: (mode: ColorMode) => void;
  toggleMode: () => void;
}

const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

const applyTheme = (mode: ColorMode) => {
  if (typeof window === 'undefined') return;
  
  const root = document.documentElement;
  root.setAttribute('data-theme', mode);
  root.classList.toggle('dark', mode === 'dark');
  
  // Sync with localStorage for Blade compatibility
  window.localStorage.setItem('theme', mode);
  window.localStorage.setItem('zenamanage.theme', mode);
};

const getInitialMode = (): ColorMode => {
  if (typeof window === 'undefined') return 'light';
  
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

const resolveNextMode = (current: ColorMode): ColorMode => {
  return current === 'light' ? 'dark' : 'light';
};

export const ThemeProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [mode, setMode] = useState<ColorMode>(() => getInitialMode());

  useEffect(() => {
    applyTheme(mode);
  }, [mode]);

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

  // Listen for system theme changes
  useEffect(() => {
    if (typeof window === 'undefined') return;
    
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

  return <ThemeContext.Provider value={contextValue}>{children}</ThemeContext.Provider>;
};

export const useTheme = () => {
  const context = useContext(ThemeContext);
  if (!context) {
    throw new Error('useTheme must be used within ThemeProvider');
  }
  return context;
};

