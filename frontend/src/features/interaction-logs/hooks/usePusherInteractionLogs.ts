/**
 * Custom hook để subscribe realtime updates cho Interaction Logs qua Pusher
 * Tự động cập nhật cache khi có thay đổi từ server
 */
import { useEffect, useCallback } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import Pusher from 'pusher-js';
import { InteractionLog } from '../types/interactionLog';
import { INTERACTION_LOGS_QUERY_KEYS } from './useInteractionLogs';

// Pusher event types
interface PusherInteractionLogEvent {
  type: 'created' | 'updated' | 'deleted' | 'approved';
  data: InteractionLog;
  project_id: number;
  user_id: number;
}

/**
 * Hook để subscribe realtime updates cho interaction logs
 */
export const usePusherInteractionLogs = (projectId: number, enabled: boolean = true) => {
  const queryClient = useQueryClient();

  // Handler cho các events từ Pusher
  const handleInteractionLogEvent = useCallback(
    (event: PusherInteractionLogEvent) => {
      const { type, data, project_id } = event;

      // Chỉ xử lý events của project hiện tại
      if (project_id !== projectId) return;

      switch (type) {
        case 'created':
          // Invalidate lists để fetch lại data mới
          queryClient.invalidateQueries({ 
            queryKey: INTERACTION_LOGS_QUERY_KEYS.lists() 
          });
          
          // Cập nhật stats
          queryClient.invalidateQueries({ 
            queryKey: INTERACTION_LOGS_QUERY_KEYS.stats(project_id) 
          });
          break;

        case 'updated':
        case 'approved':
          // Cập nhật cache cho item cụ thể
          queryClient.setQueryData(
            INTERACTION_LOGS_QUERY_KEYS.detail(data.id),
            data
          );
          
          // Invalidate lists để refresh
          queryClient.invalidateQueries({ 
            queryKey: INTERACTION_LOGS_QUERY_KEYS.lists() 
          });
          break;

        case 'deleted':
          // Remove từ cache
          queryClient.removeQueries({ 
            queryKey: INTERACTION_LOGS_QUERY_KEYS.detail(data.id) 
          });
          
          // Invalidate lists
          queryClient.invalidateQueries({ 
            queryKey: INTERACTION_LOGS_QUERY_KEYS.lists() 
          });
          
          // Cập nhật stats
          queryClient.invalidateQueries({ 
            queryKey: INTERACTION_LOGS_QUERY_KEYS.stats(project_id) 
          });
          break;
      }
    },
    [queryClient, projectId]
  );

  useEffect(() => {
    if (!enabled || !projectId) return;

    // Khởi tạo Pusher connection
    const pusher = new Pusher(process.env.VITE_PUSHER_KEY!, {
      cluster: process.env.VITE_PUSHER_CLUSTER!,
      encrypted: true,
      authEndpoint: '/api/v1/pusher/auth',
      auth: {
        headers: {
          Authorization: `Bearer ${localStorage.getItem('auth_token')}`,
        },
      },
    });

    // Subscribe to project-specific channel
    const channelName = `private-project.${projectId}.interaction-logs`;
    const channel = pusher.subscribe(channelName);

    // Bind event listeners
    channel.bind('interaction-log.created', (data: PusherInteractionLogEvent) => {
      handleInteractionLogEvent({ ...data, type: 'created' });
    });

    channel.bind('interaction-log.updated', (data: PusherInteractionLogEvent) => {
      handleInteractionLogEvent({ ...data, type: 'updated' });
    });

    channel.bind('interaction-log.deleted', (data: PusherInteractionLogEvent) => {
      handleInteractionLogEvent({ ...data, type: 'deleted' });
    });

    channel.bind('interaction-log.approved', (data: PusherInteractionLogEvent) => {
      handleInteractionLogEvent({ ...data, type: 'approved' });
    });

    // Cleanup function
    return () => {
      channel.unbind_all();
      pusher.unsubscribe(channelName);
      pusher.disconnect();
    };
  }, [enabled, projectId, handleInteractionLogEvent]);

  return {
    // Có thể return thêm connection status nếu cần
    isConnected: enabled && !!projectId,
  };
};