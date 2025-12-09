import React, { useState, useCallback } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { Input } from '../../../components/ui/primitives/Input';
import { Select } from '../../../components/ui/primitives/Select';
import { useProjectDocuments, useUpdateProjectDocument, useDeleteProjectDocument } from '../hooks';
import { projectsApi } from '../api';
import { UploadDocumentModal } from './UploadDocumentModal';
import { EditDocumentModal } from './EditDocumentModal';
import { DeleteDocumentConfirmDialog } from './DeleteDocumentConfirmDialog';
import { DocumentVersionsModal } from './DocumentVersionsModal';

interface ProjectDocumentsSectionProps {
  projectId: string | number;
  filters?: { category?: string; status?: string; search?: string };
  onUploadClick?: () => void;
  showUploadButton?: boolean;
  // If onUploadClick is not provided, component will handle upload internally
}

// Document categories - can be extended or fetched from API in future
const DOCUMENT_CATEGORIES = [
  { value: '', label: 'All Categories' },
  { value: 'contract', label: 'Contract' },
  { value: 'drawing', label: 'Drawing' },
  { value: 'specification', label: 'Specification' },
  { value: 'report', label: 'Report' },
  { value: 'other', label: 'Other' },
];

// Document statuses - align with backend if known
const DOCUMENT_STATUSES = [
  { value: '', label: 'All Statuses' },
  { value: 'active', label: 'Active' },
  { value: 'pending', label: 'Pending' },
  { value: 'archived', label: 'Archived' },
  { value: 'approved', label: 'Approved' },
];

export const ProjectDocumentsSection: React.FC<ProjectDocumentsSectionProps> = ({
  projectId,
  filters: externalFilters,
  onUploadClick,
  showUploadButton = true,
}) => {
  // Local filter state
  const [search, setSearch] = useState<string>(externalFilters?.search || '');
  const [category, setCategory] = useState<string>(externalFilters?.category || '');
  const [status, setStatus] = useState<string>(externalFilters?.status || '');
  
  // Track which document is currently downloading
  const [downloadingId, setDownloadingId] = useState<string | number | null>(null);
  
  // Upload modal state
  const [isUploadModalOpen, setIsUploadModalOpen] = useState<boolean>(false);
  
  // Edit modal state
  const [isEditModalOpen, setIsEditModalOpen] = useState<boolean>(false);
  const [editingDocument, setEditingDocument] = useState<any | null>(null);
  
  // Delete dialog state
  const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState<boolean>(false);
  const [deletingDocument, setDeletingDocument] = useState<any | null>(null);
  
  // Versions modal state
  const [isVersionsModalOpen, setIsVersionsModalOpen] = useState<boolean>(false);
  const [versionsDocumentId, setVersionsDocumentId] = useState<string | number | null>(null);
  
  // Mutations
  const updateMutation = useUpdateProjectDocument();
  const deleteMutation = useDeleteProjectDocument();

  // Build filters object - only include non-empty values
  const filters = React.useMemo(() => {
    const filterObj: { category?: string; status?: string; search?: string } = {};
    if (search.trim()) filterObj.search = search.trim();
    if (category) filterObj.category = category;
    if (status) filterObj.status = status;
    return filterObj;
  }, [search, category, status]);

  const { data: documentsData, isLoading, error } = useProjectDocuments(projectId, filters, { page: 1, per_page: 50 });

  // Handle search input - trigger on Enter or search button
  const handleSearchKeyDown = useCallback((e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      // State update will trigger refetch via useMemo dependency
    }
  }, []);

  const handleSearchChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
    setSearch(e.target.value);
  }, []);

  const handleCategoryChange = useCallback((value: string) => {
    setCategory(value);
  }, []);

  const handleStatusChange = useCallback((value: string) => {
    setStatus(value);
  }, []);

  // Handle document download
  const handleDownload = useCallback(async (doc: any) => {
    try {
      setDownloadingId(doc.id);
      await projectsApi.downloadProjectDocument(projectId, doc.id);
    } catch (error) {
      // Show error feedback
      console.error('Failed to download document:', error);
      // TODO: Replace with proper UI feedback (toast/snackbar) if available
      // For now, we'll rely on console.error and the user can see the error in dev tools
      // In production, this should show a user-friendly error message
    } finally {
      setDownloadingId(null);
    }
  }, [projectId]);

  // Handle upload button click
  const handleUploadClick = useCallback(() => {
    if (onUploadClick) {
      // If parent provides handler, use it
      onUploadClick();
    } else {
      // Otherwise, open internal modal
      setIsUploadModalOpen(true);
    }
  }, [onUploadClick]);

  // Handle upload success - modal will close and invalidate queries automatically
  const handleUploadSuccess = useCallback(() => {
    // Modal already closed, queries already invalidated by hook
    // This callback is for any additional actions if needed
  }, []);

  // Handle edit click
  const handleEditClick = useCallback((doc: any) => {
    setEditingDocument(doc);
    setIsEditModalOpen(true);
  }, []);

  // Handle edit close
  const handleEditClose = useCallback(() => {
    setIsEditModalOpen(false);
    setEditingDocument(null);
  }, []);

  // Handle edit success
  const handleEditSuccess = useCallback(() => {
    // Modal already closed, queries already invalidated by hook
    // This callback is for any additional actions if needed
  }, []);

  // Handle delete click
  const handleDeleteClick = useCallback((doc: any) => {
    setDeletingDocument(doc);
    setIsDeleteDialogOpen(true);
  }, []);

  // Handle delete cancel
  const handleDeleteCancel = useCallback(() => {
    setIsDeleteDialogOpen(false);
    setDeletingDocument(null);
  }, []);

  // Handle delete confirm
  const handleDeleteConfirm = useCallback(async () => {
    if (!deletingDocument) return;

    try {
      await deleteMutation.mutateAsync({
        projectId,
        documentId: deletingDocument.id,
      });
      // Success - close dialog and reset state
      setIsDeleteDialogOpen(false);
      setDeletingDocument(null);
      // Queries already invalidated by hook
    } catch (err: any) {
      // Handle error - keep dialog open so user can retry
      console.error('Delete error:', err);
      // TODO: Replace with proper UI feedback (toast/snackbar) if available
    }
  }, [deletingDocument, projectId, deleteMutation]);

  // Handle versions click
  const handleVersionsClick = useCallback((doc: any) => {
    setVersionsDocumentId(doc.id);
    setIsVersionsModalOpen(true);
  }, []);

  // Handle versions modal close
  const handleVersionsClose = useCallback(() => {
    setIsVersionsModalOpen(false);
    setVersionsDocumentId(null);
  }, []);

  // Extract documents from response
  const documents = React.useMemo(() => {
    if (!documentsData) return [];
    // Handle both response formats: { success: true, data: [...] } or { data: [...] }
    const items = (documentsData as any).data ?? (Array.isArray(documentsData) ? documentsData : []);
    return Array.isArray(items) ? items : [];
  }, [documentsData]);

  if (isLoading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Documents</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            {[1, 2, 3].map((i) => (
              <div key={i} className="animate-pulse">
                <div className="h-4 bg-[var(--muted-surface)] rounded w-3/4 mb-2"></div>
                <div className="h-3 bg-[var(--muted-surface)] rounded w-1/2"></div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
    );
  }

  if (error) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Documents</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="text-center py-8 text-[var(--color-semantic-danger-600)]">
            <p className="text-sm">
              Error loading documents: {(error as Error).message}
            </p>
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <CardTitle>
            Documents
            {documents.length > 0 && (
              <span className="ml-2 text-sm font-normal text-[var(--muted)]">
                ({documents.length})
              </span>
            )}
          </CardTitle>
          {showUploadButton && (
            <Button 
              variant="secondary" 
              size="sm" 
              onClick={handleUploadClick}
              data-testid="upload-document-button"
            >
              Upload Document
            </Button>
          )}
        </div>
      </CardHeader>
      <CardContent>
        {/* Filter Bar */}
        <div className="mb-4 space-y-3">
          <div className="flex flex-col sm:flex-row gap-3">
            {/* Search Input */}
            <div className="flex-1">
              <Input
                type="text"
                placeholder="Search documents..."
                value={search}
                onChange={handleSearchChange}
                onKeyDown={handleSearchKeyDown}
                data-testid="documents-search-input"
              />
            </div>
            {/* Category Select */}
            <div className="w-full sm:w-48">
              <Select
                options={DOCUMENT_CATEGORIES}
                value={category}
                onChange={handleCategoryChange}
                placeholder="All Categories"
                data-testid="documents-category-select"
              />
            </div>
            {/* Status Select */}
            <div className="w-full sm:w-48">
              <Select
                options={DOCUMENT_STATUSES}
                value={status}
                onChange={handleStatusChange}
                placeholder="All Statuses"
                data-testid="documents-status-select"
              />
            </div>
          </div>
        </div>
        {documents.length > 0 ? (
          <div className="space-y-3">
            {documents.map((doc: any) => (
              <div
                key={doc.id}
                className="p-4 border border-[var(--border)] rounded-lg hover:bg-[var(--muted-surface)] transition-colors"
              >
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-3 flex-1 min-w-0">
                    <div className="text-2xl flex-shrink-0">📄</div>
                    <div className="flex-1 min-w-0">
                      <h4 className="font-semibold text-[var(--text)] truncate">
                        {doc.name || doc.title || 'Untitled Document'}
                      </h4>
                      {doc.description && (
                        <p className="text-sm text-[var(--muted)] mt-1 line-clamp-1">
                          {doc.description}
                        </p>
                      )}
                      <div className="flex items-center gap-4 text-xs text-[var(--muted)] mt-2 flex-wrap">
                        {doc.category && (
                          <span className="px-2 py-1 rounded bg-[var(--muted-surface)]">
                            {doc.category}
                          </span>
                        )}
                        {doc.status && (
                          <span className={`px-2 py-1 rounded ${
                            doc.status === 'active' || doc.status === 'approved'
                              ? 'bg-green-100 text-green-700'
                              : doc.status === 'pending'
                              ? 'bg-yellow-100 text-yellow-700'
                              : 'bg-gray-100 text-gray-700'
                          }`}>
                            {doc.status}
                          </span>
                        )}
                        {doc.file_type && <span>Type: {doc.file_type}</span>}
                        {doc.mime_type && <span>MIME: {doc.mime_type}</span>}
                        {doc.file_size && (
                          <span>
                            Size: {typeof doc.file_size === 'number' 
                              ? `${(doc.file_size / 1024 / 1024).toFixed(2)} MB`
                              : doc.file_size}
                          </span>
                        )}
                        {doc.uploaded_by && (
                          <span>
                            Uploaded by: <span className="font-medium">{doc.uploaded_by.name || doc.uploaded_by.email || 'Unknown'}</span>
                          </span>
                        )}
                        {doc.created_at && (
                          <span>
                            {new Date(doc.created_at).toLocaleDateString()}
                          </span>
                        )}
                      </div>
                    </div>
                  </div>
                  <div className="flex items-center gap-2 ml-4 flex-shrink-0">
                    {doc.url && (
                      <Button
                        variant="tertiary"
                        size="sm"
                        onClick={() => window.open(doc.url, '_blank')}
                      >
                        View
                      </Button>
                    )}
                    {/* Download button - use new hybrid download helper */}
                    {(doc.download_url || doc.file_path || doc.id) && (
                      <Button
                        variant="tertiary"
                        size="sm"
                        onClick={() => handleDownload(doc)}
                        disabled={downloadingId === doc.id}
                        data-testid={`download-button-${doc.id}`}
                      >
                        {downloadingId === doc.id ? 'Downloading...' : 'Download'}
                      </Button>
                    )}
                    {/* Edit button */}
                    <Button
                      variant="tertiary"
                      size="sm"
                      onClick={() => handleEditClick(doc)}
                      disabled={updateMutation.isPending && editingDocument?.id === doc.id}
                      data-testid={`edit-button-${doc.id}`}
                    >
                      Edit
                    </Button>
                    {/* Versions button */}
                    <Button
                      variant="tertiary"
                      size="sm"
                      onClick={() => handleVersionsClick(doc)}
                      data-testid={`versions-button-${doc.id}`}
                    >
                      Versions
                    </Button>
                    {/* Delete button */}
                    <Button
                      variant="tertiary"
                      size="sm"
                      onClick={() => handleDeleteClick(doc)}
                      disabled={deleteMutation.isPending && deletingDocument?.id === doc.id}
                      data-testid={`delete-button-${doc.id}`}
                    >
                      {deleteMutation.isPending && deletingDocument?.id === doc.id ? 'Deleting...' : 'Delete'}
                    </Button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div className="text-center py-8 text-[var(--muted)]">
            <p className="text-sm mb-2">No documents found for this project</p>
            {showUploadButton && (
              <Button 
                variant="secondary" 
                size="sm" 
                onClick={handleUploadClick}
                data-testid="upload-first-document-button"
              >
                Upload First Document
              </Button>
            )}
          </div>
        )}
      </CardContent>
      
      {/* Upload Document Modal */}
      {!onUploadClick && (
        <UploadDocumentModal
          isOpen={isUploadModalOpen}
          onClose={() => setIsUploadModalOpen(false)}
          onUploadSuccess={handleUploadSuccess}
          projectId={projectId}
        />
      )}
      
      {/* Edit Document Modal */}
      {editingDocument && (
        <EditDocumentModal
          isOpen={isEditModalOpen}
          onClose={handleEditClose}
          onSuccess={handleEditSuccess}
          projectId={projectId}
          document={editingDocument}
        />
      )}
      
      {/* Delete Document Confirmation Dialog */}
      {deletingDocument && (
        <DeleteDocumentConfirmDialog
          isOpen={isDeleteDialogOpen}
          onCancel={handleDeleteCancel}
          onConfirm={handleDeleteConfirm}
          documentName={deletingDocument.name || deletingDocument.title}
        />
      )}
      
      {/* Document Versions Modal */}
      {versionsDocumentId && (
        <DocumentVersionsModal
          isOpen={isVersionsModalOpen}
          onClose={handleVersionsClose}
          projectId={projectId}
          documentId={versionsDocumentId}
        />
      )}
    </Card>
  );
};

