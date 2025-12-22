import React from 'react';
import { Modal } from '../../../shared/ui/modal';

interface DeleteDocumentConfirmDialogProps {
  isOpen: boolean;
  onCancel: () => void;
  onConfirm: () => void;
  documentName?: string;
}

export const DeleteDocumentConfirmDialog: React.FC<DeleteDocumentConfirmDialogProps> = ({
  isOpen,
  onCancel,
  onConfirm,
  documentName,
}) => {
  return (
    <Modal
      open={isOpen}
      onOpenChange={(open) => {
        if (!open) {
          onCancel();
        }
      }}
      title="Delete document?"
      description={documentName ? `Delete "${documentName}"` : 'Delete this document'}
      primaryAction={{
        label: 'Delete',
        onClick: onConfirm,
        variant: 'destructive',
      }}
      secondaryAction={{
        label: 'Cancel',
        onClick: onCancel,
        variant: 'outline',
      }}
      data-testid="delete-document-dialog"
    >
      <div className="space-y-2">
        <p className="text-sm text-[var(--color-text-secondary)]">
          This action will permanently delete the document and its file. This cannot be undone.
        </p>
      </div>
    </Modal>
  );
};

