import React, { useState, useCallback } from 'react';
import { Modal } from '../../../shared/ui/modal';
import { Button } from '../../../components/ui/primitives/Button';
import { useDocumentVersions, useRestoreDocumentVersion } from '../hooks';
import { projectsApi } from '../api';
import { UploadDocumentVersionModal } from './UploadDocumentVersionModal';

interface DocumentVersionsModalProps {
  isOpen: boolean;
  onClose: () => void;
  projectId: string | number;
  documentId: string | number;
}

/**
 * Format file size in human-readable format
 */
const formatFileSize = (bytes: number): string => {
  if (bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return `${(bytes / Math.pow(k, i)).toFixed(2)} ${sizes[i]}`;
};

/**
 * DocumentVersionsModal Component
 * 
 * Round 187: Document Versioning (View & Download Version)
 * 
 * Displays a list of document versions with download functionality
 */
export const DocumentVersionsModal: React.FC<DocumentVersionsModalProps> = ({
  isOpen,
  onClose,
  projectId,
  documentId,
}) => {
  const [downloadingVersionId, setDownloadingVersionId] = useState<string | number | null>(null);
  const [isUploadVersionModalOpen, setIsUploadVersionModalOpen] = useState(false);
  const [restoreTargetVersion, setRestoreTargetVersion] = useState<any | null>(null);
  const [isRestoreDialogOpen, setIsRestoreDialogOpen] = useState(false);
  const [restoreError, setRestoreError] = useState<string | null>(null);
  const [expandedVersionId, setExpandedVersionId] = useState<string | number | null>(null);
  
  const { data: versionsData, isLoading, error } = useDocumentVersions(projectId, documentId);
  const { mutate: restoreVersion, isPending: isRestoring } = useRestoreDocumentVersion();

  // Extract versions from response
  const versions = React.useMemo(() => {
    if (!versionsData) return [];
    // Handle both response formats: { success: true, data: [...] } or { data: [...] }
    const items = (versionsData as any).data ?? (Array.isArray(versionsData) ? versionsData : []);
    return Array.isArray(items) ? items : [];
  }, [versionsData]);

  // Handle version download
  const handleDownloadVersion = useCallback(async (versionId: string | number) => {
    try {
      setDownloadingVersionId(versionId);
      await projectsApi.downloadDocumentVersion(projectId, documentId, versionId);
    } catch (error) {
      // Show error feedback
      console.error('Failed to download version:', error);
      // TODO: Replace with proper UI feedback (toast/snackbar) if available
    } finally {
      setDownloadingVersionId(null);
    }
  }, [projectId, documentId]);

  // Handle upload version modal
  const handleOpenUploadVersionModal = useCallback(() => {
    setIsUploadVersionModalOpen(true);
  }, []);

  const handleCloseUploadVersionModal = useCallback(() => {
    setIsUploadVersionModalOpen(false);
  }, []);

  const handleUploadVersionSuccess = useCallback(() => {
    setIsUploadVersionModalOpen(false);
    // Queries are automatically invalidated by the hook, so versions list will refresh
  }, []);

  // Handle restore version
  const handleRestoreClick = useCallback((version: any) => {
    setRestoreTargetVersion(version);
    setIsRestoreDialogOpen(true);
    setRestoreError(null);
  }, []);

  const handleRestoreCancel = useCallback(() => {
    setIsRestoreDialogOpen(false);
    setRestoreTargetVersion(null);
    setRestoreError(null);
  }, []);

  const handleRestoreConfirm = useCallback(() => {
    if (!restoreTargetVersion) return;
    
    restoreVersion(
      {
        projectId,
        documentId,
        versionId: restoreTargetVersion.id,
      },
      {
        onSuccess: () => {
          setIsRestoreDialogOpen(false);
          setRestoreTargetVersion(null);
          setRestoreError(null);
          // Queries are automatically invalidated by the hook
        },
        onError: (error: any) => {
          setRestoreError(error?.message || 'Failed to restore version. Please try again.');
        },
      }
    );
  }, [restoreTargetVersion, projectId, documentId, restoreVersion]);

  return (
    <>
    <Modal
      open={isOpen}
      onOpenChange={(open) => {
        if (!open) {
          onClose();
        }
      }}
      title="Document Versions"
      description="View and download previous versions of this document"
      data-testid="document-versions-modal"
    >
      <div className="space-y-4">
        {/* Upload New Version Button */}
        <div className="flex justify-end">
          <Button
            variant="primary"
            size="sm"
            onClick={handleOpenUploadVersionModal}
            data-testid="open-upload-version-modal-button"
          >
            Upload New Version
          </Button>
        </div>

        {/* Versions List */}
        {isLoading ? (
          <div className="py-8 text-center text-[var(--muted)]">
            <p className="text-sm">Loading versions...</p>
          </div>
        ) : error ? (
          <div className="py-8 text-center text-[var(--color-semantic-danger-600)]">
            <p className="text-sm">
              Error loading versions: {(error as Error).message}
            </p>
          </div>
        ) : versions.length === 0 ? (
          <div className="py-8 text-center text-[var(--muted)]">
            <p className="text-sm">No versions found for this document</p>
          </div>
        ) : (
          <div className="space-y-3">
            <div className="max-h-[400px] overflow-y-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-[var(--border)]">
                  <th className="text-left py-2 px-3 font-semibold text-[var(--text)]">Version</th>
                  <th className="text-left py-2 px-3 font-semibold text-[var(--text)]">Name</th>
                  <th className="text-left py-2 px-3 font-semibold text-[var(--text)]">Note</th>
                  <th className="text-left py-2 px-3 font-semibold text-[var(--text)]">Size</th>
                  <th className="text-left py-2 px-3 font-semibold text-[var(--text)]">Uploaded By</th>
                  <th className="text-left py-2 px-3 font-semibold text-[var(--text)]">Date</th>
                  <th className="text-right py-2 px-3 font-semibold text-[var(--text)]">Action</th>
                </tr>
              </thead>
              <tbody>
                {versions.map((version: any) => (
                  <React.Fragment key={version.id}>
                    <tr
                      className="border-b border-[var(--border)] hover:bg-[var(--muted-surface)] transition-colors cursor-pointer"
                      data-testid={`version-row-${version.id}`}
                      onClick={() => setExpandedVersionId(expandedVersionId === version.id ? null : version.id)}
                    >
                      <td className="py-3 px-3 text-[var(--text)]">
                        <span className="font-medium">v{version.version_number}</span>
                      </td>
                      <td className="py-3 px-3 text-[var(--text)]">
                        <span className="truncate block max-w-[200px]" title={version.name || version.original_name || 'Untitled'}>
                          {version.name || version.original_name || 'Untitled'}
                        </span>
                      </td>
                      <td className="py-3 px-3 text-[var(--muted)]">
                        <span className="truncate block max-w-[150px]" title={version.note || '—'}>
                          {version.note ? (version.note.length > 30 ? `${version.note.substring(0, 30)}...` : version.note) : '—'}
                        </span>
                      </td>
                      <td className="py-3 px-3 text-[var(--muted)]">
                        {version.file_size ? formatFileSize(version.file_size) : 'N/A'}
                      </td>
                      <td className="py-3 px-3 text-[var(--muted)]">
                        {version.uploaded_by ? (
                          <span>{version.uploaded_by.name || version.uploaded_by.email || 'Unknown'}</span>
                        ) : (
                          <span>N/A</span>
                        )}
                      </td>
                      <td className="py-3 px-3 text-[var(--muted)]">
                        {version.created_at ? (
                          <span>{new Date(version.created_at).toLocaleDateString()}</span>
                        ) : (
                          <span>N/A</span>
                        )}
                      </td>
                      <td className="py-3 px-3 text-right" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center gap-2 justify-end">
                          <Button
                            variant="outline"
                            size="xs"
                            onClick={() => handleRestoreClick(version)}
                            disabled={isRestoring}
                            data-testid={`restore-version-button-${version.id}`}
                          >
                            Restore
                          </Button>
                          <Button
                            variant="tertiary"
                            size="sm"
                            onClick={() => handleDownloadVersion(version.id)}
                            disabled={downloadingVersionId === version.id}
                            data-testid={`download-version-button-${version.id}`}
                          >
                            {downloadingVersionId === version.id ? 'Downloading...' : 'Download'}
                          </Button>
                        </div>
                      </td>
                    </tr>
                    {/* Expanded Details Row */}
                    {expandedVersionId === version.id && (
                      <tr
                        className="border-b border-[var(--border)] bg-[var(--muted-surface)]"
                        data-testid={`version-details-${version.id}`}
                      >
                        <td colSpan={7} className="py-4 px-3">
                          <div className="space-y-2 text-sm">
                            <div className="grid grid-cols-2 gap-4">
                              <div>
                                <span className="font-semibold text-[var(--text)]">Version:</span>
                                <span className="ml-2 text-[var(--muted)]">v{version.version_number}</span>
                              </div>
                              <div>
                                <span className="font-semibold text-[var(--text)]">File Size:</span>
                                <span className="ml-2 text-[var(--muted)]">
                                  {version.file_size ? formatFileSize(version.file_size) : 'N/A'}
                                </span>
                              </div>
                              <div>
                                <span className="font-semibold text-[var(--text)]">MIME Type:</span>
                                <span className="ml-2 text-[var(--muted)]">
                                  {version.mime_type || 'N/A'}
                                </span>
                              </div>
                              <div>
                                <span className="font-semibold text-[var(--text)]">File Type:</span>
                                <span className="ml-2 text-[var(--muted)]">
                                  {version.file_type || 'N/A'}
                                </span>
                              </div>
                              <div>
                                <span className="font-semibold text-[var(--text)]">Uploaded By:</span>
                                <span className="ml-2 text-[var(--muted)]">
                                  {version.uploaded_by ? (version.uploaded_by.name || version.uploaded_by.email || 'Unknown') : 'N/A'}
                                </span>
                              </div>
                              <div>
                                <span className="font-semibold text-[var(--text)]">Created At:</span>
                                <span className="ml-2 text-[var(--muted)]">
                                  {version.created_at ? new Date(version.created_at).toLocaleString() : 'N/A'}
                                </span>
                              </div>
                            </div>
                            {version.note && (
                              <div>
                                <span className="font-semibold text-[var(--text)]">Note:</span>
                                <p className="mt-1 text-[var(--muted)] whitespace-pre-wrap">{version.note}</p>
                              </div>
                            )}
                          </div>
                        </td>
                      </tr>
                    )}
                  </React.Fragment>
                ))}
              </tbody>
            </table>
          </div>
        </div>
        )}
      </div>

      {/* Upload Version Modal */}
      {isUploadVersionModalOpen && (
        <UploadDocumentVersionModal
          isOpen={isUploadVersionModalOpen}
          onClose={handleCloseUploadVersionModal}
          projectId={projectId}
          documentId={documentId}
          onSuccess={handleUploadVersionSuccess}
        />
      )}
    </Modal>

    {/* Restore Version Confirm Dialog - rendered outside main modal */}
    <Modal
      open={isRestoreDialogOpen}
      onOpenChange={(open) => {
        if (!open) {
          handleRestoreCancel();
        }
      }}
      title="Restore this version?"
      description={`Restore document to version ${restoreTargetVersion?.version_number || ''}`}
      primaryAction={{
        label: isRestoring ? 'Restoring...' : 'Restore',
        onClick: handleRestoreConfirm,
        variant: 'destructive',
        disabled: isRestoring,
      }}
      secondaryAction={{
        label: 'Cancel',
        onClick: handleRestoreCancel,
        variant: 'outline',
        disabled: isRestoring,
      }}
      data-testid="restore-version-dialog"
    >
      <div className="space-y-2">
        <p className="text-sm text-[var(--color-text-secondary)]">
          This will replace the current document file with this version. Previous state will be saved as a new version.
        </p>
        {restoreError && (
          <p className="text-sm text-[var(--color-semantic-danger-600)]">
            {restoreError}
          </p>
        )}
      </div>
    </Modal>
  </>
  );
};

