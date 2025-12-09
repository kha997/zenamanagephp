import React, { useState, useCallback } from 'react';
import { Modal } from '../../../shared/ui/modal';
import { Input } from '../../../components/ui/primitives/Input';
import { Select } from '../../../components/ui/primitives/Select';
import { useUploadDocumentVersion } from '../hooks';

interface UploadDocumentVersionModalProps {
  isOpen: boolean;
  onClose: () => void;
  projectId: string | number;
  documentId: string | number;
  onSuccess?: () => void;
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

/**
 * UploadDocumentVersionModal Component
 * 
 * Round 188: Frontend Document Versioning: Upload New Version
 * 
 * Allows users to upload a new version of an existing document
 */
export const UploadDocumentVersionModal: React.FC<UploadDocumentVersionModalProps> = ({
  isOpen,
  onClose,
  projectId,
  documentId,
  onSuccess,
}) => {
  const [file, setFile] = useState<File | null>(null);
  const [name, setName] = useState<string>('');
  const [description, setDescription] = useState<string>('');
  const [category, setCategory] = useState<string>('general');
  const [status, setStatus] = useState<string>('active');
  const [error, setError] = useState<string | null>(null);

  const uploadMutation = useUploadDocumentVersion();

  // Reset form when modal closes
  const handleClose = useCallback(() => {
    setFile(null);
    setName('');
    setDescription('');
    setCategory('general');
    setStatus('active');
    setError(null);
    onClose();
  }, [onClose]);

  const handleFileChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
    const selectedFile = e.target.files?.[0];
    if (selectedFile) {
      setFile(selectedFile);
      setError(null);
      // Auto-fill name if empty
      if (!name) {
        setName(selectedFile.name);
      }
    }
  }, [name]);

  const handleSubmit = useCallback(async () => {
    if (!file) {
      setError('Please select a file to upload');
      return;
    }

    // Validate name length
    if (name.trim() && name.trim().length > 255) {
      setError('Name must be 255 characters or less');
      return;
    }

    // Validate description length
    if (description.trim() && description.trim().length > 2000) {
      setError('Description must be 2000 characters or less');
      return;
    }

    setError(null);

    try {
      const formData = new FormData();
      formData.append('file', file);
      if (name.trim()) {
        formData.append('name', name.trim());
      }
      if (description.trim()) {
        formData.append('description', description.trim());
      }
      formData.append('category', category);
      formData.append('status', status);

      await uploadMutation.mutateAsync({
        projectId,
        documentId,
        formData,
      });

      // Success - close modal and call callback
      handleClose();
      onSuccess?.();
    } catch (err: any) {
      // Handle error
      const errorMessage = err?.message || 'Failed to upload new version. Please try again.';
      setError(errorMessage);
      // TODO: Replace with proper UI feedback (toast/snackbar) if available
      console.error('Upload version error:', err);
    }
  }, [file, name, description, category, status, projectId, documentId, uploadMutation, handleClose, onSuccess]);

  return (
    <Modal
      open={isOpen}
      onOpenChange={(open) => {
        if (!open) {
          handleClose();
        }
      }}
      title="Upload New Version"
      description="Upload a new version of this document"
      primaryAction={{
        label: uploadMutation.isPending ? 'Uploading...' : 'Upload',
        onClick: handleSubmit,
        loading: uploadMutation.isPending,
      }}
      secondaryAction={{
        label: 'Cancel',
        onClick: handleClose,
        variant: 'outline',
      }}
      data-testid="upload-document-version-modal"
    >
      <div className="space-y-4">
        {/* File Input */}
        <div>
          <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
            File <span className="text-[var(--color-semantic-danger-600)]">*</span>
          </label>
          <input
            type="file"
            onChange={handleFileChange}
            className="w-full px-3 py-2 border border-[var(--color-border-default)] rounded-[var(--radius-md)] bg-transparent text-[var(--color-text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-border-focus)]"
            data-testid="upload-version-file-input"
            accept="*/*"
          />
          {file && (
            <p className="mt-2 text-sm text-[var(--color-text-muted)]">
              Selected: {file.name} ({(file.size / 1024 / 1024).toFixed(2)} MB)
            </p>
          )}
        </div>

        {/* Name Input */}
        <div>
          <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
            Name (optional)
          </label>
          <Input
            type="text"
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="Document name"
            maxLength={255}
            data-testid="upload-version-name-input"
          />
        </div>

        {/* Version Note Textarea */}
        {/* Round 190: Changed label to "Version note" but still sends as "description" field to backend */}
        <div>
          <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
            Version note (optional)
          </label>
          <textarea
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            placeholder="Explain what changed in this version"
            rows={3}
            maxLength={2000}
            className="w-full px-3 py-2 border border-[var(--color-border-default)] rounded-[var(--radius-md)] bg-transparent text-[var(--color-text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-border-focus)] resize-none"
            data-testid="upload-version-description-input"
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
            data-testid="upload-version-category-select"
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
            data-testid="upload-version-status-select"
          />
        </div>

        {/* Error Message */}
        {error && (
          <div className="p-3 rounded-[var(--radius-md)] bg-[var(--color-semantic-danger-50)] border border-[var(--color-semantic-danger-200)]">
            <p className="text-sm text-[var(--color-semantic-danger-700)]" data-testid="upload-version-error-message">
              {error}
            </p>
          </div>
        )}
      </div>
    </Modal>
  );
};

