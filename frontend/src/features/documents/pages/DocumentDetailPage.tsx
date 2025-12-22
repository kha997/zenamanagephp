import React, { useState, useCallback, useMemo } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { KpiStrip } from '../../../components/shared/KpiStrip';
import { AlertBar } from '../../../components/shared/AlertBar';
import { ActivityFeed } from '../../../components/shared/ActivityFeed';
import { useDocument, useDeleteDocument, useDownloadDocument, useDocumentsActivity, useDocumentsAlerts } from '../hooks';
import { useAutoReadNotificationsForEntity } from '../../../hooks/useAutoReadNotificationsForEntity';
import type { KpiItem } from '../../../components/shared/KpiStrip';
import type { Alert } from '../../../components/shared/AlertBar';
import type { Activity } from '../../../components/shared/ActivityFeed';

type TabId = 'overview' | 'activity';

interface Tab {
  id: TabId;
  label: string;
  icon?: string;
}

const tabs: Tab[] = [
  { id: 'overview', label: 'Overview', icon: '📊' },
  { id: 'activity', label: 'Activity', icon: '📝' },
];

export const DocumentDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [activeTab, setActiveTab] = useState<TabId>('overview');
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  
  const { data: documentData, isLoading, error } = useDocument(id!);
  const deleteDocument = useDeleteDocument();
  const downloadDocument = useDownloadDocument();
  const { data: activityData, isLoading: activityLoading } = useDocumentsActivity(20);
  const { data: alertsData, isLoading: alertsLoading } = useDocumentsAlerts();
  
  const document = documentData?.data;

  // Round 260: Auto-read notifications for this document
  useAutoReadNotificationsForEntity({
    module: 'documents',
    entityType: 'document',
    entityId: id || '',
    delayMs: 5000,
  });
  
  // Transform KPIs data for KpiStrip component
  const kpiItems: KpiItem[] = useMemo(() => {
    if (!document) return [];
    return [
      {
        label: 'File Size',
        value: document.size ? `${(document.size / 1024 / 1024).toFixed(2)} MB` : 'N/A',
        variant: 'default',
      },
      {
        label: 'Version',
        value: document.version || 1,
        variant: 'info',
      },
      {
        label: 'Downloads',
        value: document.download_count || 0,
        variant: 'default',
      },
      {
        label: 'Status',
        value: document.is_public ? 'Public' : 'Private',
        variant: document.is_public ? 'success' : 'default',
      },
    ];
  }, [document]);
  
  // Transform alerts data for AlertBar component
  const alerts: Alert[] = useMemo(() => {
    if (!alertsData?.data) return [];
    return Array.isArray(alertsData.data)
      ? alertsData.data
          .filter((alert: any) => alert.document_id === id || alert.metadata?.document_id === id)
          .map((alert: any) => ({
            id: alert.id,
            message: alert.message || alert.title || 'Alert',
            type: alert.type || alert.severity || 'info',
            priority: alert.priority || 0,
            created_at: alert.created_at || alert.createdAt,
          }))
      : [];
  }, [alertsData, id]);
  
  const handleDelete = useCallback(async () => {
    if (!id) return;
    
    try {
      await deleteDocument.mutateAsync(id);
      navigate('/app/documents');
    } catch (error) {
      console.error('Failed to delete document:', error);
      alert('Failed to delete document. Please try again.');
    } finally {
      setShowDeleteConfirm(false);
    }
  }, [id, deleteDocument, navigate]);
  
  const handleDownload = useCallback(async () => {
    if (!id) return;
    
    try {
      await downloadDocument.mutateAsync(id);
    } catch (error) {
      console.error('Failed to download document:', error);
      alert('Failed to download document. Please try again.');
    }
  }, [id, downloadDocument]);
  
  if (isLoading) {
    return (
      <Container>
        <div className="animate-pulse">
          <div className="h-8 bg-[var(--muted-surface)] rounded w-1/3 mb-4"></div>
        </div>
      </Container>
    );
  }
  
  if (error || !document) {
    return (
      <Container>
        <Card>
          <CardContent className="py-8">
            <div className="text-center">
              <p className="text-[var(--muted)] mb-4">
                {error ? `Error: ${(error as Error).message}` : 'Document not found'}
              </p>
              <Button variant="secondary" onClick={() => navigate('/app/documents')}>
                Back to Documents
              </Button>
            </div>
          </CardContent>
        </Card>
      </Container>
    );
  }
  
  return (
    <Container>
      <div className="space-y-6">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div className="flex-1">
            <h1 className="text-[var(--font-heading-3-size)] font-semibold text-[var(--text)] mb-2">
              {document.name || document.original_filename}
            </h1>
            {document.project_name && (
              <p className="text-sm text-[var(--muted)]">
                Project: {document.project_name}
              </p>
            )}
          </div>
          
          <div className="flex items-center gap-2">
            <Button variant="secondary" onClick={handleDownload} disabled={downloadDocument.isPending}>
              {downloadDocument.isPending ? 'Downloading...' : 'Download'}
            </Button>
            <Button
              variant="secondary"
              onClick={() => setShowDeleteConfirm(true)}
              style={{ color: 'var(--color-semantic-danger-600)' }}
            >
              Delete
            </Button>
          </div>
        </div>
        
        {showDeleteConfirm && (
          <Card style={{ borderColor: 'var(--color-semantic-danger-200)' }}>
            <CardContent className="py-6">
              <h3 className="text-lg font-semibold text-[var(--text)] mb-2">
                Delete Document?
              </h3>
              <p className="text-sm text-[var(--muted)] mb-4">
                Are you sure you want to delete "{document.name || document.original_filename}"? This action cannot be undone.
              </p>
              <div className="flex items-center gap-3">
                <Button
                  variant="secondary"
                  onClick={() => setShowDeleteConfirm(false)}
                  disabled={deleteDocument.isPending}
                >
                  Cancel
                </Button>
                <Button
                  onClick={handleDelete}
                  disabled={deleteDocument.isPending}
                  style={{ backgroundColor: 'var(--color-semantic-danger-600)' }}
                >
                  {deleteDocument.isPending ? 'Deleting...' : 'Delete'}
                </Button>
              </div>
            </CardContent>
          </Card>
        )}
        
        {/* KPI Strip */}
        <KpiStrip
          kpis={kpiItems}
          loading={false}
        />
        
        {/* Alert Bar */}
        <AlertBar
          alerts={alerts}
          loading={alertsLoading}
          onDismiss={(id) => console.log('Dismiss alert:', id)}
          onDismissAll={() => console.log('Dismiss all alerts')}
        />
        
        {/* Tabs */}
        <Card>
          <CardHeader>
            <div className="flex items-center gap-4 border-b border-[var(--border)]">
              {tabs.map((tab) => (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id)}
                  className={`px-4 py-2 text-sm font-medium transition-colors ${
                    activeTab === tab.id
                      ? 'text-[var(--text)] border-b-2 border-[var(--primary)]'
                      : 'text-[var(--muted)] hover:text-[var(--text)]'
                  }`}
                >
                  {tab.icon && <span className="mr-2">{tab.icon}</span>}
                  {tab.label}
                </button>
              ))}
            </div>
          </CardHeader>
          <CardContent className="pt-6">
            {activeTab === 'overview' && (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {document.description && (
                  <div className="md:col-span-2">
                    <label className="text-sm font-medium text-[var(--muted)]">Description</label>
                    <p className="text-[var(--text)] mt-1">{document.description}</p>
                  </div>
                )}
                <div>
                  <label className="text-sm font-medium text-[var(--muted)]">File Type</label>
                  <p className="text-[var(--text)] mt-1">{document.mime_type || 'N/A'}</p>
                </div>
                <div>
                  <label className="text-sm font-medium text-[var(--muted)]">File Size</label>
                  <p className="text-[var(--text)] mt-1">
                    {document.size ? `${(document.size / 1024 / 1024).toFixed(2)} MB` : 'N/A'}
                  </p>
                </div>
                {document.uploaded_by_name && (
                  <div>
                    <label className="text-sm font-medium text-[var(--muted)]">Uploaded By</label>
                    <p className="text-[var(--text)] mt-1">{document.uploaded_by_name}</p>
                  </div>
                )}
                <div>
                  <label className="text-sm font-medium text-[var(--muted)]">Uploaded At</label>
                  <p className="text-[var(--text)] mt-1">
                    {new Date(document.uploaded_at).toLocaleDateString()}
                  </p>
                </div>
                {document.tags && document.tags.length > 0 && (
                  <div className="md:col-span-2">
                    <label className="text-sm font-medium text-[var(--muted)]">Tags</label>
                    <div className="flex flex-wrap gap-2 mt-1">
                      {document.tags.map((tag: string, index: number) => (
                        <span
                          key={index}
                          className="px-2 py-1 text-xs bg-[var(--muted-surface)] text-[var(--muted)] rounded"
                        >
                          {tag}
                        </span>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            )}
            
            {activeTab === 'activity' && (
              <ActivityFeed
                activities={useMemo(() => {
                  if (!activityData?.data) return [];
                  return Array.isArray(activityData.data)
                    ? activityData.data
                        .filter((activity: any) => activity.document_id === id || activity.metadata?.document_id === id)
                        .map((activity: any) => ({
                          id: activity.id,
                          type: activity.type || 'document',
                          action: activity.action,
                          description: activity.description || activity.message || 'Activity',
                          timestamp: activity.timestamp || activity.created_at || activity.createdAt,
                          user: activity.user,
                          metadata: activity.metadata,
                        }))
                    : [];
                }, [activityData, id])}
                loading={activityLoading}
              />
            )}
          </CardContent>
        </Card>
      </div>
    </Container>
  );
};

export default DocumentDetailPage;

