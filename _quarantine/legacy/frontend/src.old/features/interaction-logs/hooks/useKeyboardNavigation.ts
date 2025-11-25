import { useEffect, useRef, useState } from 'react';

interface UseKeyboardNavigationOptions {
  items: any[];
  onSelect?: (index: number, item: any) => void;
  onEscape?: () => void;
  loop?: boolean;
  disabled?: boolean;
}

export const useKeyboardNavigation = ({
  items,
  onSelect,
  onEscape,
  loop = true,
  disabled = false,
}: UseKeyboardNavigationOptions) => {
  const [activeIndex, setActiveIndex] = useState(-1);
  const containerRef = useRef<HTMLElement>(null);

  const handleKeyDown = (event: KeyboardEvent) => {
    if (disabled || items.length === 0) return;

    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault();
        setActiveIndex(prev => {
          const next = prev + 1;
          return next >= items.length ? (loop ? 0 : prev) : next;
        });
        break;

      case 'ArrowUp':
        event.preventDefault();
        setActiveIndex(prev => {
          const next = prev - 1;
          return next < 0 ? (loop ? items.length - 1 : 0) : next;
        });
        break;

      case 'Enter':
      case ' ':
        event.preventDefault();
        if (activeIndex >= 0 && activeIndex < items.length && onSelect) {
          onSelect(activeIndex, items[activeIndex]);
        }
        break;

      case 'Escape':
        event.preventDefault();
        setActiveIndex(-1);
        if (onEscape) {
          onEscape();
        }
        break;

      case 'Home':
        event.preventDefault();
        setActiveIndex(0);
        break;

      case 'End':
        event.preventDefault();
        setActiveIndex(items.length - 1);
        break;
    }
  };

  useEffect(() => {
    const container = containerRef.current;
    if (container) {
      container.addEventListener('keydown', handleKeyDown);
      return () => container.removeEventListener('keydown', handleKeyDown);
    }
  }, [items, activeIndex, disabled]);

  // Focus management
  useEffect(() => {
    if (activeIndex >= 0 && containerRef.current) {
      const activeElement = containerRef.current.querySelector(
        `[data-index="${activeIndex}"]`
      ) as HTMLElement;
      if (activeElement) {
        activeElement.focus();
      }
    }
  }, [activeIndex]);

  return {
    activeIndex,
    setActiveIndex,
    containerRef,
  };
};