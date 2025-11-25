import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { Switch } from '../../../components/ui/primitives/Switch';
import { Select, type SelectOption } from '../../../components/ui/primitives/Select';
import { useSettings, useUpdatePrivacy } from '../hooks';
import toast from 'react-hot-toast';
import type { PrivacySettings } from '../types';
import { useAuthStore } from '../../auth/store';

/**
 * PrivacySettings Component
 * 
 * Form for managing privacy preferences: profile visibility, activity sharing, data collection, analytics
 */
export const PrivacySettings: React.FC = () => {
  const { data: settingsData, isLoading } = useSettings();
  const updatePrivacy = useUpdatePrivacy();
  const { hasTenantPermission } = useAuthStore();
  const canManageSettings = hasTenantPermission('tenant.manage_settings');

  const [formData, setFormData] = useState<Partial<PrivacySettings>>({
    profile_visibility: 'public',
    activity_sharing: true,
    data_collection: true,
    analytics_tracking: true,
  });

  // Initialize form data from settings
  useEffect(() => {
    if (settingsData?.privacy_settings) {
      setFormData(settingsData.privacy_settings);
    }
  }, [settingsData]);

  const handleToggle = (field: keyof PrivacySettings, value: boolean) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const handleChange = (field: keyof PrivacySettings, value: string) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await updatePrivacy.mutateAsync(formData);
      toast.success('Privacy settings updated successfully');
    } catch (error: any) {
      toast.error(error?.message || 'Failed to update privacy settings');
    }
  };

  const handleReset = () => {
    if (settingsData?.privacy_settings) {
      setFormData(settingsData.privacy_settings);
    }
  };

  const visibilityOptions: SelectOption[] = [
    { value: 'public', label: 'Public' },
    { value: 'private', label: 'Private' },
    { value: 'friends', label: 'Friends Only' },
  ];

  const hasChanges =
    JSON.stringify(formData) !== JSON.stringify(settingsData?.privacy_settings);

  if (isLoading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Privacy Settings</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            {[1, 2, 3, 4].map((i) => (
              <div key={i} className="animate-pulse">
                <div className="h-6 bg-[var(--muted-surface)] rounded w-3/4"></div>
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
        <CardTitle>Privacy Settings</CardTitle>
        <p className="text-sm text-[var(--color-text-muted)] mt-1">
          Control who can see your profile and how your data is used.
        </p>
      </CardHeader>
      <form onSubmit={handleSubmit}>
        <CardContent className="space-y-6">
          {/* Profile Visibility */}
          <div>
            <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
              Profile Visibility
            </label>
            <Select
              options={visibilityOptions}
              value={formData.profile_visibility || ''}
              onChange={(value) => handleChange('profile_visibility', value)}
              placeholder="Select visibility"
            />
            <p className="text-xs text-[var(--color-text-muted)] mt-1">
              Control who can view your profile information
            </p>
          </div>

          {/* Activity Sharing */}
          <div>
            <Switch
              label="Activity Sharing"
              description="Allow others to see your activity and updates"
              checked={formData.activity_sharing ?? false}
              onChange={(e) => handleToggle('activity_sharing', e.target.checked)}
            />
          </div>

          {/* Data Collection */}
          <div>
            <Switch
              label="Data Collection"
              description="Allow us to collect usage data to improve the service"
              checked={formData.data_collection ?? false}
              onChange={(e) => handleToggle('data_collection', e.target.checked)}
            />
          </div>

          {/* Analytics Tracking */}
          <div>
            <Switch
              label="Analytics Tracking"
              description="Help us understand how you use the platform"
              checked={formData.analytics_tracking ?? false}
              onChange={(e) => handleToggle('analytics_tracking', e.target.checked)}
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
                disabled={!hasChanges || updatePrivacy.isPending}
              >
                Reset
              </Button>
              <Button
                type="submit"
                disabled={!hasChanges || updatePrivacy.isPending}
                style={{ marginLeft: 'auto' }}
              >
                {updatePrivacy.isPending ? 'Saving...' : 'Save Changes'}
              </Button>
            </div>
          </CardFooter>
        )}
      </form>
    </Card>
  );
};

