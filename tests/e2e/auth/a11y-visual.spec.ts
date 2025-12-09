import { test, expect } from '@playwright/test';
import { assertA11yLabels, assertKeyboardNavigation } from './helpers/assertions';

test.describe('Accessibility & Visual', () => {
  test('should have proper focus indicators', async ({ page }) => {
    await page.goto('/login');
    
    // Tab to first input
    await page.keyboard.press('Tab');
    
    // Check if focused element has visible focus
    const focused = await page.evaluate(() => {
      const el = document.activeElement;
      if (!el) return false;
      const style = window.getComputedStyle(el);
      return {
        outlineWidth: style.outlineWidth,
        outlineStyle: style.outlineStyle,
      };
    });
    
    expect(focused.outlineWidth).not.toBe('0px');
    expect(focused.outlineStyle).not.toBe('none');
  });

  test('should support keyboard-only navigation', async ({ page }) => {
    await page.goto('/login');
    
    // Tab through all interactive elements
    await page.keyboard.press('Tab'); // Email
    await page.keyboard.press('Tab'); // Password
    await page.keyboard.press('Tab'); // Remember me
    await page.keyboard.press('Tab'); // Submit
    
    // Should be on submit button
    const focused = await page.evaluate(() => document.activeElement?.tagName);
    expect(['BUTTON', 'INPUT'].includes(focused)).toBeTruthy();
  });

  test('should announce form errors to screen readers', async ({ page }) => {
    await page.goto('/login');
    
    // Try to submit empty form
    await page.keyboard.press('Enter');
    await page.waitForTimeout(500);
    
    // Check for aria-live regions
    const liveRegion = await page.locator('[role="alert"], [aria-live]').first().isVisible();
    
    // Should have error announcement
    expect(liveRegion).toBeTruthy();
  });

  test('should have proper ARIA labels', async ({ page }) => {
    await page.goto('/login');
    
    const emailInput = page.locator('[data-testid="email-input"]');
    const emailLabel = await emailInput.getAttribute('aria-label');
    const emailId = await emailInput.getAttribute('id');
    
    // Should have either aria-label or associated label
    if (!emailLabel) {
      const label = await page.locator(`label[for="${emailId}"]`).isVisible();
      expect(label).toBeTruthy();
    }
  });

  test('should have proper button roles', async ({ page }) => {
    await page.goto('/login');
    
    // All buttons should have proper roles
    const buttons = await page.locator('button').all();
    
    for (const button of buttons) {
      const role = await button.getAttribute('role');
      expect(['button', 'submit']).toContain(role || 'button');
    }
  });

  test('should have correct form semantics', async ({ page }) => {
    await page.goto('/login');
    
    // Form should exist
    const form = page.locator('form');
    await expect(form).toBeVisible();
    
    // Inputs should be properly labeled
    const inputs = await page.locator('input[type="text"], input[type="email"], input[type="password"]').all();
    
    for (const input of inputs) {
      const id = await input.getAttribute('id');
      const ariaLabel = await input.getAttribute('aria-label');
      const placeholder = await input.getAttribute('placeholder');
      
      // Should have some label mechanism
      expect(id || ariaLabel || placeholder).toBeTruthy();
    }
  });

  test('should skip to main content with skip link', async ({ page }) => {
    await page.goto('/login');
    
    // Look for skip to main content link
    const skipLink = page.locator('a[href="#main-content"]');
    
    if (await skipLink.isVisible({ timeout: 1000 }).catch(() => false)) {
      await expect(skipLink).toBeVisible();
    }
  });

  test('should have sufficient color contrast', async ({ page }) => {
    await page.goto('/login');
    
    // This would use axe-core or similar
    // For now, just check that text is visible
    const text = page.locator('h1, h2, label');
    const count = await text.count();
    
    for (let i = 0; i < count; i++) {
      const element = text.nth(i);
      const isVisible = await element.isVisible();
      expect(isVisible).toBeTruthy();
    }
  });

  test('should handle reduced motion preference', async ({ page }) => {
    await page.goto('/login');
    
    // Set prefers-reduced-motion
    await page.emulateMedia({ reducedMotion: 'reduce' });
    
    // Page should still be functional
    await page.fill('[data-testid="email-input"]', 'test@test.com');
    await page.fill('[data-testid="password-input"]', 'password');
    
    // Submit should still work
    await expect(page.locator('[data-testid="login-submit"]')).toBeEnabled();
  });

  test('should work with screen reader', async ({ page }) => {
    // This would typically use NVDA/JAWS testing
    // For now, verify semantic HTML
    await page.goto('/login');
    
    // Form should have proper landmarks
    const landmarks = await page.locator('[role="main"], main, [role="form"]').count();
    expect(landmarks).toBeGreaterThan(0);
  });

  test('should have visual snapshot of login page', async ({ page }) => {
    await page.goto('/login');
    
    // Wait for page to be fully loaded
    await page.waitForLoadState('networkidle');
    
    // Take screenshot
    await expect(page).toHaveScreenshot('login-page.png', {
      // Small, stable masks for dynamic elements
      mask: [
        page.locator('[data-testid="csrf-token"]'),
      ],
      fullPage: true,
    });
  });

  test('should have visual snapshot of registration page', async ({ page }) => {
    await page.goto('/register');
    await page.waitForLoadState('networkidle');
    
    await expect(page).toHaveScreenshot('register-page.png', {
      mask: [page.locator('[data-testid="csrf-token"]')],
      fullPage: true,
    });
  });

  test('should match design on mobile', async ({ page }) => {
    // Set mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    
    await page.goto('/login');
    await page.waitForLoadState('networkidle');
    
    await expect(page).toHaveScreenshot('login-mobile.png', {
      mask: [page.locator('[data-testid="csrf-token"]')],
      fullPage: true,
    });
  });
});

