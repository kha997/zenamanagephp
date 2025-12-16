import { Page, APIRequestContext } from '@playwright/test';

export interface AuthTokenResponse {
  token: string;
  user: {
    id: string;
    email: string;
    name: string;
    tenant_id: string;
    role?: string;
  };
}

/**
 * Get authentication token via API login endpoint
 * 
 * @param request APIRequestContext from Playwright
 * @param email User email
 * @param password User password
 * @param remember Remember me flag
 * @returns AuthTokenResponse with token and user info
 */
export async function getAuthToken(
  request: APIRequestContext,
  email: string,
  password: string,
  remember: boolean = false
): Promise<AuthTokenResponse> {
  const baseURL = process.env.API_BASE_URL || process.env.BASE_URL || 'http://127.0.0.1:8000';
  
  const response = await request.post(`${baseURL}/api/v1/auth/login`, {
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    },
    data: {
      email,
      password,
      remember,
    },
  });

  if (response.status() !== 200) {
    const errorText = await response.text();
    throw new Error(`Login failed with status ${response.status()}: ${errorText}`);
  }

  const body = await response.json();
  
  // Handle both ApiResponse format (status: 'success', data: {...}) 
  // and direct format (success: true, data: {...})
  let token: string;
  let user: any;

  if (body.status === 'success' && body.data) {
    token = body.data.token;
    user = body.data.user;
  } else if (body.success === true && body.data) {
    token = body.data.token;
    user = body.data.user;
  } else {
    throw new Error(`Unexpected login response format: ${JSON.stringify(body)}`);
  }

  if (!token) {
    throw new Error('Token not found in login response');
  }

  return {
    token,
    user: {
      id: user.id,
      email: user.email,
      name: user.name,
      tenant_id: user.tenant_id,
      role: user.role,
    },
  };
}

/**
 * Set authentication token in page localStorage and cookies
 * This simulates a logged-in user in the React Frontend
 * 
 * Round 135: Updated to use zena-auth-storage (canonical auth store)
 * Legacy auth-storage is no longer used for auth state persistence
 * 
 * @param page Playwright Page instance
 * @param token Authentication token
 */
export async function setAuthToken(page: Page, token: string): Promise<void> {
  // Set token in localStorage (canonical store reads from auth_token)
  await page.evaluate((authToken) => {
    localStorage.setItem('auth_token', authToken);
    // Note: zena-auth-storage will be populated by checkAuth() when the app loads
    // We don't set it here to avoid stale state - let the canonical store manage it
  }, token);

  // Also set as cookie for API requests
  await page.context().addCookies([
    {
      name: 'auth_token',
      value: token,
      domain: 'localhost',
      path: '/',
      httpOnly: false,
      secure: false,
      sameSite: 'Lax',
    },
  ]);
}

/**
 * Authenticate page as a user (get token and set it)
 * 
 * @param page Playwright Page instance
 * @param request APIRequestContext from Playwright
 * @param email User email
 * @param password User password
 * @param remember Remember me flag
 * @returns AuthTokenResponse with token and user info
 */
export async function authenticatePage(
  page: Page,
  request: APIRequestContext,
  email: string,
  password: string,
  remember: boolean = false
): Promise<AuthTokenResponse> {
  const authData = await getAuthToken(request, email, password, remember);
  await setAuthToken(page, authData.token);
  return authData;
}

/**
 * Clear authentication token from page
 * 
 * Round 135: Updated to clear zena-auth-storage (canonical auth store)
 * 
 * @param page Playwright Page instance
 */
export async function clearAuthToken(page: Page): Promise<void> {
  await page.evaluate(() => {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('zena-auth-storage');
    // Also clear legacy auth-storage if it exists (cleanup)
    localStorage.removeItem('auth-storage');
  });

  await page.context().clearCookies();
}

