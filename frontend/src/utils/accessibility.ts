/**
 * Accessibility utilities
 * 
 * Helper functions for WCAG 2.1 AA compliance.
 */

/**
 * Check if element is focusable
 */
export const isFocusable = (element: HTMLElement): boolean => {
  const focusableSelectors = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
  ];

  return focusableSelectors.some((selector) => element.matches(selector));
};

/**
 * Get all focusable elements within a container
 */
export const getFocusableElements = (container: HTMLElement): HTMLElement[] => {
  const selector = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
  ].join(', ');

  return Array.from(container.querySelectorAll(selector)).filter(
    (el) => el instanceof HTMLElement && !el.hasAttribute('disabled')
  ) as HTMLElement[];
};

/**
 * Check color contrast ratio (WCAG AA: 4.5:1 for normal text, 3:1 for large text)
 */
export const getContrastRatio = (color1: string, color2: string): number => {
  // Simplified contrast calculation
  // In production, use a library like `color-contrast` or `wcag-contrast`
  const getLuminance = (color: string): number => {
    // Convert hex to RGB
    const hex = color.replace('#', '');
    const r = parseInt(hex.substr(0, 2), 16) / 255;
    const g = parseInt(hex.substr(2, 2), 16) / 255;
    const b = parseInt(hex.substr(4, 2), 16) / 255;

    // Apply gamma correction
    const [r2, g2, b2] = [r, g, b].map((val) => {
      return val <= 0.03928 ? val / 12.92 : Math.pow((val + 0.055) / 1.055, 2.4);
    });

    return 0.2126 * r2 + 0.7152 * g2 + 0.0722 * b2;
  };

  const lum1 = getLuminance(color1);
  const lum2 = getLuminance(color2);

  const lighter = Math.max(lum1, lum2);
  const darker = Math.min(lum1, lum2);

  return (lighter + 0.05) / (darker + 0.05);
};

/**
 * Check if contrast meets WCAG AA standards
 */
export const meetsWCAGAA = (foreground: string, background: string, isLargeText = false): boolean => {
  const ratio = getContrastRatio(foreground, background);
  return isLargeText ? ratio >= 3 : ratio >= 4.5;
};

/**
 * Skip to main content link (for screen readers)
 */
export const createSkipLink = (targetId: string = 'main-content'): HTMLAnchorElement => {
  const skipLink = document.createElement('a');
  skipLink.href = `#${targetId}`;
  skipLink.textContent = 'Skip to main content';
  skipLink.className = 'skip-link';
  skipLink.style.cssText = `
    position: absolute;
    top: -40px;
    left: 0;
    background: var(--primary);
    color: var(--primary-foreground);
    padding: 8px 16px;
    text-decoration: none;
    z-index: 10000;
  `;
  skipLink.addEventListener('focus', () => {
    skipLink.style.top = '0';
  });
  skipLink.addEventListener('blur', () => {
    skipLink.style.top = '-40px';
  });
  return skipLink;
};

/**
 * Add ARIA labels to icon-only buttons
 */
export const ensureAriaLabels = (container: HTMLElement): void => {
  const iconButtons = container.querySelectorAll('button:not([aria-label]):not([aria-labelledby])');
  iconButtons.forEach((button) => {
    const icon = button.querySelector('svg, [class*="icon"]');
    if (icon && !button.textContent?.trim()) {
      const title = button.getAttribute('title') || button.getAttribute('aria-label');
      if (!title) {
        console.warn('Icon-only button missing aria-label:', button);
      }
    }
  });
};

