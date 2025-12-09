import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { activityFeedApi, type ActivityFeedParams } from './api';
import { notificationsApi, type NotificationsParams } from './api';

/**
 * useActivityFeed hook
 * 
 * Round 248: Global Activity / My Work Feed
 * 
 * Fetches activity feed for current user with filters
 */
export function useActivityFeed(params: ActivityFeedParams = {}) {
  return useQuery({
    queryKey: ['activity-feed', params],
    queryFn: () => activityFeedApi.getActivityFeed(params),
    staleTime: 30000, // 30 seconds - activity feed should be relatively fresh
  });
}

/**
 * useNotifications hook
 * 
 * Round 251: Notifications Center Phase 1
 * 
 * Fetches notifications for current user with filters
 */
export function useNotifications(params: NotificationsParams = {}) {
  return useQuery({
    queryKey: ['notifications', params],
    queryFn: () => notificationsApi.getNotifications(params),
    staleTime: 30000, // 30 seconds - notifications should be relatively fresh
  });
}

/**
 * useMarkNotificationRead hook
 * 
 * Round 251: Notifications Center Phase 1
 * 
 * Marks a single notification as read
 */
export function useMarkNotificationRead() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (notificationId: string) => notificationsApi.markNotificationRead(notificationId),
    onSuccess: () => {
      // Invalidate notifications queries to refetch
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
  });
}

/**
 * useMarkAllNotificationsRead hook
 * 
 * Round 251: Notifications Center Phase 1
 * 
 * Marks all notifications as read
 */
export function useMarkAllNotificationsRead() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => notificationsApi.markAllNotificationsRead(),
    onSuccess: () => {
      // Invalidate notifications queries to refetch
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
  });
}
