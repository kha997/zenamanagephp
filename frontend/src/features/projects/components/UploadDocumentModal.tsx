import React, { useState, useCallback } from 'react';
import { Modal } from '../../../shared/ui/modal';
import { Input } from '../../../components/ui/primitives/Input';
import { Select } from '../../../components/ui/primitives/Select';
import { useUploadProjectDocument } from '../hooks';

interface UploadDocumentModalProps {
  isOpen: boolean;
  onClose: () => void;
  onUploadSuccess?: () => void;
  projectId: string | number;
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

export const UploadDocumentModal: React.FC<UploadDocumentModalProps> = ({
  isOpen,
  onClose,
  onUploadSuccess,
  projectId,
}) => {
  const [file, setFile] = useState<File | null>(null);
  const [name, setName] = useState<string>('');
  const [description, setDescription] = useState<string>('');
  const [category, setCategory] = useState<string>('general');
  const [error, setError] = useState<string | null>(null);

  const uploadMutation = useUploadProjectDocument();

  // Reset form when modal closes
  const handleClose = useCallback(() => {
    setFile(null);
    setName('');
    setDescription('');
    setCategory('general');
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

      await uploadMutation.mutateAsync({
        projectId,
        formData,
      });

      // Success - close modal and call callback
      handleClose();
      onUploadSuccess?.();
    } catch (err: any) {
      // Handle error
      const errorMessage = err?.message || 'Failed to upload document. Please try again.';
      setError(errorMessage);
      // TODO: Replace with proper UI feedback (toast/snackbar) if available
      console.error('Upload error:', err);
    }
  }, [file, name, description, category, projectId, uploadMutation, handleClose, onUploadSuccess]);

  return (
    <Modal
      open={isOpen}
      onOpenChange={(open) => {
        if (!open) {
          handleClose();
        }
      }}
      title="Upload Document"
      description="Upload a new document to this project"
      primaryAction={{
        label: 'Upload',
        onClick: handleSubmit,
        loading: uploadMutation.isPending,
      }}
      secondaryAction={{
        label: 'Cancel',
        onClick: handleClose,
        variant: 'outline',
      }}
      data-testid="upload-document-modal"
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
            data-testid="upload-file-input"
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
            data-testid="upload-name-input"
          />
        </div>

        {/* Description Textarea */}
        <div>
          <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
            Description (optional)
          </label>
          <textarea
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            placeholder="Document description"
            rows={3}
            className="w-full px-3 py-2 border border-[var(--color-border-default)] rounded-[var(--radius-md)] bg-transparent text-[var(--color-text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-border-focus)] resize-none"
            data-testid="upload-description-input"
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
            data-testid="upload-category-select"
          />
        </div>

        {/* Error Message */}
        {error && (
          <div className="p-3 rounded-[var(--radius-md)] bg-[var(--color-semantic-danger-50)] border border-[var(--color-semantic-danger-200)]">
            <p className="text-sm text-[var(--color-semantic-danger-700)]" data-testid="upload-error-message">
              {error}
            </p>
          </div>
        )}
      </div>
    </Modal>
  );
};

