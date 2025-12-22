import React from 'react';
import { cn } from './utils';

export interface CardProps extends React.HTMLAttributes<HTMLElement> {}

export const Card: React.FC<CardProps> = ({ className, ...props }) => (
  <section
    className={cn(
      'rounded-[var(--radius-lg)] border border-[var(--color-border-subtle)] bg-[var(--color-surface-card)] shadow-sm transition-shadow duration-200 focus-within:ring-2 focus-within:ring-[var(--color-semantic-primary-200)]',
      className,
    )}
    {...props}
  />
);

export const CardHeader: React.FC<React.HTMLAttributes<HTMLDivElement>> = ({ className, ...props }) => (
  <header
    className={cn(
      'border-b border-[var(--color-border-subtle)] px-6 py-4 first:rounded-t-[var(--radius-lg)]',
      className,
    )}
    {...props}
  />
);

export const CardTitle: React.FC<React.HTMLAttributes<HTMLHeadingElement>> = ({ className, ...props }) => (
  <h2
    className={cn('text-lg font-semibold text-[var(--color-text-primary)]', className)}
    {...props}
  />
);

export const CardDescription: React.FC<React.HTMLAttributes<HTMLParagraphElement>> = ({ className, ...props }) => (
  <p
    className={cn('text-sm text-[var(--color-text-muted)]', className)}
    {...props}
  />
);

export const CardContent: React.FC<React.HTMLAttributes<HTMLDivElement>> = ({ className, ...props }) => (
  <div
    className={cn('px-6 py-5', className)}
    {...props}
  />
);

export const CardFooter: React.FC<React.HTMLAttributes<HTMLDivElement>> = ({ className, ...props }) => (
  <footer
    className={cn(
      'flex items-center justify-end gap-3 border-t border-[var(--color-border-subtle)] px-6 py-4 last:rounded-b-[var(--radius-lg)]',
      className,
    )}
    {...props}
  />
);
