import { useState, useEffect } from 'react';

interface BreakpointConfig {
  sm: number;
  md: number;
  lg: number;
  xl: number;
  '2xl': number;
}

const defaultBreakpoints: BreakpointConfig = {
  sm: 640,
  md: 768,
  lg: 1024,
  xl: 1280,
  '2xl': 1536
};

export type Breakpoint = keyof BreakpointConfig;

/**
 * Hook để detect screen size và breakpoints
 * Trả về thông tin về current breakpoint và screen size
 */
export const useResponsive = (breakpoints: BreakpointConfig = defaultBreakpoints) => {
  const [screenSize, setScreenSize] = useState({
    width: typeof window !== 'undefined' ? window.innerWidth : 0,
    height: typeof window !== 'undefined' ? window.innerHeight : 0
  });

  useEffect(() => {
    const handleResize = () => {
      setScreenSize({
        width: window.innerWidth,
        height: window.innerHeight
      });
    };

    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, []);

  /**
   * Check if current screen is at or above a breakpoint
   */
  const isAbove = (breakpoint: Breakpoint): boolean => {
    return screenSize.width >= breakpoints[breakpoint];
  };

  /**
   * Check if current screen is below a breakpoint
   */
  const isBelow = (breakpoint: Breakpoint): boolean => {
    return screenSize.width < breakpoints[breakpoint];
  };

  /**
   * Get current breakpoint
   */
  const getCurrentBreakpoint = (): Breakpoint => {
    const { width } = screenSize;
    
    if (width >= breakpoints['2xl']) return '2xl';
    if (width >= breakpoints.xl) return 'xl';
    if (width >= breakpoints.lg) return 'lg';
    if (width >= breakpoints.md) return 'md';
    if (width >= breakpoints.sm) return 'sm';
    
    return 'sm'; // Default to smallest
  };

  return {
    screenSize,
    isAbove,
    isBelow,
    getCurrentBreakpoint,
    isMobile: isBelow('md'),
    isTablet: isAbove('md') && isBelow('lg'),
    isDesktop: isAbove('lg'),
    currentBreakpoint: getCurrentBreakpoint()
  };
};

/**
 * Hook để detect if device is mobile based on user agent
 * Useful for detecting actual mobile devices vs small desktop windows
 */
export const useIsMobileDevice = (): boolean => {
  const [isMobileDevice, setIsMobileDevice] = useState(false);

  useEffect(() => {
    const userAgent = navigator.userAgent.toLowerCase();
    const mobileKeywords = [
      'android', 'webos', 'iphone', 'ipad', 'ipod',
      'blackberry', 'windows phone', 'mobile'
    ];
    
    const isMobile = mobileKeywords.some(keyword => 
      userAgent.includes(keyword)
    );
    
    setIsMobileDevice(isMobile);
  }, []);

  return isMobileDevice;
};