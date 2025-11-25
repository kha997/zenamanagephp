import React from 'react';
import { useNavigate } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { useDocuments } from '../hooks';

/**
 * DocumentsApprovalsPage
 * 
 * Page for operational document approvals (tác nghiệp).
 * If this is workflow configuration, it should be moved to /admin/settings/approvals (Blade).
 * 
 * Route: /app/documents/approvals
 */
export const DocumentsApprovalsPage: React.FC = () => {
  const navigate = useNavigate();
  const { data: documentsData, isLoading, error } = useDocuments({}, { page: 1, per_page: 50 });

  // Filter documents that need approval (pending status)
  const pendingDocuments = documentsData?.data?.filter(doc => {
    // This would need to be determined based on actual document approval status
    // For now, showing all documents as a placeholder
    return true;
  }) || [];

  if (isLoading) {
    return (
      <Container>
        <div className="animate-pulse">
          <div className="h-8 bg-[var(--muted-surface)] rounded w-1/3 mb-4"></div>
        </div>
      </Container>
    );
  }

  if (error) {
    return (
      <Container>
        <Card>
          <CardContent className="py-8">
            <div className="text-center">
              <p className="text-[var(--muted)] mb-4">
                Error loading documents: {(error as Error).message}
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
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-[var(--font-heading-3-size)] font-semibold text-[var(--text)]">
              Document Approvals
            </h1>
            <p className="text-sm text-[var(--muted)] mt-1">
              Review and approve pending documents
            </p>
          </div>
          <Button variant="secondary" onClick={() => navigate('/app/documents')}>
            Back to Documents
          </Button>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Pending Approvals</CardTitle>
          </CardHeader>
          <CardContent>
            {pendingDocuments.length === 0 ? (
              <div className="text-center py-8">
                <p className="text-[var(--muted)]">No documents pending approval.</p>
              </div>
            ) : (
              <div className="space-y-4">
                {pendingDocuments.map((doc) => (
                  <div
                    key={doc.id}
                    className="flex items-center justify-between p-4 border border-[var(--border)] rounded-lg"
                  >
                    <div className="flex items-center gap-3">
                      <span className="text-2xl">📄</span>
                      <div>
                        <p className="font-medium text-[var(--text)]">{doc.name}</p>
                        {doc.description && (
                          <p className="text-sm text-[var(--muted)]">{doc.description}</p>
                        )}
                        {doc.project_name && (
                          <p className="text-xs text-[var(--muted)]">Project: {doc.project_name}</p>
                        )}
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      <Button
                        variant="secondary"
                        onClick={() => {
                          // TODO: Implement approve action
                          alert('Approve functionality to be implemented');
                        }}
                      >
                        Approve
                      </Button>
                      <Button
                        variant="secondary"
                        onClick={() => {
                          // TODO: Implement reject action
                          alert('Reject functionality to be implemented');
                        }}
                        style={{ color: 'var(--color-semantic-danger-600)' }}
                      >
                        Reject
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>

        <div className="text-sm text-[var(--muted)] p-4 bg-[var(--muted-surface)] rounded-lg">
          <p className="font-medium mb-1">Note:</p>
          <p>
            This page is for operational document approvals. If you need to configure approval workflows,
            please visit <a href="/admin/settings/approvals" className="text-[var(--accent)] hover:underline">Admin Settings</a>.
          </p>
        </div>
      </div>
    </Container>
  );
};

export default DocumentsApprovalsPage;

