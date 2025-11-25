import React, { createContext, useContext, useMemo, useState, useEffect } from 'react';
import { getTranslations, type GetTranslationsOptions } from '../../shared/api/i18n';

interface I18nContextType {
  locale: string;
  t: (key: string, params?: Record<string, string | number>) => string;
  loading: boolean;
  error: Error | null;
}

const I18nContext = createContext<I18nContextType | undefined>(undefined);

const readLocale = (): string => {
  if (typeof window === 'undefined') return 'en';
  return window.Laravel?.locale ?? document.documentElement.lang ?? 'en';
};

// Default namespaces to load (can be configured)
const DEFAULT_NAMESPACES = ['app', 'settings', 'tasks', 'projects', 'dashboard', 'auth'];

export const I18nProvider: React.FC<{ 
  children: React.ReactNode;
  locale?: string;
  namespaces?: string[];
}> = ({ children, locale: propLocale, namespaces = DEFAULT_NAMESPACES }) => {
  // Use state to store locale and only update when propLocale changes or on mount
  const [locale, setLocale] = useState(() => propLocale ?? readLocale());
  const [translations, setTranslations] = useState<Record<string, Record<string, string>>>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);
  const [etag, setEtag] = useState<string | null>(null);
  
  // Load translations when locale changes
  useEffect(() => {
    const loadTranslations = async () => {
      setLoading(true);
      setError(null);
      
      try {
        const options: GetTranslationsOptions = {
          locale,
          namespaces,
          flat: false, // Use nested structure
        };
        
        const response = await getTranslations(options, etag);
        
        if (response.success && response.data) {
          setTranslations(response.data);
          // Extract ETag from response headers if available
          // Note: axios doesn't expose response headers by default, 
          // but we can cache based on response data hash
          setEtag(null); // Will be set from response if available
        }
      } catch (err: any) {
        // Handle NOT_MODIFIED (304) - use cached translations
        if (err.isNotModified || err.message === 'NOT_MODIFIED') {
          // Keep existing translations, no error
          setLoading(false);
          return;
        }
        console.error('Failed to load translations:', err);
        setError(err);
        setLoading(false);
      }
    };
    
    loadTranslations();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [locale, namespaces.join(',')]); // Reload when locale or namespaces change
  
  // Update locale when propLocale changes or when window.Laravel.locale changes
  useEffect(() => {
    if (propLocale !== undefined) {
      setLocale(propLocale);
    } else {
      // Read from window.Laravel.locale or document.documentElement.lang on mount and when they change
      const currentLocale = readLocale();
      if (currentLocale !== locale) {
        setLocale(currentLocale);
      }
    }
  }, [propLocale, locale]);
  
  // Listen for locale changes from document.documentElement.lang
  useEffect(() => {
    const observer = new MutationObserver(() => {
      const newLocale = readLocale();
      if (newLocale !== locale) {
        setLocale(newLocale);
      }
    });
    
    observer.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ['lang'],
    });
    
    return () => observer.disconnect();
  }, [locale]);
  
  // Translation function
  const t = useMemo(() => {
    return (key: string, params?: Record<string, string | number>): string => {
      // Support nested keys: "settings.title" or "app.welcome"
      const parts = key.split('.');
      
      if (parts.length >= 2) {
        const namespace = parts[0];
        const keyPath = parts.slice(1).join('.');
        
        // Try nested structure first
        if (translations[namespace]) {
          const value = getNestedValue(translations[namespace], keyPath);
          if (value !== undefined) {
            return replaceParams(String(value), params);
          }
        }
      }
      
      // Try flat lookup in all namespaces
      for (const namespace of Object.keys(translations)) {
        const value = getNestedValue(translations[namespace], key);
        if (value !== undefined) {
          return replaceParams(String(value), params);
        }
      }
      
      // Fallback to key if translation not found
      return replaceParams(key, params);
    };
  }, [translations]);
  
  const contextValue = useMemo(
    () => ({
      locale,
      t,
      loading,
      error,
    }),
    [locale, t, loading, error],
  );

  return <I18nContext.Provider value={contextValue}>{children}</I18nContext.Provider>;
};

/**
 * Get nested value from object using dot notation
 */
function getNestedValue(obj: any, path: string): any {
  return path.split('.').reduce((current, key) => {
    return current && typeof current === 'object' ? current[key] : undefined;
  }, obj);
}

/**
 * Replace parameters in translation string
 */
function replaceParams(text: string, params?: Record<string, string | number>): string {
  if (!params) return text;
  
  let result = text;
  Object.entries(params).forEach(([key, value]) => {
    result = result.replace(new RegExp(`\\{${key}\\}`, 'g'), String(value));
  });
  return result;
}

export const useI18n = () => {
  const context = useContext(I18nContext);
  if (!context) {
    throw new Error('useI18n must be used within I18nProvider');
  }
  return context;
};

