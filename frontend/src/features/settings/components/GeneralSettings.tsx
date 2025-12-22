import React, { useState, useEffect, useRef } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { Select, type SelectOption } from '../../../components/ui/primitives/Select';
import { useSettings, useUpdateGeneral } from '../hooks';
import { apiClient } from '../../../shared/api/client';
import toast from 'react-hot-toast';
import type { UserSettings } from '../types';
import { useAuthStore } from '../../auth/store';

/**
 * GeneralSettings Component
 * 
 * Form for managing general user settings: timezone, language, date_format, time_format, currency
 */
export const GeneralSettings: React.FC = () => {
  const { data: settingsData, isLoading } = useSettings();
  const updateGeneral = useUpdateGeneral();
  const { hasTenantPermission } = useAuthStore();
  const canManageSettings = hasTenantPermission('tenant.manage_settings');

  const [formData, setFormData] = useState<Partial<UserSettings>>({
    timezone: 'UTC',
    language: 'en',
    date_format: 'Y-m-d',
    time_format: '24',
    currency: 'USD',
  });

  // Track previous settingsData to prevent unnecessary updates
  const prevSettingsDataRef = useRef<string>('');
  
  // Initialize form data from settings (only on mount or when settings actually change)
  useEffect(() => {
    if (settingsData?.user_settings) {
      // Only update if the values actually changed to prevent infinite loops
      const newData = settingsData.user_settings;
      const newDataStr = JSON.stringify(newData);
      
      // Skip update if data hasn't actually changed
      if (prevSettingsDataRef.current === newDataStr) {
        return;
      }
      
      prevSettingsDataRef.current = newDataStr;
      setFormData({ ...newData });
    }
  }, [settingsData]);

  const handleChange = (field: keyof UserSettings, value: string) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      const previousLanguage = settingsData?.user_settings?.language;
      const newLanguage = formData.language;
      
      await updateGeneral.mutateAsync(formData);
      
      // If language changed, call i18n API to set session/cookie and reload page
      if (newLanguage && newLanguage !== previousLanguage) {
        try {
          await apiClient.post('/i18n/language', { language: newLanguage });
          // Update document lang attribute
          document.documentElement.lang = newLanguage;
          // Reload page to apply language change
          setTimeout(() => {
            window.location.reload();
          }, 500); // Small delay to show success message
        } catch (i18nError: any) {
          console.warn('Failed to set language via i18n API:', i18nError);
          // Still show success for settings save, but language might not apply until reload
          toast.success('General settings updated successfully. Please reload page to apply language change.');
        }
      } else {
        toast.success('General settings updated successfully');
      }
    } catch (error: any) {
      toast.error(error?.message || 'Failed to update general settings');
    }
  };

  const handleReset = () => {
    if (settingsData?.user_settings) {
      setFormData(settingsData.user_settings);
    }
  };

  // Timezone options (common timezones)
  const timezoneOptions: SelectOption[] = [
    { value: 'UTC', label: 'UTC (Coordinated Universal Time)' },
    { value: 'America/New_York', label: 'Eastern Time (ET)' },
    { value: 'America/Chicago', label: 'Central Time (CT)' },
    { value: 'America/Denver', label: 'Mountain Time (MT)' },
    { value: 'America/Los_Angeles', label: 'Pacific Time (PT)' },
    { value: 'Europe/London', label: 'London (GMT)' },
    { value: 'Europe/Paris', label: 'Paris (CET)' },
    { value: 'Asia/Tokyo', label: 'Tokyo (JST)' },
    { value: 'Asia/Shanghai', label: 'Shanghai (CST)' },
    { value: 'Australia/Sydney', label: 'Sydney (AEST)' },
  ];

  const languageOptions: SelectOption[] = [
    { value: 'en', label: 'English' },
    { value: 'vi', label: 'Tiếng Việt' },
  ];

  const dateFormatOptions: SelectOption[] = [
    { value: 'Y-m-d', label: 'YYYY-MM-DD (2024-01-15)' },
    { value: 'm/d/Y', label: 'MM/DD/YYYY (01/15/2024)' },
    { value: 'd/m/Y', label: 'DD/MM/YYYY (15/01/2024)' },
    { value: 'd M Y', label: 'DD MMM YYYY (15 Jan 2024)' },
    { value: 'M d, Y', label: 'MMM DD, YYYY (Jan 15, 2024)' },
  ];

  const timeFormatOptions: SelectOption[] = [
    { value: '24', label: '24-hour (14:30)' },
    { value: '12', label: '12-hour (2:30 PM)' },
  ];

  const currencyOptions: SelectOption[] = [
    { value: 'USD', label: 'USD - US Dollar ($)' },
    { value: 'EUR', label: 'EUR - Euro (€)' },
    { value: 'GBP', label: 'GBP - British Pound (£)' },
    { value: 'JPY', label: 'JPY - Japanese Yen (¥)' },
    { value: 'VND', label: 'VND - Vietnamese Dong (₫)' },
    { value: 'CNY', label: 'CNY - Chinese Yuan (¥)' },
    { value: 'AUD', label: 'AUD - Australian Dollar (A$)' },
  ];

  const hasChanges = JSON.stringify(formData) !== JSON.stringify(settingsData?.user_settings);

  if (isLoading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>General Settings</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            {[1, 2, 3, 4, 5].map((i) => (
              <div key={i} className="animate-pulse">
                <div className="h-4 bg-[var(--muted-surface)] rounded w-1/4 mb-2"></div>
                <div className="h-10 bg-[var(--muted-surface)] rounded"></div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>General Settings</CardTitle>
        <p className="text-sm text-[var(--color-text-muted)] mt-1">
          Configure your timezone, language, date and time formats, and currency preferences.
        </p>
      </CardHeader>
      <form onSubmit={handleSubmit}>
        <CardContent className="space-y-6">
          {/* Timezone */}
          <div>
            <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
              Timezone
            </label>
            <Select
              options={timezoneOptions}
              value={formData.timezone || ''}
              onChange={(value) => handleChange('timezone', value)}
              placeholder="Select timezone"
            />
          </div>

          {/* Language */}
          <div>
            <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
              Language
            </label>
            <Select
              options={languageOptions}
              value={formData.language || ''}
              onChange={(value) => handleChange('language', value)}
              placeholder="Select language"
            />
          </div>

          {/* Date Format */}
          <div>
            <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
              Date Format
            </label>
            <Select
              options={dateFormatOptions}
              value={formData.date_format || ''}
              onChange={(value) => handleChange('date_format', value)}
              placeholder="Select date format"
            />
          </div>

          {/* Time Format */}
          <div>
            <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
              Time Format
            </label>
            <Select
              options={timeFormatOptions}
              value={formData.time_format || ''}
              onChange={(value) => handleChange('time_format', value)}
              placeholder="Select time format"
            />
          </div>

          {/* Currency */}
          <div>
            <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
              Currency
            </label>
            <Select
              options={currencyOptions}
              value={formData.currency || ''}
              onChange={(value) => handleChange('currency', value)}
              placeholder="Select currency"
            />
          </div>
        </CardContent>
        {canManageSettings && (
          <CardFooter>
            <div className="flex items-center gap-3 w-full">
              <Button
                type="button"
                variant="secondary"
                onClick={handleReset}
                disabled={!hasChanges || updateGeneral.isPending}
              >
                Reset
              </Button>
              <Button
                type="submit"
                disabled={!hasChanges || updateGeneral.isPending}
                style={{ marginLeft: 'auto' }}
              >
                {updateGeneral.isPending ? 'Saving...' : 'Save Changes'}
              </Button>
            </div>
          </CardFooter>
        )}
      </form>
    </Card>
  );
};

