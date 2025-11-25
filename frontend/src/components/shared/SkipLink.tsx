import React, { useEffect } from 'react';
import { spacing } from '../../shared/tokens/spacing';
import { radius } from '../../shared/tokens/radius';

export interface SkipLinkProps {
  /** Target ID for skip link */
  targetId?: string;
  /** Skip link text */
  text?: string;
}

/**
 * SkipLink - Skip to main content link for keyboard navigation
 * 
 * Follows WCAG 2.1 AA guidelines for keyboard navigation.
 */
export const SkipLink: React.FC<SkipLinkProps> = ({
  targetId = 'main-content',
  text = 'Skip to main content',
}) => {
  useEffect(() => {
    // Ensure target exists
    const target = document.getElementById(targetId);
    if (!target) {
      console.warn(`SkipLink target "${targetId}" not found`);
    } else {
      target.setAttribute('tabindex', '-1');
    }
  }, [targetId]);

  const handleClick = (e: React.MouseEvent<HTMLAnchorElement>) => {
    e.preventDefault();
    const target = document.getElementById(targetId);
    if (target) {
      target.focus();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };

  return (
    <a
      href={`#${targetId}`}
      onClick={handleClick}
      style={{
        position: 'absolute',
        top: '-40px',
        left: spacing.md,
        backgroundColor: 'var(--primary)',
        color: 'var(--primary-foreground)',
        padding: `${spacing.sm}px ${spacing.md}px`,
        borderRadius: radius.sm,
        textDecoration: 'none',
        zIndex: 10000,
        transition: 'top 0.2s',
      }}
      onFocus={(e) => {
        e.currentTarget.style.top = spacing.md;
      }}
      onBlur={(e) => {
        e.currentTarget.style.top = '-40px';
      }}
      data-testid="skip-link"
    >
      {text}
    </a>
  );
};

