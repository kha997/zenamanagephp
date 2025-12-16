import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { Table } from '../../../components/ui/Table';
import { MoneyCell } from '../../reports/components/MoneyCell';
import { Badge } from '../../../shared/ui/badge';
import { useChangeOrderDetail, useContractDetail, useProject, useProposeChangeOrder, useApproveChangeOrder, useRejectChangeOrder, useChangeOrderWorkflowTimeline } from '../hooks';
import { usePermissions } from '../../../hooks/usePermissions';
import { CostStatusBadge } from '../components/CostStatusBadge';
import { CostWorkflowTimeline } from '../components/CostWorkflowTimeline';
import { DualApprovalBadge } from '../components/DualApprovalBadge';
import { DualApprovalInfo } from '../components/DualApprovalInfo';
import type { ChangeOrderLine } from '../api';
import { projectsApi } from '../api';
import { downloadBlob } from '../../../utils/downloadBlob';
import { useAuth } from '../../../hooks/useAuth';
import { useAutoReadNotificationsForEntity } from '../../../hooks/useAutoReadNotificationsForEntity';
import toast from 'react-hot-toast';

/**
 * Change Order Detail Page
 * 
 * Round 225: Contract & Change Order Drilldown
 * 
 * Displays:
 * - Change order header with key information
 * - Change order lines with deltas
 */
export const ChangeOrderDetailPage: React.FC = () => {
  const { id: projectId, contractId, coId } = useParams<{ id: string; contractId: string; coId: string }>();
  const navigate = useNavigate();
  const { canApproveCost, canViewCost } = usePermissions();
  const { user } = useAuth();

  const { data: projectData } = useProject(projectId);
  const { data: contractData } = useContractDetail(projectId, contractId);
  const { data: changeOrderData, isLoading, error } = useChangeOrderDetail(projectId, contractId, coId);
  const { data: timelineItems, isLoading: timelineLoading } = useChangeOrderWorkflowTimeline(
    projectId!,
    contractId!,
    coId!
  );

  const proposeMutation = useProposeChangeOrder();
  const approveMutation = useApproveChangeOrder();
  const rejectMutation = useRejectChangeOrder();

  const changeOrder = changeOrderData?.data;
  const contract = contractData?.data;
  const currency = contract?.currency || changeOrder?.amount_delta ? 'VND' : 'VND';
  const [exporting, setExporting] = useState(false);
  const [showConfirmDialog, setShowConfirmDialog] = useState<{ type: 'propose' | 'approve' | 'reject' } | null>(null);

  // Round 260: Auto-read notifications for this change order
  useAutoReadNotificationsForEntity({
    module: 'cost',
    entityType: 'change_order',
    entityId: coId || '',
    delayMs: 5000,
  });

  const handleExportChangeOrder = async () => {
    if (!projectId || !contractId || !coId || !changeOrder) return;
    
    setExporting(true);
    try {
      const blob = await projectsApi.exportChangeOrderPdf(projectId, contractId, coId);
      downloadBlob(blob, `change-order-${changeOrder.code}.pdf`);
    } catch (error) {
      console.error('Failed to export change order PDF:', error);
      alert('Failed to export change order PDF. Please try again.');
    } finally {
      setExporting(false);
    }
  };

  const handlePropose = async () => {
    if (!projectId || !contractId || !coId) return;
    try {
      await proposeMutation.mutateAsync({ projectId, contractId, coId });
      setShowConfirmDialog(null);
    } catch (error) {
      console.error('Failed to propose change order:', error);
    }
  };

  const handleApprove = async () => {
    if (!projectId || !contractId || !coId) return;
    try {
      await approveMutation.mutateAsync({ projectId, contractId, coId });
      setShowConfirmDialog(null);
      toast.success('Change order approved successfully');
    } catch (error: any) {
      console.error('Failed to approve change order:', error);
      
      // Round 242: Handle dual approval same user error
      const errorCode = error?.response?.data?.error?.id || error?.response?.data?.error_code;
      const errorMessage = error?.response?.data?.error?.message || error?.response?.data?.message || error?.message;
      
      if (errorCode === 'DUAL_APPROVAL_SAME_USER' || errorMessage?.includes('different approver')) {
        toast.error('You cannot approve this change order as the second approver because you already approved it as the first approver.');
      } else {
        toast.error(errorMessage || 'Failed to approve change order');
      }
    }
  };

  const handleReject = async () => {
    if (!projectId || !contractId || !coId) return;
    try {
      await rejectMutation.mutateAsync({ projectId, contractId, coId });
      setShowConfirmDialog(null);
    } catch (error) {
      console.error('Failed to reject change order:', error);
    }
  };

  // Change order lines columns
  const coLinesColumns = [
    {
      key: 'item_code',
      title: 'Item Code',
      width: '120px',
    },
    {
      key: 'description',
      title: 'Description',
    },
    {
      key: 'quantity_delta',
      title: 'Quantity Δ',
      align: 'right' as const,
      width: '120px',
      render: (_value: any, record: any) => {
        if (record.quantity_delta === null || record.quantity_delta === undefined) {
          return '-';
        }
        const sign = record.quantity_delta > 0 ? '+' : '';
        return (
          <span className={record.quantity_delta > 0 ? 'text-[var(--color-semantic-success-600)]' : record.quantity_delta < 0 ? 'text-[var(--color-semantic-danger-600)]' : ''}>
            {sign}{record.quantity_delta} {record.unit || ''}
          </span>
        );
      },
    },
    {
      key: 'unit_price_delta',
      title: 'Unit Price Δ',
      align: 'right' as const,
      width: '150px',
      render: (_value: unknown, record: ChangeOrderLine) => {
        if (record.unit_price_delta === null || record.unit_price_delta === undefined) {
          return '-';
        }
        return (
          <MoneyCell 
            value={record.unit_price_delta} 
            currency={currency}
            showPlusWhenPositive
            tone={record.unit_price_delta > 0 ? 'normal' : record.unit_price_delta < 0 ? 'danger' : 'normal'}
          />
        );
      },
    },
    {
      key: 'amount_delta',
      title: 'Amount Δ',
      align: 'right' as const,
      width: '150px',
      render: (_value: unknown, record: ChangeOrderLine) => (
        <MoneyCell 
          value={record.amount_delta} 
          currency={currency}
          showPlusWhenPositive
          tone={record.amount_delta > 0 ? 'normal' : record.amount_delta < 0 ? 'danger' : 'normal'}
        />
      ),
    },
  ];

  if (isLoading) {
    return (
      <Container>
        <div className="py-12 text-center">
          <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-[var(--accent)]"></div>
          <p className="mt-2 text-[var(--muted)]">Loading change order details...</p>
        </div>
      </Container>
    );
  }

  if (error || !changeOrder) {
    return (
      <Container>
        <div className="py-12 text-center">
          <p className="text-[var(--color-semantic-danger-600)] mb-4">
            {error ? `Error: ${(error as Error).message}` : 'Change order not found'}
          </p>
          <Button variant="primary" onClick={() => navigate(`/app/projects/${projectId}/contracts/${contractId}`)}>
            Back to Contract
          </Button>
        </div>
      </Container>
    );
  }

  const statusTone = changeOrder.status === 'approved' 
    ? 'success' 
    : changeOrder.status === 'pending' 
    ? 'warning' 
    : 'neutral';

  return (
    <Container>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-semibold text-[var(--text)]">{changeOrder.title}</h1>
            <p className="text-sm text-[var(--muted)] mt-1">
              Code: {changeOrder.code} | Contract: {contract?.name || contractId} | Project: {projectData?.data?.name || projectId}
            </p>
          </div>
          <div className="flex gap-2">
            <Button
              variant="primary"
              onClick={handleExportChangeOrder}
              disabled={exporting}
            >
              {exporting ? 'Exporting...' : '📄 Export CO PDF'}
            </Button>
            <Button
              variant="secondary"
              onClick={() => navigate(`/app/projects/${projectId}/contracts/${contractId}`)}
            >
              Back to Contract
            </Button>
            <Button
              variant="secondary"
              onClick={() => navigate(`/app/projects/${projectId}/contracts`)}
            >
              Back to Contracts
            </Button>
          </div>
        </div>

        {/* Change Order Header */}
        <Card>
          <CardHeader>
            <CardTitle>Change Order Information</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <div>
                <label className="text-sm font-medium text-[var(--muted)] mb-1 block">
                  Code
                </label>
                <p className="text-[var(--text)]">{changeOrder.code}</p>
              </div>
              <div>
                <label className="text-sm font-medium text-[var(--muted)] mb-1 block">
                  Status
                </label>
                <div className="flex items-center">
                  <CostStatusBadge status={changeOrder.status} entityType="change_order" />
                  <DualApprovalBadge
                    requiresDualApproval={changeOrder.requires_dual_approval}
                    secondApprovedBy={changeOrder.second_approved_by}
                  />
                </div>
              </div>
              <div>
                <label className="text-sm font-medium text-[var(--muted)] mb-1 block">
                  Amount Delta
                </label>
                <p className={`text-lg font-semibold ${
                  changeOrder.amount_delta > 0 
                    ? 'text-[var(--color-semantic-success-600)]' 
                    : changeOrder.amount_delta < 0 
                    ? 'text-[var(--color-semantic-danger-600)]' 
                    : 'text-[var(--text)]'
                }`}>
                  <MoneyCell 
                    value={changeOrder.amount_delta} 
                    currency={currency}
                    showPlusWhenPositive
                  />
                </p>
              </div>
              {changeOrder.reason && (
                <div className="md:col-span-2 lg:col-span-3">
                  <label className="text-sm font-medium text-[var(--muted)] mb-1 block">
                    Reason
                  </label>
                  <p className="text-[var(--text)]">{changeOrder.reason}</p>
                </div>
              )}
              {changeOrder.effective_date && (
                <div>
                  <label className="text-sm font-medium text-[var(--muted)] mb-1 block">
                    Effective Date
                  </label>
                  <p className="text-[var(--text)]">{changeOrder.effective_date}</p>
                </div>
              )}
            </div>
          </CardContent>
        </Card>

        {/* Dual Approval Info - Round 242 */}
        {changeOrder && (
          <DualApprovalInfo
            requiresDualApproval={changeOrder.requires_dual_approval}
            firstApprovedBy={changeOrder.first_approved_by}
            firstApprovedAt={changeOrder.first_approved_at}
            secondApprovedBy={changeOrder.second_approved_by}
            secondApprovedAt={changeOrder.second_approved_at}
          />
        )}

        {/* Workflow Actions - Round 230 */}
        {canApproveCost(Number(projectId)) && changeOrder && (
          <Card>
            <CardHeader>
              <CardTitle>Workflow Actions</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="flex gap-2">
                {changeOrder.status === 'draft' && (
                  <Button
                    variant="primary"
                    onClick={() => setShowConfirmDialog({ type: 'propose' })}
                    disabled={proposeMutation.isPending}
                  >
                    Propose CO
                  </Button>
                )}
                {changeOrder.status === 'proposed' && (
                  <>
                    <Button
                      variant="success"
                      onClick={() => setShowConfirmDialog({ type: 'approve' })}
                      disabled={
                        approveMutation.isPending ||
                        // Round 242: Disable if user is first approver and dual approval is required
                        (changeOrder.requires_dual_approval &&
                          changeOrder.first_approved_by === user?.id?.toString() &&
                          !changeOrder.second_approved_by)
                      }
                      title={
                        changeOrder.requires_dual_approval &&
                        changeOrder.first_approved_by === user?.id?.toString() &&
                        !changeOrder.second_approved_by
                          ? 'You cannot approve this change order as the second approver because you already approved it as the first approver.'
                          : undefined
                      }
                    >
                      Approve CO
                    </Button>
                    <Button
                      variant="danger"
                      onClick={() => setShowConfirmDialog({ type: 'reject' })}
                      disabled={rejectMutation.isPending}
                    >
                      Reject CO
                    </Button>
                  </>
                )}
              </div>
            </CardContent>
          </Card>
        )}

        {/* Workflow Timeline - Round 231 */}
        {canViewCost(Number(projectId)) && (
          <CostWorkflowTimeline
            items={
              timelineItems?.map((item: any) => ({
                id: item.id,
                timestamp: item.created_at || item.timestamp,
                action: item.action,
                action_label: item.action_label,
                userName: item.user?.name,
                userEmail: item.user?.email,
                metadata: item.metadata,
                description: item.description || item.message,
              })) || []
            }
            title="Approval History"
          />
        )}

        {/* Confirmation Dialog */}
        {showConfirmDialog && (
          <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <Card className="w-full max-w-md">
              <CardHeader>
                <CardTitle>
                  {showConfirmDialog.type === 'propose' && 'Propose Change Order'}
                  {showConfirmDialog.type === 'approve' && 'Approve Change Order'}
                  {showConfirmDialog.type === 'reject' && 'Reject Change Order'}
                </CardTitle>
              </CardHeader>
              <CardContent>
                <p className="mb-4">
                  {showConfirmDialog.type === 'propose' && 'Are you sure you want to propose this change order?'}
                  {showConfirmDialog.type === 'approve' && 'Are you sure you want to approve this change order?'}
                  {showConfirmDialog.type === 'reject' && 'Are you sure you want to reject this change order?'}
                </p>
                <div className="flex gap-2 justify-end">
                  <Button variant="secondary" onClick={() => setShowConfirmDialog(null)}>
                    Cancel
                  </Button>
                  <Button
                    variant={showConfirmDialog.type === 'reject' ? 'danger' : 'primary'}
                    onClick={() => {
                      if (showConfirmDialog.type === 'propose') handlePropose();
                      else if (showConfirmDialog.type === 'approve') handleApprove();
                      else if (showConfirmDialog.type === 'reject') handleReject();
                    }}
                  >
                    Confirm
                  </Button>
                </div>
              </CardContent>
            </Card>
          </div>
        )}

        {/* Change Order Lines */}
        {changeOrder.lines && changeOrder.lines.length > 0 && (
          <Card>
            <CardHeader>
              <CardTitle>Change Order Lines</CardTitle>
            </CardHeader>
            <CardContent>
              <Table
                columns={coLinesColumns}
                data={changeOrder.lines}
                size="md"
              />
              <div className="mt-4 pt-4 border-t border-[var(--border)]">
                <div className="flex justify-end">
                  <div className="text-right">
                    <p className="text-sm text-[var(--muted)] mb-1">Total Amount Delta</p>
                    <p className={`text-xl font-semibold ${
                      changeOrder.amount_delta > 0 
                        ? 'text-[var(--color-semantic-success-600)]' 
                        : changeOrder.amount_delta < 0 
                        ? 'text-[var(--color-semantic-danger-600)]' 
                        : 'text-[var(--text)]'
                    }`}>
                      <MoneyCell 
                        value={changeOrder.amount_delta} 
                        currency={currency}
                        showPlusWhenPositive
                      />
                    </p>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        )}

        {(!changeOrder.lines || changeOrder.lines.length === 0) && (
          <Card>
            <CardContent className="py-8">
              <div className="text-center text-[var(--muted)]">
                <p>No change order lines found.</p>
              </div>
            </CardContent>
          </Card>
        )}
      </div>
    </Container>
  );
};
