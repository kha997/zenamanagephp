import React from 'react';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../../shared/ui/card';
import { Button } from '../../shared/ui/button';
import { Badge } from '../../shared/ui/badge';
import { useI18n } from '../../app/i18n-context';
import { useThemeMode } from '../../app/theme-context';
import { applyTheme } from '../../shared/tokens';
import { preferencesSchema, defaultPreferences, type PreferencesFormData } from './schema';

interface PreferencesFormProps {
  initialData?: Partial<PreferencesFormData>;
  onSubmit: (data: PreferencesFormData) => Promise<void>;
  isLoading?: boolean;
}

export const PreferencesForm: React.FC<PreferencesFormProps> = ({
  initialData = defaultPreferences,
  onSubmit,
  isLoading = false,
}) => {
  const { t } = useI18n();
  const { mode, setMode } = useThemeMode();

  const {
    control,
    handleSubmit,
    formState: { errors, isDirty },
    reset,
  } = useForm<PreferencesFormData>({
    resolver: zodResolver(preferencesSchema),
    defaultValues: { ...defaultPreferences, ...initialData },
  });


  const handleFormSubmit = async (data: PreferencesFormData) => {
    try {
      await onSubmit(data);
    } catch (error) {
      console.error('Failed to save preferences:', error);
    }
  };

  const handlePreviewTheme = (theme: 'light' | 'dark') => {
    setMode(theme);
    applyTheme(theme);
  };

  const handleReset = () => {
    reset({ ...defaultPreferences, ...initialData });
  };

  return (
    <form onSubmit={handleSubmit(handleFormSubmit)} className="space-y-6">
      {/* Theme Settings */}
      <Card>
        <CardHeader>
          <CardTitle>{t('preferences.theme.title', { defaultValue: 'Theme Settings' })}</CardTitle>
          <CardDescription>
            {t('preferences.theme.description', { defaultValue: 'Customize the appearance of your dashboard' })}
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <label className="text-sm font-medium text-[var(--color-text-primary)]">
              {t('preferences.theme.mode', { defaultValue: 'Theme Mode' })}
            </label>
            <Controller
              name="theme"
              control={control}
              render={({ field }) => (
                <div className="flex gap-2">
                  {(['light', 'dark', 'auto'] as const).map((theme) => (
                    <Button
                      key={theme}
                      type="button"
                      variant={field.value === theme ? 'primary' : 'outline'}
                      size="sm"
                      onClick={() => field.onChange(theme)}
                    >
                      {t(`preferences.theme.${theme}`, { defaultValue: theme })}
                    </Button>
                  ))}
                </div>
              )}
            />
            {errors.theme && (
              <p className="text-sm text-[var(--color-semantic-danger-500)]">
                {errors.theme.message}
              </p>
            )}
          </div>

          {/* Theme Preview */}
          <div className="space-y-2">
            <label className="text-sm font-medium text-[var(--color-text-primary)]">
              {t('preferences.theme.preview', { defaultValue: 'Preview' })}
            </label>
            <div className="flex gap-2">
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => handlePreviewTheme('light')}
                className={mode === 'light' ? 'ring-2 ring-[var(--color-semantic-primary-500)]' : ''}
              >
                ☀️ {t('preferences.theme.light', { defaultValue: 'Light' })}
              </Button>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => handlePreviewTheme('dark')}
                className={mode === 'dark' ? 'ring-2 ring-[var(--color-semantic-primary-500)]' : ''}
              >
                🌙 {t('preferences.theme.dark', { defaultValue: 'Dark' })}
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Layout Settings */}
      <Card>
        <CardHeader>
          <CardTitle>{t('preferences.layout.title', { defaultValue: 'Layout Settings' })}</CardTitle>
          <CardDescription>
            {t('preferences.layout.description', { defaultValue: 'Configure how content is displayed' })}
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <label className="text-sm font-medium text-[var(--color-text-primary)]">
              {t('preferences.layout.type', { defaultValue: 'Layout Type' })}
            </label>
            <Controller
              name="layout"
              control={control}
              render={({ field }) => (
                <div className="flex gap-2">
                  {(['grid', 'list', 'compact'] as const).map((layout) => (
                    <Button
                      key={layout}
                      type="button"
                      variant={field.value === layout ? 'primary' : 'outline'}
                      size="sm"
                      onClick={() => field.onChange(layout)}
                    >
                      {t(`preferences.layout.${layout}`, { defaultValue: layout })}
                    </Button>
                  ))}
                </div>
              )}
            />
            {errors.layout && (
              <p className="text-sm text-[var(--color-semantic-danger-500)]">
                {errors.layout.message}
              </p>
            )}
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium text-[var(--color-text-primary)]">
              {t('preferences.layout.density', { defaultValue: 'Density' })}
            </label>
            <Controller
              name="density"
              control={control}
              render={({ field }) => (
                <div className="flex gap-2">
                  {(['comfortable', 'compact', 'spacious'] as const).map((density) => (
                    <Button
                      key={density}
                      type="button"
                      variant={field.value === density ? 'primary' : 'outline'}
                      size="sm"
                      onClick={() => field.onChange(density)}
                    >
                      {t(`preferences.layout.${density}`, { defaultValue: density })}
                    </Button>
                  ))}
                </div>
              )}
            />
            {errors.density && (
              <p className="text-sm text-[var(--color-semantic-danger-500)]">
                {errors.density.message}
              </p>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Notification Settings */}
      <Card>
        <CardHeader>
          <CardTitle>{t('preferences.notifications.title', { defaultValue: 'Notification Settings' })}</CardTitle>
          <CardDescription>
            {t('preferences.notifications.description', { defaultValue: 'Configure how you receive notifications' })}
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-3">
            <Controller
              name="notifications.enabled"
              control={control}
              render={({ field }) => (
                <div className="flex items-center justify-between">
                  <div>
                    <label className="text-sm font-medium text-[var(--color-text-primary)]">
                      {t('preferences.notifications.enabled', { defaultValue: 'Enable Notifications' })}
                    </label>
                    <p className="text-xs text-[var(--color-text-muted)]">
                      {t('preferences.notifications.enabledDescription', { defaultValue: 'Receive system notifications' })}
                    </p>
                  </div>
                  <input
                    type="checkbox"
                    checked={field.value}
                    onChange={field.onChange}
                    className="rounded border-[var(--color-border-default)]"
                  />
                </div>
              )}
            />

            <Controller
              name="notifications.sound"
              control={control}
              render={({ field }) => (
                <div className="flex items-center justify-between">
                  <div>
                    <label className="text-sm font-medium text-[var(--color-text-primary)]">
                      {t('preferences.notifications.sound', { defaultValue: 'Sound Notifications' })}
                    </label>
                    <p className="text-xs text-[var(--color-text-muted)]">
                      {t('preferences.notifications.soundDescription', { defaultValue: 'Play sound for notifications' })}
                    </p>
                  </div>
                  <input
                    type="checkbox"
                    checked={field.value}
                    onChange={field.onChange}
                    className="rounded border-[var(--color-border-default)]"
                  />
                </div>
              )}
            />

            <Controller
              name="notifications.desktop"
              control={control}
              render={({ field }) => (
                <div className="flex items-center justify-between">
                  <div>
                    <label className="text-sm font-medium text-[var(--color-text-primary)]">
                      {t('preferences.notifications.desktop', { defaultValue: 'Desktop Notifications' })}
                    </label>
                    <p className="text-xs text-[var(--color-text-muted)]">
                      {t('preferences.notifications.desktopDescription', { defaultValue: 'Show desktop notifications' })}
                    </p>
                  </div>
                  <input
                    type="checkbox"
                    checked={field.value}
                    onChange={field.onChange}
                    className="rounded border-[var(--color-border-default)]"
                  />
                </div>
              )}
            />
          </div>
        </CardContent>
      </Card>

      {/* Widget Settings */}
      <Card>
        <CardHeader>
          <CardTitle>{t('preferences.widgets.title', { defaultValue: 'Widget Settings' })}</CardTitle>
          <CardDescription>
            {t('preferences.widgets.description', { defaultValue: 'Configure widget behavior and appearance' })}
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <label className="text-sm font-medium text-[var(--color-text-primary)]">
              {t('preferences.widgets.defaultSize', { defaultValue: 'Default Widget Size' })}
            </label>
            <Controller
              name="widgets.defaultSize"
              control={control}
              render={({ field }) => (
                <div className="flex gap-2">
                  {(['small', 'medium', 'large', 'xlarge'] as const).map((size) => (
                    <Button
                      key={size}
                      type="button"
                      variant={field.value === size ? 'primary' : 'outline'}
                      size="sm"
                      onClick={() => field.onChange(size)}
                    >
                      {t(`preferences.widgets.${size}`, { defaultValue: size })}
                    </Button>
                  ))}
                </div>
              )}
            />
            {errors.widgets?.defaultSize && (
              <p className="text-sm text-[var(--color-semantic-danger-500)]">
                {errors.widgets.defaultSize.message}
              </p>
            )}
          </div>

          <div className="space-y-3">
            <Controller
              name="widgets.autoRefresh"
              control={control}
              render={({ field }) => (
                <div className="flex items-center justify-between">
                  <div>
                    <label className="text-sm font-medium text-[var(--color-text-primary)]">
                      {t('preferences.widgets.autoRefresh', { defaultValue: 'Auto Refresh Widgets' })}
                    </label>
                    <p className="text-xs text-[var(--color-text-muted)]">
                      {t('preferences.widgets.autoRefreshDescription', { defaultValue: 'Automatically refresh widget data' })}
                    </p>
                  </div>
                  <input
                    type="checkbox"
                    checked={field.value}
                    onChange={field.onChange}
                    className="rounded border-[var(--color-border-default)]"
                  />
                </div>
              )}
            />

            <Controller
              name="widgets.showTitles"
              control={control}
              render={({ field }) => (
                <div className="flex items-center justify-between">
                  <div>
                    <label className="text-sm font-medium text-[var(--color-text-primary)]">
                      {t('preferences.widgets.showTitles', { defaultValue: 'Show Widget Titles' })}
                    </label>
                    <p className="text-xs text-[var(--color-text-muted)]">
                      {t('preferences.widgets.showTitlesDescription', { defaultValue: 'Display titles on widgets' })}
                    </p>
                  </div>
                  <input
                    type="checkbox"
                    checked={field.value}
                    onChange={field.onChange}
                    className="rounded border-[var(--color-border-default)]"
                  />
                </div>
              )}
            />
          </div>
        </CardContent>
      </Card>

      {/* Refresh Interval */}
      <Card>
        <CardHeader>
          <CardTitle>{t('preferences.refresh.title', { defaultValue: 'Refresh Settings' })}</CardTitle>
          <CardDescription>
            {t('preferences.refresh.description', { defaultValue: 'Configure data refresh intervals' })}
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <label className="text-sm font-medium text-[var(--color-text-primary)]">
              {t('preferences.refresh.interval', { defaultValue: 'Refresh Interval (seconds)' })}
            </label>
            <Controller
              name="refreshInterval"
              control={control}
              render={({ field }) => (
                <div className="flex items-center gap-2">
                  <input
                    type="range"
                    min="30"
                    max="300"
                    step="30"
                    value={field.value}
                    onChange={(e) => field.onChange(parseInt(e.target.value))}
                    className="flex-1"
                  />
                  <Badge tone="info">{field.value}s</Badge>
                </div>
              )}
            />
            {errors.refreshInterval && (
              <p className="text-sm text-[var(--color-semantic-danger-500)]">
                {errors.refreshInterval.message}
              </p>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Form Actions */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          {isDirty && (
            <Badge tone="warning">
              {t('preferences.unsavedChanges', { defaultValue: 'Unsaved changes' })}
            </Badge>
          )}
        </div>
        
        <div className="flex items-center gap-2">
          <Button
            type="button"
            variant="outline"
            onClick={handleReset}
            disabled={!isDirty}
          >
            {t('preferences.reset', { defaultValue: 'Reset' })}
          </Button>
          <Button
            type="submit"
            loading={isLoading}
            disabled={!isDirty}
          >
            {t('preferences.save', { defaultValue: 'Save Preferences' })}
          </Button>
        </div>
      </div>
    </form>
  );
};
