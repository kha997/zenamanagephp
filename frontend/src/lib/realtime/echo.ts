/**
 * Laravel Echo Client Singleton
 * 
 * Round 257: Real-time notification handling
 * 
 * Provides a singleton Echo instance for real-time broadcasting.
 * Supports Pusher and Laravel WebSockets based on environment configuration.
 * 
 * Environment Variables:
 * - Backend (Laravel): PUSHER_APP_KEY, PUSHER_APP_CLUSTER
 * - Frontend (Vite): VITE_PUSHER_APP_KEY, VITE_PUSHER_APP_CLUSTER
 * 
 * Ensure .env mapping:
 *   VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
 *   VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
 * 
 * Authentication:
 * - Uses Bearer token from localStorage (same as Axios client)
 * - Token is fetched fresh on each auth request (supports token refresh)
 * - Custom authorizer ensures token is always up-to-date
 * 
 * SSR-safe: Only initializes in browser environment.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { getToken } from '../utils/auth';

// Ensure Pusher is available globally for Laravel Echo
if (typeof window !== 'undefined') {
  (window as any).Pusher = Pusher;
}

let echoInstance: Echo | null = null;

/**
 * Get or create Echo instance
 * 
 * @returns Echo instance or null if not in browser environment
 */
export function getEcho(): Echo | null {
  // SSR guard: only initialize in browser
  if (typeof window === 'undefined') {
    return null;
  }

  // Return existing instance if already created
  if (echoInstance) {
    return echoInstance;
  }

  // Get Pusher configuration from environment
  // 
  // Env Mapping:
  // - Backend (Laravel): PUSHER_APP_KEY, PUSHER_APP_CLUSTER (server-side)
  // - Frontend (Vite): VITE_PUSHER_APP_KEY, VITE_PUSHER_APP_CLUSTER (client-side)
  // 
  // In .env file, ensure mapping:
  //   PUSHER_APP_KEY=your_key
  //   PUSHER_APP_CLUSTER=mt1
  //   VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
  //   VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
  //
  // Support both VITE_PUSHER_APP_KEY (preferred) and VITE_PUSHER_KEY (legacy compatibility)
  const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY || import.meta.env.VITE_PUSHER_KEY;
  const pusherCluster = import.meta.env.VITE_PUSHER_APP_CLUSTER || import.meta.env.VITE_PUSHER_CLUSTER || 'mt1';
  
  // If no Pusher key is configured, return null (real-time features disabled)
  if (!pusherKey) {
    console.warn('[Echo] Pusher key not configured. Real-time features disabled.');
    return null;
  }

  // Get auth token using same utility as Axios client
  // This ensures we use the same storage key and token refresh logic

  try {
    // Initialize Echo with Pusher
    // Use custom authorizer to always get fresh Bearer token from localStorage
    // This matches the Axios client pattern (Bearer token from localStorage)
    echoInstance = new Echo({
      broadcaster: 'pusher',
      key: pusherKey,
      cluster: pusherCluster,
      forceTLS: true,
      encrypted: true,
      authorizer: (channel: any, options: any) => {
        return {
          authorize: (socketId: string, callback: (error: any, data?: any) => void) => {
            // Always get fresh token using same utility as Axios client
            // This ensures Bearer token is always up-to-date (supports token refresh)
            const token = getToken();
            
            // Prepare headers (same as Axios client)
            const headers: Record<string, string> = {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
            };
            
            // Add Bearer token if available (same as Axios client)
            if (token) {
              headers['Authorization'] = `Bearer ${token}`;
            }
            
            // Make auth request to Laravel broadcasting endpoint
            fetch('/broadcasting/auth', {
              method: 'POST',
              headers: {
                ...headers,
                'Content-Type': 'application/json',
              },
              body: JSON.stringify({
                socket_id: socketId,
                channel_name: channel.name,
              }),
            })
              .then((response) => {
                if (!response.ok) {
                  throw new Error(`Auth failed: ${response.status}`);
                }
                return response.json();
              })
              .then((data) => {
                callback(false, data);
              })
              .catch((error) => {
                console.error('[Echo] Authorization error:', error);
                callback(true, error);
              });
          },
        };
      },
      // Enable logging in development
      enabledTransports: ['ws', 'wss'],
      disableStats: false,
    });

    // Handle connection events for debugging
    if (import.meta.env.DEV) {
      echoInstance.connector.pusher.connection.bind('connected', () => {
        console.log('[Echo] Connected to Pusher');
      });

      echoInstance.connector.pusher.connection.bind('disconnected', () => {
        console.log('[Echo] Disconnected from Pusher');
      });

      echoInstance.connector.pusher.connection.bind('error', (error: any) => {
        console.error('[Echo] Connection error:', error);
      });
    }

    return echoInstance;
  } catch (error) {
    console.error('[Echo] Failed to initialize Echo:', error);
    return null;
  }
}

/**
 * Disconnect and cleanup Echo instance
 * 
 * Call this when user logs out or app unmounts
 */
export function disconnectEcho(): void {
  if (echoInstance) {
    try {
      echoInstance.disconnect();
    } catch (error) {
      console.error('[Echo] Error disconnecting:', error);
    }
    echoInstance = null;
  }
}

/**
 * Reconnect Echo instance (useful after token refresh)
 */
export function reconnectEcho(): void {
  disconnectEcho();
  getEcho();
}

// Export default instance getter
export default getEcho;
