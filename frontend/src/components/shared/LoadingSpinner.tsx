import React from 'react';
import { spacing } from '../../shared/tokens/spacing';

export interface LoadingSpinnerProps {
  /** Size: 'sm' | 'md' | 'lg' */
  size?: 'sm' | 'md' | 'lg';
  /** Custom color */
  color?: string;
  /** Full page overlay */
  fullPage?: boolean;
  /** Loading message */
  message?: string;
}

const sizeMap = {
  sm: 16,
  md: 24,
  lg: 32,
};

/**
 * LoadingSpinner - Displays a loading spinner
 * 
 * Follows Apple-style design spec with tokens and spacing.
 */
export const LoadingSpinner: React.FC<LoadingSpinnerProps> = ({
  size = 'md',
  color = 'var(--primary)',
  fullPage = false,
  message,
}) => {
  const spinnerSize = sizeMap[size];

  const spinner = (
    <div
      style={{
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        gap: spacing.sm,
      }}
      data-testid="loading-spinner"
    >
      <div
        style={{
          width: spinnerSize,
          height: spinnerSize,
          border: `2px solid var(--muted-surface)`,
          borderTopColor: color,
          borderRadius: '50%',
          animation: 'spin 0.8s linear infinite',
        }}
      />
      {message && (
        <p style={{ fontSize: '14px', color: 'var(--muted)' }}>{message}</p>
      )}
    </div>
  );

  if (fullPage) {
    return (
      <div
        style={{
          position: 'fixed',
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          backgroundColor: 'rgba(255, 255, 255, 0.9)',
          zIndex: 9999,
        }}
      >
        {spinner}
      </div>
    );
  }

  return spinner;
};

// Add CSS animation
if (typeof document !== 'undefined') {
  const style = document.createElement('style');
  style.textContent = `
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
  `;
  document.head.appendChild(style);
}

