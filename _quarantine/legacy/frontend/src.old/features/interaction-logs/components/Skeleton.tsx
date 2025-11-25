import React from 'react';

interface SkeletonProps {
  variant?: 'list' | 'card' | 'detail' | 'filter';
  count?: number;
}

/**
 * Skeleton component để hiển thị loading state
 * Hỗ trợ các variant khác nhau cho các layout khác nhau
 */
export const Skeleton: React.FC<SkeletonProps> = ({ 
  variant = 'list', 
  count = 3 
}) => {
  /**
   * Base skeleton element
   */
  const SkeletonElement: React.FC<{ className?: string }> = ({ className = '' }) => (
    <div className={`animate-pulse bg-gray-200 rounded ${className}`} />
  );

  /**
   * Skeleton cho list item
   */
  const ListItemSkeleton: React.FC = () => (
    <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-4">
      <div className="flex items-start justify-between mb-3">
        <div className="flex items-center gap-3">
          <SkeletonElement className="h-8 w-8 rounded-full" />
          <div>
            <SkeletonElement className="h-4 w-24 mb-2" />
            <SkeletonElement className="h-3 w-16" />
          </div>
        </div>
        <SkeletonElement className="h-6 w-20" />
      </div>
      
      <SkeletonElement className="h-4 w-full mb-2" />
      <SkeletonElement className="h-4 w-3/4 mb-3" />
      
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <SkeletonElement className="h-5 w-16" />
          <SkeletonElement className="h-5 w-20" />
        </div>
        <SkeletonElement className="h-8 w-24" />
      </div>
    </div>
  );

  /**
   * Skeleton cho card layout
   */
  const CardSkeleton: React.FC = () => (
    <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
      <div className="flex items-center gap-3 mb-4">
        <SkeletonElement className="h-10 w-10 rounded-full" />
        <div>
          <SkeletonElement className="h-5 w-32 mb-2" />
          <SkeletonElement className="h-4 w-24" />
        </div>
      </div>
      
      <SkeletonElement className="h-4 w-full mb-2" />
      <SkeletonElement className="h-4 w-full mb-2" />
      <SkeletonElement className="h-4 w-2/3 mb-4" />
      
      <div className="flex items-center justify-between">
        <div className="flex gap-2">
          <SkeletonElement className="h-6 w-16" />
          <SkeletonElement className="h-6 w-20" />
        </div>
        <SkeletonElement className="h-8 w-28" />
      </div>
    </div>
  );

  /**
   * Skeleton cho detail page
   */
  const DetailSkeleton: React.FC = () => (
    <div className="bg-white rounded-lg shadow-sm border border-gray-200">
      {/* Header */}
      <div className="p-6 border-b border-gray-200">
        <div className="flex items-center justify-between mb-4">
          <SkeletonElement className="h-8 w-48" />
          <SkeletonElement className="h-10 w-32" />
        </div>
        
        <div className="flex items-center gap-4 mb-4">
          <SkeletonElement className="h-6 w-20" />
          <SkeletonElement className="h-6 w-24" />
          <SkeletonElement className="h-6 w-28" />
        </div>
        
        <SkeletonElement className="h-4 w-full mb-2" />
        <SkeletonElement className="h-4 w-3/4" />
      </div>
      
      {/* Content */}
      <div className="p-6">
        <SkeletonElement className="h-6 w-32 mb-4" />
        
        <SkeletonElement className="h-4 w-full mb-2" />
        <SkeletonElement className="h-4 w-full mb-2" />
        <SkeletonElement className="h-4 w-full mb-2" />
        <SkeletonElement className="h-4 w-2/3 mb-6" />
        
        <div className="grid grid-cols-2 gap-6">
          <div>
            <SkeletonElement className="h-5 w-24 mb-3" />
            <SkeletonElement className="h-4 w-full mb-2" />
            <SkeletonElement className="h-4 w-3/4" />
          </div>
          <div>
            <SkeletonElement className="h-5 w-28 mb-3" />
            <SkeletonElement className="h-4 w-full mb-2" />
            <SkeletonElement className="h-4 w-2/3" />
          </div>
        </div>
      </div>
    </div>
  );

  /**
   * Skeleton cho filter bar
   */
  const FilterSkeleton: React.FC = () => (
    <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
      <div className="flex items-center justify-between mb-4">
        <SkeletonElement className="h-6 w-32" />
        <SkeletonElement className="h-4 w-20" />
      </div>
      
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        {Array.from({ length: 8 }).map((_, index) => (
          <SkeletonElement key={index} className="h-10 w-full" />
        ))}
      </div>
    </div>
  );

  /**
   * Render skeleton dựa trên variant
   */
  const renderSkeleton = () => {
    switch (variant) {
      case 'card':
        return Array.from({ length: count }).map((_, index) => (
          <CardSkeleton key={index} />
        ));
      case 'detail':
        return <DetailSkeleton />;
      case 'filter':
        return <FilterSkeleton />;
      default:
        return Array.from({ length: count }).map((_, index) => (
          <ListItemSkeleton key={index} />
        ));
    }
  };

  return (
    <div className={variant === 'card' ? 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6' : ''}>
      {renderSkeleton()}
    </div>
  );
};