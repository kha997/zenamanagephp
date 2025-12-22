import React from 'react';
import { cn } from './utils';

export interface ProgressProps extends React.HTMLAttributes<HTMLDivElement> {
  /** Progress value (0-100) */
  value: number;
  /** Optional label */
  label?: string;
  /** Size variant */
  size?: 'sm' | 'md' | 'lg';
}

/**
 * Progress - Progress bar component
 */
export const Progress: React.FC<ProgressProps> = ({
  value,
  label,
  size = 'md',
  className,
  ...props
}) => {
  const clampedValue = Math.min(100, Math.max(0, value));
  
  const sizeClasses = {
    sm: 'h-1',
    md: 'h-2',
    lg: 'h-3',
  };

  return (
    <div className={cn('w-full', className)} {...props}>
      {label && (
        <div className="flex items-center justify-between mb-1">
          <span className="text-xs text-[var(--color-text-muted)]">{label}</span>
          <span className="text-xs text-[var(--color-text-muted)]">{clampedValue}%</span>
        </div>
      )}
      <div
        className={cn(
          'w-full overflow-hidden rounded-full bg-[var(--color-surface-muted)]',
          sizeClasses[size]
        )}
        role="progressbar"
        aria-valuenow={clampedValue}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-label={label || `Progress: ${clampedValue}%`}
      >
        <div
          className="h-full bg-[var(--color-semantic-primary-500)] transition-all duration-300 ease-out"
          style={{ width: `${clampedValue}%` }}
        />
      </div>
    </div>
  );
};

export default Progress;

