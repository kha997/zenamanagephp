import React, { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../../shared/ui/card';
import { Button } from '../../shared/ui/button';
import { PreferencesForm } from '../../features/preferences/PreferencesForm';
import { useDashboardPreferences, useSavePreferences, useResetDashboard } from '../../entities/dashboard/hooks';
import { useI18n } from '../../app/i18n-context';
import { useThemeMode } from '../../app/theme-context';
import { applyTheme } from '../../shared/tokens';
import type { PreferencesFormData } from '../../features/preferences/schema';

const PreferencesPage: React.FC = () => {
  const { t } = useI18n();
  const { mode, setMode } = useThemeMode();
  const [showSuccess, setShowSuccess] = useState(false);
  
  const { data: preferences, isLoading, error } = useDashboardPreferences();
  const savePreferencesMutation = useSavePreferences();
  const resetDashboardMutation = useResetDashboard();

  const handleResetToDefaults = async () => {
    try {
      await resetDashboardMutation.mutateAsync();
      setShowSuccess(true);
      setTimeout(() => setShowSuccess(false), 3000);
    } catch (error) {
      console.error('Failed to reset to defaults:', error);
    }
  };

  const handleSavePreferences = async (data: PreferencesFormData) => {
    try {
      await savePreferencesMutation.mutateAsync(data);
      
      // Apply theme changes immediately
      if (data.theme !== 'auto') {
        setMode(data.theme);
        applyTheme(data.theme);
      } else {
        // Handle auto theme - you might want to detect system preference
        const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        setMode(systemTheme);
        applyTheme(systemTheme);
      }

      // Store in localStorage for persistence
      localStorage.setItem('user-preferences', JSON.stringify(data));
      
      setShowSuccess(true);
      setTimeout(() => setShowSuccess(false), 3000);
    } catch (error) {
      console.error('Failed to save preferences:', error);
    }
  };

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">
              {t('preferences.title', { defaultValue: 'Preferences' })}
            </h1>
            <p className="text-[var(--color-text-muted)]">
              {t('preferences.description', { defaultValue: 'Customize your dashboard experience' })}
            </p>
          </div>
        </div>
        <div className="space-y-4">
          {[1, 2, 3, 4].map((i) => (
            <Card key={i}>
              <CardHeader>
                <div className="h-6 bg-[var(--color-surface-muted)] rounded animate-pulse" />
                <div className="h-4 bg-[var(--color-surface-muted)] rounded animate-pulse w-2/3" />
              </CardHeader>
              <CardContent>
                <div className="space-y-2">
                  <div className="h-4 bg-[var(--color-surface-muted)] rounded animate-pulse" />
                  <div className="h-4 bg-[var(--color-surface-muted)] rounded animate-pulse w-1/2" />
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">
              {t('preferences.title', { defaultValue: 'Preferences' })}
            </h1>
            <p className="text-[var(--color-text-muted)]">
              {t('preferences.description', { defaultValue: 'Customize your dashboard experience' })}
            </p>
          </div>
        </div>
        <Card>
          <CardContent className="p-6">
            <div className="text-center text-[var(--color-text-muted)]">
              <p className="text-lg font-medium mb-2">
                {t('preferences.errorTitle', { defaultValue: 'Failed to load preferences' })}
              </p>
              <p className="text-sm mb-4">
                {t('preferences.errorDescription', { defaultValue: 'Please try refreshing the page' })}
              </p>
              <Button variant="outline" size="sm">
                {t('preferences.retry', { defaultValue: 'Retry' })}
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  const preferencesData = preferences?.data || {};

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">
            {t('preferences.title', { defaultValue: 'Preferences' })}
          </h1>
          <p className="text-[var(--color-text-muted)]">
            {t('preferences.description', { defaultValue: 'Customize your dashboard experience' })}
          </p>
        </div>
        <div className="flex items-center gap-2">
          {showSuccess && (
            <div className="text-sm text-[var(--color-semantic-success-500)]">
              ✅ {t('preferences.saved', { defaultValue: 'Preferences saved!' })}
            </div>
          )}
          <Button variant="outline" size="sm" onClick={handleResetToDefaults}>
            {t('preferences.resetToDefaults', { defaultValue: 'Reset to Defaults' })}
          </Button>
        </div>
      </div>

      {/* Current Theme Indicator */}
      <Card>
        <CardContent className="p-4">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-sm font-medium text-[var(--color-text-primary)]">
                {t('preferences.currentTheme', { defaultValue: 'Current Theme' })}
              </h3>
              <p className="text-xs text-[var(--color-text-muted)]">
                {t('preferences.currentThemeDescription', { defaultValue: 'Active theme mode' })}
              </p>
            </div>
            <div className="flex items-center gap-2">
              <span className="text-sm text-[var(--color-text-muted)]">
                {mode === 'light' ? '☀️' : '🌙'} {mode}
              </span>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Preferences Form */}
      <PreferencesForm
        initialData={preferencesData}
        onSubmit={handleSavePreferences}
        isLoading={savePreferencesMutation.isPending}
      />

      {/* Help Section */}
      <Card>
        <CardHeader>
          <CardTitle className="text-lg">{t('preferences.help.title', { defaultValue: 'Need Help?' })}</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-2 text-sm text-[var(--color-text-muted)]">
            <p>{t('preferences.help.description', { defaultValue: 'Your preferences are automatically saved and synced across all your devices.' })}</p>
            <p>{t('preferences.help.theme', { defaultValue: 'Auto theme will follow your system preference (light/dark mode).' })}</p>
            <p>{t('preferences.help.refresh', { defaultValue: 'Refresh intervals are applied to all dashboard widgets and data.' })}</p>
          </div>
        </CardContent>
      </Card>
    </div>
  );
};

export default PreferencesPage;
