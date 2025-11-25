import React from 'react';
import { spacing } from '../../shared/tokens/spacing';

export interface HamburgerButtonProps {
  /** Is menu open */
  isOpen: boolean;
  /** Toggle handler */
  onToggle: () => void;
  /** Size */
  size?: number;
  /** Color */
  color?: string;
}

/**
 * HamburgerButton - Animated hamburger menu button
 * 
 * Follows Apple-style design spec with tokens and spacing.
 */
export const HamburgerButton: React.FC<HamburgerButtonProps> = ({
  isOpen,
  onToggle,
  size = 24,
  color = 'var(--text)',
}) => {
  return (
    <button
      onClick={onToggle}
      aria-label={isOpen ? 'Close menu' : 'Open menu'}
      aria-expanded={isOpen}
      style={{
        background: 'none',
        border: 'none',
        padding: spacing.xs,
        cursor: 'pointer',
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'space-around',
        width: size,
        height: size,
        zIndex: 10000,
      }}
      data-testid="hamburger-button"
    >
      <span
        style={{
          display: 'block',
          width: '100%',
          height: '2px',
          backgroundColor: color,
          borderRadius: '1px',
          transition: 'all 0.3s ease-in-out',
          transform: isOpen ? 'rotate(45deg) translate(5px, 5px)' : 'none',
        }}
      />
      <span
        style={{
          display: 'block',
          width: '100%',
          height: '2px',
          backgroundColor: color,
          borderRadius: '1px',
          transition: 'all 0.3s ease-in-out',
          opacity: isOpen ? 0 : 1,
        }}
      />
      <span
        style={{
          display: 'block',
          width: '100%',
          height: '2px',
          backgroundColor: color,
          borderRadius: '1px',
          transition: 'all 0.3s ease-in-out',
          transform: isOpen ? 'rotate(-45deg) translate(7px, -6px)' : 'none',
        }}
      />
    </button>
  );
};

