import React, { useState, useEffect } from 'react';
import { Button } from '../ui/primitives/Button';
import { spacing } from '../../shared/tokens/spacing';
import { radius } from '../../shared/tokens/radius';

export interface MobileMenuProps {
  /** Menu items */
  items: Array<{
    id: string;
    label: string;
    icon?: React.ReactNode;
    onClick: () => void;
    badge?: number;
  }>;
  /** Is menu open */
  isOpen: boolean;
  /** Close handler */
  onClose: () => void;
  /** Show backdrop */
  showBackdrop?: boolean;
}

/**
 * MobileMenu - Hamburger menu for mobile navigation
 * 
 * Follows Apple-style design spec with tokens and spacing.
 */
export const MobileMenu: React.FC<MobileMenuProps> = ({
  items,
  isOpen,
  onClose,
  showBackdrop = true,
}) => {
  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    return () => {
      document.body.style.overflow = '';
    };
  }, [isOpen]);

  if (!isOpen) return null;

  return (
    <>
      {/* Backdrop */}
      {showBackdrop && (
        <div
          onClick={onClose}
          style={{
            position: 'fixed',
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
            backgroundColor: 'rgba(0, 0, 0, 0.5)',
            zIndex: 9998,
            animation: 'fadeIn 0.2s ease-in-out',
          }}
          data-testid="mobile-menu-backdrop"
        />
      )}

      {/* Menu */}
      <div
        style={{
          position: 'fixed',
          top: 0,
          left: 0,
          bottom: 0,
          width: '280px',
          maxWidth: '80vw',
          backgroundColor: 'var(--surface)',
          boxShadow: 'var(--shadow-xl)',
          zIndex: 9999,
          transform: isOpen ? 'translateX(0)' : 'translateX(-100%)',
          transition: 'transform 0.3s ease-in-out',
          overflowY: 'auto',
        }}
        data-testid="mobile-menu"
      >
        {/* Header */}
        <div
          style={{
            padding: spacing.lg,
            borderBottom: '1px solid var(--border)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
          }}
        >
          <h2 style={{ fontSize: '18px', fontWeight: 600 }}>Menu</h2>
          <button
            onClick={onClose}
            aria-label="Close menu"
            style={{
              background: 'none',
              border: 'none',
              fontSize: '24px',
              cursor: 'pointer',
              color: 'var(--text)',
            }}
          >
            ×
          </button>
        </div>

        {/* Items */}
        <nav style={{ padding: spacing.md }}>
          {items.map((item) => (
            <button
              key={item.id}
              onClick={() => {
                item.onClick();
                onClose();
              }}
              style={{
                width: '100%',
                padding: spacing.md,
                textAlign: 'left',
                backgroundColor: 'transparent',
                border: 'none',
                borderRadius: radius.sm,
                cursor: 'pointer',
                display: 'flex',
                alignItems: 'center',
                gap: spacing.sm,
                color: 'var(--text)',
                fontSize: '16px',
                marginBottom: spacing.xs,
                transition: 'background-color 0.2s',
              }}
              onMouseEnter={(e) => {
                e.currentTarget.style.backgroundColor = 'var(--muted-surface)';
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.backgroundColor = 'transparent';
              }}
            >
              {item.icon && <span>{item.icon}</span>}
              <span style={{ flex: 1 }}>{item.label}</span>
              {item.badge && (
                <span
                  style={{
                    backgroundColor: 'var(--primary)',
                    color: 'var(--primary-foreground)',
                    borderRadius: radius.full,
                    padding: `2px ${spacing.xs}px`,
                    fontSize: '12px',
                    minWidth: '20px',
                    textAlign: 'center',
                  }}
                >
                  {item.badge}
                </span>
              )}
            </button>
          ))}
        </nav>
      </div>

      {/* Add CSS animation */}
      <style>
        {`
          @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
          }
        `}
      </style>
    </>
  );
};

