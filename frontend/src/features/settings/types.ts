/**
 * Settings Types
 * 
 * Type definitions for user settings, notification settings, appearance settings,
 * security settings, privacy settings, and integration settings.
 */

export interface UserSettings {
  timezone: string;
  language: string;
  date_format: string;
  time_format: string;
  currency: string;
}

export interface TenantSettings {
  company_name: string;
  logo_url: string | null;
  primary_color: string;
  secondary_color: string;
  timezone: string;
  date_format: string;
  currency: string;
}

export interface NotificationSettings {
  email_notifications: boolean;
  push_notifications: boolean;
  sms_notifications: boolean;
  project_updates: boolean;
  task_assignments: boolean;
  deadline_reminders: boolean;
  team_invitations: boolean;
  system_alerts: boolean;
}

export interface AppearanceSettings {
  theme: 'light' | 'dark' | 'auto';
  sidebar_collapsed: boolean;
  density: 'compact' | 'comfortable' | 'spacious';
  primary_color: string;
  font_size: 'small' | 'medium' | 'large';
}

export interface SecuritySettings {
  two_factor_enabled: boolean;
  password_expiry_days: number | null;
  session_timeout_minutes: number | null;
  login_attempts_limit: number | null;
}

export interface PrivacySettings {
  profile_visibility: 'public' | 'private' | 'friends';
  activity_sharing: boolean;
  data_collection: boolean;
  analytics_tracking: boolean;
}

export interface IntegrationSettings {
  google_calendar_sync: boolean;
  slack_integration: boolean;
  github_integration: boolean;
  jira_integration: boolean;
}

export interface SettingsData {
  user_settings: UserSettings;
  tenant_settings: TenantSettings;
  notification_settings: NotificationSettings;
  appearance_settings: AppearanceSettings;
  security_settings?: SecuritySettings;
  privacy_settings?: PrivacySettings;
  integration_settings?: IntegrationSettings;
}

// Update request types
export interface UpdateGeneralSettingsRequest {
  timezone?: string;
  language?: string;
  date_format?: string;
  time_format?: string;
  currency?: string;
}

export interface UpdateNotificationSettingsRequest {
  email_notifications?: boolean;
  push_notifications?: boolean;
  sms_notifications?: boolean;
  project_updates?: boolean;
  task_assignments?: boolean;
  deadline_reminders?: boolean;
  team_invitations?: boolean;
  system_alerts?: boolean;
}

export interface UpdateAppearanceSettingsRequest {
  theme?: 'light' | 'dark' | 'auto';
  sidebar_collapsed?: boolean;
  density?: 'compact' | 'comfortable' | 'spacious';
  primary_color?: string;
  font_size?: 'small' | 'medium' | 'large';
}

export interface UpdateSecuritySettingsRequest {
  two_factor_enabled?: boolean;
  password_expiry_days?: number;
  session_timeout_minutes?: number;
  login_attempts_limit?: number;
}

export interface UpdatePrivacySettingsRequest {
  profile_visibility?: 'public' | 'private' | 'friends';
  activity_sharing?: boolean;
  data_collection?: boolean;
  analytics_tracking?: boolean;
}

export interface UpdateIntegrationSettingsRequest {
  google_calendar_sync?: boolean;
  slack_integration?: boolean;
  github_integration?: boolean;
  jira_integration?: boolean;
}

