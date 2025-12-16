import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { Select, type SelectOption } from '../../../components/ui/primitives/Select';
import { Switch } from '../../../components/ui/primitives/Switch';
import { useSettings, useUpdateAppearance } from '../hooks';
import toast from 'react-hot-toast';
import type { AppearanceSettings } from '../types';
import { useAuthStore } from '../../auth/store';

/**
 * AppearanceSettings Component
 * 
 * Form for managing appearance preferences: theme, density, font_size, primary_color
 */
export const AppearanceSettings: React.FC = () => {
  const { data: settingsData, isLoading } = useSettings();
  const updateAppearance = useUpdateAppearance();
  const { hasTenantPermission } = useAuthStore();
  const canManageSettings = hasTenantPermission('tenant.manage_settings');

  const [formData, setFormData] = useState<Partial<AppearanceSettings>>({
    theme: 'light',
    sidebar_collapsed: false,
    density: 'comfortable',
    primary_color: '#3B82F6',
    font_size: 'medium',
  });

  // Initialize form data from settings
  useEffect(() => {
    if (settingsData?.appearance_settings) {
      setFormData(settingsData.appearance_settings);
    }
  }, [settingsData]);

  const handleChange = (field: keyof AppearanceSettings, value: string | boolean) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await updateAppearance.mutateAsync(formData);
      toast.success('Appearance settings updated successfully');
    } catch (error: any) {
      toast.error(error?.message || 'Failed to update appearance settings');
    }
  };

  const handleReset = () => {
    if (settingsData?.appearance_settings) {
      setFormData(settingsData.appearance_settings);
    }
  };

  const themeOptions: SelectOption[] = [
    { value: 'light', label: 'Light' },
    { value: 'dark', label: 'Dark' },
    { value: 'auto', label: 'Auto (follow system)' },
  ];

  const densityOptions: SelectOption[] = [
    { value: 'compact', label: 'Compact' },
    { value: 'comfortable', label: 'Comfortable' },
    { value: 'spacious', label: 'Spacious' },
  ];

  const fontSizeOptions: SelectOption[] = [
    { value: 'small', label: 'Small' },
    { value: 'medium', label: 'Medium' },
    { value: 'large', label: 'Large' },
  ];

  const hasChanges =
    JSON.stringify(formData) !== JSON.stringify(settingsData?.appearance_settings);

  if (isLoading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Appearance Settings</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            {[1, 2, 3, 4].map((i) => (
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
        <CardTitle>Appearance Settings</CardTitle>
        <p className="text-sm text-[var(--color-text-muted)] mt-1">
          Customize the look and feel of your interface.
        </p>
      </CardHeader>
      <form onSubmit={handleSubmit}>
        <CardContent className="space-y-6">
          {/* Theme */}
          <div>
            <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
              Theme
            </label>
            <Select
              options={themeOptions}
              value={formData.theme || ''}
              onChange={(value) => handleChange('theme', value)}
              placeholder="Select theme"
            />
          </div>

          {/* Density */}
          <div>
            <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
              Density
            </label>
            <Select
              options={densityOptions}
              value={formData.density || ''}
              onChange={(value) => handleChange('density', value)}
              placeholder="Select density"
            />
            <p className="text-xs text-[var(--color-text-muted)] mt-1">
              Controls the spacing and size of UI elements
            </p>
          </div>

          {/* Font Size */}
          <div>
            <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
              Font Size
            </label>
            <Select
              options={fontSizeOptions}
              value={formData.font_size || ''}
              onChange={(value) => handleChange('font_size', value)}
              placeholder="Select font size"
            />
          </div>

          {/* Sidebar Collapsed */}
          <div>
            <Switch
              label="Collapse Sidebar by Default"
              description="Start with the sidebar collapsed"
              checked={formData.sidebar_collapsed ?? false}
              onChange={(e) => handleChange('sidebar_collapsed', e.target.checked)}
            />
          </div>

          {/* Primary Color */}
          <div>
            <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
              Primary Color
            </label>
            <div className="flex items-center gap-3">
              <input
                type="color"
                value={formData.primary_color || '#3B82F6'}
                onChange={(e) => handleChange('primary_color', e.target.value)}
                style={{
                  width: 60,
                  height: 40,
                  borderRadius: 10,
                  border: '1px solid var(--border)',
                  cursor: 'pointer',
                }}
              />
              <input
                type="text"
                value={formData.primary_color || '#3B82F6'}
                onChange={(e) => handleChange('primary_color', e.target.value)}
                placeholder="#3B82F6"
                style={{
                  flex: 1,
                  height: 40,
                  borderRadius: 10,
                  border: '1px solid var(--border)',
                  padding: '0 12px',
                  fontSize: 14,
                  color: 'var(--text)',
                  backgroundColor: 'transparent',
                }}
              />
            </div>
            <p className="text-xs text-[var(--color-text-muted)] mt-1">
              Choose your primary accent color
            </p>
          </div>
        </CardContent>
        {canManageSettings && (
          <CardFooter>
            <div className="flex items-center gap-3 w-full">
              <Button
                type="button"
                variant="secondary"
                onClick={handleReset}
                disabled={!hasChanges || updateAppearance.isPending}
              >
                Reset
              </Button>
              <Button
                type="submit"
                disabled={!hasChanges || updateAppearance.isPending}
                style={{ marginLeft: 'auto' }}
              >
                {updateAppearance.isPending ? 'Saving...' : 'Save Changes'}
              </Button>
            </div>
          </CardFooter>
        )}
      </form>
    </Card>
  );
};

