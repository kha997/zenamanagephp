import React from 'react';
import { spacing } from '../../shared/tokens/spacing';
import { radius } from '../../shared/tokens/radius';

export interface FloatingActionButtonProps {
  /** Icon or content */
  icon: React.ReactNode;
  /** Click handler */
  onClick: () => void;
  /** Position: 'bottom-right' | 'bottom-left' | 'top-right' | 'top-left' */
  position?: 'bottom-right' | 'bottom-left' | 'top-right' | 'top-left';
  /** Size: 'sm' | 'md' | 'lg' */
  size?: 'sm' | 'md' | 'lg';
  /** Label for accessibility */
  label?: string;
  /** Show only on mobile */
  mobileOnly?: boolean;
}

const sizeMap = {
  sm: 40,
  md: 56,
  lg: 64,
};

const positionMap = {
  'bottom-right': { bottom: spacing.xl, right: spacing.xl },
  'bottom-left': { bottom: spacing.xl, left: spacing.xl },
  'top-right': { top: spacing.xl, right: spacing.xl },
  'top-left': { top: spacing.xl, left: spacing.xl },
};

/**
 * FloatingActionButton (FAB) - Floating action button for mobile
 * 
 * Follows Apple-style design spec with tokens and spacing.
 */
export const FloatingActionButton: React.FC<FloatingActionButtonProps> = ({
  icon,
  onClick,
  position = 'bottom-right',
  size = 'md',
  label,
  mobileOnly = true,
}) => {
  const buttonSize = sizeMap[size];
  const positionStyle = positionMap[position];

  return (
    <button
      onClick={onClick}
      aria-label={label || 'Floating action button'}
      style={{
        position: 'fixed',
        ...positionStyle,
        width: buttonSize,
        height: buttonSize,
        borderRadius: '50%',
        backgroundColor: 'var(--primary)',
        color: 'var(--primary-foreground)',
        border: 'none',
        boxShadow: 'var(--shadow-lg)',
        cursor: 'pointer',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        zIndex: 1000,
        transition: 'transform 0.2s, box-shadow 0.2s',
        ...(mobileOnly && {
          '@media (min-width: 768px)': {
            display: 'none',
          },
        }),
      }}
      onMouseEnter={(e) => {
        e.currentTarget.style.transform = 'scale(1.1)';
      }}
      onMouseLeave={(e) => {
        e.currentTarget.style.transform = 'scale(1)';
      }}
      data-testid="floating-action-button"
    >
      {icon}
    </button>
  );
};

