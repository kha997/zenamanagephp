/**
 * Loading Component
 * Spinner và skeleton loading states
 */
import React from 'react';
import { cn } from '../../lib/utils/format';

interface LoadingProps {
  size?: 'sm' | 'md' | 'lg';
  color?: 'primary' | 'secondary' | 'white';
  text?: string;
  overlay?: boolean;
  className?: string;
}

const sizeVariants = {
  sm: 'h-4 w-4',
  md: 'h-6 w-6',
  lg: 'h-8 w-8'
};

const colorVariants = {
  primary: 'text-blue-600',
  secondary: 'text-gray-600',
  white: 'text-white'
};

export const Loading: React.FC<LoadingProps> = ({
  size = 'md',
  color = 'primary',
  text,
  overlay = false,
  className
}) => {
  const spinner = (
    <div className={cn('flex items-center justify-center', className)}>
      <svg
        className={cn(
          'animate-spin',
          sizeVariants[size],
          colorVariants[color]
        )}
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
      >
        <circle
          className="opacity-25"
          cx="12"
          cy="12"
          r="10"
          stroke="currentColor"
          strokeWidth="4"
        />
        <path
          className="opacity-75"
          fill="currentColor"
          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
        />
      </svg>
      {text && (
        <span className={cn('ml-2 text-sm', colorVariants[color])}>
          {text}
        </span>
      )}
    </div>
  );

  if (overlay) {
    return (
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-white bg-opacity-75">
        {spinner}
      </div>
    );
  }

  return spinner;
};

// Skeleton Loading Component
interface SkeletonProps {
  width?: string | number;
  height?: string | number;
  className?: string;
  rounded?: boolean;
  lines?: number;
}

export const Skeleton: React.FC<SkeletonProps> = ({
  width,
  height = '1rem',
  className,
  rounded = false,
  lines = 1
}) => {
  if (lines > 1) {
    return (
      <div className={cn('space-y-2', className)}>
        {Array.from({ length: lines }).map((_, index) => (
          <div
            key={index}
            className={cn(
              'animate-pulse bg-gray-200',
              rounded ? 'rounded-full' : 'rounded',
              index === lines - 1 && 'w-3/4' // Last line is shorter
            )}
            style={{
              width: index === lines - 1 ? '75%' : width,
              height
            }}
          />
        ))}
      </div>
    );
  }

  return (
    <div
      className={cn(
        'animate-pulse bg-gray-200',
        rounded ? 'rounded-full' : 'rounded',
        className
      )}
      style={{ width, height }}
    />
  );
};