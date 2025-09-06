import React, { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { cva, type VariantProps } from 'class-variance-authority';

// Định nghĩa các variant cho Badge component
const badgeVariants = cva(
  'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
  {
    variants: {
      variant: {
        default:
          'border-transparent bg-primary text-primary-foreground hover:bg-primary/80',
        secondary:
          'border-transparent bg-secondary text-secondary-foreground hover:bg-secondary/80',
        destructive:
          'border-transparent bg-destructive text-destructive-foreground hover:bg-destructive/80',
        outline: 'text-foreground',
        success:
          'border-transparent bg-green-500 text-white hover:bg-green-600',
        warning:
          'border-transparent bg-yellow-500 text-white hover:bg-yellow-600',
        info:
          'border-transparent bg-blue-500 text-white hover:bg-blue-600',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  }
);

// Interface cho Badge props
export interface BadgeProps
  extends React.HTMLAttributes<HTMLDivElement>,
    VariantProps<typeof badgeVariants> {
  children: ReactNode;
}

/**
 * Badge component - Hiển thị nhãn nhỏ với các variant màu sắc khác nhau
 * Sử dụng cho status, tags, notifications count, etc.
 */
export const Badge: React.FC<BadgeProps> = ({
  className,
  variant,
  children,
  ...props
}) => {
  return (
    <div
      className={cn(badgeVariants({ variant }), className)}
      {...props}
    >
      {children}
    </div>
  );
};

// Export variant function để có thể sử dụng ở nơi khác
export { badgeVariants };