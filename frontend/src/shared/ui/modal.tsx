import React, { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import { Button, type ButtonVariant } from './button';

type ModalButtonVariant = Extract<ButtonVariant, 'primary' | 'secondary' | 'outline' | 'destructive'>;

interface ModalAction {
  label: string;
  onClick: () => void;
  variant?: ModalButtonVariant;
  loading?: boolean;
}

export interface ModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  description?: string;
  children: React.ReactNode;
  primaryAction?: ModalAction;
  secondaryAction?: ModalAction;
  closeLabel?: string;
}

const focusableSelector =
  'a[href], area[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

const trapFocus = (event: KeyboardEvent, container: HTMLDivElement | null) => {
  if (!container) return;
  const focusable = Array.from(container.querySelectorAll<HTMLElement>(focusableSelector));
  if (focusable.length === 0) {
    event.preventDefault();
    return;
  }

  const first = focusable[0];
  const last = focusable[focusable.length - 1];
  const current = document.activeElement as HTMLElement | null;

  if (event.shiftKey) {
    if (current === first) {
      last.focus();
      event.preventDefault();
    }
  } else if (current === last) {
    first.focus();
    event.preventDefault();
  }
};

export const Modal: React.FC<ModalProps> = ({
  open,
  onOpenChange,
  title,
  description,
  children,
  primaryAction,
  secondaryAction,
  closeLabel = 'Đóng',
}) => {
  const contentRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return undefined;
    const node = contentRef.current;
    const previouslyFocused = document.activeElement as HTMLElement | null;
    node?.focus({ preventScroll: true });

    const keyHandler = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        onOpenChange(false);
      }

      if (event.key === 'Tab') {
        trapFocus(event, node);
      }
    };

    document.addEventListener('keydown', keyHandler);
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    return () => {
      document.removeEventListener('keydown', keyHandler);
      document.body.style.overflow = previousOverflow;
      previouslyFocused?.focus();
    };
  }, [open, onOpenChange]);

  if (!open) return null;

  return createPortal(
    <div
      className="fixed inset-0 z-[80] flex items-center justify-center px-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="modal-title"
      aria-describedby={description ? 'modal-description' : undefined}
    >
      <div
        className="absolute inset-0 bg-[var(--color-surface-overlay)] backdrop-blur-sm"
        aria-hidden="true"
        onClick={() => onOpenChange(false)}
      />

      <div
        ref={contentRef}
        tabIndex={-1}
        role="document"
        className="relative z-10 w-full max-w-lg rounded-[var(--radius-xl)] border border-[var(--color-border-default)] bg-[var(--color-surface-card)] shadow-xl focus:outline-none"
      >
        <header className="flex items-start justify-between gap-4 border-b border-[var(--color-border-subtle)] px-6 py-4">
          <div className="space-y-1">
            <h2 id="modal-title" className="text-lg font-semibold text-[var(--color-text-primary)]">
              {title}
            </h2>
            {description ? (
              <p id="modal-description" className="text-sm text-[var(--color-text-muted)]">
                {description}
              </p>
            ) : null}
          </div>
          <Button
            variant="ghost"
            size="sm"
            aria-label={closeLabel}
            onClick={() => onOpenChange(false)}
          >
            ✕
          </Button>
        </header>

        <div className="px-6 py-5 text-[var(--color-text-secondary)]">{children}</div>

        <footer className="flex items-center justify-end gap-2 border-t border-[var(--color-border-subtle)] px-6 py-4">
          {secondaryAction ? (
            <Button
              variant={secondaryAction.variant ?? 'outline'}
              size="sm"
              onClick={secondaryAction.onClick}
              disabled={secondaryAction.loading}
            >
              {secondaryAction.loading ? 'Đang xử lý…' : secondaryAction.label}
            </Button>
          ) : null}
          {primaryAction ? (
            <Button
              variant={primaryAction.variant ?? 'primary'}
              size="sm"
              onClick={primaryAction.onClick}
              loading={primaryAction.loading}
            >
              {primaryAction.label}
            </Button>
          ) : null}
        </footer>
      </div>
    </div>,
    document.body,
  );
};

export default Modal;
