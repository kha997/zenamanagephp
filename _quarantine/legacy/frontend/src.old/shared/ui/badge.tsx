import React from 'react';
import { cn } from './utils';

export type BadgeTone = 'neutral' | 'primary' | 'success' | 'warning' | 'danger' | 'info';

export interface BadgeProps extends React.HTMLAttributes<HTMLSpanElement> {
  tone?: BadgeTone;
}

const toneStyles: Record<BadgeTone, string> = {
  neutral:
    'bg-[var(--color-semantic-neutral-100)] text-[var(--color-text-secondary)]',
  primary:
    'bg-[var(--color-semantic-primary-100)] text-[var(--color-semantic-primary-700)]',
  success:
    'bg-[var(--color-semantic-success-100)] text-[var(--color-semantic-success-700)]',
  warning:
    'bg-[var(--color-semantic-warning-100)] text-[var(--color-semantic-warning-800)]',
  danger:
    'bg-[var(--color-semantic-danger-100)] text-[var(--color-semantic-danger-700)]',
  info:
    'bg-[var(--color-semantic-info-100)] text-[var(--color-semantic-info-700)]',
};

export const Badge: React.FC<BadgeProps> = ({ tone = 'neutral', className, children, ...props }) => (
  <span
    className={cn(
      'inline-flex items-center rounded-[var(--radius-pill)] px-2.5 py-0.5 text-xs font-medium',
      toneStyles[tone],
      className,
    )}
    {...props}
  >
    {children}
  </span>
);
