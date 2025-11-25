import { useEffect, useRef } from 'react';

export interface AriaLiveOptions {
  /** Priority: 'polite' | 'assertive' */
  priority?: 'polite' | 'assertive';
  /** Clear previous announcements */
  clearOnUnmount?: boolean;
}

/**
 * useAriaLive - Hook for ARIA live region announcements
 * 
 * Provides screen reader announcements for dynamic content changes.
 * Follows WCAG 2.1 AA guidelines.
 */
export const useAriaLive = (options: AriaLiveOptions = {}) => {
  const { priority = 'polite', clearOnUnmount = true } = options;
  const liveRegionRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    // Create or get live region
    let liveRegion = document.getElementById('aria-live-region') as HTMLDivElement;
    if (!liveRegion) {
      liveRegion = document.createElement('div');
      liveRegion.id = 'aria-live-region';
      liveRegion.setAttribute('role', 'status');
      liveRegion.setAttribute('aria-live', priority);
      liveRegion.setAttribute('aria-atomic', 'true');
      liveRegion.style.position = 'absolute';
      liveRegion.style.left = '-10000px';
      liveRegion.style.width = '1px';
      liveRegion.style.height = '1px';
      liveRegion.style.overflow = 'hidden';
      document.body.appendChild(liveRegion);
    } else {
      liveRegion.setAttribute('aria-live', priority);
    }

    liveRegionRef.current = liveRegion;

    return () => {
      if (clearOnUnmount && liveRegion) {
        liveRegion.textContent = '';
      }
    };
  }, [priority, clearOnUnmount]);

  const announce = (message: string) => {
    if (liveRegionRef.current) {
      liveRegionRef.current.textContent = message;
      // Clear after announcement for next message
      setTimeout(() => {
        if (liveRegionRef.current) {
          liveRegionRef.current.textContent = '';
        }
      }, 1000);
    }
  };

  return { announce };
};

