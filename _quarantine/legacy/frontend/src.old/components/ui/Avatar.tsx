import React, { useState, ReactNode } from 'react';
import { cn } from '@/lib/utils';

// Avatar Root Component
interface AvatarProps {
  children: ReactNode;
  className?: string;
}

export const Avatar: React.FC<AvatarProps> = ({ children, className }) => {
  return (
    <div
      className={cn(
        'relative flex h-10 w-10 shrink-0 overflow-hidden rounded-full',
        className
      )}
    >
      {children}
    </div>
  );
};

// AvatarImage Component
interface AvatarImageProps {
  src?: string;
  alt?: string;
  className?: string;
  onLoadingStatusChange?: (status: 'loading' | 'loaded' | 'error') => void;
}

export const AvatarImage: React.FC<AvatarImageProps> = ({
  src,
  alt,
  className,
  onLoadingStatusChange
}) => {
  const [imageStatus, setImageStatus] = useState<'loading' | 'loaded' | 'error'>('loading');

  const handleLoad = () => {
    setImageStatus('loaded');
    onLoadingStatusChange?.('loaded');
  };

  const handleError = () => {
    setImageStatus('error');
    onLoadingStatusChange?.('error');
  };

  if (!src || imageStatus === 'error') {
    return null;
  }

  return (
    <img
      src={src}
      alt={alt}
      className={cn('aspect-square h-full w-full', className)}
      onLoad={handleLoad}
      onError={handleError}
    />
  );
};

// AvatarFallback Component
interface AvatarFallbackProps {
  children: ReactNode;
  className?: string;
}

export const AvatarFallback: React.FC<AvatarFallbackProps> = ({
  children,
  className
}) => {
  return (
    <div
      className={cn(
        'flex h-full w-full items-center justify-center rounded-full bg-muted text-sm font-medium text-muted-foreground',
        className
      )}
    >
      {children}
    </div>
  );
};