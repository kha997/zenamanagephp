import React, { forwardRef } from 'react';
import { Button, ButtonProps } from './Button';
import { useAccessibility } from '../../hooks/useAccessibility';

interface AccessibleButtonProps extends ButtonProps {
  ariaLabel?: string;
  ariaDescribedBy?: string;
  announceOnClick?: string;
}

export const AccessibleButton = forwardRef<HTMLButtonElement, AccessibleButtonProps>(
  ({ ariaLabel, ariaDescribedBy, announceOnClick, onClick, children, ...props }, ref) => {
    const { announce } = useAccessibility();

    const handleClick = (event: React.MouseEvent<HTMLButtonElement>) => {
      if (announceOnClick) {
        announce(announceOnClick);
      }
      onClick?.(event);
    };

    return (
      <Button
        ref={ref}
        onClick={handleClick}
        aria-label={ariaLabel}
        aria-describedby={ariaDescribedBy}
        {...props}
      >
        {children}
      </Button>
    );
  }
);

AccessibleButton.displayName = 'AccessibleButton';