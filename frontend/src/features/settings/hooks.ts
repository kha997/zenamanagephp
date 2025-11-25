import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { settingsApi } from './api';
import type {
  UpdateGeneralSettingsRequest,
  UpdateNotificationSettingsRequest,
  UpdateAppearanceSettingsRequest,
  UpdateSecuritySettingsRequest,
  UpdatePrivacySettingsRequest,
  UpdateIntegrationSettingsRequest,
} from './types';

/**
 * Settings Hooks (React Query)
 */

/**
 * Get all settings
 */
export const useSettings = () => {
  return useQuery({
    queryKey: ['settings'],
    queryFn: () => settingsApi.getSettings(),
    staleTime: 5 * 60 * 1000, // 5 minutes - settings don't change frequently
    gcTime: 10 * 60 * 1000, // 10 minutes
  });
};

/**
 * Update general settings mutation
 */
export const useUpdateGeneral = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: UpdateGeneralSettingsRequest) => settingsApi.updateGeneral(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['settings'] });
    },
  });
};

/**
 * Update notification settings mutation
 */
export const useUpdateNotifications = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: UpdateNotificationSettingsRequest) =>
      settingsApi.updateNotifications(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['settings'] });
    },
  });
};

/**
 * Update appearance settings mutation
 */
export const useUpdateAppearance = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: UpdateAppearanceSettingsRequest) =>
      settingsApi.updateAppearance(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['settings'] });
    },
  });
};

/**
 * Update security settings mutation
 */
export const useUpdateSecurity = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: UpdateSecuritySettingsRequest) => settingsApi.updateSecurity(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['settings'] });
    },
  });
};

/**
 * Update privacy settings mutation
 */
export const useUpdatePrivacy = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: UpdatePrivacySettingsRequest) => settingsApi.updatePrivacy(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['settings'] });
    },
  });
};

/**
 * Update integration settings mutation
 */
export const useUpdateIntegrations = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: UpdateIntegrationSettingsRequest) =>
      settingsApi.updateIntegrations(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['settings'] });
    },
  });
};

