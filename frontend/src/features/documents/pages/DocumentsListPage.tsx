import React, { useState, useMemo, useCallback, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { KpiStrip } from '../../../components/shared/KpiStrip';
import { AlertBar } from '../../../components/shared/AlertBar';
import { ActivityFeed } from '../../../components/shared/ActivityFeed';
import { EmptyState } from '../../../components/shared/EmptyState';
import { LoadingSpinner } from '../../../components/shared/LoadingSpinner';
import { AccessRestricted } from '../../../components/shared/AccessRestricted';
import { Button } from '../../../components/ui/primitives/Button';
import { Input } from '../../../components/ui/primitives/Input';
import { Select } from '../../../components/ui/primitives/Select';
import { Modal } from '../../../shared/ui/modal';
import { useDocuments, useUploadDocument, useDeleteDocument, useDownloadDocument, useDocumentsKpis, useDocumentsAlerts, useDocumentsActivity } from '../hooks';
import { useAuthStore } from '../../auth/store';
import type { KpiItem } from '../../../components/shared/KpiStrip';
import type { Alert } from '../../../components/shared/AlertBar';
import type { Activity } from '../../../components/shared/ActivityFeed';
import type { DocumentsFilters, Document } from '../types';

// File upload constants
const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
const ALLOWED_MIME_TYPES = [
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.ms-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'application/vnd.ms-powerpoint',
  'application/vnd.openxmlformats-officedocument.presentationml.presentation',
  'image/jpeg',
  'image/png',
  'image/gif',
  'text/plain',
];

// File validation helpers
const validateFile = (file: File): { valid: boolean; error?: string } => {
  if (file.size > MAX_FILE_SIZE) {
    return { valid: false, error: `File size must be less than 10MB. Current size: ${(file.size / 1024 / 1024).toFixed(2)}MB` };
  }
  if (!ALLOWED_MIME_TYPES.includes(file.type)) {
    return { valid: false, error: `File type not allowed. Allowed types: PDF, Word, Excel, PowerPoint, Images, and Text files` };
  }
  return { valid: true };
};

const formatFileSize = (bytes: number): string => {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(2)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
};

const getFileIcon = (mimeType: string): string => {
  if (mimeType.includes('pdf')) return '📄';
  if (mimeType.includes('word') || mimeType.includes('document')) return '📝';
  if (mimeType.includes('sheet') || mimeType.includes('excel')) return '📊';
  if (mimeType.includes('presentation') || mimeType.includes('powerpoint')) return '📽️';
  if (mimeType.includes('image')) return '🖼️';
  return '📎';
};

/**
 * DocumentsListPage - Main documents list page with Universal Page Frame
 * 
 * Displays KPIs, alerts, documents list with filters, and activity feed.
 * Follows Universal Page Frame structure like DashboardPage.
 */
export const DocumentsListPage: React.FC = () => {
  const navigate = useNavigate();
  const { user, hasTenantPermission } = useAuthStore();
  const fileInputRef = useRef<HTMLInputElement>(null);
  
  // Permission checks (Round 15)
  const canViewDocuments = hasTenantPermission('tenant.view_documents') || hasTenantPermission('tenant.manage_documents');
  const canManageDocuments = hasTenantPermission('tenant.manage_documents');
  const isReadOnly = canViewDocuments && !canManageDocuments;
  
  // Filters state
  const [filters, setFilters] = useState<DocumentsFilters>({
    page: 1,
    per_page: 12,
  });
  
  // Search input state (for debouncing)
  const [searchInput, setSearchInput] = useState('');
  
  // Upload modal state
  const [showUploadModal, setShowUploadModal] = useState(false);
  const [uploadFile, setUploadFile] = useState<File | null>(null);
  const [uploadDescription, setUploadDescription] = useState('');
  const [uploadTags, setUploadTags] = useState('');
  const [isPublic, setIsPublic] = useState(false);
  
  // All hooks must be called before any conditional returns (React hooks rules)
  // Pass enabled: canViewDocuments to prevent API calls when user lacks permission
  const { data: documentsResponse, isLoading, error } = useDocuments(filters, { page: filters.page, per_page: filters.per_page }, { enabled: canViewDocuments });
  const { data: kpisData, isLoading: kpisLoading } = useDocumentsKpis({ enabled: canViewDocuments });
  const { data: alertsData, isLoading: alertsLoading, error: alertsError } = useDocumentsAlerts({ enabled: canViewDocuments });
  const { data: activityData, isLoading: activityLoading, error: activityError } = useDocumentsActivity(10, { enabled: canViewDocuments });
  
  // Early return if user doesn't have view permission
  if (!canViewDocuments) {
    return (
      <Container>
        <AccessRestricted
          title="Access Restricted"
          description="You don't have permission to view documents in this workspace. Please contact an administrator to request access."
        />
      </Container>
    );
  }
  
  const uploadMutation = useUploadDocument();
  const deleteMutation = useDeleteDocument();
  const downloadMutation = useDownloadDocument();
  
  const documents = documentsResponse?.data || [];
  const meta = documentsResponse?.meta;
  
  // RBAC checks - permission-based (Round 15)
  // canManageDocuments already defined above - use it for all mutation operations
  const canUpload = canManageDocuments;
  const canDelete = canManageDocuments;
  const canUpdate = canManageDocuments;
  
  // Download is allowed for anyone with view permission
  const canDownload = canViewDocuments;
  
  // Debounce search input
  React.useEffect(() => {
    const timer = setTimeout(() => {
      setFilters(prev => ({ ...prev, search: searchInput || undefined, page: 1 }));
    }, 300);
    return () => clearTimeout(timer);
  }, [searchInput]);
  
  // Get unique projects for filter
  const projects = useMemo(() => {
    const projectSet = new Set<string>();
    documents.forEach(doc => {
      if (doc.project_name) projectSet.add(doc.project_name);
    });
    return Array.from(projectSet);
  }, [documents]);
  
  // Transform KPI data to KpiItem format
  const kpiItems: KpiItem[] = useMemo(() => {
    if (!kpisData) return [];
    
    const kpis = kpisData;
    const trends = kpis.trends || {};
    
    return [
      {
        label: 'Total Documents',
        value: kpis.total_documents || 0,
        variant: 'default',
        change: trends.total_documents ? `${trends.total_documents.direction === 'up' ? '+' : '-'}${trends.total_documents.value}%` : undefined,
        trend: trends.total_documents?.direction,
      },
      {
        label: 'Recent Uploads',
        value: kpis.recent_uploads || 0,
        variant: 'info',
        change: trends.recent_uploads ? `${trends.recent_uploads.direction === 'up' ? '+' : '-'}${trends.recent_uploads.value}%` : undefined,
        trend: trends.recent_uploads?.direction,
      },
      {
        label: 'Storage Used',
        value: formatFileSize(kpis.storage_used || 0),
        variant: 'default',
        change: trends.storage_used ? `${trends.storage_used.direction === 'up' ? '+' : '-'}${trends.storage_used.value}%` : undefined,
        trend: trends.storage_used?.direction,
      },
      {
        label: 'PDF Documents',
        value: kpis.by_type?.['application/pdf'] || 0,
        variant: 'default',
      },
    ];
  }, [kpisData]);
  
  // Transform alerts data
  const transformedAlerts: Alert[] = useMemo(() => {
    if (!alertsData) return [];
    return alertsData.map((alert) => ({
      id: alert.id,
      message: alert.message,
      type: alert.severity === 'critical' || alert.severity === 'high' ? 'error' : alert.severity === 'medium' ? 'warning' : 'info',
      priority: alert.severity === 'critical' ? 10 : alert.severity === 'high' ? 8 : alert.severity === 'medium' ? 5 : 3,
      created_at: alert.createdAt,
      dismissed: alert.status === 'read' || alert.status === 'archived',
    }));
  }, [alertsData]);
  
  // Transform activity data
  const transformedActivities: Activity[] = useMemo(() => {
    if (!activityData) return [];
    return activityData.map((activity) => ({
      id: activity.id,
      type: 'document',
      action: activity.action,
      description: activity.description,
      timestamp: activity.timestamp,
      user: activity.user,
    }));
  }, [activityData]);
  
  // Track dismissed alerts locally
  const [dismissedAlerts, setDismissedAlerts] = useState<Set<string | number>>(new Set());
  const activeAlerts = useMemo(() => {
    return transformedAlerts.filter(alert => !dismissedAlerts.has(alert.id));
  }, [transformedAlerts, dismissedAlerts]);
  
  const handleDismissAlert = useCallback((id: string | number) => {
    setDismissedAlerts(prev => new Set(prev).add(id));
  }, []);
  
  const handleDismissAllAlerts = useCallback(() => {
    activeAlerts.forEach(alert => setDismissedAlerts(prev => new Set(prev).add(alert.id)));
  }, [activeAlerts]);
  
  // Handlers
  const handleSearch = useCallback((value: string) => {
    setSearchInput(value);
  }, []);
  
  const handleProjectFilter = useCallback((projectName: string) => {
    if (projectName === 'all') {
      setFilters(prev => ({ ...prev, project_id: undefined, page: 1 }));
    } else {
      const project = documents.find(doc => doc.project_name === projectName);
      if (project?.project_id) {
        setFilters(prev => ({ ...prev, project_id: project.project_id, page: 1 }));
      }
    }
  }, [documents]);
  
  const handleTypeFilter = useCallback((mimeType: string) => {
    if (mimeType === 'all') {
      setFilters(prev => ({ ...prev, mime_type: undefined, page: 1 }));
    } else {
      setFilters(prev => ({ ...prev, mime_type: mimeType, page: 1 }));
    }
  }, []);
  
  const handleFileSelect = useCallback((event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;
    
    const validation = validateFile(file);
    if (!validation.valid) {
      toast.error(validation.error || 'Invalid file');
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
      return;
    }
    
    setUploadFile(file);
  }, []);
  
  const handleUpload = useCallback(async () => {
    if (!uploadFile) {
      toast.error('Please select a file');
      return;
    }
    
    try {
      const tags = uploadTags.split(',').map(tag => tag.trim()).filter(Boolean);
      await uploadMutation.mutateAsync({
        file: uploadFile,
        description: uploadDescription || undefined,
        tags: tags.length > 0 ? tags : undefined,
        is_public: isPublic,
      });
      
      toast.success('Document uploaded successfully');
      setShowUploadModal(false);
      setUploadFile(null);
      setUploadDescription('');
      setUploadTags('');
      setIsPublic(false);
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
    } catch (error: any) {
      toast.error(error?.message || 'Failed to upload document');
    }
  }, [uploadFile, uploadDescription, uploadTags, isPublic, uploadMutation]);
  
  const handleDelete = useCallback(async (documentId: number, documentName: string) => {
    if (!window.confirm(`Are you sure you want to delete "${documentName}"?`)) {
      return;
    }
    
    try {
      await deleteMutation.mutateAsync(documentId);
      toast.success('Document deleted successfully');
    } catch (error: any) {
      toast.error(error?.message || 'Failed to delete document');
    }
  }, [deleteMutation]);
  
  const handleDownload = useCallback(async (doc: Document) => {
    try {
      const blob = await downloadMutation.mutateAsync(doc.id);
      const url = window.URL.createObjectURL(blob);
      const a = window.document.createElement('a');
      a.href = url;
      a.download = doc.filename || doc.name;
      window.document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      window.document.body.removeChild(a);
      toast.success('Document downloaded successfully');
    } catch (error: any) {
      toast.error(error?.message || 'Failed to download document');
    }
  }, [downloadMutation]);
  
  const handleView = useCallback((document: Document) => {
    navigate(`/app/documents/${document.id}`);
  }, [navigate]);
  
  const handlePageChange = useCallback((newPage: number) => {
    setFilters(prev => ({ ...prev, page: newPage }));
  }, []);
  
  return (
    <Container>
      <div className="space-y-6" aria-live="polite">
        {/* Header */}
        <div className="flex justify-between items-center">
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-3xl font-bold text-[var(--color-text-primary)]">Documents</h1>
              {isReadOnly && (
                <span className="px-2 py-1 text-xs font-medium rounded bg-[var(--muted-surface)] text-[var(--muted)]">
                  Read-only mode
                </span>
              )}
            </div>
            <p className="text-[var(--color-text-muted)] mt-1">Manage and organize your project documents</p>
          </div>
          {canUpload && (
            <Button onClick={() => setShowUploadModal(true)}>
              Upload Document
            </Button>
          )}
        </div>
        
        {/* KPI Strip */}
        <KpiStrip
          kpis={kpiItems}
          loading={kpisLoading}
        />
        
        {/* Alert Bar */}
        <AlertBar
          alerts={activeAlerts}
          loading={alertsLoading}
          error={alertsError}
          onDismiss={handleDismissAlert}
          onDismissAll={handleDismissAllAlerts}
        />
        
        {/* Filters */}
        <Card>
          <CardHeader>
            <CardTitle>Filters</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
                  Search
                </label>
                <Input
                  placeholder="Search documents..."
                  value={searchInput}
                  onChange={(e) => handleSearch(e.target.value)}
                  leadingIcon={<span>🔍</span>}
                />
              </div>
              
              <div>
                <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
                  File Type
                </label>
                <Select
                  value={filters.mime_type || 'all'}
                  onChange={handleTypeFilter}
                  options={[
                    { value: 'all', label: 'All Types' },
                    { value: 'application/pdf', label: 'PDF' },
                    { value: 'application/msword', label: 'Word (DOC)' },
                    { value: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', label: 'Word (DOCX)' },
                    { value: 'application/vnd.ms-excel', label: 'Excel (XLS)' },
                    { value: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', label: 'Excel (XLSX)' },
                    { value: 'image/jpeg', label: 'JPEG' },
                    { value: 'image/png', label: 'PNG' },
                  ]}
                />
              </div>
              
              <div>
                <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
                  Project
                </label>
                <Select
                  value={filters.project_id?.toString() || 'all'}
                  onChange={(value) => {
                    if (value === 'all') {
                      handleProjectFilter('all');
                    } else {
                      handleProjectFilter(value);
                    }
                  }}
                  options={[
                    { value: 'all', label: 'All Projects' },
                    ...projects.map(project => ({ value: project, label: project })),
                  ]}
                />
              </div>
            </div>
          </CardContent>
        </Card>
        
        {/* Documents List */}
        <Card>
          <CardHeader>
            <CardTitle>Documents ({meta?.total || 0})</CardTitle>
          </CardHeader>
          <CardContent>
            {isLoading ? (
              <div className="flex items-center justify-center h-64">
                <LoadingSpinner size="lg" message="Loading documents..." />
              </div>
            ) : error ? (
              <div className="text-center py-12">
                <div className="text-[var(--color-semantic-danger-500)]">
                  <h3 className="text-lg font-medium mb-2">Failed to load documents</h3>
                  <p className="text-[var(--color-text-muted)]">There was an error loading the documents. Please try again.</p>
                  <Button onClick={() => window.location.reload()} variant="outline" className="mt-4">
                    Retry
                  </Button>
                </div>
              </div>
            ) : documents.length === 0 ? (
              <EmptyState
                icon="📁"
                title="No documents found"
                description="Try adjusting your filters or upload a new document."
                actionText={canUpload ? "Upload Document" : undefined}
                onAction={canUpload ? () => setShowUploadModal(true) : undefined}
              />
            ) : (
              <div className="space-y-4">
                {documents.map(doc => (
                  <div
                    key={doc.id}
                    className="flex items-center justify-between p-4 border border-[var(--color-border-subtle)] rounded-lg hover:bg-[var(--color-surface-muted)] transition-colors"
                  >
                    <div className="flex items-center space-x-4 flex-1">
                      <span className="text-2xl">{getFileIcon(doc.mime_type)}</span>
                      <div className="flex-1">
                        <h4 className="font-medium text-[var(--color-text-primary)]">{doc.name}</h4>
                        <div className="flex items-center space-x-4 mt-1">
                          <span className="text-sm text-[var(--color-text-muted)]">{formatFileSize(doc.size)}</span>
                          <span className="text-sm text-[var(--color-text-muted)]">by {doc.uploaded_by_name}</span>
                          {doc.project_name && (
                            <span className="text-xs px-2 py-1 bg-[var(--color-surface-muted)] rounded text-[var(--color-text-muted)]">
                              {doc.project_name}
                            </span>
                          )}
                        </div>
                      </div>
                    </div>
                    <div className="flex space-x-2">
                      {canDownload && (
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => handleView(doc)}
                          aria-label={`View ${doc.name}`}
                        >
                          👁️
                        </Button>
                      )}
                      {canDownload && (
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => handleDownload(doc)}
                          disabled={downloadMutation.isPending}
                          aria-label={`Download ${doc.name}`}
                        >
                          ⬇️
                        </Button>
                      )}
                      {canUpdate && (
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => navigate(`/app/documents/${doc.id}/edit`)}
                          aria-label={`Edit ${doc.name}`}
                        >
                          ✏️
                        </Button>
                      )}
                      {canDelete && (
                        <Button
                          variant="ghost"
                          size="sm"
                          className="text-[var(--color-semantic-danger-500)]"
                          onClick={() => handleDelete(doc.id, doc.name)}
                          disabled={deleteMutation.isPending}
                          aria-label={`Delete ${doc.name}`}
                        >
                          🗑️
                        </Button>
                      )}
                    </div>
                  </div>
                ))}
                
                {/* Pagination */}
                {meta && meta.last_page > 1 && (
                  <div className="flex items-center justify-between mt-6">
                    <div className="text-sm text-[var(--color-text-muted)]">
                      Showing {((meta.current_page - 1) * meta.per_page) + 1} to {Math.min(meta.current_page * meta.per_page, meta.total)} of {meta.total} documents
                    </div>
                    <div className="flex space-x-2">
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handlePageChange(meta.current_page - 1)}
                        disabled={meta.current_page === 1}
                      >
                        Previous
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handlePageChange(meta.current_page + 1)}
                        disabled={meta.current_page === meta.last_page}
                      >
                        Next
                      </Button>
                    </div>
                  </div>
                )}
              </div>
            )}
          </CardContent>
        </Card>
        
        {/* Activity Feed */}
        <ActivityFeed
          activities={transformedActivities}
          loading={activityLoading}
          error={activityError}
          limit={10}
          title="Recent Activity"
        />
        
        {/* Upload Modal */}
        <Modal
          open={showUploadModal}
          onOpenChange={setShowUploadModal}
          title="Upload Document"
          description="Select a file to upload. Maximum file size is 10MB."
          primaryAction={{
            label: uploadMutation.isPending ? 'Uploading...' : 'Upload',
            onClick: handleUpload,
            loading: uploadMutation.isPending,
            variant: 'primary',
          }}
          secondaryAction={{
            label: 'Cancel',
            onClick: () => setShowUploadModal(false),
            variant: 'outline',
          }}
        >
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
                Select File *
              </label>
              <input
                ref={fileInputRef}
                type="file"
                onChange={handleFileSelect}
                className="block w-full text-sm text-[var(--color-text-secondary)] file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-[var(--color-surface-muted)] file:text-[var(--color-text-primary)] hover:file:bg-[var(--color-surface-hover)]"
                accept={ALLOWED_MIME_TYPES.join(',')}
              />
              {uploadFile && (
                <div className="mt-2 p-2 bg-[var(--color-surface-muted)] rounded">
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-[var(--color-text-primary)]">{uploadFile.name}</span>
                    <button
                      onClick={() => {
                        setUploadFile(null);
                        if (fileInputRef.current) {
                          fileInputRef.current.value = '';
                        }
                      }}
                      className="text-[var(--color-text-muted)] hover:text-[var(--color-text-primary)]"
                    >
                      ✕
                    </button>
                  </div>
                  <div className="text-xs text-[var(--color-text-muted)] mt-1">
                    Size: {formatFileSize(uploadFile.size)} | Type: {uploadFile.type}
                  </div>
                </div>
              )}
            </div>
            
            <div>
              <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
                Description
              </label>
              <textarea
                value={uploadDescription}
                onChange={(e) => setUploadDescription(e.target.value)}
                placeholder="Optional description for this document..."
                rows={3}
                className="w-full px-3 py-2 border border-[var(--color-border-default)] rounded-md focus:outline-none focus:ring-2 focus:ring-[var(--color-semantic-primary-200)] bg-[var(--color-surface-base)] text-[var(--color-text-primary)]"
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-2">
                Tags (comma-separated)
              </label>
              <Input
                value={uploadTags}
                onChange={(e) => setUploadTags(e.target.value)}
                placeholder="e.g., project, milestone, important"
              />
            </div>
            
            <div className="flex items-center">
              <input
                type="checkbox"
                id="is-public"
                checked={isPublic}
                onChange={(e) => setIsPublic(e.target.checked)}
                className="h-4 w-4 text-[var(--color-semantic-primary-500)] focus:ring-[var(--color-semantic-primary-200)] border-[var(--color-border-default)] rounded"
              />
              <label htmlFor="is-public" className="ml-2 text-sm text-[var(--color-text-primary)]">
                Make this document public
              </label>
            </div>
          </div>
        </Modal>
      </div>
    </Container>
  );
};

export default DocumentsListPage;

