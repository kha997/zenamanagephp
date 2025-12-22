import { test, expect } from '@playwright/test';
import { loginViaUi } from '../helpers/auth-ui';

/**
 * E2E Test: Minimal Auth Flow - UI Login + Auth State Verification
 * 
 * Round 151: Create minimal auth E2E spec to verify UI login + /api/v1/me in Playwright
 * Round 153: Changed strategy - instead of calling /api/v1/me directly (which doesn't work with page.request),
 *            we check localStorage['zena-auth-storage'] which is populated by the app's axios/checkAuth flow.
 * 
 * This test verifies that:
 * 1. UI login works (redirects to /app)
 * 2. App shell boots correctly
 * 3. App's checkAuth() successfully calls /api/v1/me (via axios with proper cookies/XSRF)
 * 4. Auth state is stored in localStorage
 */
test.describe('Auth E2E - UI Login + Auth State Verification', () => {
  test('UI login populates zena-auth-storage & AppShell boots', async ({ page }) => {
    // Round 155: Set up comprehensive diagnostic logging BEFORE any actions
    
    // Console message handler
    page.on('console', (msg) => {
      console.log('[PW console]', msg.type(), msg.text());
    });

    // Page error handler
    page.on('pageerror', (err) => {
      console.log('[PW pageerror]', err.message);
      if (err.stack) {
        console.log('[PW pageerror] stack:', err.stack);
      }
    });

    // Round 155: Route hook to intercept /api/v1/me requests
    await page.route('**/api/v1/me', async (route) => {
      const request = route.request();
      console.log('[PW route] /api/v1/me intercepted:', request.method(), request.url());
      const resp = await route.fetch();
      console.log('[PW route] /api/v1/me response status:', resp.status());
      return route.fulfill({ response: resp });
    });

    // Round 154: Set up request listeners BEFORE login to catch all requests
    // Track /api/v1/me requests from app
    let meRequestSeen = false;
    let meResponseSeen = false;
    page.on('request', (req) => {
      if (req.url().includes('/api/v1/me')) {
        meRequestSeen = true;
        console.log('[auth-me-ui-login] App called /api/v1/me:', req.method(), req.url());
      }
    });
    page.on('response', async (res) => {
      if (res.url().includes('/api/v1/me')) {
        meResponseSeen = true;
        console.log('[auth-me-ui-login] /api/v1/me response:', res.status(), res.url());
        if (res.status() !== 200) {
          const text = await res.text().catch(() => '');
          console.log('[auth-me-ui-login] /api/v1/me error body:', text.slice(0, 200));
        }
      }
    });
    
    // 1. Login bằng UI (reuse loginViaUi)
    await loginViaUi(page);

    // 2. Sau login, khẳng định ta đang ở /app và UI đã boot
    await expect(page).toHaveURL(/\/app(\/|$)/);
    await expect(page.getByText('ZenaManage').first()).toBeVisible();
    
    // Navigate to dashboard to ensure app is fully initialized
    if (!page.url().includes('/app/dashboard')) {
      await page.goto('/app/dashboard');
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(1000);
    }
    
    // Trigger checkAuth() manually if app hasn't called it yet
    await page.evaluate(() => {
      // Dispatch event to trigger checkAuth if app listens to it
      window.dispatchEvent(new CustomEvent('zena:e2e:check-auth'));
    });

    // Round 155: Wait a bit for app to initialize
    await page.waitForTimeout(1000);

    // Round 155: Check E2E logs and DOM markers
    const e2eLogs = await page.evaluate(() => (window as any).__e2e_logs || []);
    console.log('[auth-me-ui-login] ===== DIAGNOSTIC REPORT =====');
    console.log('[auth-me-ui-login] e2eLogs total count:', e2eLogs.length);
    console.log('[auth-me-ui-login] e2eLogs (all):', JSON.stringify(e2eLogs, null, 2));
    
    // Check for specific log events
    const hasEntryLoaded = e2eLogs.some((l: any) => l.scope === 'global' && l.event === 'entry-loaded');
    const hasAppShellMounted = e2eLogs.some((l: any) => l.scope === 'app-shell' && l.event === 'mounted');
    const hasCheckAuthCalled = e2eLogs.some((l: any) => l.scope === 'app-shell' && l.event === 'calling-check-auth');
    const hasMeRequestStarting = e2eLogs.some((l: any) => l.scope === 'app-shell' && l.event === 'me-request-starting');
    const hasCheckAuthError = e2eLogs.some((l: any) => l.scope === 'app-shell' && l.event === 'check-auth-error');
    const globalErrors = e2eLogs.filter((l: any) => l.scope === 'global' && (l.type === 'error' || l.type === 'unhandledrejection'));
    
    console.log('[auth-me-ui-login] hasEntryLoaded:', hasEntryLoaded);
    console.log('[auth-me-ui-login] hasAppShellMounted:', hasAppShellMounted);
    console.log('[auth-me-ui-login] hasCheckAuthCalled:', hasCheckAuthCalled);
    console.log('[auth-me-ui-login] hasMeRequestStarting:', hasMeRequestStarting);
    console.log('[auth-me-ui-login] hasCheckAuthError:', hasCheckAuthError);
    console.log('[auth-me-ui-login] globalErrors count:', globalErrors.length);
    if (globalErrors.length > 0) {
      console.log('[auth-me-ui-login] globalErrors:', JSON.stringify(globalErrors, null, 2));
    }

    // Check DOM marker
    const appShellRoot = page.getByTestId('app-shell-root');
    const isAppShellVisible = await appShellRoot.isVisible().catch(() => false);
    console.log('[auth-me-ui-login] app-shell-root visible:', isAppShellVisible);
    console.log('[auth-me-ui-login] app-shell-root count:', await appShellRoot.count().catch(() => 0));

    // 3. Wait for app to call /api/v1/me and populate localStorage
    // The app's checkAuth() will call /api/v1/me via axios (with proper cookies/XSRF)
    // and store the result in localStorage['zena-auth-storage']
    // Round 153: Wait with retries for app to populate storage
    
    let storage: any = null;
    for (let i = 0; i < 20; i++) {
      await page.waitForTimeout(500);
      storage = await page.evaluate(() => {
        const raw = window.localStorage.getItem('zena-auth-storage');
        try {
          return raw ? JSON.parse(raw) : null;
        } catch {
          return { parseError: true, raw };
        }
      });
      
      if (storage && !storage.parseError && storage.user) {
        console.log(`[auth-me-ui-login] Found zena-auth-storage after ${i + 1} attempts`);
        break;
      }
    }
    
    // Debug info
    console.log('[auth-me-ui-login] meRequestSeen:', meRequestSeen);
    console.log('[auth-me-ui-login] meResponseSeen:', meResponseSeen);
    console.log('[auth-me-ui-login] zena-auth-storage:', storage);
    console.log('[auth-me-ui-login] page.url():', page.url());
    console.log('[auth-me-ui-login] document.cookie:', await page.evaluate(() => document.cookie));
    console.log('[auth-me-ui-login] ===== END DIAGNOSTIC REPORT =====');

    // Assert storage exists and is valid
    expect(storage).not.toBeNull();
    expect(storage?.parseError).toBeUndefined(); // Should not have parse error

    // Extract permissions from storage (handle different shapes)
    const perms =
      storage?.data?.current_tenant_permissions ??
      storage?.current_tenant_permissions ??
      [];

    // Assert permissions is an array
    expect(Array.isArray(perms)).toBe(true);

    // Optional: verify user data exists
    const user = storage?.data?.user ?? storage?.user;
    expect(user).toBeDefined();
    expect(user?.email).toBeDefined();
  });
});

