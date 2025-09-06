/**
 * Input Component
 * Hỗ trợ validation, icon, và các trạng thái khác nhau
 */
import React, { forwardRef } from 'react';
import { cn } from '../../lib/utils/format';

interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string;
  helperText?: string;
  leftIcon?: React.ReactNode;
  rightIcon?: React.ReactNode;
  fullWidth?: boolean;
}

export const Input = forwardRef<HTMLInputElement, InputProps>((
  {
    label,
    error,
    helperText,
    leftIcon,
    rightIcon,
    fullWidth = false,
    className,
    ...props
  },
  ref
) => {
  const hasError = !!error;

  return (
    <div className={cn('flex flex-col', fullWidth && 'w-full')}>
      {label && (
        <label className="block text-sm font-medium text-gray-700 mb-1">
          {label}
          {props.required && <span className="text-red-500 ml-1">*</span>}
        </label>
      )}
      
      <div className="relative">
        {leftIcon && (
          <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <span className="text-gray-400">{leftIcon}</span>
          </div>
        )}
        
        <input
          ref={ref}
          className={cn(
            // Base styles
            'block w-full px-3 py-2 border rounded-md shadow-sm',
            'placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-0',
            'transition-colors duration-200',
            
            // Icon padding
            leftIcon && 'pl-10',
            rightIcon && 'pr-10',
            
            // Error state
            hasError
              ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
              : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500',
            
            // Disabled state
            props.disabled && 'bg-gray-50 cursor-not-allowed',
            
            className
          )}
          {...props}
        />
        
        {rightIcon && (
          <div className="absolute inset-y-0 right-0 pr-3 flex items-center">
            <span className={cn('text-gray-400', hasError && 'text-red-400')}>
              {rightIcon}
            </span>
          </div>
        )}
      </div>
      
      {(error || helperText) && (
        <p className={cn(
          'mt-1 text-sm',
          hasError ? 'text-red-600' : 'text-gray-500'
        )}>
          {error || helperText}
        </p>
      )}
    </div>
  );
});

Input.displayName = 'Input';