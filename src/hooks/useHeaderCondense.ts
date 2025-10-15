import { useEffect, useRef } from 'react';

interface UseHeaderCondenseOptions {
  enabled?: boolean;
  threshold?: number;
  onCondense?: (isCondensed: boolean) => void;
}

export const useHeaderCondense = ({
  enabled = true,
  threshold = 100,
  onCondense,
}: UseHeaderCondenseOptions = {}) => {
  const isCondensedRef = useRef(false);
  const rafRef = useRef<number>();

  useEffect(() => {
    if (!enabled) return;

    let lastScrollY = window.scrollY;
    let ticking = false;

    const updateCondensedState = () => {
      const currentScrollY = window.scrollY;
      const shouldBeCondensed = currentScrollY > threshold;
      
      if (shouldBeCondensed !== isCondensedRef.current) {
        isCondensedRef.current = shouldBeCondensed;
        onCondense?.(shouldBeCondensed);
      }
      
      ticking = false;
    };

    const handleScroll = () => {
      if (!ticking) {
        rafRef.current = requestAnimationFrame(updateCondensedState);
        ticking = true;
      }
    };

    // Initial check
    updateCondensedState();

    // Add scroll listener with passive option for better performance
    window.addEventListener('scroll', handleScroll, { passive: true });

    return () => {
      window.removeEventListener('scroll', handleScroll);
      if (rafRef.current) {
        cancelAnimationFrame(rafRef.current);
      }
    };
  }, [enabled, threshold, onCondense]);

  return {
    isCondensed: isCondensedRef.current,
  };
};
