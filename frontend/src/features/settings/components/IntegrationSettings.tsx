import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { Switch } from '../../../components/ui/primitives/Switch';
import { useSettings, useUpdateIntegrations } from '../hooks';
import toast from 'react-hot-toast';
import type { IntegrationSettings } from '../types';
import { useAuthStore } from '../../auth/store';

/**
 * IntegrationSettings Component
 * 
 * Form for managing third-party integrations: Google Calendar, Slack, GitHub, Jira
 */
export const IntegrationSettings: React.FC = () => {
  const { data: settingsData, isLoading } = useSettings();
  const updateIntegrations = useUpdateIntegrations();
  const { hasTenantPermission } = useAuthStore();
  const canManageSettings = hasTenantPermission('tenant.manage_settings');

  const [formData, setFormData] = useState<Partial<IntegrationSettings>>({
    google_calendar_sync: false,
    slack_integration: false,
    github_integration: false,
    jira_integration: false,
  });

  // Initialize form data from settings
  useEffect(() => {
    if (settingsData?.integration_settings) {
      setFormData(settingsData.integration_settings);
    }
  }, [settingsData]);

  const handleToggle = (field: keyof IntegrationSettings, value: boolean) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await updateIntegrations.mutateAsync(formData);
      toast.success('Integration settings updated successfully');
    } catch (error: any) {
      toast.error(error?.message || 'Failed to update integration settings');
    }
  };

  const handleReset = () => {
    if (settingsData?.integration_settings) {
      setFormData(settingsData.integration_settings);
    }
  };

  const handleConnect = (integration: keyof IntegrationSettings) => {
    // In a real app, this would open OAuth flow
    toast.info(`Connecting ${integration}...`);
  };

  const handleDisconnect = (integration: keyof IntegrationSettings) => {
    handleToggle(integration, false);
    toast.success(`${integration} disconnected`);
  };

  const hasChanges =
    JSON.stringify(formData) !== JSON.stringify(settingsData?.integration_settings);

  if (isLoading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Integration Settings</CardTitle>
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
        <CardTitle>Integration Settings</CardTitle>
        <p className="text-sm text-[var(--color-text-muted)] mt-1">
          Connect your account with third-party services to enhance your workflow.
        </p>
      </CardHeader>
      <form onSubmit={handleSubmit}>
        <CardContent className="space-y-6">
          {/* Google Calendar */}
          <div className="flex items-start justify-between">
            <div className="flex-1">
              <Switch
                label="Google Calendar Sync"
                description="Sync your tasks and deadlines with Google Calendar"
                checked={formData.google_calendar_sync ?? false}
                onChange={(e) => handleToggle('google_calendar_sync', e.target.checked)}
              />
            </div>
            {formData.google_calendar_sync ? (
              <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={() => handleDisconnect('google_calendar_sync')}
              >
                Disconnect
              </Button>
            ) : (
              <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={() => handleConnect('google_calendar_sync')}
              >
                Connect
              </Button>
            )}
          </div>

          {/* Slack */}
          <div className="flex items-start justify-between">
            <div className="flex-1">
              <Switch
                label="Slack Integration"
                description="Receive notifications and updates in Slack"
                checked={formData.slack_integration ?? false}
                onChange={(e) => handleToggle('slack_integration', e.target.checked)}
              />
            </div>
            {formData.slack_integration ? (
              <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={() => handleDisconnect('slack_integration')}
              >
                Disconnect
              </Button>
            ) : (
              <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={() => handleConnect('slack_integration')}
              >
                Connect
              </Button>
            )}
          </div>

          {/* GitHub */}
          <div className="flex items-start justify-between">
            <div className="flex-1">
              <Switch
                label="GitHub Integration"
                description="Link commits and pull requests to tasks"
                checked={formData.github_integration ?? false}
                onChange={(e) => handleToggle('github_integration', e.target.checked)}
              />
            </div>
            {formData.github_integration ? (
              <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={() => handleDisconnect('github_integration')}
              >
                Disconnect
              </Button>
            ) : (
              <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={() => handleConnect('github_integration')}
              >
                Connect
              </Button>
            )}
          </div>

          {/* Jira */}
          <div className="flex items-start justify-between">
            <div className="flex-1">
              <Switch
                label="Jira Integration"
                description="Sync issues and tasks with Jira"
                checked={formData.jira_integration ?? false}
                onChange={(e) => handleToggle('jira_integration', e.target.checked)}
              />
            </div>
            {formData.jira_integration ? (
              <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={() => handleDisconnect('jira_integration')}
              >
                Disconnect
              </Button>
            ) : (
              <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={() => handleConnect('jira_integration')}
              >
                Connect
              </Button>
            )}
          </div>
        </CardContent>
        {canManageSettings && (
          <CardFooter>
            <div className="flex items-center gap-3 w-full">
              <Button
                type="button"
                variant="secondary"
                onClick={handleReset}
                disabled={!hasChanges || updateIntegrations.isPending}
              >
                Reset
              </Button>
              <Button
                type="submit"
                disabled={!hasChanges || updateIntegrations.isPending}
                style={{ marginLeft: 'auto' }}
              >
                {updateIntegrations.isPending ? 'Saving...' : 'Save Changes'}
              </Button>
            </div>
          </CardFooter>
        )}
      </form>
    </Card>
  );
};

