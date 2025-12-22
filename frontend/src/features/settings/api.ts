import { createApiClient, mapAxiosError } from '../../shared/api/client';
import type {
  SettingsData,
  UpdateGeneralSettingsRequest,
  UpdateNotificationSettingsRequest,
  UpdateAppearanceSettingsRequest,
  UpdateSecuritySettingsRequest,
  UpdatePrivacySettingsRequest,
  UpdateIntegrationSettingsRequest,
  UserSettings,
  NotificationSettings,
  AppearanceSettings,
  SecuritySettings,
  PrivacySettings,
  IntegrationSettings,
} from './types';

const apiClient = createApiClient();

/**
 * Settings API Client
 * 
 * Endpoints from routes/api_v1.php: /api/v1/app/settings/*
 */
export const settingsApi = {
  /**
   * Get all settings
   * GET /api/v1/app/settings
   */
  async getSettings(): Promise<SettingsData> {
    try {
      const response = await apiClient.get<{ data: SettingsData }>('/v1/app/settings');
      return response.data?.data || response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Update general settings
   * PUT /api/v1/app/settings/general
   */
  async updateGeneral(data: UpdateGeneralSettingsRequest): Promise<UserSettings> {
    try {
      const response = await apiClient.put<{ data: UserSettings; message?: string }>(
        '/v1/app/settings/general',
        data
      );
      return response.data?.data || response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Update notification settings
   * PUT /api/v1/app/settings/notifications
   */
  async updateNotifications(data: UpdateNotificationSettingsRequest): Promise<NotificationSettings> {
    try {
      const response = await apiClient.put<{ data: NotificationSettings; message?: string }>(
        '/v1/app/settings/notifications',
        data
      );
      return response.data?.data || response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Update appearance settings
   * PUT /api/v1/app/settings/appearance
   */
  async updateAppearance(data: UpdateAppearanceSettingsRequest): Promise<AppearanceSettings> {
    try {
      const response = await apiClient.put<{ data: AppearanceSettings; message?: string }>(
        '/v1/app/settings/appearance',
        data
      );
      return response.data?.data || response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Update security settings
   * PUT /api/v1/app/settings/security
   */
  async updateSecurity(data: UpdateSecuritySettingsRequest): Promise<SecuritySettings> {
    try {
      const response = await apiClient.put<{ data: SecuritySettings; message?: string }>(
        '/v1/app/settings/security',
        data
      );
      return response.data?.data || response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Update privacy settings
   * PUT /api/v1/app/settings/privacy
   */
  async updatePrivacy(data: UpdatePrivacySettingsRequest): Promise<PrivacySettings> {
    try {
      const response = await apiClient.put<{ data: PrivacySettings; message?: string }>(
        '/v1/app/settings/privacy',
        data
      );
      return response.data?.data || response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Update integration settings
   * PUT /api/v1/app/settings/integrations
   */
  async updateIntegrations(data: UpdateIntegrationSettingsRequest): Promise<IntegrationSettings> {
    try {
      const response = await apiClient.put<{ data: IntegrationSettings; message?: string }>(
        '/v1/app/settings/integrations',
        data
      );
      return response.data?.data || response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

