import React, { useEffect } from 'react';
import { Modal, ModalProps } from './Modal';
import { useModalAccessibility } from '../../hooks/useAccessibility';

interface AccessibleModalProps extends Omit<ModalProps, 'isOpen' | 'onClose'> {
  isOpen: boolean;
  onClose: () => void;
  title: string;
  description?: string;
  announceOnOpen?: string;
}

export const AccessibleModal: React.FC<AccessibleModalProps> = ({
  isOpen,
  onClose,
  title,
  description,
  announceOnOpen,
  children,
  ...props
}) => {
  const { containerRef, handleModalKeyDown } = useModalAccessibility();

  useEffect(() => {
    if (isOpen && announceOnOpen) {
      // Announce modal opening
      setTimeout(() => {
        const announcement = announceOnOpen || `Modal ${title} đã mở`;
        // Use screen reader announcement
      }, 100);
    }
  }, [isOpen, announceOnOpen, title]);

  if (!isOpen) return null;

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      {...props}
    >
      <div
        ref={containerRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-title"
        aria-describedby={description ? "modal-description" : undefined}
        onKeyDown={handleModalKeyDown}
        className="focus:outline-none"
      >
        <h2 id="modal-title" className="sr-only">
          {title}
        </h2>
        {description && (
          <p id="modal-description" className="sr-only">
            {description}
          </p>
        )}
        {children}
      </div>
    </Modal>
  );
};