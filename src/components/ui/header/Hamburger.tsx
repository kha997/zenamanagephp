import React from 'react';

export interface HamburgerProps {
  isOpen: boolean;
  onClick: () => void;
  className?: string;
  'aria-label'?: string;
}

export const Hamburger: React.FC<HamburgerProps> = ({
  isOpen,
  onClick,
  className = '',
  'aria-label': ariaLabel = 'Toggle mobile menu',
}) => {
  return (
    <button
      className={`hamburger ${isOpen ? 'active' : ''} ${className}`}
      onClick={onClick}
      aria-expanded={isOpen}
      aria-controls="mobile-menu"
      aria-label={ariaLabel}
      type="button"
    >
      <span className="hamburger-line" aria-hidden="true"></span>
      <span className="hamburger-line" aria-hidden="true"></span>
      <span className="hamburger-line" aria-hidden="true"></span>
    </button>
  );
};

export default Hamburger;
