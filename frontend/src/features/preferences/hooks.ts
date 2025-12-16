import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { preferencesApi, type UserPreferences } from './api';

export const usePreferences = () => {
  return useQuery({
    queryKey: ['user-preferences'],
    queryFn: () => preferencesApi.getPreferences(),
    staleTime: 5 * 60 * 1000, // 5 minutes
  });
};

export const useUpdatePreferences = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (preferences: Partial<UserPreferences>) =>
      preferencesApi.updatePreferences(preferences),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['user-preferences'] });
    },
  });
};

export const useKpiPreferences = () => {
  return useQuery({
    queryKey: ['kpi-preferences'],
    queryFn: () => preferencesApi.getKpiPreferences(),
    staleTime: 5 * 60 * 1000,
  });
};

export const useSaveKpiPreferences = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (preferences: { [page: string]: string[] }) =>
      preferencesApi.saveKpiPreferences(preferences),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['kpi-preferences'] });
    },
  });
};

