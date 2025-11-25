import React, { memo } from 'react';
import { spacing } from '../../../shared/tokens/spacing';
import { radius } from '../../../shared/tokens/radius';

export interface TaskAttachment {
  id: string | number;
  task_id: string | number;
  name: string;
  original_name: string;
  file_path: string;
  mime_type: string;
  extension: string;
  size: number;
  category?: string;
  description?: string;
  download_count?: number;
  created_at: string;
  uploaded_by?: string | number;
  uploader?: {
    id: string | number;
    name: string;
    email?: string;
  };
}

export interface AttachmentListProps {
  /** Array of attachments */
  attachments: TaskAttachment[];
  /** Handler for downloading attachment */
  onDownload?: (attachment: TaskAttachment) => void;
  /** Handler for deleting attachment */
  onDelete?: (attachmentId: string | number) => void;
  /** Current user ID */
  currentUserId?: string | number;
  /** Loading state */
  loading?: boolean;
}

/**
 * AttachmentList - Component to display list of task attachments
 * 
 * Follows Apple-style design spec with tokens and spacing.
 */
export const AttachmentList: React.FC<AttachmentListProps> = memo(({
  attachments,
  onDownload,
  onDelete,
  currentUserId,
  loading = false,
}) => {
  const formatFileSize = (bytes: number) => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  };

  const getFileIcon = (mimeType: string) => {
    if (mimeType.startsWith('image/')) return '🖼️';
    if (mimeType.startsWith('video/')) return '🎥';
    if (mimeType.startsWith('audio/')) return '🎵';
    if (mimeType.includes('pdf')) return '📄';
    if (mimeType.includes('word') || mimeType.includes('document')) return '📝';
    if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return '📊';
    return '📎';
  };

  if (loading) {
    return (
      <div className="text-center py-4" style={{ color: 'var(--muted)' }}>
        Loading attachments...
      </div>
    );
  }

  if (attachments.length === 0) {
    return (
      <div className="text-center py-8" style={{ color: 'var(--muted)' }}>
        <p className="text-sm">No attachments yet</p>
      </div>
    );
  }

  return (
    <div className="space-y-2" data-testid="attachment-list">
      {attachments.map((attachment) => {
        const isOwnAttachment = currentUserId && attachment.uploaded_by === currentUserId;
        return (
          <div
            key={attachment.id}
            className="flex items-center gap-3 p-3 rounded-lg hover:bg-[var(--muted-surface)] transition-colors"
            style={{
              borderRadius: radius.md,
            }}
            data-testid={`attachment-item-${attachment.id}`}
          >
            {/* File Icon */}
            <div
              style={{
                width: 40,
                height: 40,
                borderRadius: radius.sm,
                backgroundColor: 'var(--muted-surface)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontSize: '20px',
                flexShrink: 0,
              }}
            >
              {getFileIcon(attachment.mime_type)}
            </div>

            {/* File Info */}
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium truncate" style={{ color: 'var(--text)' }}>
                {attachment.original_name}
              </p>
              <div className="flex items-center gap-2 mt-1">
                <span className="text-xs" style={{ color: 'var(--muted)' }}>
                  {formatFileSize(attachment.size)}
                </span>
                {attachment.uploader && (
                  <>
                    <span className="text-xs" style={{ color: 'var(--muted)' }}>
                      •
                    </span>
                    <span className="text-xs" style={{ color: 'var(--muted)' }}>
                      by {attachment.uploader.name}
                    </span>
                  </>
                )}
                {attachment.download_count !== undefined && attachment.download_count > 0 && (
                  <>
                    <span className="text-xs" style={{ color: 'var(--muted)' }}>
                      •
                    </span>
                    <span className="text-xs" style={{ color: 'var(--muted)' }}>
                      {attachment.download_count} downloads
                    </span>
                  </>
                )}
              </div>
            </div>

            {/* Actions */}
            <div className="flex items-center gap-2">
              {onDownload && (
                <button
                  onClick={() => onDownload(attachment)}
                  className="text-xs underline"
                  style={{ color: 'var(--color-semantic-primary-600)' }}
                  aria-label={`Download ${attachment.original_name}`}
                >
                  Download
                </button>
              )}
              {isOwnAttachment && onDelete && (
                <button
                  onClick={() => onDelete(attachment.id)}
                  className="text-xs underline"
                  style={{ color: 'var(--color-semantic-danger-600)' }}
                  aria-label={`Delete ${attachment.original_name}`}
                >
                  Delete
                </button>
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
});

AttachmentList.displayName = 'AttachmentList';

