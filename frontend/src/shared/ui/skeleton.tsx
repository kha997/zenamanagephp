import React from 'react';
import { Card, CardContent } from '../../shared/ui/card';
import { cn } from '../../shared/ui/utils';

interface SkeletonProps {
  className?: string;
}

export const Skeleton: React.FC<SkeletonProps> = ({ className }) => (
  <div
    className={cn(
      'animate-pulse rounded-[var(--radius-md)] bg-[var(--color-surface-muted)]',
      className,
    )}
  />
);

export const WidgetSkeleton: React.FC<{ size?: 'small' | 'medium' | 'large' }> = ({ size = 'medium' }) => {
  const sizeClasses = {
    small: 'h-32',
    medium: 'h-48',
    large: 'h-64',
  };

  return (
    <Card className={sizeClasses[size]}>
      <CardContent className="p-6">
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <Skeleton className="h-4 w-24" />
            <Skeleton className="h-4 w-4 rounded-full" />
          </div>
          <Skeleton className="h-8 w-16" />
          <div className="space-y-2">
            <Skeleton className="h-3 w-full" />
            <Skeleton className="h-3 w-3/4" />
          </div>
        </div>
      </CardContent>
    </Card>
  );
};

export const DashboardSkeleton: React.FC = () => (
  <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
    <WidgetSkeleton size="small" />
    <WidgetSkeleton size="medium" />
    <WidgetSkeleton size="small" />
    <WidgetSkeleton size="large" />
    <WidgetSkeleton size="medium" />
    <WidgetSkeleton size="small" />
  </div>
);

export const AlertSkeleton: React.FC = () => (
  <div className="space-y-3">
    {Array.from({ length: 5 }).map((_, i) => (
      <Card key={i}>
        <CardContent className="p-4">
          <div className="flex items-start gap-3">
            <Skeleton className="h-2 w-2 rounded-full" />
            <div className="flex-1 space-y-2">
              <Skeleton className="h-4 w-3/4" />
              <Skeleton className="h-3 w-1/2" />
            </div>
            <Skeleton className="h-4 w-16" />
          </div>
        </CardContent>
      </Card>
    ))}
  </div>
);

export const MetricsSkeleton: React.FC = () => (
  <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
    {Array.from({ length: 8 }).map((_, i) => (
      <Card key={i}>
        <CardContent className="p-4">
          <div className="space-y-2">
            <Skeleton className="h-3 w-16" />
            <Skeleton className="h-6 w-12" />
            <Skeleton className="h-2 w-full" />
          </div>
        </CardContent>
      </Card>
    ))}
  </div>
);
