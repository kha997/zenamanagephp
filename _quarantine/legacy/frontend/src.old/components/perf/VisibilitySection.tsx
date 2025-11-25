import React from 'react';
import { cn } from '../../shared/ui/utils';

export interface VisibilitySectionProps {
  /** Children to render */
  children: React.ReactNode;
  /** Additional CSS classes */
  className?: string;
  /** Intrinsic width in pixels (for content-visibility optimization) */
  intrinsicWidth?: number;
  /** Intrinsic height in pixels (for content-visibility optimization) */
  intrinsicHeight?: number;
}

/**
 * VisibilitySection - Performance optimization component using content-visibility
 * 
 * Uses CSS content-visibility: auto to defer rendering of off-screen content,
 * reducing initial layout calculations and forced reflow.
 * 
 * Features:
 * - Defers rendering until element enters viewport
 * - Maintains scroll position with intrinsic size hints
 * - Reduces initial JavaScript execution
 * - Improves LCP and FCP metrics
 */
export const VisibilitySection: React.FC<VisibilitySectionProps> = ({
  children,
  className,
  intrinsicWidth = 600,
  intrinsicHeight = 400,
}) => {
  return (
    <div
      className={className}
      style={{
        contentVisibility: 'auto',
        containIntrinsicSize: `${intrinsicHeight}px ${intrinsicWidth}px`,
      }}
    >
      {children}
    </div>
  );
};

export default VisibilitySection;

