import { useEffect, useRef, useCallback } from 'react';

export interface FocusManagementOptions {
  /** Trap focus within container */
  trapFocus?: boolean;
  /** Return focus to element on unmount */
  returnFocus?: boolean;
  /** Auto-focus first element on mount */
  autoFocus?: boolean;
  /** Focus selector */
  focusSelector?: string;
}

/**
 * useFocusManagement - Hook for focus management (trap, return, auto-focus)
 * 
 * Follows WCAG 2.1 AA guidelines for focus management.
 */
export const useFocusManagement = (options: FocusManagementOptions = {}) => {
  const {
    trapFocus = false,
    returnFocus = true,
    autoFocus = false,
    focusSelector = 'a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])',
  } = options;

  const containerRef = useRef<HTMLElement | null>(null);
  const previousActiveElementRef = useRef<HTMLElement | null>(null);

  // Save previous active element
  useEffect(() => {
    if (returnFocus) {
      previousActiveElementRef.current = document.activeElement as HTMLElement;
    }
  }, [returnFocus]);

  // Auto-focus first element
  useEffect(() => {
    if (autoFocus && containerRef.current) {
      const firstFocusable = containerRef.current.querySelector(focusSelector) as HTMLElement;
      if (firstFocusable) {
        firstFocusable.focus();
      }
    }
  }, [autoFocus, focusSelector]);

  // Focus trap
  useEffect(() => {
    if (!trapFocus || !containerRef.current) return;

    const container = containerRef.current;
    const focusableElements = Array.from(
      container.querySelectorAll(focusSelector)
    ).filter((el) => el instanceof HTMLElement && !el.hasAttribute('disabled')) as HTMLElement[];

    if (focusableElements.length === 0) return;

    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];

    const handleTabKey = (e: KeyboardEvent) => {
      if (e.key !== 'Tab') return;

      if (e.shiftKey) {
        // Shift + Tab
        if (document.activeElement === firstElement) {
          e.preventDefault();
          lastElement.focus();
        }
      } else {
        // Tab
        if (document.activeElement === lastElement) {
          e.preventDefault();
          firstElement.focus();
        }
      }
    };

    container.addEventListener('keydown', handleTabKey);
    return () => {
      container.removeEventListener('keydown', handleTabKey);
    };
  }, [trapFocus, focusSelector]);

  // Return focus on unmount
  useEffect(() => {
    return () => {
      if (returnFocus && previousActiveElementRef.current) {
        previousActiveElementRef.current.focus();
      }
    };
  }, [returnFocus]);

  return {
    containerRef,
    focusFirst: useCallback(() => {
      if (containerRef.current) {
        const firstFocusable = containerRef.current.querySelector(focusSelector) as HTMLElement;
        if (firstFocusable) firstFocusable.focus();
      }
    }, [focusSelector]),
    focusLast: useCallback(() => {
      if (containerRef.current) {
        const focusableElements = Array.from(
          containerRef.current.querySelectorAll(focusSelector)
        ) as HTMLElement[];
        if (focusableElements.length > 0) {
          focusableElements[focusableElements.length - 1].focus();
        }
      }
    }, [focusSelector]),
  };
};

