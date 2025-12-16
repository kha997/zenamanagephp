import { useEffect, useRef } from 'react';

export interface SwipeGestureOptions {
  /** Minimum swipe distance in pixels */
  threshold?: number;
  /** Maximum vertical deviation allowed */
  maxVerticalDeviation?: number;
  /** Callback for left swipe */
  onSwipeLeft?: () => void;
  /** Callback for right swipe */
  onSwipeRight?: () => void;
  /** Callback for up swipe */
  onSwipeUp?: () => void;
  /** Callback for down swipe */
  onSwipeDown?: () => void;
  /** Enable the gesture */
  enabled?: boolean;
}

/**
 * useSwipeGesture - Hook for detecting swipe gestures
 * 
 * Returns touch handlers for swipe detection.
 */
export const useSwipeGesture = (options: SwipeGestureOptions = {}) => {
  const {
    threshold = 50,
    maxVerticalDeviation = 30,
    onSwipeLeft,
    onSwipeRight,
    onSwipeUp,
    onSwipeDown,
    enabled = true,
  } = options;

  const touchStartRef = useRef<{ x: number; y: number } | null>(null);
  const touchEndRef = useRef<{ x: number; y: number } | null>(null);

  const handleTouchStart = (e: TouchEvent) => {
    if (!enabled) return;
    const touch = e.touches[0];
    touchStartRef.current = { x: touch.clientX, y: touch.clientY };
  };

  const handleTouchEnd = (e: TouchEvent) => {
    if (!enabled || !touchStartRef.current) return;
    const touch = e.changedTouches[0];
    touchEndRef.current = { x: touch.clientX, y: touch.clientY };
    handleSwipe();
  };

  const handleSwipe = () => {
    if (!touchStartRef.current || !touchEndRef.current) return;

    const deltaX = touchEndRef.current.x - touchStartRef.current.x;
    const deltaY = touchEndRef.current.y - touchStartRef.current.y;
    const absDeltaX = Math.abs(deltaX);
    const absDeltaY = Math.abs(deltaY);

    // Determine if swipe is primarily horizontal or vertical
    if (absDeltaX > absDeltaY) {
      // Horizontal swipe
      if (absDeltaY > maxVerticalDeviation) return; // Too much vertical movement
      if (absDeltaX < threshold) return; // Not enough movement

      if (deltaX > 0 && onSwipeRight) {
        onSwipeRight();
      } else if (deltaX < 0 && onSwipeLeft) {
        onSwipeLeft();
      }
    } else {
      // Vertical swipe
      if (absDeltaX > maxVerticalDeviation) return; // Too much horizontal movement
      if (absDeltaY < threshold) return; // Not enough movement

      if (deltaY > 0 && onSwipeDown) {
        onSwipeDown();
      } else if (deltaY < 0 && onSwipeUp) {
        onSwipeUp();
      }
    }

    // Reset
    touchStartRef.current = null;
    touchEndRef.current = null;
  };

  useEffect(() => {
    if (!enabled) return;

    const element = document.body;
    element.addEventListener('touchstart', handleTouchStart, { passive: true });
    element.addEventListener('touchend', handleTouchEnd, { passive: true });

    return () => {
      element.removeEventListener('touchstart', handleTouchStart);
      element.removeEventListener('touchend', handleTouchEnd);
    };
  }, [enabled, onSwipeLeft, onSwipeRight, onSwipeUp, onSwipeDown]);

  return {
    onTouchStart: handleTouchStart,
    onTouchEnd: handleTouchEnd,
  };
};

