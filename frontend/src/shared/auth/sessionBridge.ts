/**
 * SPA Auth Bridge
 * 
 * Handles authentication bridge between web session and SPA token.
 * 
 * Flow:
 * 1. User logs in via web (session-based)
 * 2. SPA calls /api/v1/auth/session-token to get Sanctum token
 * 3. Token is stored in localStorage
 * 4. When session expires, token is rotated
 */

import axios from 'axios';

export interface SessionTokenResponse {
  ok: boolean;
  token?: string;
  token_type?: string;
  expires_in?: number;
  expires_at?: string;
  user?: {
    id: string;
    name: string;
    email: string;
    tenant_id: string;
    role: string;
  };
  traceId?: string;
  code?: string;
  message?: string;
}

/**
 * Get CSRF cookie for session-based auth
 * Should be called before session-token if using session auth
 */
export async function getCsrfCookie(): Promise<void> {
  try {
    await axios.get('/sanctum/csrf-cookie', {
      withCredentials: true,
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });
  } catch (error) {
    console.debug('CSRF cookie request failed (may not be needed for token auth):', error);
  }
}

/**
 * Get session token from web session
 * 
 * @returns SessionTokenResponse
 */
export async function getSessionToken(): Promise<SessionTokenResponse> {
  try {
    // First, get CSRF cookie if needed
    await getCsrfCookie();
    
    const response = await axios.get<SessionTokenResponse>('/api/v1/auth/session-token', {
      withCredentials: true, // Include cookies for session auth
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });
    
    return response.data;
  } catch (error: any) {
    if (axios.isAxiosError(error)) {
      return error.response?.data || {
        ok: false,
        code: 'SESSION_TOKEN_ERROR',
        message: error.message,
      };
    }
    
    return {
      ok: false,
      code: 'SESSION_TOKEN_ERROR',
      message: 'Failed to get session token',
    };
  }
}

/**
 * Initialize auth from session (called on app startup)
 * 
 * @param onTokenReceived Callback when token is received
 * @param onError Callback when error occurs
 */
export async function initializeAuthFromSession(
  onTokenReceived?: (token: string, user: SessionTokenResponse['user']) => void,
  onError?: (error: string) => void
): Promise<boolean> {
  // Check if token already exists in localStorage
  const existingToken = localStorage.getItem('auth_token');
  if (existingToken) {
    // Verify token is still valid by checking expiry
    const tokenData = parseTokenExpiry(existingToken);
    if (tokenData && tokenData.expiresAt > Date.now()) {
      // Token is still valid
      return true;
    }
    // Token expired, remove it
    localStorage.removeItem('auth_token');
  }
  
  // Try to get token from session
  const response = await getSessionToken();
  
  if (response.ok && response.token) {
    // Store token
    localStorage.setItem('auth_token', response.token);
    
    // Set axios default header
    axios.defaults.headers.common['Authorization'] = `Bearer ${response.token}`;
    
    // Call callback
    if (onTokenReceived && response.user) {
      onTokenReceived(response.token, response.user);
    }
    
    return true;
  } else {
    // No session available
    if (onError) {
      onError(response.message || 'Not authenticated');
    }
    return false;
  }
}

/**
 * Rotate token when session expires
 * 
 * @returns New token or null if rotation failed
 */
export async function rotateToken(): Promise<string | null> {
  const response = await getSessionToken();
  
  if (response.ok && response.token) {
    localStorage.setItem('auth_token', response.token);
    axios.defaults.headers.common['Authorization'] = `Bearer ${response.token}`;
    return response.token;
  }
  
  // Rotation failed, clear token
  localStorage.removeItem('auth_token');
  delete axios.defaults.headers.common['Authorization'];
  
  return null;
}

/**
 * Parse token expiry (if stored with expiry info)
 * This is a helper - actual token validation should be done server-side
 */
function parseTokenExpiry(token: string): { expiresAt: number } | null {
  try {
    // If token includes expiry info (custom format), parse it
    // For now, just return null - server will validate
    return null;
  } catch {
    return null;
  }
}

/**
 * Logout and clear session token
 */
export async function logout(): Promise<void> {
  // Clear local token
  localStorage.removeItem('auth_token');
  delete axios.defaults.headers.common['Authorization'];
  
  // Call logout endpoint
  try {
    await axios.post('/logout', {}, {
      withCredentials: true,
    });
  } catch (error) {
    // Ignore errors - token is already cleared
    console.debug('Logout endpoint error (non-critical):', error);
  }
}

