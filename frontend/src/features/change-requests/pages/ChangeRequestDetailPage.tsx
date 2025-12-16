import React, { useState, useCallback, useMemo } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { KpiStrip } from '../../../components/shared/KpiStrip';
import { AlertBar } from '../../../components/shared/AlertBar';
import { ActivityFeed } from '../../../components/shared/ActivityFeed';
import { useChangeRequest, useDeleteChangeRequest, useSubmitChangeRequest, useApproveChangeRequest, useRejectChangeRequest, useChangeRequestsActivity, useChangeRequestsAlerts } from '../hooks';
import type { KpiItem } from '../../../components/shared/KpiStrip';
import type { Alert } from '../../../components/shared/AlertBar';
import type { Activity } from '../../../components/shared/ActivityFeed';

type TabId = 'overview' | 'timeline' | 'activity';

interface Tab {
  id: TabId;
  label: string;
  icon?: string;
}

const tabs: Tab[] = [
  { id: 'overview', label: 'Overview', icon: '📊' },
  { id: 'timeline', label: 'Timeline', icon: '🕐' },
  { id: 'activity', label: 'Activity', icon: '📝' },
];

export const ChangeRequestDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [activeTab, setActiveTab] = useState<TabId>('overview');
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  const [showApproveModal, setShowApproveModal] = useState(false);
  const [showRejectModal, setShowRejectModal] = useState(false);
  const [approvalNotes, setApprovalNotes] = useState('');
  const [rejectionReason, setRejectionReason] = useState('');
  
  const { data: changeRequestData, isLoading, error } = useChangeRequest(id!);
  const deleteChangeRequest = useDeleteChangeRequest();
  const submitChangeRequest = useSubmitChangeRequest();
  const approveChangeRequest = useApproveChangeRequest();
  const rejectChangeRequest = useRejectChangeRequest();
  const { data: activityData, isLoading: activityLoading } = useChangeRequestsActivity(20);
  const { data: alertsData, isLoading: alertsLoading } = useChangeRequestsAlerts();
  
  const changeRequest = changeRequestData?.data;
  
  // Transform KPIs data for KpiStrip component
  const kpiItems: KpiItem[] = useMemo(() => {
    if (!changeRequest) return [];
    return [
      {
        label: 'Status',
        value: changeRequest.status.replace('_', ' '),
        variant: changeRequest.status === 'approved' ? 'success' : changeRequest.status === 'rejected' ? 'danger' : 'warning',
      },
      {
        label: 'Priority',
        value: changeRequest.priority || 'N/A',
        variant: changeRequest.priority === 'urgent' ? 'danger' : changeRequest.priority === 'high' ? 'warning' : 'default',
      },
      {
        label: 'Estimated Cost',
        value: changeRequest.estimated_cost ? `$${changeRequest.estimated_cost.toLocaleString()}` : 'N/A',
        variant: 'default',
      },
      {
        label: 'Estimated Days',
        value: changeRequest.estimated_days || 0,
        variant: 'default',
      },
    ];
  }, [changeRequest]);
  
  // Transform alerts data for AlertBar component
  const alerts: Alert[] = useMemo(() => {
    if (!alertsData?.data) return [];
    return Array.isArray(alertsData.data)
      ? alertsData.data
          .filter((alert: any) => alert.change_request_id === id || alert.metadata?.change_request_id === id)
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
      await deleteChangeRequest.mutateAsync(id);
      navigate('/app/change-requests');
    } catch (error) {
      console.error('Failed to delete change request:', error);
      alert('Failed to delete change request. Please try again.');
    } finally {
      setShowDeleteConfirm(false);
    }
  }, [id, deleteChangeRequest, navigate]);
  
  const handleSubmit = useCallback(async () => {
    if (!id) return;
    
    try {
      await submitChangeRequest.mutateAsync(id);
      alert('Change request submitted for approval');
    } catch (error) {
      console.error('Failed to submit change request:', error);
      alert('Failed to submit change request. Please try again.');
    }
  }, [id, submitChangeRequest]);
  
  const handleApprove = useCallback(async () => {
    if (!id) return;
    
    try {
      await approveChangeRequest.mutateAsync({ id, notes: approvalNotes });
      setShowApproveModal(false);
      setApprovalNotes('');
      alert('Change request approved');
    } catch (error) {
      console.error('Failed to approve change request:', error);
      alert('Failed to approve change request. Please try again.');
    }
  }, [id, approvalNotes, approveChangeRequest]);
  
  const handleReject = useCallback(async () => {
    if (!id) return;
    
    try {
      await rejectChangeRequest.mutateAsync({ id, reason: rejectionReason });
      setShowRejectModal(false);
      setRejectionReason('');
      alert('Change request rejected');
    } catch (error) {
      console.error('Failed to reject change request:', error);
      alert('Failed to reject change request. Please try again.');
    }
  }, [id, rejectionReason, rejectChangeRequest]);
  
  if (isLoading) {
    return (
      <Container>
        <div className="animate-pulse">
          <div className="h-8 bg-[var(--muted-surface)] rounded w-1/3 mb-4"></div>
        </div>
      </Container>
    );
  }
  
  if (error || !changeRequest) {
    return (
      <Container>
        <Card>
          <CardContent className="py-8">
            <div className="text-center">
              <p className="text-[var(--muted)] mb-4">
                {error ? `Error: ${(error as Error).message}` : 'Change request not found'}
              </p>
              <Button variant="secondary" onClick={() => navigate('/app/change-requests')}>
                Back to Change Requests
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
              {changeRequest.title}
            </h1>
            {changeRequest.change_number && (
              <p className="text-sm text-[var(--muted)]">
                #{changeRequest.change_number}
              </p>
            )}
          </div>
          
          <div className="flex items-center gap-2">
            {changeRequest.status === 'draft' && (
              <Button variant="secondary" onClick={handleSubmit} disabled={submitChangeRequest.isPending}>
                {submitChangeRequest.isPending ? 'Submitting...' : 'Submit for Approval'}
              </Button>
            )}
            {changeRequest.status === 'awaiting_approval' && (
              <>
                <Button
                  variant="secondary"
                  onClick={() => setShowApproveModal(true)}
                  style={{ backgroundColor: 'var(--color-semantic-success-600)' }}
                >
                  Approve
                </Button>
                <Button
                  variant="secondary"
                  onClick={() => setShowRejectModal(true)}
                  style={{ backgroundColor: 'var(--color-semantic-danger-600)' }}
                >
                  Reject
                </Button>
              </>
            )}
            {changeRequest.status === 'draft' && (
              <Button
                variant="secondary"
                onClick={() => setShowDeleteConfirm(true)}
                style={{ color: 'var(--color-semantic-danger-600)' }}
              >
                Delete
              </Button>
            )}
          </div>
        </div>
        
        {showDeleteConfirm && (
          <Card style={{ borderColor: 'var(--color-semantic-danger-200)' }}>
            <CardContent className="py-6">
              <h3 className="text-lg font-semibold text-[var(--text)] mb-2">
                Delete Change Request?
              </h3>
              <p className="text-sm text-[var(--muted)] mb-4">
                Are you sure you want to delete "{changeRequest.title}"? This action cannot be undone.
              </p>
              <div className="flex items-center gap-3">
                <Button
                  variant="secondary"
                  onClick={() => setShowDeleteConfirm(false)}
                  disabled={deleteChangeRequest.isPending}
                >
                  Cancel
                </Button>
                <Button
                  onClick={handleDelete}
                  disabled={deleteChangeRequest.isPending}
                  style={{ backgroundColor: 'var(--color-semantic-danger-600)' }}
                >
                  {deleteChangeRequest.isPending ? 'Deleting...' : 'Delete'}
                </Button>
              </div>
            </CardContent>
          </Card>
        )}
        
        {showApproveModal && (
          <Card>
            <CardContent className="py-6">
              <h3 className="text-lg font-semibold text-[var(--text)] mb-2">
                Approve Change Request
              </h3>
              <textarea
                value={approvalNotes}
                onChange={(e) => setApprovalNotes(e.target.value)}
                placeholder="Approval notes (optional)"
                rows={3}
                className="w-full px-3 py-2 border border-[var(--border)] rounded-md bg-[var(--surface)] text-[var(--text)] mb-4"
              />
              <div className="flex items-center gap-3">
                <Button
                  variant="secondary"
                  onClick={() => setShowApproveModal(false)}
                  disabled={approveChangeRequest.isPending}
                >
                  Cancel
                </Button>
                <Button
                  onClick={handleApprove}
                  disabled={approveChangeRequest.isPending}
                  style={{ backgroundColor: 'var(--color-semantic-success-600)' }}
                >
                  {approveChangeRequest.isPending ? 'Approving...' : 'Approve'}
                </Button>
              </div>
            </CardContent>
          </Card>
        )}
        
        {showRejectModal && (
          <Card>
            <CardContent className="py-6">
              <h3 className="text-lg font-semibold text-[var(--text)] mb-2">
                Reject Change Request
              </h3>
              <textarea
                value={rejectionReason}
                onChange={(e) => setRejectionReason(e.target.value)}
                placeholder="Rejection reason (required)"
                rows={3}
                className="w-full px-3 py-2 border border-[var(--border)] rounded-md bg-[var(--surface)] text-[var(--text)] mb-4"
              />
              <div className="flex items-center gap-3">
                <Button
                  variant="secondary"
                  onClick={() => setShowRejectModal(false)}
                  disabled={rejectChangeRequest.isPending}
                >
                  Cancel
                </Button>
                <Button
                  onClick={handleReject}
                  disabled={rejectChangeRequest.isPending || !rejectionReason.trim()}
                  style={{ backgroundColor: 'var(--color-semantic-danger-600)' }}
                >
                  {rejectChangeRequest.isPending ? 'Rejecting...' : 'Reject'}
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
                {changeRequest.description && (
                  <div className="md:col-span-2">
                    <label className="text-sm font-medium text-[var(--muted)]">Description</label>
                    <p className="text-[var(--text)] mt-1">{changeRequest.description}</p>
                  </div>
                )}
                <div>
                  <label className="text-sm font-medium text-[var(--muted)]">Status</label>
                  <p className="text-[var(--text)] mt-1 capitalize">{changeRequest.status.replace('_', ' ')}</p>
                </div>
                <div>
                  <label className="text-sm font-medium text-[var(--muted)]">Priority</label>
                  <p className="text-[var(--text)] mt-1 capitalize">{changeRequest.priority || 'N/A'}</p>
                </div>
                <div>
                  <label className="text-sm font-medium text-[var(--muted)]">Change Type</label>
                  <p className="text-[var(--text)] mt-1 capitalize">{changeRequest.change_type || 'N/A'}</p>
                </div>
                {changeRequest.due_date && (
                  <div>
                    <label className="text-sm font-medium text-[var(--muted)]">Due Date</label>
                    <p className="text-[var(--text)] mt-1">
                      {new Date(changeRequest.due_date).toLocaleDateString()}
                    </p>
                  </div>
                )}
                {changeRequest.estimated_cost && (
                  <div>
                    <label className="text-sm font-medium text-[var(--muted)]">Estimated Cost</label>
                    <p className="text-[var(--text)] mt-1">
                      ${changeRequest.estimated_cost.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                    </p>
                  </div>
                )}
                {changeRequest.estimated_days && (
                  <div>
                    <label className="text-sm font-medium text-[var(--muted)]">Estimated Days</label>
                    <p className="text-[var(--text)] mt-1">{changeRequest.estimated_days} days</p>
                  </div>
                )}
                <div>
                  <label className="text-sm font-medium text-[var(--muted)]">Created</label>
                  <p className="text-[var(--text)] mt-1">
                    {new Date(changeRequest.created_at).toLocaleDateString()}
                  </p>
                </div>
              </div>
            )}
            
            {activeTab === 'timeline' && (
              <div className="space-y-4">
                <div className="flex items-start gap-4">
                  <div className="flex-shrink-0 w-2 h-2 bg-[var(--primary)] rounded-full mt-2"></div>
                  <div>
                    <p className="font-medium text-[var(--text)]">Created</p>
                    <p className="text-sm text-[var(--muted)]">
                      {new Date(changeRequest.created_at).toLocaleString()}
                    </p>
                  </div>
                </div>
                {changeRequest.status === 'awaiting_approval' && (
                  <div className="flex items-start gap-4">
                    <div className="flex-shrink-0 w-2 h-2 bg-yellow-500 rounded-full mt-2"></div>
                    <div>
                      <p className="font-medium text-[var(--text)]">Submitted for Approval</p>
                      <p className="text-sm text-[var(--muted)]">
                        {changeRequest.requested_at ? new Date(changeRequest.requested_at).toLocaleString() : 'Pending'}
                      </p>
                    </div>
                  </div>
                )}
                {changeRequest.status === 'approved' && changeRequest.approved_at && (
                  <div className="flex items-start gap-4">
                    <div className="flex-shrink-0 w-2 h-2 bg-green-500 rounded-full mt-2"></div>
                    <div>
                      <p className="font-medium text-[var(--text)]">Approved</p>
                      <p className="text-sm text-[var(--muted)]">
                        {new Date(changeRequest.approved_at).toLocaleString()}
                      </p>
                      {changeRequest.approval_notes && (
                        <p className="text-sm text-[var(--text)] mt-1">{changeRequest.approval_notes}</p>
                      )}
                    </div>
                  </div>
                )}
                {changeRequest.status === 'rejected' && changeRequest.rejected_at && (
                  <div className="flex items-start gap-4">
                    <div className="flex-shrink-0 w-2 h-2 bg-red-500 rounded-full mt-2"></div>
                    <div>
                      <p className="font-medium text-[var(--text)]">Rejected</p>
                      <p className="text-sm text-[var(--muted)]">
                        {new Date(changeRequest.rejected_at).toLocaleString()}
                      </p>
                      {changeRequest.rejection_reason && (
                        <p className="text-sm text-[var(--text)] mt-1">{changeRequest.rejection_reason}</p>
                      )}
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
                        .filter((activity: any) => activity.change_request_id === id || activity.metadata?.change_request_id === id)
                        .map((activity: any) => ({
                          id: activity.id,
                          type: activity.type || 'change_request',
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

export default ChangeRequestDetailPage;

