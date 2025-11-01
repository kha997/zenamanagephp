import { createContext, useContext } from 'react';
import type { ColorMode } from '../shared/tokens';

export interface ThemeContextValue {
  mode: ColorMode;
  setMode: (mode: ColorMode) => void;
  toggleMode: () => void;
}

export const ThemeContext = createContext<ThemeContextValue | undefined>(undefined);

export const useThemeMode = (): ThemeContextValue => {
  const context = useContext(ThemeContext);
  if (!context) {
    throw new Error('useThemeMode phải được sử dụng trong ThemeContext.Provider');
  }
  return context;
};
