import React, { createContext, useContext } from 'react';
import { createI18nProvider } from '../shared/i18n';

export interface I18nContextValue {
  t: (key: string, params?: Record<string, string | number>) => string;
  setLocale: (locale: string) => void;
  getLocale: () => string;
}

export const I18nContext = createContext<I18nContextValue | undefined>(undefined);

export const useI18n = (): I18nContextValue => {
  const context = useContext(I18nContext);
  if (!context) {
    throw new Error('useI18n phải được sử dụng trong I18nContext.Provider');
  }
  return context;
};

interface I18nProviderProps {
  children: React.ReactNode;
  locale?: string;
}

export const I18nProvider: React.FC<I18nProviderProps> = ({ children, locale = 'en' }) => {
  const [i18nProvider] = React.useState(() => createI18nProvider(locale));

  const contextValue = React.useMemo(
    () => ({
      t: (key: string, params?: Record<string, string | number>) => i18nProvider.t(key, params),
      setLocale: (newLocale: string) => i18nProvider.setLocale(newLocale),
      getLocale: () => i18nProvider.getLocale(),
    }),
    [i18nProvider],
  );

  return <I18nContext.Provider value={contextValue}>{children}</I18nContext.Provider>;
};
