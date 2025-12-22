/**
 * Image optimization utilities
 * 
 * Provides utilities for lazy loading and optimizing images.
 */

import React, { useState, useEffect, useRef } from 'react';

export interface ImageOptimizationOptions {
  /** Lazy load images */
  lazy?: boolean;
  /** Placeholder image URL */
  placeholder?: string;
  /** Image quality (0-100) */
  quality?: number;
  /** Image format */
  format?: 'webp' | 'avif' | 'jpeg' | 'png';
  /** Responsive sizes */
  sizes?: string;
  /** Source set for responsive images */
  srcSet?: string;
}

/**
 * Generate optimized image URL
 * 
 * In production, this would integrate with an image CDN or optimization service.
 */
export const getOptimizedImageUrl = (
  url: string,
  options: ImageOptimizationOptions = {}
): string => {
  const { quality = 80, format = 'webp' } = options;
  
  // In production, integrate with image optimization service
  // For now, return original URL
  return url;
};

/**
 * Lazy load image with Intersection Observer
 */
export const useLazyImage = (
  src: string,
  options: ImageOptimizationOptions = {}
): { src: string | undefined; loading: boolean; error: boolean; imgRef: React.RefObject<HTMLImageElement> } => {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [imageSrc, setImageSrc] = useState<string | undefined>(
    options.lazy ? undefined : src
  );
  const imgRef = useRef<HTMLImageElement | null>(null);

  useEffect(() => {
    if (!options.lazy || !imgRef.current) {
      setImageSrc(src);
      return;
    }

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            setImageSrc(src);
            observer.disconnect();
          }
        });
      },
      { rootMargin: '50px' }
    );

    observer.observe(imgRef.current);

    return () => {
      observer.disconnect();
    };
  }, [src, options.lazy]);

  useEffect(() => {
    if (!imageSrc) return;

    const img = new Image();
    img.onload = () => setLoading(false);
    img.onerror = () => {
      setError(true);
      setLoading(false);
    };
    img.src = imageSrc;
  }, [imageSrc]);

  return { src: imageSrc, loading, error, imgRef };
};

/**
 * Optimized Image Component
 */
export const OptimizedImage: React.FC<
  React.ImgHTMLAttributes<HTMLImageElement> & ImageOptimizationOptions
> = ({ src, lazy = true, placeholder, quality, format, sizes, srcSet, ...props }) => {
  const { src: optimizedSrc, loading, error, imgRef } = useLazyImage(src || '', { lazy });

  if (error) {
    return <img {...props} src={placeholder || '/placeholder.png'} alt={props.alt || ''} />;
  }

  return (
    <img
      {...props}
      ref={imgRef}
      src={loading && placeholder ? placeholder : optimizedSrc}
      loading={lazy ? 'lazy' : 'eager'}
      sizes={sizes}
      srcSet={srcSet}
      style={{
        ...props.style,
        opacity: loading ? 0.5 : 1,
        transition: 'opacity 0.3s',
      }}
    />
  );
};

