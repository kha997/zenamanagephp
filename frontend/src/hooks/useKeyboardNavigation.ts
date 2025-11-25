import { useEffect, useRef, useCallback } from 'react';

export interface KeyboardNavigationOptions {
  /** Container element ref */
  containerRef?: React.RefObject<HTMLElement>;
  /** Selector for focusable elements */
  selector?: string;
  /** Enable arrow key navigation */
  arrowKeys?: boolean;
  /** Enable home/end keys */
  homeEnd?: boolean;
  /** Enable tab navigation */
  tabNavigation?: boolean;
  /** Callback when focus changes */
  onFocusChange?: (element: HTMLElement) => void;
}

/**
 * useKeyboardNavigation - Hook for keyboard navigation management
 * 
 * Provides keyboard navigation (arrow keys, home/end, tab) for focusable elements.
 * Follows WCAG 2.1 AA guidelines.
 */
export const useKeyboardNavigation = (options: KeyboardNavigationOptions = {}) => {
  const {
    containerRef,
    selector = 'a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])',
    arrowKeys = true,
    homeEnd = true,
    tabNavigation = true,
    onFocusChange,
  } = options;

  const getFocusableElements = useCallback((): HTMLElement[] => {
    const container = containerRef?.current || document;
    return Array.from(container.querySelectorAll(selector)).filter(
      (el) => el instanceof HTMLElement && !el.hasAttribute('disabled')
    ) as HTMLElement[];
  }, [containerRef, selector]);

  const handleKeyDown = useCallback(
    (e: KeyboardEvent) => {
      const focusableElements = getFocusableElements();
      if (focusableElements.length === 0) return;

      const currentIndex = focusableElements.indexOf(document.activeElement as HTMLElement);

      let nextIndex = -1;

      // Arrow keys
      if (arrowKeys) {
        if (e.key === 'ArrowDown' || e.key === 'ArrowRight') {
          e.preventDefault();
          nextIndex = currentIndex < focusableElements.length - 1 ? currentIndex + 1 : 0;
        } else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') {
          e.preventDefault();
          nextIndex = currentIndex > 0 ? currentIndex - 1 : focusableElements.length - 1;
        }
      }

      // Home/End keys
      if (homeEnd) {
        if (e.key === 'Home') {
          e.preventDefault();
          nextIndex = 0;
        } else if (e.key === 'End') {
          e.preventDefault();
          nextIndex = focusableElements.length - 1;
        }
      }

      // Tab navigation (enhanced)
      if (tabNavigation && e.key === 'Tab') {
        // Let browser handle default tab behavior, but track focus
        setTimeout(() => {
          const activeElement = document.activeElement as HTMLElement;
          if (onFocusChange && focusableElements.includes(activeElement)) {
            onFocusChange(activeElement);
          }
        }, 0);
        return;
      }

      if (nextIndex >= 0 && focusableElements[nextIndex]) {
        focusableElements[nextIndex].focus();
        if (onFocusChange) {
          onFocusChange(focusableElements[nextIndex]);
        }
      }
    },
    [getFocusableElements, arrowKeys, homeEnd, tabNavigation, onFocusChange]
  );

  useEffect(() => {
    const container = containerRef?.current || document;
    container.addEventListener('keydown', handleKeyDown);
    return () => {
      container.removeEventListener('keydown', handleKeyDown);
    };
  }, [containerRef, handleKeyDown]);

  return {
    getFocusableElements,
    focusFirst: () => {
      const elements = getFocusableElements();
      if (elements[0]) elements[0].focus();
    },
    focusLast: () => {
      const elements = getFocusableElements();
      if (elements[elements.length - 1]) elements[elements.length - 1].focus();
    },
  };
};

