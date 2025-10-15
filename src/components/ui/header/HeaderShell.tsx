import React, { ReactNode, useEffect, useState } from 'react';
import { useHeaderCondense } from '../../../hooks/useHeaderCondense';

export interface HeaderShellProps {
  theme?: 'light' | 'dark';
  size?: 'sm' | 'md' | 'lg';
  sticky?: boolean;
  condensedOnScroll?: boolean;
  withBorder?: boolean;
  logo: ReactNode;
  primaryNav?: ReactNode;
  secondaryActions?: ReactNode;
  userMenu?: ReactNode;
  notifications?: ReactNode;
  breadcrumbs?: ReactNode;
  className?: string;
}

export const HeaderShell: React.FC<HeaderShellProps> = ({
  theme = 'light',
  size = 'md',
  sticky = true,
  condensedOnScroll = true,
  withBorder = true,
  logo,
  primaryNav,
  secondaryActions,
  userMenu,
  notifications,
  breadcrumbs,
  className = '',
}) => {
  const [isCondensed, setIsCondensed] = useState(false);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

  // Handle header condensing on scroll
  useHeaderCondense({
    enabled: condensedOnScroll,
    onCondense: setIsCondensed,
  });

  // Apply theme to document
  useEffect(() => {
    document.documentElement.setAttribute('data-theme', theme);
  }, [theme]);

  // Handle escape key for mobile menu
  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isMobileMenuOpen) {
        setIsMobileMenuOpen(false);
      }
    };

    document.addEventListener('keydown', handleEscape);
    return () => document.removeEventListener('keydown', handleEscape);
  }, [isMobileMenuOpen]);

  const headerClasses = [
    'header-shell',
    sticky && 'sticky top-0',
    isCondensed && 'condensed',
    withBorder && 'border-b',
    className,
  ].filter(Boolean).join(' ');

  const containerClasses = [
    'header-container',
    size === 'sm' && 'py-2',
    size === 'md' && 'py-3',
    size === 'lg' && 'py-4',
  ].filter(Boolean).join(' ');

  return (
    <>
      <header
        className={headerClasses}
        role="banner"
        aria-label="Main navigation"
      >
        <div className={containerClasses}>
          {/* Left Section: Logo + Hamburger */}
          <div className="flex items-center space-x-4">
            {/* Mobile Hamburger */}
            <button
              className="hamburger"
              onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
              aria-expanded={isMobileMenuOpen}
              aria-controls="mobile-menu"
              aria-label="Toggle mobile menu"
            >
              <span className="hamburger-line"></span>
              <span className="hamburger-line"></span>
              <span className="hamburger-line"></span>
            </button>

            {/* Logo */}
            <div className="header-logo">
              {logo}
            </div>
          </div>

          {/* Center Section: Primary Navigation (Desktop) */}
          {primaryNav && (
            <nav className="header-nav" role="navigation" aria-label="Primary navigation">
              {primaryNav}
            </nav>
          )}

          {/* Right Section: Actions + User Menu */}
          <div className="header-actions">
            {/* Secondary Actions */}
            {secondaryActions && (
              <div className="flex items-center space-x-2">
                {secondaryActions}
              </div>
            )}

            {/* Notifications */}
            {notifications && (
              <div className="flex items-center">
                {notifications}
              </div>
            )}

            {/* User Menu */}
            {userMenu && (
              <div className="header-user-menu">
                {userMenu}
              </div>
            )}
          </div>
        </div>

        {/* Breadcrumbs (Optional) */}
        {breadcrumbs && (
          <div className="border-t border-header-border bg-header-bg">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2">
              {breadcrumbs}
            </div>
          </div>
        )}
      </header>

      {/* Mobile Menu Overlay */}
      {isMobileMenuOpen && (
        <div
          className="mobile-overlay"
          onClick={() => setIsMobileMenuOpen(false)}
          aria-hidden="true"
        />
      )}

      {/* Mobile Menu Sheet */}
      <div
        id="mobile-menu"
        className={`mobile-sheet ${isMobileMenuOpen ? 'open' : 'closed'}`}
        role="dialog"
        aria-modal="true"
        aria-label="Mobile navigation menu"
      >
        <div className="p-4 space-y-4">
          {/* Mobile Primary Nav */}
          {primaryNav && (
            <nav role="navigation" aria-label="Mobile primary navigation">
              {primaryNav}
            </nav>
          )}

          {/* Mobile Secondary Actions */}
          {secondaryActions && (
            <div className="space-y-2">
              {secondaryActions}
            </div>
          )}

          {/* Mobile User Menu */}
          {userMenu && (
            <div className="border-t border-header-border pt-4">
              {userMenu}
            </div>
          )}
        </div>
      </div>
    </>
  );
};

export default HeaderShell;
