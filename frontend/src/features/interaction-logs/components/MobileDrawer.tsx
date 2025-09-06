import React, { useEffect } from 'react';
import { X } from 'lucide-react';

interface MobileDrawerProps {
  isOpen: boolean;
  onClose: () => void;
  title?: string;
  children: React.ReactNode;
  position?: 'left' | 'right' | 'bottom';
  size?: 'sm' | 'md' | 'lg' | 'full';
}

/**
 * MobileDrawer component để hiển thị content trong drawer trên mobile
 * Hỗ trợ các vị trí và kích thước khác nhau
 */
export const MobileDrawer: React.FC<MobileDrawerProps> = ({
  isOpen,
  onClose,
  title,
  children,
  position = 'right',
  size = 'md'
}) => {
  /**
   * Handle escape key
   */
  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isOpen) {
        onClose();
      }
    };

    document.addEventListener('keydown', handleEscape);
    return () => document.removeEventListener('keydown', handleEscape);
  }, [isOpen, onClose]);

  /**
   * Prevent body scroll when drawer is open
   */
  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = 'unset';
    }

    return () => {
      document.body.style.overflow = 'unset';
    };
  }, [isOpen]);

  /**
   * Lấy transform class dựa trên position
   */
  const getTransformClass = () => {
    if (!isOpen) {
      switch (position) {
        case 'left':
          return '-translate-x-full';
        case 'bottom':
          return 'translate-y-full';
        default:
          return 'translate-x-full';
      }
    }
    return 'translate-x-0 translate-y-0';
  };

  /**
   * Lấy position classes
   */
  const getPositionClasses = () => {
    switch (position) {
      case 'left':
        return 'left-0 top-0 h-full';
      case 'bottom':
        return 'bottom-0 left-0 right-0';
      default:
        return 'right-0 top-0 h-full';
    }
  };

  /**
   * Lấy size classes
   */
  const getSizeClasses = () => {
    if (position === 'bottom') {
      switch (size) {
        case 'sm':
          return 'h-1/3';
        case 'lg':
          return 'h-3/4';
        case 'full':
          return 'h-full';
        default:
          return 'h-1/2';
      }
    } else {
      switch (size) {
        case 'sm':
          return 'w-64';
        case 'lg':
          return 'w-96';
        case 'full':
          return 'w-full';
        default:
          return 'w-80';
      }
    }
  };

  if (!isOpen) return null;

  return (
    <>
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black bg-opacity-50 z-40 transition-opacity"
        onClick={onClose}
      />
      
      {/* Drawer */}
      <div
        className={`
          fixed z-50 bg-white shadow-xl
          transform transition-transform duration-300 ease-in-out
          ${getPositionClasses()}
          ${getSizeClasses()}
          ${getTransformClass()}
        `}
      >
        {/* Header */}
        {title && (
          <div className="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 className="text-lg font-semibold text-gray-900">
              {title}
            </h2>
            <button
              onClick={onClose}
              className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-md transition-colors"
            >
              <X className="h-5 w-5" />
            </button>
          </div>
        )}
        
        {/* Content */}
        <div className="flex-1 overflow-y-auto p-4">
          {children}
        </div>
      </div>
    </>
  );
};