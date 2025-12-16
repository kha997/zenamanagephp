import React, { useState, useRef } from 'react';
import { Button } from '../../../components/ui/primitives/Button';
import { spacing } from '../../../shared/tokens/spacing';
import { radius } from '../../../shared/tokens/radius';

export interface AttachmentUploadProps {
  /** Task ID */
  taskId: string | number;
  /** Upload handler */
  onUpload: (file: File, metadata?: { description?: string; category?: string }) => Promise<void>;
  /** Loading state */
  loading?: boolean;
  /** Maximum file size in bytes (default: 50MB) */
  maxSize?: number;
  /** Accepted file types */
  accept?: string;
}

/**
 * AttachmentUpload - Component for uploading task attachments
 * 
 * Follows Apple-style design spec with tokens and spacing.
 */
export const AttachmentUpload: React.FC<AttachmentUploadProps> = ({
  taskId,
  onUpload,
  loading = false,
  maxSize = 50 * 1024 * 1024, // 50MB default
  accept,
}) => {
  const [dragActive, setDragActive] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const handleFile = async (file: File) => {
    if (file.size > maxSize) {
      setError(`File size exceeds ${Math.round(maxSize / 1024 / 1024)}MB limit`);
      return;
    }

    setError(null);
    setUploading(true);
    try {
      await onUpload(file);
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
    } catch (err) {
      setError((err as Error).message || 'Failed to upload file');
    } finally {
      setUploading(false);
    }
  };

  const handleDrag = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    if (e.type === 'dragenter' || e.type === 'dragover') {
      setDragActive(true);
    } else if (e.type === 'dragleave') {
      setDragActive(false);
    }
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setDragActive(false);

    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      handleFile(e.dataTransfer.files[0]);
    }
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      handleFile(e.target.files[0]);
    }
  };

  return (
    <div
      onDragEnter={handleDrag}
      onDragLeave={handleDrag}
      onDragOver={handleDrag}
      onDrop={handleDrop}
      style={{
        border: `2px dashed ${dragActive ? 'var(--accent)' : 'var(--border)'}`,
        borderRadius: radius.md,
        padding: spacing.xl,
        textAlign: 'center',
        backgroundColor: dragActive ? 'var(--muted-surface)' : 'var(--surface)',
        transition: 'all 150ms ease',
        cursor: 'pointer',
      }}
      onClick={() => fileInputRef.current?.click()}
      data-testid="attachment-upload"
    >
      <input
        ref={fileInputRef}
        type="file"
        accept={accept}
        onChange={handleChange}
        disabled={loading || uploading}
        style={{ display: 'none' }}
        aria-label="Upload attachment"
      />
      {uploading ? (
        <div style={{ color: 'var(--muted)' }}>
          <p className="text-sm">Uploading...</p>
        </div>
      ) : (
        <>
          <p className="text-sm mb-2" style={{ color: 'var(--text)' }}>
            {dragActive ? 'Drop file here' : 'Drag & drop a file or click to browse'}
          </p>
          <p className="text-xs" style={{ color: 'var(--muted)' }}>
            Max size: {Math.round(maxSize / 1024 / 1024)}MB
          </p>
          {error && (
            <p className="text-xs mt-2" style={{ color: 'var(--color-semantic-danger-600)' }}>
              {error}
            </p>
          )}
        </>
      )}
    </div>
  );
};

