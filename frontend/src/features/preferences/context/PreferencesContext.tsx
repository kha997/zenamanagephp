import React, { createContext, useContext, useCallback } from 'react';
import { usePreferences, useUpdatePreferences } from '../hooks';
import type { UserPreferences } from '../api';

interface PreferencesContextValue {
  preferences: UserPreferences | null;
  loading: boolean;
  updatePreference: <K extends keyof UserPreferences>(
    key: K,
    value: UserPreferences[K]
  ) => Promise<void>;
  updateViewPreference: (
    page: string,
    preference: {
      mode?: 'table' | 'card' | 'list';
      density?: 'compact' | 'comfortable' | 'spacious';
      columns?: string[];
      sortBy?: string;
      sortDirection?: 'asc' | 'desc';
    }
  ) => Promise<void>;
  updateKpiPreference: (page: string, kpiIds: string[]) => Promise<void>;
  getViewPreference: (page: string) => {
    mode?: 'table' | 'card' | 'list';
    density?: 'compact' | 'comfortable' | 'spacious';
    columns?: string[];
    sortBy?: string;
    sortDirection?: 'asc' | 'desc';
  };
  getKpiPreference: (page: string) => string[];
}

const PreferencesContext = createContext<PreferencesContextValue | null>(null);

export const PreferencesProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const { data, isLoading } = usePreferences();
  const updateMutation = useUpdatePreferences();

  const preferences = data?.data || null;

  const updatePreference = useCallback(
    async <K extends keyof UserPreferences>(key: K, value: UserPreferences[K]) => {
      await updateMutation.mutateAsync({ [key]: value });
    },
    [updateMutation]
  );

  const updateViewPreference = useCallback(
    async (
      page: string,
      preference: {
        mode?: 'table' | 'card' | 'list';
        density?: 'compact' | 'comfortable' | 'spacious';
        columns?: string[];
        sortBy?: string;
        sortDirection?: 'asc' | 'desc';
      }
    ) => {
      const currentViews = preferences?.views || {};
      await updatePreference('views', {
        ...currentViews,
        [page]: { ...currentViews[page], ...preference },
      });
    },
    [preferences, updatePreference]
  );

  const updateKpiPreference = useCallback(
    async (page: string, kpiIds: string[]) => {
      const currentKpi = preferences?.kpi || {};
      await updatePreference('kpi', {
        ...currentKpi,
        [page]: kpiIds,
      });
    },
    [preferences, updatePreference]
  );

  const getViewPreference = useCallback(
    (page: string) => {
      return preferences?.views?.[page] || {};
    },
    [preferences]
  );

  const getKpiPreference = useCallback(
    (page: string) => {
      return preferences?.kpi?.[page] || [];
    },
    [preferences]
  );

  return (
    <PreferencesContext.Provider
      value={{
        preferences,
        loading: isLoading,
        updatePreference,
        updateViewPreference,
        updateKpiPreference,
        getViewPreference,
        getKpiPreference,
      }}
    >
      {children}
    </PreferencesContext.Provider>
  );
};

export const usePreferencesContext = () => {
  const context = useContext(PreferencesContext);
  if (!context) {
    throw new Error('usePreferencesContext must be used within PreferencesProvider');
  }
  return context;
};

