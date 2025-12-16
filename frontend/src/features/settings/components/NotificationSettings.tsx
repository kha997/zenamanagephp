import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { Switch } from '../../../components/ui/primitives/Switch';
import { useSettings, useUpdateNotifications } from '../hooks';
import toast from 'react-hot-toast';
import type { NotificationSettings } from '../types';
import { useAuthStore } from '../../auth/store';

/**
 * NotificationSettings Component
 * 
 * Form for managing notification preferences with Switch components
 */
export const NotificationSettings: React.FC = () => {
  const { data: settingsData, isLoading } = useSettings();
  const updateNotifications = useUpdateNotifications();
  const { hasTenantPermission } = useAuthStore();
  const canManageSettings = hasTenantPermission('tenant.manage_settings');

  const [formData, setFormData] = useState<Partial<NotificationSettings>>({
    email_notifications: true,
    push_notifications: true,
    sms_notifications: false,
    project_updates: true,
    task_assignments: true,
    deadline_reminders: true,
    team_invitations: true,
    system_alerts: true,
  });

  // Initialize form data from settings
  useEffect(() => {
    if (settingsData?.notification_settings) {
      setFormData(settingsData.notification_settings);
    }
  }, [settingsData]);

  const handleToggle = (field: keyof NotificationSettings, value: boolean) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await updateNotifications.mutateAsync(formData);
      toast.success('Notification settings updated successfully');
    } catch (error: any) {
      toast.error(error?.message || 'Failed to update notification settings');
    }
  };

  const handleReset = () => {
    if (settingsData?.notification_settings) {
      setFormData(settingsData.notification_settings);
    }
  };

  const hasChanges =
    JSON.stringify(formData) !== JSON.stringify(settingsData?.notification_settings);

  if (isLoading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Notification Settings</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            {[1, 2, 3, 4, 5].map((i) => (
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
        <CardTitle>Notification Settings</CardTitle>
        <p className="text-sm text-[var(--color-text-muted)] mt-1">
          Choose how you want to be notified about updates and activities.
        </p>
      </CardHeader>
      <form onSubmit={handleSubmit}>
        <CardContent className="space-y-6">
          {/* Notification Channels */}
          <div>
            <h3 className="text-sm font-semibold text-[var(--color-text-primary)] mb-4">
              Notification Channels
            </h3>
            <div className="space-y-4">
              <Switch
                label="Email Notifications"
                description="Receive notifications via email"
                checked={formData.email_notifications ?? false}
                onChange={(e) => handleToggle('email_notifications', e.target.checked)}
              />
              <Switch
                label="Push Notifications"
                description="Receive browser push notifications"
                checked={formData.push_notifications ?? false}
                onChange={(e) => handleToggle('push_notifications', e.target.checked)}
              />
              <Switch
                label="SMS Notifications"
                description="Receive notifications via SMS (requires phone number)"
                checked={formData.sms_notifications ?? false}
                onChange={(e) => handleToggle('sms_notifications', e.target.checked)}
              />
            </div>
          </div>

          {/* Notification Types */}
          <div>
            <h3 className="text-sm font-semibold text-[var(--color-text-primary)] mb-4">
              What to Notify Me About
            </h3>
            <div className="space-y-4">
              <Switch
                label="Project Updates"
                description="Get notified when projects you're involved in are updated"
                checked={formData.project_updates ?? false}
                onChange={(e) => handleToggle('project_updates', e.target.checked)}
              />
              <Switch
                label="Task Assignments"
                description="Get notified when tasks are assigned to you"
                checked={formData.task_assignments ?? false}
                onChange={(e) => handleToggle('task_assignments', e.target.checked)}
              />
              <Switch
                label="Deadline Reminders"
                description="Receive reminders before task and project deadlines"
                checked={formData.deadline_reminders ?? false}
                onChange={(e) => handleToggle('deadline_reminders', e.target.checked)}
              />
              <Switch
                label="Team Invitations"
                description="Get notified when you're invited to join a team or project"
                checked={formData.team_invitations ?? false}
                onChange={(e) => handleToggle('team_invitations', e.target.checked)}
              />
              <Switch
                label="System Alerts"
                description="Receive important system alerts and announcements"
                checked={formData.system_alerts ?? false}
                onChange={(e) => handleToggle('system_alerts', e.target.checked)}
              />
            </div>
          </div>
        </CardContent>
        {canManageSettings && (
          <CardFooter>
            <div className="flex items-center gap-3 w-full">
              <Button
                type="button"
                variant="secondary"
                onClick={handleReset}
                disabled={!hasChanges || updateNotifications.isPending}
              >
                Reset
              </Button>
              <Button
                type="submit"
                disabled={!hasChanges || updateNotifications.isPending}
                style={{ marginLeft: 'auto' }}
              >
                {updateNotifications.isPending ? 'Saving...' : 'Save Changes'}
              </Button>
            </div>
          </CardFooter>
        )}
      </form>
    </Card>
  );
};

