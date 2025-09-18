import { useState, useEffect, useRef, useCallback } from 'react';
import { useAuth } from './useAuth';
import { apiClient } from '../lib/api';

interface RealTimeEvent {
  type: string;
  data: any;
  timestamp: string;
}

interface WebSocketMessage {
  type: string;
  event?: string;
  data?: any;
  message?: string;
  timestamp: string;
}

interface SSEEvent {
  id: string;
  event: string;
  data: any;
}

interface UseRealTimeUpdatesOptions {
  channels?: string[];
  projectId?: string;
  autoReconnect?: boolean;
  reconnectInterval?: number;
  heartbeatInterval?: number;
}

export const useRealTimeUpdates = (options: UseRealTimeUpdatesOptions = {}) => {
  const { user } = useAuth();
  const {
    channels = ['dashboard', 'alerts', 'metrics'],
    projectId,
    autoReconnect = true,
    reconnectInterval = 5000,
    heartbeatInterval = 30000
  } = options;

  const [isConnected, setIsConnected] = useState(false);
  const [connectionType, setConnectionType] = useState<'websocket' | 'sse' | null>(null);
  const [lastEvent, setLastEvent] = useState<RealTimeEvent | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [stats, setStats] = useState({
    messagesReceived: 0,
    lastHeartbeat: null as Date | null,
    connectionUptime: 0
  });

  const wsRef = useRef<WebSocket | null>(null);
  const sseRef = useRef<EventSource | null>(null);
  const reconnectTimeoutRef = useRef<NodeJS.Timeout | null>(null);
  const heartbeatTimeoutRef = useRef<NodeJS.Timeout | null>(null);
  const startTimeRef = useRef<Date>(new Date());
  const messageCountRef = useRef(0);

  // WebSocket connection
  const connectWebSocket = useCallback(() => {
    if (!user?.id) return;

    try {
      const wsUrl = `${process.env.REACT_APP_WS_URL || 'ws://localhost:8080'}`;
      const ws = new WebSocket(wsUrl);

      ws.onopen = () => {
        console.log('WebSocket connected');
        setIsConnected(true);
        setConnectionType('websocket');
        setError(null);
        
        // Authenticate
        ws.send(JSON.stringify({
          type: 'authenticate',
          token: localStorage.getItem('auth_token')
        }));

        // Subscribe to channels
        ws.send(JSON.stringify({
          type: 'subscribe',
          channels: channels
        }));

        // Start heartbeat
        startHeartbeat(ws);
      };

      ws.onmessage = (event) => {
        try {
          const message: WebSocketMessage = JSON.parse(event.data);
          handleMessage(message);
        } catch (err) {
          console.error('Error parsing WebSocket message:', err);
        }
      };

      ws.onclose = () => {
        console.log('WebSocket disconnected');
        setIsConnected(false);
        setConnectionType(null);
        
        if (autoReconnect) {
          scheduleReconnect();
        }
      };

      ws.onerror = (error) => {
        console.error('WebSocket error:', error);
        setError('WebSocket connection error');
      };

      wsRef.current = ws;
    } catch (err) {
      console.error('Error creating WebSocket connection:', err);
      setError('Failed to create WebSocket connection');
    }
  }, [user?.id, channels, autoReconnect]);

  // Server-Sent Events connection
  const connectSSE = useCallback(() => {
    if (!user?.id) return;

    try {
      const params = new URLSearchParams({
        channels: channels.join(','),
        ...(projectId && { project_id: projectId })
      });

      const sseUrl = `${process.env.REACT_APP_API_URL}/dashboard/sse?${params}`;
      const sse = new EventSource(sseUrl, {
        withCredentials: true
      });

      sse.onopen = () => {
        console.log('SSE connected');
        setIsConnected(true);
        setConnectionType('sse');
        setError(null);
        startHeartbeat();
      };

      sse.onmessage = (event) => {
        try {
          const data = JSON.parse(event.data);
          handleMessage({
            type: 'sse_message',
            data: data,
            timestamp: new Date().toISOString()
          });
        } catch (err) {
          console.error('Error parsing SSE message:', err);
        }
      };

      // Handle specific event types
      channels.forEach(channel => {
        sse.addEventListener(channel, (event) => {
          try {
            const data = JSON.parse(event.data);
            handleMessage({
              type: channel,
              data: data,
              timestamp: new Date().toISOString()
            });
          } catch (err) {
            console.error(`Error parsing SSE ${channel} event:`, err);
          }
        });
      });

      sse.onerror = (error) => {
        console.error('SSE error:', error);
        setError('SSE connection error');
        
        if (sse.readyState === EventSource.CLOSED) {
          setIsConnected(false);
          setConnectionType(null);
          
          if (autoReconnect) {
            scheduleReconnect();
          }
        }
      };

      sseRef.current = sse;
    } catch (err) {
      console.error('Error creating SSE connection:', err);
      setError('Failed to create SSE connection');
    }
  }, [user?.id, channels, projectId, autoReconnect]);

  // Handle incoming messages
  const handleMessage = useCallback((message: WebSocketMessage) => {
    messageCountRef.current++;
    
    const realTimeEvent: RealTimeEvent = {
      type: message.type,
      data: message.data || message,
      timestamp: message.timestamp
    };

    setLastEvent(realTimeEvent);
    setStats(prev => ({
      ...prev,
      messagesReceived: messageCountRef.current,
      lastHeartbeat: message.type === 'pong' || message.type === 'heartbeat' ? new Date() : prev.lastHeartbeat
    }));

    // Handle specific message types
    switch (message.type) {
      case 'dashboard_update':
        handleDashboardUpdate(message.data);
        break;
      case 'widget_update':
        handleWidgetUpdate(message.data);
        break;
      case 'new_alert':
        handleNewAlert(message.data);
        break;
      case 'metric_update':
        handleMetricUpdate(message.data);
        break;
      case 'project_update':
        handleProjectUpdate(message.data);
        break;
      case 'system_notification':
        handleSystemNotification(message.data);
        break;
      case 'pong':
      case 'heartbeat':
        // Heartbeat received, connection is alive
        break;
      default:
        console.log('Unknown message type:', message.type);
    }
  }, []);

  // Handle dashboard update
  const handleDashboardUpdate = useCallback((data: any) => {
    console.log('Dashboard update received:', data);
    // Trigger dashboard refresh
    window.dispatchEvent(new CustomEvent('dashboard:update', { detail: data }));
  }, []);

  // Handle widget update
  const handleWidgetUpdate = useCallback((data: any) => {
    console.log('Widget update received:', data);
    // Trigger widget refresh
    window.dispatchEvent(new CustomEvent('widget:update', { detail: data }));
  }, []);

  // Handle new alert
  const handleNewAlert = useCallback((data: any) => {
    console.log('New alert received:', data);
    // Trigger alert notification
    window.dispatchEvent(new CustomEvent('alert:new', { detail: data }));
  }, []);

  // Handle metric update
  const handleMetricUpdate = useCallback((data: any) => {
    console.log('Metric update received:', data);
    // Trigger metric refresh
    window.dispatchEvent(new CustomEvent('metric:update', { detail: data }));
  }, []);

  // Handle project update
  const handleProjectUpdate = useCallback((data: any) => {
    console.log('Project update received:', data);
    // Trigger project refresh
    window.dispatchEvent(new CustomEvent('project:update', { detail: data }));
  }, []);

  // Handle system notification
  const handleSystemNotification = useCallback((data: any) => {
    console.log('System notification received:', data);
    // Trigger system notification
    window.dispatchEvent(new CustomEvent('system:notification', { detail: data }));
  }, []);

  // Start heartbeat
  const startHeartbeat = useCallback((ws?: WebSocket) => {
    if (heartbeatTimeoutRef.current) {
      clearTimeout(heartbeatTimeoutRef.current);
    }

    heartbeatTimeoutRef.current = setTimeout(() => {
      if (ws && ws.readyState === WebSocket.OPEN) {
        ws.send(JSON.stringify({ type: 'ping' }));
      }
      startHeartbeat(ws);
    }, heartbeatInterval);
  }, [heartbeatInterval]);

  // Schedule reconnection
  const scheduleReconnect = useCallback(() => {
    if (reconnectTimeoutRef.current) {
      clearTimeout(reconnectTimeoutRef.current);
    }

    reconnectTimeoutRef.current = setTimeout(() => {
      console.log('Attempting to reconnect...');
      connect();
    }, reconnectInterval);
  }, [reconnectInterval]);

  // Connect using best available method
  const connect = useCallback(() => {
    // Try WebSocket first, fallback to SSE
    if (window.WebSocket) {
      connectWebSocket();
    } else {
      connectSSE();
    }
  }, [connectWebSocket, connectSSE]);

  // Disconnect
  const disconnect = useCallback(() => {
    if (wsRef.current) {
      wsRef.current.close();
      wsRef.current = null;
    }

    if (sseRef.current) {
      sseRef.current.close();
      sseRef.current = null;
    }

    if (reconnectTimeoutRef.current) {
      clearTimeout(reconnectTimeoutRef.current);
    }

    if (heartbeatTimeoutRef.current) {
      clearTimeout(heartbeatTimeoutRef.current);
    }

    setIsConnected(false);
    setConnectionType(null);
  }, []);

  // Send message (WebSocket only)
  const sendMessage = useCallback((message: any) => {
    if (wsRef.current && wsRef.current.readyState === WebSocket.OPEN) {
      wsRef.current.send(JSON.stringify(message));
      return true;
    }
    return false;
  }, []);

  // Update connection uptime
  useEffect(() => {
    const interval = setInterval(() => {
      if (isConnected) {
        const uptime = Math.floor((Date.now() - startTimeRef.current.getTime()) / 1000);
        setStats(prev => ({ ...prev, connectionUptime: uptime }));
      }
    }, 1000);

    return () => clearInterval(interval);
  }, [isConnected]);

  // Connect on mount
  useEffect(() => {
    if (user?.id) {
      connect();
    }

    return () => {
      disconnect();
    };
  }, [user?.id, connect, disconnect]);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      disconnect();
    };
  }, [disconnect]);

  return {
    // Connection state
    isConnected,
    connectionType,
    error,
    lastEvent,
    stats,

    // Actions
    connect,
    disconnect,
    sendMessage,
    reconnect: connect,

    // Event handlers
    onDashboardUpdate: (callback: (data: any) => void) => {
      const handler = (event: CustomEvent) => callback(event.detail);
      window.addEventListener('dashboard:update', handler as EventListener);
      return () => window.removeEventListener('dashboard:update', handler as EventListener);
    },
    onWidgetUpdate: (callback: (data: any) => void) => {
      const handler = (event: CustomEvent) => callback(event.detail);
      window.addEventListener('widget:update', handler as EventListener);
      return () => window.removeEventListener('widget:update', handler as EventListener);
    },
    onNewAlert: (callback: (data: any) => void) => {
      const handler = (event: CustomEvent) => callback(event.detail);
      window.addEventListener('alert:new', handler as EventListener);
      return () => window.removeEventListener('alert:new', handler as EventListener);
    },
    onMetricUpdate: (callback: (data: any) => void) => {
      const handler = (event: CustomEvent) => callback(event.detail);
      window.addEventListener('metric:update', handler as EventListener);
      return () => window.removeEventListener('metric:update', handler as EventListener);
    },
    onProjectUpdate: (callback: (data: any) => void) => {
      const handler = (event: CustomEvent) => callback(event.detail);
      window.addEventListener('project:update', handler as EventListener);
      return () => window.removeEventListener('project:update', handler as EventListener);
    },
    onSystemNotification: (callback: (data: any) => void) => {
      const handler = (event: CustomEvent) => callback(event.detail);
      window.addEventListener('system:notification', handler as EventListener);
      return () => window.removeEventListener('system:notification', handler as EventListener);
    }
  };
};
