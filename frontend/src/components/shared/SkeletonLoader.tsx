import React from 'react';
import { spacing } from '../../shared/tokens/spacing';
import { radius } from '../../shared/tokens/radius';

export interface SkeletonLoaderProps {
  /** Number of lines */
  lines?: number;
  /** Width of skeleton (can be percentage or pixels) */
  width?: string | number;
  /** Height of skeleton */
  height?: string | number;
  /** Show circle (for avatars) */
  circle?: boolean;
  /** Show rectangle (default) */
  rectangle?: boolean;
  /** Custom className */
  className?: string;
}

/**
 * SkeletonLoader - Displays skeleton loading placeholders
 * 
 * Follows Apple-style design spec with tokens and spacing.
 */
export const SkeletonLoader: React.FC<SkeletonLoaderProps> = ({
  lines = 1,
  width = '100%',
  height,
  circle = false,
  rectangle = true,
}) => {
  const defaultHeight = circle ? width : height || spacing.md * 2;

  const skeletonStyle: React.CSSProperties = {
    backgroundColor: 'var(--muted-surface)',
    borderRadius: circle ? '50%' : radius.sm,
    width: typeof width === 'number' ? `${width}px` : width,
    height: typeof defaultHeight === 'number' ? `${defaultHeight}px` : defaultHeight,
    animation: 'pulse 1.5s ease-in-out infinite',
  };

  if (lines > 1) {
    return (
      <div className="space-y-2" data-testid="skeleton-loader">
        {Array.from({ length: lines }).map((_, index) => (
          <div
            key={index}
            style={{
              ...skeletonStyle,
              width: index === lines - 1 ? '80%' : '100%',
            }}
          />
        ))}
      </div>
    );
  }

  return <div style={skeletonStyle} data-testid="skeleton-loader" />;
};

// Add CSS animation
if (typeof document !== 'undefined') {
  const style = document.createElement('style');
  style.textContent = `
    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.5; }
    }
  `;
  document.head.appendChild(style);
}

/**
 * SkeletonCard - Pre-built skeleton for card layouts
 */
export const SkeletonCard: React.FC = () => {
  return (
    <div
      style={{
        padding: spacing.lg,
        backgroundColor: 'var(--surface)',
        border: '1px solid var(--border)',
        borderRadius: radius.md,
      }}
      data-testid="skeleton-card"
    >
      <SkeletonLoader width={60} height={60} circle />
      <div style={{ marginTop: spacing.md }}>
        <SkeletonLoader lines={2} />
      </div>
    </div>
  );
};

/**
 * SkeletonTable - Pre-built skeleton for table layouts
 */
export const SkeletonTable: React.FC<{ rows?: number; cols?: number }> = ({
  rows = 5,
  cols = 4,
}) => {
  return (
    <div data-testid="skeleton-table">
      <div className="space-y-2">
        {Array.from({ length: rows }).map((_, rowIndex) => (
          <div key={rowIndex} className="flex gap-4">
            {Array.from({ length: cols }).map((_, colIndex) => (
              <SkeletonLoader
                key={colIndex}
                width={`${100 / cols}%`}
                height={spacing.xl}
              />
            ))}
          </div>
        ))}
      </div>
    </div>
  );
};

