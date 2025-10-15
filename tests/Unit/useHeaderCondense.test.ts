import { renderHook, act } from '@testing-library/react';
import { useHeaderCondense } from '../../src/hooks/useHeaderCondense';

// Mock requestAnimationFrame
const mockRAF = jest.fn((callback) => setTimeout(callback, 16));
const mockCancelRAF = jest.fn();

Object.defineProperty(window, 'requestAnimationFrame', {
  writable: true,
  value: mockRAF,
});

Object.defineProperty(window, 'cancelAnimationFrame', {
  writable: true,
  value: mockCancelRAF,
});

// Mock window.scrollY
Object.defineProperty(window, 'scrollY', {
  writable: true,
  value: 0,
});

describe('useHeaderCondense', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    window.scrollY = 0;
  });

  afterEach(() => {
    jest.clearAllTimers();
  });

  it('should return initial state', () => {
    const { result } = renderHook(() => useHeaderCondense());
    expect(result.current.isCondensed).toBe(false);
  });

  it('should not condense when disabled', () => {
    const onCondense = jest.fn();
    const { result } = renderHook(() =>
      useHeaderCondense({
        enabled: false,
        threshold: 100,
        onCondense,
      })
    );

    // Simulate scroll
    act(() => {
      window.scrollY = 150;
      window.dispatchEvent(new Event('scroll'));
    });

    expect(onCondense).not.toHaveBeenCalled();
    expect(result.current.isCondensed).toBe(false);
  });

  it('should condense when scroll exceeds threshold', () => {
    const onCondense = jest.fn();
    renderHook(() =>
      useHeaderCondense({
        enabled: true,
        threshold: 100,
        onCondense,
      })
    );

    // Simulate scroll beyond threshold
    act(() => {
      window.scrollY = 150;
      window.dispatchEvent(new Event('scroll'));
    });

    // Wait for RAF
    act(() => {
      jest.advanceTimersByTime(16);
    });

    expect(onCondense).toHaveBeenCalledWith(true);
  });

  it('should not condense when scroll is below threshold', () => {
    const onCondense = jest.fn();
    renderHook(() =>
      useHeaderCondense({
        enabled: true,
        threshold: 100,
        onCondense,
      })
    );

    // Simulate scroll below threshold
    act(() => {
      window.scrollY = 50;
      window.dispatchEvent(new Event('scroll'));
    });

    // Wait for RAF
    act(() => {
      jest.advanceTimersByTime(16);
    });

    expect(onCondense).toHaveBeenCalledWith(false);
  });

  it('should uncondense when scroll returns below threshold', () => {
    const onCondense = jest.fn();
    renderHook(() =>
      useHeaderCondense({
        enabled: true,
        threshold: 100,
        onCondense,
      })
    );

    // First, scroll beyond threshold
    act(() => {
      window.scrollY = 150;
      window.dispatchEvent(new Event('scroll'));
    });

    act(() => {
      jest.advanceTimersByTime(16);
    });

    expect(onCondense).toHaveBeenCalledWith(true);

    // Then, scroll back below threshold
    act(() => {
      window.scrollY = 50;
      window.dispatchEvent(new Event('scroll'));
    });

    act(() => {
      jest.advanceTimersByTime(16);
    });

    expect(onCondense).toHaveBeenCalledWith(false);
  });

  it('should use custom threshold', () => {
    const onCondense = jest.fn();
    renderHook(() =>
      useHeaderCondense({
        enabled: true,
        threshold: 200,
        onCondense,
      })
    );

    // Scroll to 150 (below custom threshold)
    act(() => {
      window.scrollY = 150;
      window.dispatchEvent(new Event('scroll'));
    });

    act(() => {
      jest.advanceTimersByTime(16);
    });

    expect(onCondense).toHaveBeenCalledWith(false);

    // Scroll to 250 (above custom threshold)
    act(() => {
      window.scrollY = 250;
      window.dispatchEvent(new Event('scroll'));
    });

    act(() => {
      jest.advanceTimersByTime(16);
    });

    expect(onCondense).toHaveBeenCalledWith(true);
  });

  it('should throttle scroll events with RAF', () => {
    const onCondense = jest.fn();
    renderHook(() =>
      useHeaderCondense({
        enabled: true,
        threshold: 100,
        onCondense,
      })
    );

    // Multiple rapid scroll events
    act(() => {
      window.scrollY = 150;
      window.dispatchEvent(new Event('scroll'));
      window.dispatchEvent(new Event('scroll'));
      window.dispatchEvent(new Event('scroll'));
    });

    // Should only call RAF once due to throttling
    expect(mockRAF).toHaveBeenCalledTimes(1);

    act(() => {
      jest.advanceTimersByTime(16);
    });

    expect(onCondense).toHaveBeenCalledTimes(1);
  });

  it('should clean up event listeners and RAF on unmount', () => {
    const removeEventListenerSpy = jest.spyOn(window, 'removeEventListener');
    const { unmount } = renderHook(() =>
      useHeaderCondense({
        enabled: true,
        threshold: 100,
      })
    );

    unmount();

    expect(removeEventListenerSpy).toHaveBeenCalledWith('scroll', expect.any(Function));
  });

  it('should handle initial scroll position', () => {
    const onCondense = jest.fn();
    
    // Set initial scroll position
    window.scrollY = 150;

    renderHook(() =>
      useHeaderCondense({
        enabled: true,
        threshold: 100,
        onCondense,
      })
    );

    // Should check initial position
    act(() => {
      jest.advanceTimersByTime(16);
    });

    expect(onCondense).toHaveBeenCalledWith(true);
  });
});
