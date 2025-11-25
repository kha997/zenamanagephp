import { useEffect, useRef, useState } from 'react';
import { 
  generateId, 
  focusManagement, 
  screenReader, 
  keyboardNavigation,
  reducedMotion 
} from '../lib/accessibility';

interface UseAccessibilityOptions {
  announceOnMount?: string;
  trapFocus?: boolean;
  restoreFocusOnUnmount?: boolean;
}

export const useAccessibility = (options: UseAccessibilityOptions = {}) => {
  const {
    announceOnMount,
    trapFocus = false,
    restoreFocusOnUnmount = false
  } = options;

  const containerRef = useRef<HTMLElement>(null);
  const previousFocusRef = useRef<HTMLElement | null>(null);
  const [uniqueId] = useState(() => generateId('accessible'));

  // Announce message on mount
  useEffect(() => {
    if (announceOnMount) {
      screenReader.announce(announceOnMount);
    }
  }, [announceOnMount]);

  // Save focus on mount, restore on unmount
  useEffect(() => {
    if (restoreFocusOnUnmount) {
      previousFocusRef.current = focusManagement.saveFocus();
    }

    return () => {
      if (restoreFocusOnUnmount && previousFocusRef.current) {
        focusManagement.restoreFocus(previousFocusRef.current);
      }
    };
  }, [restoreFocusOnUnmount]);

  // Handle focus trapping
  useEffect(() => {
    if (!trapFocus || !containerRef.current) return;

    const handleKeyDown = (event: KeyboardEvent) => {
      if (containerRef.current) {
        focusManagement.trapFocus(containerRef.current, event);
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [trapFocus]);

  return {
    containerRef,
    uniqueId,
    announce: screenReader.announce,
    handleArrowNavigation: keyboardNavigation.handleArrowNavigation,
    handleEscape: keyboardNavigation.handleEscape,
    prefersReducedMotion: reducedMotion.prefersReducedMotion()
  };
};

// Hook for managing focus within lists/menus
export const useFocusManagement = (itemCount: number) => {
  const [focusedIndex, setFocusedIndex] = useState(-1);
  const itemRefs = useRef<(HTMLElement | null)[]>([]);

  const setItemRef = (index: number) => (element: HTMLElement | null) => {
    itemRefs.current[index] = element;
  };

  const focusItem = (index: number) => {
    if (itemRefs.current[index]) {
      itemRefs.current[index]?.focus();
      setFocusedIndex(index);
    }
  };

  const handleKeyNavigation = (event: KeyboardEvent) => {
    keyboardNavigation.handleArrowNavigation(
      event,
      itemRefs.current.filter(Boolean) as HTMLElement[],
      focusedIndex,
      setFocusedIndex
    );
  };

  return {
    focusedIndex,
    setItemRef,
    focusItem,
    handleKeyNavigation
  };
};

// Hook for managing modal accessibility
export const useModalAccessibility = () => {
  const { containerRef, announce, handleEscape } = useAccessibility({
    trapFocus: true,
    restoreFocusOnUnmount: true
  });

  const [isOpen, setIsOpen] = useState(false);

  const openModal = (announceMessage?: string) => {
    setIsOpen(true);
    if (announceMessage) {
      announce(announceMessage);
    }
  };

  const closeModal = () => {
    setIsOpen(false);
    announce('Modal đã đóng');
  };

  const handleModalKeyDown = (event: KeyboardEvent) => {
    handleEscape(event, closeModal);
  };

  return {
    containerRef,
    isOpen,
    openModal,
    closeModal,
    handleModalKeyDown
  };
};