import { Page, expect } from '@playwright/test';

/**
 * Shared helper: Login via UI form (real login flow)
 * 
 * Round 151: Extracted from apply-template.spec.ts and improved with better error handling
 * Round 161: Documented credentials - E2E login uses admin@zena.local / password
 * Round 164: Improved with waitForResponse, better logging, and explicit error handling
 * 
 * Navigates to /login, fills email/password, submits, waits for redirect to /app
 * Fails clearly if login doesn't succeed (shows error message or doesn't redirect)
 * 
 * E2E CREDENTIALS (Round 161):
 * - Email: admin@zena.local (created by E2EDatabaseSeeder)
 * - Password: password (bcrypt hashed in seeder)
 * - User has tenant_id set and user_tenants pivot record with role=owner
 * 
 * @param page Playwright Page instance
 * @param opts Optional credentials (defaults to admin@zena.local / password)
 * @throws Error if login fails (error message shown, wrong password, or no redirect)
 */
export async function loginViaUi(
  page: Page, 
  opts?: { email?: string; password?: string }
): Promise<void> {
  // Round 161: E2E login credentials - must match E2EDatabaseSeeder
  const email = opts?.email ?? 'admin@zena.local';
  const password = opts?.password ?? 'password';

  // Round 164: Log before login
  console.log('[loginViaUi] before login, url:', page.url());

  // Note: Login form already includes X-Web-Login header in JavaScript (login.blade.php line 171)
  // No need to intercept - the form's fetch() call already has the header

  // Navigate to login page if not already there
  const currentUrl = page.url();
  if (!currentUrl.includes('/login')) {
    await page.goto('/login');
  }
  
  // Wait for login form to be ready
  await page.waitForSelector('input[name="email"], input[type="email"], [data-testid="email-input"]', { timeout: 10000 });
  
  // Fill email (try multiple selectors for robustness)
  await page.fill('input[name="email"], input[type="email"], [data-testid="email-input"]', email);
  
  // Fill password
  await page.fill('input[name="password"], input[type="password"], [data-testid="password-input"]', password);
  
  // Round 164: Wait for login response BEFORE clicking submit
  // This ensures we catch the response and can check its status
  const loginResponsePromise = page.waitForResponse(
    (response) => {
      const url = response.url();
      const method = response.request().method();
      return url.includes('/api/auth/login') && method === 'POST';
    },
    { timeout: 30000 }
  );
  
  // Submit form (try multiple selectors)
  await page.click('button[type="submit"], #loginButton, button:has-text("Login"), button:has-text("Sign In"), button:has-text("Đăng nhập")');
  
  // Round 164: Wait for login response and check status
  const loginResponse = await loginResponsePromise;
  const loginStatus = loginResponse.status();
  
  if (!loginResponse.ok() || loginStatus !== 200) {
    // Try to get error body for better error message
    let errorBody = '';
    try {
      errorBody = await loginResponse.text();
    } catch (e) {
      errorBody = 'Could not read response body';
    }
    throw new Error(`[loginViaUi] /api/auth/login failed with status ${loginStatus}. URL: ${loginResponse.url()}. Body: ${errorBody.substring(0, 500)}`);
  }
  
  // Round 164: Parse response to check if login was successful
  let loginData: any = null;
  try {
    loginData = await loginResponse.json();
  } catch (e) {
    console.log('[loginViaUi] WARNING: Could not parse login response JSON, but status was 200. Continuing...');
  }
  
  if (loginData && loginData.success === false) {
    const errorMessage = loginData.error?.message || loginData.error || 'Login failed';
    throw new Error(`[loginViaUi] Login failed – API returned success=false. Error: ${errorMessage}`);
  }
  
  // Round 164: Wait a bit for the login page to process the response and prepare redirect
  // The login page shows success message and then redirects after 2 seconds
  await page.waitForTimeout(500);
  
  // Round 164: Wait for redirect to /app/** (login page redirects to /app/dashboard after 2 seconds)
  // The login page uses setTimeout(() => window.location.href = '/app/dashboard', 2000)
  // So we need to wait for the navigation to happen (give it up to 10 seconds for the redirect)
  try {
    await page.waitForURL('**/app/**', { timeout: 10000 });
  } catch (e) {
    // If redirect didn't happen, check if we're still on /login and if there's an error
    const currentUrl = page.url();
    if (currentUrl.includes('/login')) {
      // Check for error messages on the page
      const errorText = await page.getByText(/sai mật khẩu|login failed|invalid credentials|authentication failed/i).isVisible().catch(() => false);
      if (errorText) {
        const errorMessage = await page.getByText(/sai mật khẩu|login failed|invalid credentials|authentication failed/i).textContent().catch(() => 'Unknown error');
        throw new Error(`[loginViaUi] Login failed – error message shown on screen: "${errorMessage}"`);
      }
      // No error message but still on /login - might be a session issue
      // Try navigating to /app/dashboard manually
      console.log('[loginViaUi] WARNING: Still on /login after successful API response. Attempting manual navigation to /app/dashboard...');
      await page.goto('/app/dashboard');
      await page.waitForLoadState('networkidle');
      // Check if we're still redirected back to /login (session issue)
      const checkUrl = page.url();
      if (checkUrl.includes('/login')) {
        throw new Error(`[loginViaUi] Login failed – session not set properly. After manual navigation to /app/dashboard, still redirected to /login. This suggests a session/auth issue.`);
      }
    } else {
      // Not on /login and not on /app - unexpected state
      throw new Error(`[loginViaUi] Login failed – unexpected URL after login attempt. Current URL: ${currentUrl}`);
    }
  }
  
  // Round 164: Log after login
  console.log('[loginViaUi] after login, url:', page.url());
  
  // Round 164: Optional - check cookies after login
  const cookies = await page.context().cookies();
  const hasSessionCookie = cookies.some(c => c.name === 'laravel_session' || c.name === 'XSRF-TOKEN');
  console.log('[loginViaUi] cookies after login:', hasSessionCookie ? 'session cookie found' : 'no session cookie');
  
  // Wait for navigation to complete
  await page.waitForLoadState('networkidle');
  
  // Round 151: Check for error messages BEFORE checking URL
  // This ensures we fail fast if login credentials are wrong
  const errorText = await page.getByText(/sai mật khẩu|login failed|invalid credentials|authentication failed/i).isVisible().catch(() => false);
  if (errorText) {
    const errorMessage = await page.getByText(/sai mật khẩu|login failed|invalid credentials|authentication failed/i).textContent().catch(() => 'Unknown error');
    throw new Error(`[loginViaUi] Login failed – error message shown on screen: "${errorMessage}"`);
  }
  
  // Verify we're logged in (should be on /app/*)
  const finalUrl = page.url();
  if (!finalUrl.includes('/app')) {
    // If not on /app, try navigating to dashboard
    await page.goto('/app/dashboard');
    await page.waitForLoadState('networkidle');
    
    // Double-check we're on /app after navigation attempt
    const checkUrl = page.url();
    if (!checkUrl.includes('/app')) {
      throw new Error(`[loginViaUi] Login failed – still not on /app after navigation attempt. Current URL: ${checkUrl}`);
    }
  }
  
  // Wait for app shell to be visible (ZenaManage header or similar)
  // Round 151: Make this a hard requirement, not just a warning
  try {
    await expect(page.getByText('ZenaManage')).toBeVisible({ timeout: 10000 });
  } catch (e) {
    // Fallback: check for any header or logged-in marker
    const hasHeader = await page.locator('header, [data-testid="header-wrapper"], [data-testid="header-shell"]').count();
    if (hasHeader === 0) {
      throw new Error(`[loginViaUi] Login may have failed – header not found after login. Current URL: ${page.url()}`);
    }
    // If header exists but ZenaManage text not found, log warning but continue
    console.log('[loginViaUi] WARNING: ZenaManage text not found, but header exists. Continuing...');
  }
  
  // Wait a bit for the app to initialize and potentially call /api/v1/me
  await page.waitForTimeout(1000);
}

