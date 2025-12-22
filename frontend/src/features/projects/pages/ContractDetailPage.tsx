import React, { useMemo, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { Table } from '../../../components/ui/Table';
import { MoneyCell } from '../../reports/components/MoneyCell';
import { Badge } from '../../../shared/ui/badge';
import {
  useContractDetail,
  useContractChangeOrders,
  useContractPaymentCertificates,
  useContractPayments,
  useProject,
  useSubmitPaymentCertificate,
  useApprovePaymentCertificate,
  useMarkPaymentPaid,
  useCertificateWorkflowTimeline,
  usePaymentWorkflowTimeline,
} from '../hooks';
import { usePermissions } from '../../../hooks/usePermissions';
import { CostStatusBadge } from '../components/CostStatusBadge';
import { CostWorkflowTimeline } from '../components/CostWorkflowTimeline';
import { DualApprovalBadge } from '../components/DualApprovalBadge';
import { DualApprovalInfo } from '../components/DualApprovalInfo';
import type { ChangeOrderSummary, PaymentCertificateSummary, PaymentSummary, ContractLine } from '../api';
import { projectsApi } from '../api';
import { downloadBlob } from '../../../utils/downloadBlob';
import { useAuth } from '../../../hooks/useAuth';
import toast from 'react-hot-toast';

/**
 * Contract Detail Page
 * 
 * Round 225: Contract & Change Order Drilldown
 * 
 * Displays:
 * - Contract header with key information
 * - Contract lines table
 * - Change orders grouped by status
 * - Payment certificates
 * - Actual payments
 */
export const ContractDetailPage: React.FC = () => {
  const { id: projectId, contractId } = useParams<{ id: string; contractId: string }>();
  const navigate = useNavigate();
  const { canViewCost, canEditCost, canApproveCost, canExportCost } = usePermissions();
  const { user } = useAuth();

  const submitCertificateMutation = useSubmitPaymentCertificate();
  const approveCertificateMutation = useApprovePaymentCertificate();
  const markPaidMutation = useMarkPaymentPaid();

  const { data: projectData } = useProject(projectId);
  const { data: contractData, isLoading: contractLoading, error: contractError } = useContractDetail(projectId, contractId);
  const { data: changeOrdersData } = useContractChangeOrders(projectId, contractId);
  const { data: certificatesData, isLoading: certLoading } = useContractPaymentCertificates(projectId, contractId);
  const { data: paymentsData, isLoading: paymentsLoading } = useContractPayments(projectId, contractId);

  const contract = contractData?.data;
  const changeOrders = changeOrdersData?.data || [];
  const certificates = certificatesData?.data || [];
  const payments = paymentsData?.data || [];

  const currency = contract?.currency || 'VND';
  const [exportingContract, setExportingContract] = useState(false);
  const [exportingCertificates, setExportingCertificates] = useState<Record<string, boolean>>({});
  const [expandedCertificateId, setExpandedCertificateId] = useState<string | null>(null);
  const [expandedPaymentId, setExpandedPaymentId] = useState<string | null>(null);

  const handleExportContract = async () => {
    if (!projectId || !contractId || !contract) return;
    
    setExportingContract(true);
    try {
      const blob = await projectsApi.exportContractPdf(projectId, contractId);
      downloadBlob(blob, `contract-${contract.code}.pdf`);
    } catch (error) {
      console.error('Failed to export contract PDF:', error);
      alert('Failed to export contract PDF. Please try again.');
    } finally {
      setExportingContract(false);
    }
  };

  const handleExportCertificate = async (certificateId: string, code: string) => {
    if (!projectId || !contractId) return;
    
    setExportingCertificates(prev => ({ ...prev, [certificateId]: true }));
    try {
      const blob = await projectsApi.exportPaymentCertificatePdf(projectId, contractId, certificateId);
      downloadBlob(blob, `payment-certificate-${code}.pdf`);
    } catch (error) {
      console.error('Failed to export payment certificate PDF:', error);
      alert('Failed to export payment certificate PDF. Please try again.');
    } finally {
      setExportingCertificates(prev => ({ ...prev, [certificateId]: false }));
    }
  };

  // Group change orders by status
  const changeOrdersByStatus = useMemo(() => {
    const grouped: Record<string, ChangeOrderSummary[]> = {
      approved: [],
      pending: [],
      rejected: [],
    };

    const orders = changeOrdersData?.data || [];
    orders.forEach((co) => {
      if (co.status === 'approved') {
        grouped.approved.push(co);
      } else if (co.status === 'pending') {
        grouped.pending.push(co);
      } else if (co.status === 'rejected') {
        grouped.rejected.push(co);
      }
    });

    return grouped;
  }, [changeOrdersData?.data]);

  const handleChangeOrderClick = (coId: string) => {
    navigate(`/app/projects/${projectId}/contracts/${contractId}/change-orders/${coId}`);
  };

  // Contract lines columns
  const contractLinesColumns = [
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
      key: 'quantity',
      title: 'Quantity',
      align: 'right' as const,
      width: '100px',
      render: (_value: unknown, record: ContractLine) => (
        <span>{record.quantity} {record.unit || ''}</span>
      ),
    },
    {
      key: 'unit_price',
      title: 'Unit Price',
      align: 'right' as const,
      width: '150px',
      render: (_value: unknown, record: ContractLine) => (
        <MoneyCell value={record.unit_price} currency={currency} />
      ),
    },
    {
      key: 'amount',
      title: 'Amount',
      align: 'right' as const,
      width: '150px',
      render: (_value: unknown, record: ContractLine) => (
        <MoneyCell value={record.amount} currency={currency} />
      ),
    },
  ];

  // Payment certificates columns
  const certificatesColumns = [
    {
      key: 'code',
      title: 'Code',
      width: '120px',
    },
    {
      key: 'title',
      title: 'Title',
    },
    {
      key: 'period_start',
      title: 'Period',
      width: '200px',
      render: (_value: unknown, record: PaymentCertificateSummary) => {
        if (record.period_start && record.period_end) {
          return `${record.period_start} to ${record.period_end}`;
        }
        return '-';
      },
    },
    {
      key: 'amount_payable',
      title: 'Amount Payable',
      align: 'right' as const,
      width: '150px',
      render: (_value: any, record: PaymentCertificateSummary) => (
        <MoneyCell value={record.amount_payable} currency={currency} />
      ),
    },
    {
      key: 'status',
      title: 'Status',
      width: '100px',
      render: (_value: any, record: PaymentCertificateSummary) => (
        <div className="flex items-center">
          <CostStatusBadge status={record.status} entityType="certificate" />
          <DualApprovalBadge
            requiresDualApproval={record.requires_dual_approval}
            secondApprovedBy={record.second_approved_by}
          />
        </div>
      ),
    },
    {
      key: 'actions',
      title: 'Actions',
      width: '200px',
      render: (_value: unknown, record: PaymentCertificateSummary) => {
        return (
          <div className="flex gap-2">
            {canApproveCost(Number(projectId)) && record.status === 'draft' && (
              <Button
                variant="primary"
                size="sm"
                onClick={async () => {
                  if (!projectId || !contractId) return;
                  try {
                    await submitCertificateMutation.mutateAsync({
                      projectId,
                      contractId,
                      certificateId: record.id,
                    });
                  } catch (error) {
                    console.error('Failed to submit certificate:', error);
                  }
                }}
                disabled={submitCertificateMutation.isPending}
              >
                Submit
              </Button>
            )}
            {canApproveCost(Number(projectId)) && record.status === 'submitted' && (
              <Button
                variant="success"
                size="sm"
                onClick={async () => {
                  if (!projectId || !contractId) return;
                  try {
                    await approveCertificateMutation.mutateAsync({
                      projectId,
                      contractId,
                      certificateId: record.id,
                    });
                    toast.success('Payment certificate approved successfully');
                  } catch (error: any) {
                    console.error('Failed to approve certificate:', error);
                    
                    // Round 242: Handle dual approval same user error
                    const errorCode = error?.response?.data?.error?.id || error?.response?.data?.error_code;
                    const errorMessage = error?.response?.data?.error?.message || error?.response?.data?.message || error?.message;
                    
                    if (errorCode === 'DUAL_APPROVAL_SAME_USER' || errorMessage?.includes('different approver')) {
                      toast.error('You cannot approve this certificate as the second approver because you already approved it as the first approver.');
                    } else {
                      toast.error(errorMessage || 'Failed to approve certificate');
                    }
                  }
                }}
                disabled={
                  approveCertificateMutation.isPending ||
                  // Round 242: Disable if user is first approver and dual approval is required
                  (record.requires_dual_approval &&
                    record.first_approved_by === user?.id?.toString() &&
                    !record.second_approved_by)
                }
                title={
                  record.requires_dual_approval &&
                  record.first_approved_by === user?.id?.toString() &&
                  !record.second_approved_by
                    ? 'You cannot approve this certificate as the second approver because you already approved it as the first approver.'
                    : undefined
                }
              >
                Approve
              </Button>
            )}
            {canExportCost(Number(projectId)) && (
              <Button
                variant="secondary"
                size="sm"
                onClick={() => handleExportCertificate(record.id, record.code)}
                disabled={exportingCertificates[record.id]}
              >
                {exportingCertificates[record.id] ? 'Exporting...' : '📄 Export PDF'}
              </Button>
            )}
            {canViewCost(Number(projectId)) && (
              <Button
                variant="secondary"
                size="sm"
                onClick={() => setExpandedCertificateId(
                  expandedCertificateId === record.id ? null : record.id
                )}
              >
                {expandedCertificateId === record.id ? 'Hide Timeline' : 'View Timeline'}
              </Button>
            )}
          </div>
        );
      },
    },
  ];

  // Payments columns
  const paymentsColumns = [
    {
      key: 'paid_date',
      title: 'Paid Date',
      width: '120px',
    },
    {
      key: 'amount_paid',
      title: 'Amount Paid',
      align: 'right' as const,
      width: '150px',
      render: (_value: unknown, record: PaymentSummary) => (
        <MoneyCell value={record.amount_paid} currency={currency} />
      ),
    },
    {
      key: 'reference_no',
      title: 'Reference No',
      width: '150px',
    },
    {
      key: 'payment_method',
      title: 'Payment Method',
      width: '120px',
    },
    {
      key: 'status',
      title: 'Status',
      width: '100px',
      render: (_value: unknown, record: PaymentSummary) => {
        // Check if payment has status field, otherwise use paid_date as indicator
        const status = (record as any).status || (record.paid_date ? 'paid' : 'planned');
        return (
          <div className="flex items-center">
            <CostStatusBadge status={status} entityType="payment" />
            <DualApprovalBadge
              requiresDualApproval={record.requires_dual_approval}
              secondApprovedBy={record.second_approved_by}
            />
          </div>
        );
      },
    },
    {
      key: 'actions',
      title: 'Actions',
      width: '200px',
      render: (_value: unknown, record: PaymentSummary) => {
        const status = (record as any).status || (record.paid_date ? 'paid' : 'planned');
        return (
          <div className="flex gap-2">
            {canApproveCost(Number(projectId)) && status === 'planned' && !record.paid_date && (
              <Button
                variant="success"
                size="sm"
                onClick={async () => {
                  if (!projectId || !contractId) return;
                  if (!confirm('Are you sure you want to mark this payment as paid?')) return;
                  try {
                    await markPaidMutation.mutateAsync({
                      projectId,
                      contractId,
                      paymentId: record.id,
                    });
                    toast.success('Payment marked as paid successfully');
                  } catch (error: any) {
                    console.error('Failed to mark payment as paid:', error);
                    
                    // Round 242: Handle dual approval same user error
                    const errorCode = error?.response?.data?.error?.id || error?.response?.data?.error_code;
                    const errorMessage = error?.response?.data?.error?.message || error?.response?.data?.message || error?.message;
                    
                    if (errorCode === 'DUAL_APPROVAL_SAME_USER' || errorMessage?.includes('different approver')) {
                      toast.error('You cannot mark this payment as paid as the second approver because you already approved it as the first approver.');
                    } else {
                      toast.error(errorMessage || 'Failed to mark payment as paid');
                    }
                  }
                }}
                disabled={
                  markPaidMutation.isPending ||
                  // Round 242: Disable if user is first approver and dual approval is required
                  (record.requires_dual_approval &&
                    record.first_approved_by === user?.id?.toString() &&
                    !record.second_approved_by)
                }
                title={
                  record.requires_dual_approval &&
                  record.first_approved_by === user?.id?.toString() &&
                  !record.second_approved_by
                    ? 'You cannot mark this payment as paid as the second approver because you already approved it as the first approver.'
                    : undefined
                }
              >
                Mark as Paid
              </Button>
            )}
            {canViewCost(Number(projectId)) && (
              <Button
                variant="secondary"
                size="sm"
                onClick={() => setExpandedPaymentId(
                  expandedPaymentId === record.id ? null : record.id
                )}
              >
                {expandedPaymentId === record.id ? 'Hide Timeline' : 'View Timeline'}
              </Button>
            )}
          </div>
        );
      },
    },
  ];

  const totalPayments = useMemo(() => {
    const paymentList = paymentsData?.data || [];
    return paymentList.reduce((sum, p) => sum + (p.amount_paid || 0), 0);
  }, [paymentsData?.data]);

  if (contractLoading) {
    return (
      <Container>
        <div className="py-12 text-center">
          <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-[var(--accent)]"></div>
          <p className="mt-2 text-[var(--muted)]">Loading contract details...</p>
        </div>
      </Container>
    );
  }

  if (contractError || !contract) {
    return (
      <Container>
        <div className="py-12 text-center">
          <p className="text-[var(--color-semantic-danger-600)] mb-4">
            {contractError ? `Error: ${(contractError as Error).message}` : 'Contract not found'}
          </p>
          <Button variant="primary" onClick={() => navigate(`/app/projects/${projectId}/contracts`)}>
            Back to Contracts
          </Button>
        </div>
      </Container>
    );
  }

  // Permission check - Round 229
  if (!canViewCost(Number(projectId))) {
    return (
      <Container>
        <Card>
          <CardContent className="py-8">
            <div className="text-center text-[var(--muted)]">
              <p>You do not have permission to view cost data for this project.</p>
            </div>
          </CardContent>
        </Card>
      </Container>
    );
  }

  return (
    <Container>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-semibold text-[var(--text)]">{contract.name}</h1>
            <p className="text-sm text-[var(--muted)] mt-1">
              Code: {contract.code} | Project: {projectData?.data?.name || projectId}
            </p>
          </div>
          <div className="flex gap-2">
            {canExportCost(Number(projectId)) && (
              <Button
                variant="primary"
                onClick={handleExportContract}
                disabled={exportingContract}
              >
                {exportingContract ? 'Exporting...' : '📄 Export Contract PDF'}
              </Button>
            )}
            <Button
              variant="secondary"
              onClick={() => navigate(`/app/projects/${projectId}/contracts`)}
            >
              Back to Contracts
            </Button>
            <Button
              variant="secondary"
              onClick={() => navigate(`/app/projects/${projectId}`)}
            >
              Back to Project
            </Button>
          </div>
        </div>

        {/* Contract Header */}
        <Card>
          <CardHeader>
            <CardTitle>Contract Information</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <div>
                <label className="text-sm font-medium text-[var(--muted)] mb-1 block">
                  Contractor
                </label>
                <p className="text-[var(--text)]">{contract.party_name || '-'}</p>
              </div>
              <div>
                <label className="text-sm font-medium text-[var(--muted)] mb-1 block">
                  Base Amount
                </label>
                <p className="text-lg font-semibold text-[var(--text)]">
                  <MoneyCell value={contract.base_amount} currency={currency} />
                </p>
              </div>
              <div>
                <label className="text-sm font-medium text-[var(--muted)] mb-1 block">
                  Current Amount
                </label>
                <p className="text-lg font-semibold text-[var(--text)]">
                  <MoneyCell value={contract.current_amount} currency={currency} />
                </p>
              </div>
              {contract.start_date && (
                <div>
                  <label className="text-sm font-medium text-[var(--muted)] mb-1 block">
                    Start Date
                  </label>
                  <p className="text-[var(--text)]">{contract.start_date}</p>
                </div>
              )}
              {contract.end_date && (
                <div>
                  <label className="text-sm font-medium text-[var(--muted)] mb-1 block">
                    End Date
                  </label>
                  <p className="text-[var(--text)]">{contract.end_date}</p>
                </div>
              )}
              <div>
                <label className="text-sm font-medium text-[var(--muted)] mb-1 block">
                  Status
                </label>
                <Badge tone="neutral">{contract.status}</Badge>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Contract Lines */}
        {contract.lines && contract.lines.length > 0 && (
          <Card>
            <CardHeader>
              <CardTitle>Contract Lines</CardTitle>
            </CardHeader>
            <CardContent>
              <Table
                columns={contractLinesColumns}
                data={contract.lines}
                size="md"
              />
            </CardContent>
          </Card>
        )}

        {/* Change Orders */}
        {(changeOrdersByStatus.approved.length > 0 ||
          changeOrdersByStatus.pending.length > 0 ||
          changeOrdersByStatus.rejected.length > 0) && (
          <Card>
            <CardHeader>
              <CardTitle>Change Orders</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
              {/* Approved Change Orders */}
              {changeOrdersByStatus.approved.length > 0 && (
                <div>
                  <h3 className="text-lg font-semibold text-[var(--text)] mb-3 flex items-center gap-2">
                    <Badge tone="success">Approved</Badge>
                    <span className="text-sm font-normal text-[var(--muted)]">
                      ({changeOrdersByStatus.approved.length})
                    </span>
                  </h3>
                  <div className="space-y-2">
                    {changeOrdersByStatus.approved.map((co) => (
                      <div
                        key={co.id}
                        onClick={() => handleChangeOrderClick(co.id)}
                        className="p-3 border border-[var(--border)] rounded-lg hover:bg-[var(--muted-surface)] cursor-pointer transition-colors"
                      >
                        <div className="flex items-center justify-between">
                          <div>
                            <p className="font-medium text-[var(--text)]">{co.code} - {co.title}</p>
                            {co.reason && (
                              <p className="text-sm text-[var(--muted)] mt-1">{co.reason}</p>
                            )}
                          </div>
                          <div className="text-right">
                            <p className="text-lg font-semibold text-[var(--color-semantic-success-600)]">
                              <MoneyCell value={co.amount_delta} currency={currency} showPlusWhenPositive />
                            </p>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Pending Change Orders */}
              {changeOrdersByStatus.pending.length > 0 && (
                <div>
                  <h3 className="text-lg font-semibold text-[var(--text)] mb-3 flex items-center gap-2">
                    <Badge tone="warning">Pending</Badge>
                    <span className="text-sm font-normal text-[var(--muted)]">
                      ({changeOrdersByStatus.pending.length})
                    </span>
                  </h3>
                  <div className="space-y-2">
                    {changeOrdersByStatus.pending.map((co) => (
                      <div
                        key={co.id}
                        onClick={() => handleChangeOrderClick(co.id)}
                        className="p-3 border border-[var(--border)] rounded-lg hover:bg-[var(--muted-surface)] cursor-pointer transition-colors"
                      >
                        <div className="flex items-center justify-between">
                          <div>
                            <p className="font-medium text-[var(--text)]">{co.code} - {co.title}</p>
                            {co.reason && (
                              <p className="text-sm text-[var(--muted)] mt-1">{co.reason}</p>
                            )}
                          </div>
                          <div className="text-right">
                            <p className="text-lg font-semibold text-[var(--color-semantic-warning-600)]">
                              <MoneyCell value={co.amount_delta} currency={currency} showPlusWhenPositive />
                            </p>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Rejected Change Orders */}
              {changeOrdersByStatus.rejected.length > 0 && (
                <div>
                  <h3 className="text-lg font-semibold text-[var(--text)] mb-3 flex items-center gap-2">
                    <Badge tone="neutral">Rejected</Badge>
                    <span className="text-sm font-normal text-[var(--muted)]">
                      ({changeOrdersByStatus.rejected.length})
                    </span>
                  </h3>
                  <div className="space-y-2">
                    {changeOrdersByStatus.rejected.map((co) => (
                      <div
                        key={co.id}
                        onClick={() => handleChangeOrderClick(co.id)}
                        className="p-3 border border-[var(--border)] rounded-lg hover:bg-[var(--muted-surface)] cursor-pointer transition-colors"
                      >
                        <div className="flex items-center justify-between">
                          <div>
                            <p className="font-medium text-[var(--text)]">{co.code} - {co.title}</p>
                            {co.reason && (
                              <p className="text-sm text-[var(--muted)] mt-1">{co.reason}</p>
                            )}
                          </div>
                          <div className="text-right">
                            <p className="text-lg font-semibold text-[var(--muted)]">
                              <MoneyCell value={co.amount_delta} currency={currency} showPlusWhenPositive />
                            </p>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </CardContent>
          </Card>
        )}

        {/* Payment Certificates */}
        {certificates.length > 0 && (
          <Card>
            <CardHeader>
              <CardTitle>Payment Certificates</CardTitle>
            </CardHeader>
            <CardContent>
              {certLoading ? (
                <div className="py-8 text-center text-[var(--muted)]">Loading certificates...</div>
              ) : (
                <>
                  <Table
                    columns={certificatesColumns}
                    data={certificates}
                    size="md"
                  />
                  {/* Certificate Workflow Timelines - Round 231 */}
                  {expandedCertificateId && canViewCost(Number(projectId)) && (
                    <>
                      {(() => {
                        const certificate = certificates.find(c => c.id === expandedCertificateId);
                        return certificate ? (
                          <div className="mt-4 pt-4 border-t border-[var(--border)]">
                            <DualApprovalInfo
                              requiresDualApproval={certificate.requires_dual_approval}
                              firstApprovedBy={certificate.first_approved_by}
                              firstApprovedAt={certificate.first_approved_at}
                              secondApprovedBy={certificate.second_approved_by}
                              secondApprovedAt={certificate.second_approved_at}
                            />
                          </div>
                        ) : null;
                      })()}
                      <CertificateTimelinePanel
                        projectId={projectId!}
                        contractId={contractId!}
                        certificateId={expandedCertificateId}
                      />
                    </>
                  )}
                </>
              )}
            </CardContent>
          </Card>
        )}

        {/* Payments */}
        {payments.length > 0 && (
          <Card>
            <CardHeader>
              <CardTitle>Actual Payments</CardTitle>
            </CardHeader>
            <CardContent>
              {paymentsLoading ? (
                <div className="py-8 text-center text-[var(--muted)]">Loading payments...</div>
              ) : (
                <>
                  <Table
                    columns={paymentsColumns}
                    data={payments}
                    size="md"
                  />
                  <div className="mt-4 pt-4 border-t border-[var(--border)]">
                    <div className="flex justify-end">
                      <div className="text-right">
                        <p className="text-sm text-[var(--muted)] mb-1">Total Paid</p>
                        <p className="text-xl font-semibold text-[var(--text)]">
                          <MoneyCell value={totalPayments} currency={currency} />
                        </p>
                      </div>
                    </div>
                  </div>
                  {/* Payment Workflow Timelines - Round 231 */}
                  {expandedPaymentId && canViewCost(Number(projectId)) && (
                    <>
                      {(() => {
                        const payment = payments.find(p => p.id === expandedPaymentId);
                        return payment ? (
                          <div className="mt-4 pt-4 border-t border-[var(--border)]">
                            <DualApprovalInfo
                              requiresDualApproval={payment.requires_dual_approval}
                              firstApprovedBy={payment.first_approved_by}
                              firstApprovedAt={payment.first_approved_at}
                              secondApprovedBy={payment.second_approved_by}
                              secondApprovedAt={payment.second_approved_at}
                            />
                          </div>
                        ) : null;
                      })()}
                      <PaymentTimelinePanel
                        projectId={projectId!}
                        contractId={contractId!}
                        paymentId={expandedPaymentId}
                      />
                    </>
                  )}
                </>
              )}
            </CardContent>
          </Card>
        )}
      </div>
    </Container>
  );
};

/**
 * Certificate Timeline Panel Component
 * Round 231: Cost Workflow Timeline
 */
const CertificateTimelinePanel: React.FC<{
  projectId: string;
  contractId: string;
  certificateId: string;
}> = ({ projectId, contractId, certificateId }) => {
  const { data: timelineItems, isLoading } = useCertificateWorkflowTimeline(
    projectId,
    contractId,
    certificateId
  );

  if (isLoading) {
    return (
      <div className="mt-4 pt-4 border-t border-[var(--border)]">
        <div className="py-4 text-center text-[var(--muted)]">Loading timeline...</div>
      </div>
    );
  }

  return (
    <div className="mt-4 pt-4 border-t border-[var(--border)]">
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
        title="Certificate Workflow Timeline"
        compact
      />
    </div>
  );
};

/**
 * Payment Timeline Panel Component
 * Round 231: Cost Workflow Timeline
 */
const PaymentTimelinePanel: React.FC<{
  projectId: string;
  contractId: string;
  paymentId: string;
}> = ({ projectId, contractId, paymentId }) => {
  const { data: timelineItems, isLoading } = usePaymentWorkflowTimeline(
    projectId,
    contractId,
    paymentId
  );

  if (isLoading) {
    return (
      <div className="mt-4 pt-4 border-t border-[var(--border)]">
        <div className="py-4 text-center text-[var(--muted)]">Loading timeline...</div>
      </div>
    );
  }

  return (
    <div className="mt-4 pt-4 border-t border-[var(--border)]">
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
        title="Payment Workflow Timeline"
        compact
      />
    </div>
  );
};
