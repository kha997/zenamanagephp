import React, { useState, useCallback, useEffect } from 'react';
import { Modal } from '../../../shared/ui/modal';
import { Input } from '../../../components/ui/primitives/Input';
import { Select } from '../../../components/ui/primitives/Select';
import { useUpdateProjectDocument } from '../hooks';

interface EditDocumentModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess?: () => void;
  projectId: string | number;
  document: {
    id: string | number;
    name?: string;
    description?: string;
    category?: string;
    status?: string;
  };
}

// Document categories matching backend enum
const DOCUMENT_CATEGORIES = [
  { value: 'general', label: 'General' },
  { value: 'contract', label: 'Contract' },
  { value: 'drawing', label: 'Drawing' },
  { value: 'specification', label: 'Specification' },
  { value: 'report', label: 'Report' },
  { value: 'other', label: 'Other' },
];

// Document statuses matching backend enum
const DOCUMENT_STATUSES = [
  { value: 'active', label: 'Active' },
  { value: 'archived', label: 'Archived' },
  { value: 'draft', label: 'Draft' },
];

export const EditDocumentModal: React.FC<EditDocumentModalProps> = ({
  isOpen,
  onClose,
  onSuccess,
  projectId,
  document,
}) => {
  const [name, setName] = useState<string>('');
  const [description, setDescription] = useState<string>('');
  const [category, setCategory] = useState<string>('general');
  const [status, setStatus] = useState<string>('active');
  const [error, setError] = useState<string | null>(null);

  const updateMutation = useUpdateProjectDocument();

  // Prefill form when document changes or modal opens
  useEffect(() => {
    if (isOpen && document) {
      setName(document.name || '');
      setDescription(document.description || '');
      setCategory(document.category || 'general');
      setStatus(document.status || 'active');
      setError(null);
    }
  }, [isOpen, document]);

  // Reset form when modal closes
  const handleClose = useCallback(() => {
    setName('');
    setDescription('');
    setCategory('general');
    setStatus('active');
    setError(null);
    onClose();
  }, [onClose]);

  const handleSubmit = useCallback(async () => {
    // Basic validation
    if (name && name.length > 255) {
      setError('Name must be 255 characters or less');
      return;
    }
    if (description && description.length > 2000) {
      setError('Description must be 2000 characters or less');
      return;
    }

    setError(null);

    try {
      // Build payload with only changed fields (or all fields if needed)
      const payload: {
        name?: string;
        description?: string;
        category?: string;
        status?: string;
      } = {};

      // Only include fields that have values or are different from original
      if (name.trim()) {
        payload.name = name.trim();
      }
      if (description.trim()) {
        payload.description = description.trim();
      }
      if (category) {
        payload.category = category;
      }
      if (status) {
        payload.status = status;
      }

      await updateMutation.mutateAsync({
        projectId,
        documentId: document.id,
        payload,
      });

      // Success - close modal and call callback
      handleClose();
      onSuccess?.();
    } catch (err: any) {
      // Handle error
      const errorMessage = err?.message || 'Failed to update document. Please try again.';
      setError(errorMessage);
      // TODO: Replace with proper UI feedback (toast/snackbar) if available
      console.error('Update error:', err);
    }
  }, [name, description, category, status, projectId, document.id, updateMutation, handleClose, onSuccess]);

  return (
    <Modal
      open={isOpen}
      onOpenChange={(open) => {
        if (!open) {
          handleClose();
        }
      }}
      title="Edit Document"
      description="Update document metadata"
      primaryAction={{
        label: 'Save Changes',
        onClick: handleSubmit,
        loading: updateMutation.isPending,
      }}
      secondaryAction={{
        label: 'Cancel',
        onClick: handleClose,
        variant: 'outline',
      }}
      data-testid="edit-document-modal"
    >
      <div className="space-y-4">
        {/* Name Input */}
        <div>
          <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
            Name
          </label>
          <Input
            type="text"
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="Document name"
            maxLength={255}
            data-testid="edit-name-input"
          />
        </div>

        {/* Description Textarea */}
        <div>
          <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
            Description
          </label>
          <textarea
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            placeholder="Document description"
            rows={3}
            maxLength={2000}
            className="w-full px-3 py-2 border border-[var(--color-border-default)] rounded-[var(--radius-md)] bg-transparent text-[var(--color-text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-border-focus)] resize-none"
            data-testid="edit-description-input"
          />
        </div>

        {/* Category Select */}
        <div>
          <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
            Category
          </label>
          <Select
            options={DOCUMENT_CATEGORIES}
            value={category}
            onChange={setCategory}
            data-testid="edit-category-select"
          />
        </div>

        {/* Status Select */}
        <div>
          <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
            Status
          </label>
          <Select
            options={DOCUMENT_STATUSES}
            value={status}
            onChange={setStatus}
            data-testid="edit-status-select"
          />
        </div>

        {/* Error Message */}
        {error && (
          <div className="p-3 rounded-[var(--radius-md)] bg-[var(--color-semantic-danger-50)] border border-[var(--color-semantic-danger-200)]">
            <p className="text-sm text-[var(--color-semantic-danger-700)]" data-testid="edit-error-message">
              {error}
            </p>
          </div>
        )}
      </div>
    </Modal>
  );
};

