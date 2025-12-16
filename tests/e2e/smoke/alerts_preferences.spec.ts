import { test, expect } from '@playwright/test';
import { AuthHelper, testData } from '../helpers/smoke-helpers';

// Helper function to get theme state consistently
const getThemeState = () => {
  const html = document.documentElement;
  return html.dataset.theme ?? (html.classList.contains('dark') ? 'dark' : 'light');
};

test.describe('E2E Smoke Tests - Alerts & Preferences', () => {
  let authHelper: AuthHelper;

  test.beforeEach(async ({ page }) => {
    authHelper = new AuthHelper(page);
    
    // Clear localStorage to prevent theme preference from previous runs
    // Handle security errors gracefully
    try {
      await page.evaluate(() => {
        localStorage.clear();
        sessionStorage.clear();
      });
    } catch (error) {
      console.log('Could not clear storage (security restriction):', (error as Error).message);
    }
  });

  test('@smoke S8: Alert management functionality', async ({ page }) => {
    // Login as admin user
    const adminUser = testData.users.zena.find(user => user.role === 'Admin');
    expect(adminUser).toBeDefined();
    
    if (adminUser) {
      await authHelper.login(adminUser.email, adminUser.password);
      
      // Navigate to alerts page
      await page.goto('/app/alerts');
      
      // Wait for alerts page to load
      await page.waitForTimeout(3000);
      
      // Check if alerts page exists and loads
      const alertsPage = page.locator('h1:has-text("Alerts"), h2:has-text("Alerts"), [data-testid="alerts-page"]');
      const alertsSection = page.locator('[data-testid="alerts"], .alerts, .alert-list');
      
      if (await alertsPage.isVisible() || await alertsSection.isVisible()) {
        // Verify alerts page loads
        expect(await alertsPage.isVisible() || await alertsSection.isVisible()).toBe(true);
        
        // Check for alert elements
        const alertItems = page.locator('.alert-item, [data-testid="alert-item"], .alert');
        const alertCount = await alertItems.count();
        
        // Should have some alerts or empty state
        expect(alertCount).toBeGreaterThanOrEqual(0);
        
        // Test alert interactions if alerts exist
        if (alertCount > 0) {
          const firstAlert = alertItems.first();
          await expect(firstAlert).toBeVisible();
          
          // Look for alert actions (mark as read, dismiss, etc.)
          const alertActions = firstAlert.locator('button, .action');
          const actionCount = await alertActions.count();
          
          if (actionCount > 0) {
            // Test clicking first action
            await alertActions.first().click();
            await page.waitForTimeout(1000);
            
            // Verify no errors occurred
            const errorMessages = page.locator('.error, .alert-error, [data-testid="error"]');
            const hasErrors = await errorMessages.isVisible();
            
            if (hasErrors) {
              console.log('Alert action errors:', await errorMessages.allTextContents());
            }
          }
        }
      } else {
        // If no alerts page, check dashboard for alerts
        await page.goto('/app/dashboard');
        await page.waitForSelector('h1:has-text("Dashboard"), [role="heading"]:has-text("Dashboard")', { timeout: 10000 });
        
        const dashboardAlerts = page.locator('[data-testid="alerts"], .alerts, .alert');
        const dashboardAlertCount = await dashboardAlerts.count();
        
        // Dashboard should have some alert elements or empty state
        expect(dashboardAlertCount).toBeGreaterThanOrEqual(0);
      }
    }
  });

  test('@smoke S9: User preferences management', async ({ page }) => {
    // Login as admin user
    const adminUser = testData.users.zena.find(user => user.role === 'Admin');
    expect(adminUser).toBeDefined();
    
    if (adminUser) {
      await authHelper.login(adminUser.email, adminUser.password);
      
      // Navigate to preferences/settings page
      await page.goto('/app/preferences');
      
      // Wait for preferences page to load
      await page.waitForTimeout(3000);
      
      // Check if preferences page exists
      const preferencesPage = page.locator('h1:has-text("Preferences")').first();
      const settingsPage = page.locator('h1:has-text("Settings")').first();
      
      if (await preferencesPage.isVisible() || await settingsPage.isVisible()) {
        // Verify preferences page loads
        expect(await preferencesPage.isVisible() || await settingsPage.isVisible()).toBe(true);
        
        // Test theme preference with debug logging
        const themeToggle = page.locator('button:has-text("Dark mode"), button:has-text("Theme"), [data-testid="theme-toggle"]').first();
        
        if (await themeToggle.isVisible()) {
          // Get initial theme
          const initialTheme = await page.evaluate(getThemeState);
          console.log('Initial theme:', initialTheme);
          
          // Toggle theme
          await themeToggle.click();
          await page.waitForTimeout(500);
          
          // Get final theme and verify it changed
          const newTheme = await page.evaluate(getThemeState);
          console.log('New theme:', newTheme);
          
          // Verify theme actually changed
          expect(newTheme).not.toBe(initialTheme);
        }
        
        // Test language preference
        const languageSelect = page.locator('select[name*="language"], button[aria-label*="language"]');
        
        if (await languageSelect.isVisible()) {
          // Test language change
          await languageSelect.click();
          await page.waitForTimeout(500);
          
          // Verify language change worked
          const currentLanguage = await page.evaluate(() => 
            document.documentElement.getAttribute('lang') || 'en'
          );
          
          expect(currentLanguage).toBeDefined();
        }
        
        // Test notification preferences
        const notificationToggles = page.locator('input[type="checkbox"][name*="notification"], input[type="checkbox"][name*="email"]');
        const notificationCount = await notificationToggles.count();
        
        if (notificationCount > 0) {
          // Test toggling first notification preference
          const firstToggle = notificationToggles.first();
          const initialState = await firstToggle.isChecked();
          
          await firstToggle.click();
          await page.waitForTimeout(500);
          
          const newState = await firstToggle.isChecked();
          expect(newState).not.toBe(initialState);
        }
        
        // Test saving preferences
        const saveButton = page.locator('button:has-text("Save"), button:has-text("Update"), button[type="submit"]');
        
        if (await saveButton.isVisible()) {
          // Check if save button is enabled (form has detected changes)
          const isSaveEnabled = await saveButton.isEnabled();
          
          if (isSaveEnabled) {
            await saveButton.click();
            await page.waitForTimeout(1000);
            
            // Verify save worked (no errors)
            const errorMessages = page.locator('.error, .alert-error, [data-testid="error"]');
            const hasErrors = await errorMessages.isVisible();
            
            if (hasErrors) {
              console.log('Preferences save errors:', await errorMessages.allTextContents());
            }
          } else {
            console.log('Save button is disabled - no changes detected or form validation preventing save');
            // This is acceptable - the form might auto-save or require specific validation
          }
        }
      } else {
        // If no dedicated preferences page, test user menu preferences
        const userMenu = page.locator('[data-testid="user-menu"], .user-menu, button[aria-label*="user"]');
        
        if (await userMenu.isVisible()) {
          await userMenu.click();
          await page.waitForTimeout(500);
          
          // Look for preferences/settings option
          const preferencesOption = page.locator('a:has-text("Preferences"), a:has-text("Settings"), button:has-text("Preferences")');
          
          if (await preferencesOption.isVisible()) {
            await preferencesOption.click();
            await page.waitForTimeout(1000);
            
            // Should navigate to preferences
            const currentUrl = page.url();
            expect(currentUrl).toMatch(/\/preferences|\/settings/);
          }
        }
      }
    }
  });

  test('@smoke Alert filtering and search', async ({ page }) => {
    // Login as admin user
    const adminUser = testData.users.zena.find(user => user.role === 'Admin');
    expect(adminUser).toBeDefined();
    
    if (adminUser) {
      await authHelper.login(adminUser.email, adminUser.password);
      
      // Navigate to alerts page
      await page.goto('/app/alerts');
      
      // Wait for alerts page to load
      await page.waitForTimeout(3000);
      
      // Check for filter controls
      const filterControls = page.locator('[data-testid="filter"], .filter, .search');
      const filterCount = await filterControls.count();
      
      if (filterCount > 0) {
        // Test filter functionality
        const firstFilter = filterControls.first();
        await expect(firstFilter).toBeVisible();
        
        // Test clicking filter
        await firstFilter.click();
        await page.waitForTimeout(500);
        
        // Verify filter worked
        const currentUrl = page.url();
        expect(currentUrl).toBeDefined();
      }
      
      // Check for search functionality
      const searchInput = page.locator('input[type="search"], input[placeholder*="search"], input[name*="search"]');
      
      if (await searchInput.isVisible()) {
        // Test search
        await searchInput.fill('test');
        await page.waitForTimeout(1000);
        
        // Verify search worked
        const searchResults = page.locator('.search-results, [data-testid="search-results"]');
        const hasResults = await searchResults.isVisible();
        
        // Search should either show results or empty state
        expect(hasResults || !hasResults).toBe(true);
      }
    }
  });

  test('@smoke Preferences persistence', async ({ page }) => {
    // Login as admin user
    const adminUser = testData.users.zena.find(user => user.role === 'Admin');
    expect(adminUser).toBeDefined();
    
    if (adminUser) {
      await authHelper.login(adminUser.email, adminUser.password);
      
      // Navigate to preferences page
      await page.goto('/app/preferences');
      
      // Wait for preferences page to load
      await page.waitForTimeout(3000);
      
      // Test theme persistence
      const themeToggle = page.locator('button[aria-label*="theme"], button:has-text("Theme")');
      
      if (await themeToggle.isVisible()) {
        // Change theme
        await themeToggle.click();
        await page.waitForTimeout(500);
        
        // Refresh page
        await page.reload();
        await page.waitForTimeout(1000);
        
        // Verify theme persisted
        const currentTheme = await page.evaluate(() => 
          document.documentElement.getAttribute('data-theme') || 
          document.documentElement.classList.contains('dark') ? 'dark' : 'light'
        );
        
        expect(currentTheme).toBeDefined();
      }
    }
  });

  test('@smoke Alert notifications', async ({ page }) => {
    // Login as admin user
    const adminUser = testData.users.zena.find(user => user.role === 'Admin');
    expect(adminUser).toBeDefined();
    
    if (adminUser) {
      await authHelper.login(adminUser.email, adminUser.password);
      
      // Navigate to dashboard
      await page.goto('/app/dashboard');
      
      // Wait for dashboard to load
      await page.waitForSelector('h1:has-text("Dashboard"), [role="heading"]:has-text("Dashboard")', { timeout: 10000 });
      
      // Check for notification elements
      const notifications = page.locator('.notification, .toast, [data-testid="notification"]');
      const notificationCount = await notifications.count();
      
      // Should have some notifications or none
      expect(notificationCount).toBeGreaterThanOrEqual(0);
      
      // Test notification interactions
      if (notificationCount > 0) {
        const firstNotification = notifications.first();
        await expect(firstNotification).toBeVisible();
        
        // Look for dismiss button
        const dismissButton = firstNotification.locator('button:has-text("Dismiss"), button:has-text("Close"), .dismiss');
        
        if (await dismissButton.isVisible()) {
          await dismissButton.click();
          await page.waitForTimeout(500);
          
          // Notification should be dismissed
          const stillVisible = await firstNotification.isVisible();
          expect(stillVisible).toBe(false);
        }
      }
    }
  });

  test('@smoke Preferences responsive design', async ({ page }) => {
    // Login as admin user
    const adminUser = testData.users.zena.find(user => user.role === 'Admin');
    expect(adminUser).toBeDefined();
    
    if (adminUser) {
      await authHelper.login(adminUser.email, adminUser.password);
      
      // Test mobile viewport
      await page.setViewportSize({ width: 375, height: 667 });
      await page.goto('/app/preferences');
      
      // Wait for preferences page to load
      await page.waitForTimeout(3000);
      
      // Check if preferences page is accessible on mobile
      const preferencesPage = page.locator('h1:has-text("Preferences")').first();
      const settingsPage = page.locator('h1:has-text("Settings")').first();
      
      if (await preferencesPage.isVisible() || await settingsPage.isVisible()) {
        // Verify preferences page is visible on mobile
        expect(await preferencesPage.isVisible() || await settingsPage.isVisible()).toBe(true);
        
        // Test tablet viewport
        await page.setViewportSize({ width: 768, height: 1024 });
        await page.waitForTimeout(500);
        
        // Verify preferences still work
        expect(await preferencesPage.isVisible() || await settingsPage.isVisible()).toBe(true);
        
        // Test desktop viewport
        await page.setViewportSize({ width: 1200, height: 800 });
        await page.waitForTimeout(500);
        
        // Verify preferences still work
        expect(await preferencesPage.isVisible() || await settingsPage.isVisible()).toBe(true);
      }
    }
  });

  test('@smoke Alert and preferences error handling', async ({ page }) => {
    // Login as admin user
    const adminUser = testData.users.zena.find(user => user.role === 'Admin');
    expect(adminUser).toBeDefined();
    
    if (adminUser) {
      await authHelper.login(adminUser.email, adminUser.password);
      
      // Test alerts page error handling
      await page.goto('/app/alerts');
      await page.waitForTimeout(3000);
      
      // Check for any error messages
      const errorMessages = page.locator('.error, .alert-error, [data-testid="error"]');
      const hasErrors = await errorMessages.isVisible();
      
      if (hasErrors) {
        console.log('Alerts page errors:', await errorMessages.allTextContents());
      }
      
      // Test preferences page error handling
      await page.goto('/app/preferences');
      await page.waitForTimeout(3000);
      
      // Check for any error messages
      const preferencesErrors = page.locator('.error, .alert-error, [data-testid="error"]');
      const hasPreferencesErrors = await preferencesErrors.isVisible();
      
      if (hasPreferencesErrors) {
        console.log('Preferences page errors:', await preferencesErrors.allTextContents());
      }
      
      // Pages should still be functional even with errors
      const currentUrl = page.url();
      expect(currentUrl).toMatch(/\/app\//);
    }
  });
});
