import React, { forwardRef } from 'react';
import { cn } from './utils';

export type ButtonVariant = 'primary' | 'secondary' | 'outline' | 'ghost' | 'destructive';
export type ButtonSize = 'sm' | 'md' | 'lg' | 'icon';

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  size?: ButtonSize;
  loading?: boolean;
  startIcon?: React.ReactNode;
  endIcon?: React.ReactNode;
}

const baseClasses =
  'inline-flex items-center justify-center gap-2 font-medium rounded-[var(--radius-md)] transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2';

const variantStyles: Record<ButtonVariant, string> = {
  primary:
    'bg-[var(--color-semantic-primary-500)] text-[var(--color-semantic-primary-contrast)] hover:bg-[var(--color-semantic-primary-600)] focus-visible:ring-[var(--color-semantic-primary-200)]',
  secondary:
    'bg-[var(--color-semantic-secondary-100)] text-[var(--color-semantic-secondary-700)] hover:bg-[var(--color-semantic-secondary-200)] focus-visible:ring-[var(--color-semantic-secondary-200)]',
  outline:
    'border border-[var(--color-border-default)] bg-transparent text-[var(--color-text-primary)] hover:bg-[var(--color-surface-muted)] focus-visible:ring-[var(--color-border-focus)]',
  ghost:
    'bg-transparent text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-muted)] hover:text-[var(--color-text-primary)] focus-visible:ring-[var(--color-border-focus)]',
  destructive:
    'bg-[var(--color-semantic-danger-500)] text-[var(--color-semantic-danger-contrast)] hover:bg-[var(--color-semantic-danger-600)] focus-visible:ring-[var(--color-semantic-danger-200)]',
};

const sizeStyles: Record<ButtonSize, string> = {
  sm: 'h-9 px-3 text-sm',
  md: 'h-10 px-4 text-sm',
  lg: 'h-11 px-5 text-base',
  icon: 'h-10 w-10 p-0',
};

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  (
    {
      variant = 'primary',
      size = 'md',
      className,
      children,
      loading = false,
      startIcon,
      endIcon,
      disabled,
      ...rest
    },
    ref,
  ) => {
    const isDisabled = disabled || loading;

    return (
      <button
        ref={ref}
        data-variant={variant}
        data-size={size}
        className={cn(
          baseClasses,
          variantStyles[variant],
          sizeStyles[size],
          isDisabled && 'cursor-not-allowed opacity-60',
          className,
        )}
        disabled={isDisabled}
        aria-busy={loading ? 'true' : undefined}
        {...rest}
      >
        {loading ? (
          <span
            className="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-[var(--color-semantic-primary-100)] border-r-transparent"
            aria-hidden="true"
          />
        ) : (
          startIcon && <span className="inline-flex">{startIcon}</span>
        )}
        <span className="whitespace-nowrap">{children}</span>
        {!loading && endIcon ? <span className="inline-flex">{endIcon}</span> : null}
      </button>
    );
  },
);

Button.displayName = 'Button';
