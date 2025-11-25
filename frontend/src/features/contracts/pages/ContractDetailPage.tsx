import React, { useState, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { AccessRestricted } from '../../../components/shared/AccessRestricted';
import { LoadingSpinner } from '../../../components/shared/LoadingSpinner';
import { Modal } from '../../../shared/ui/modal';
import { useAuthStore } from '../../auth/store';
import {
  useContractDetail,
  useContractPayments,
  useCreateContractPayment,
  useUpdateContractPayment,
  useDeleteContractPayment,
  useContractCostSummary,
} from '../hooks';
import { contractsApi } from '../api';
import { ContractPaymentForm } from '../components/ContractPaymentForm';
import type { ContractPayment, CreatePaymentData, UpdatePaymentData } from '../types';
import type { ApiError } from '../../../shared/api/client';
import toast from 'react-hot-toast';

/**
 * ContractDetailPage - Chi tiết hợp đồng + Lịch thanh toán
 * 
 * Round 39: React UI cho Contracts & Payment Schedule
 * 
 * Features:
 * - Hiển thị thông tin hợp đồng
 * - Hiển thị lịch thanh toán (payment schedule)
 * - Thêm/sửa/xóa payment (nếu có tenant.manage_contracts)
 * - RBAC: tenant.view_contracts để xem, tenant.manage_contracts để quản lý
 * - Xử lý lỗi PAYMENT_TOTAL_EXCEEDED
 */
export const ContractDetailPage: React.FC = () => {
  const { contractId } = useParams<{ contractId: string }>();
  const navigate = useNavigate();
  const { hasTenantPermission } = useAuthStore();
  
  const canView = hasTenantPermission('tenant.view_contracts');
  const canManage = hasTenantPermission('tenant.manage_contracts');
  
  const { data: contractData, isLoading: contractLoading, error: contractError } = useContractDetail(contractId);
  const { data: paymentsData, isLoading: paymentsLoading, error: paymentsError, refetch: refetchPayments } = useContractPayments(contractId);
  const { data: costSummaryData, isLoading: costSummaryLoading, error: costSummaryError, refetch: refetchCostSummary } = useContractCostSummary(contractId);
  
  const createPayment = useCreateContractPayment();
  const updatePayment = useUpdateContractPayment();
  const deletePayment = useDeleteContractPayment();
  
  const [showPaymentForm, setShowPaymentForm] = useState(false);
  const [editingPayment, setEditingPayment] = useState<ContractPayment | null>(null);
  const [formError, setFormError] = useState<ApiError | null>(null);
  
  // Early return if user doesn't have view permission
  if (!canView) {
    return (
      <Container>
        <AccessRestricted
          title="Access Restricted"
          description="You don't have permission to view contracts. Please contact an administrator to request access."
        />
      </Container>
    );
  }
  
  const contract = contractData?.data;
  const payments = paymentsData?.data || [];
  
  // Calculate payment summary with overdue info
  const paymentSummary = React.useMemo(() => {
    const totalValue = contract?.total_value || 0;
    const scheduledTotal = payments.reduce((sum, p) => sum + (p.amount || 0), 0);
    const paidTotal = payments
      .filter((p) => p.status === 'paid')
      .reduce((sum, p) => sum + (p.amount || 0), 0);
    const remainingToSchedule = Math.max(0, totalValue - scheduledTotal);
    const remainingToPay = Math.max(0, scheduledTotal - paidTotal);
    
    // Calculate overdue payments
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    const overduePayments = payments.filter((p) => {
      if (p.status === 'paid' || p.status === 'cancelled') return false;
      const dueDate = new Date(p.due_date);
      dueDate.setHours(0, 0, 0, 0);
      return dueDate < now;
    });
    const overdueCount = overduePayments.length;
    const overdueTotal = overduePayments.reduce((sum, p) => sum + (p.amount || 0), 0);
    
    // Calculate percentages for progress bar
    const scheduledPercent = totalValue > 0 ? (scheduledTotal / totalValue) * 100 : 0;
    const paidPercent = totalValue > 0 ? (paidTotal / totalValue) * 100 : 0;
    
    return {
      totalValue,
      scheduledTotal,
      paidTotal,
      remainingToSchedule,
      remainingToPay,
      overdueCount,
      overdueTotal,
      scheduledPercent,
      paidPercent,
    };
  }, [payments, contract]);
  
  // Check if payment is overdue
  const isPaymentOverdue = useCallback((payment: ContractPayment): boolean => {
    if (payment.status === 'paid' || payment.status === 'cancelled') return false;
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    const dueDate = new Date(payment.due_date);
    dueDate.setHours(0, 0, 0, 0);
    return dueDate < now;
  }, []);
  
  // Format currency
  const formatCurrency = (amount: number, currency: string = 'USD') => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currency,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount);
  };
  
  // Format date
  const formatDate = (dateString?: string) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('vi-VN');
  };
  
  // Format datetime
  const formatDateTime = (dateString?: string) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString('vi-VN');
  };
  
  // Status badge
  const getStatusBadge = (status: string, variant: 'contract' | 'payment' = 'contract') => {
    if (variant === 'contract') {
      const statusConfig: Record<string, { label: string; className: string }> = {
        draft: { label: 'Draft', className: 'bg-gray-100 text-gray-800' },
        active: { label: 'Active', className: 'bg-blue-100 text-blue-800' },
        completed: { label: 'Completed', className: 'bg-green-100 text-green-800' },
        cancelled: { label: 'Cancelled', className: 'bg-red-100 text-red-800' },
      };
      const config = statusConfig[status] || statusConfig.draft;
      return (
        <span className={`px-2 py-1 text-xs font-medium rounded-full ${config.className}`}>
          {config.label}
        </span>
      );
    } else {
      const statusConfig: Record<string, { label: string; className: string }> = {
        planned: { label: 'Planned', className: 'bg-gray-100 text-gray-800' },
        due: { label: 'Due', className: 'bg-yellow-100 text-yellow-800' },
        paid: { label: 'Paid', className: 'bg-green-100 text-green-800' },
        overdue: { label: 'Overdue', className: 'bg-red-100 text-red-800' },
        cancelled: { label: 'Cancelled', className: 'bg-gray-100 text-gray-800' },
      };
      const config = statusConfig[status] || statusConfig.planned;
      return (
        <span className={`px-2 py-1 text-xs font-medium rounded-full ${config.className}`}>
          {config.label}
        </span>
      );
    }
  };
  
  const handleCreatePayment = useCallback(async (data: CreatePaymentData) => {
    if (!contractId) return;
    
    setFormError(null);
    try {
      await createPayment.mutateAsync({ contractId, data });
      setShowPaymentForm(false);
      toast.success('Đã tạo đợt thanh toán thành công');
    } catch (error: any) {
      setFormError(error);
      // Error will be handled by ContractPaymentForm via formError prop
      console.error('Failed to create payment:', error);
    }
  }, [contractId, createPayment]);
  
  const handleUpdatePayment = useCallback(async (data: UpdatePaymentData) => {
    if (!contractId || !editingPayment) return;
    
    setFormError(null);
    try {
      await updatePayment.mutateAsync({
        contractId,
        paymentId: editingPayment.id,
        data,
      });
      setShowPaymentForm(false);
      setEditingPayment(null);
      toast.success('Đã cập nhật đợt thanh toán thành công');
    } catch (error: any) {
      setFormError(error);
      console.error('Failed to update payment:', error);
    }
  }, [contractId, editingPayment, updatePayment]);
  
  const handleDeletePayment = useCallback(async (payment: ContractPayment) => {
    if (!contractId) return;
    
    if (!window.confirm(`Bạn có chắc chắn muốn xóa đợt thanh toán "${payment.name}"?`)) {
      return;
    }
    
    try {
      await deletePayment.mutateAsync({ contractId, paymentId: payment.id });
      toast.success('Đã xóa đợt thanh toán thành công');
    } catch (error: any) {
      toast.error(error?.message || 'Không thể xóa đợt thanh toán');
      console.error('Failed to delete payment:', error);
    }
  }, [contractId, deletePayment]);
  
  const handleEditPayment = useCallback((payment: ContractPayment) => {
    setEditingPayment(payment);
    setShowPaymentForm(true);
    setFormError(null);
  }, []);
  
  const handleAddPayment = useCallback(() => {
    setEditingPayment(null);
    setShowPaymentForm(true);
    setFormError(null);
  }, []);
  
  const handleCloseForm = useCallback(() => {
    setShowPaymentForm(false);
    setEditingPayment(null);
    setFormError(null);
  }, []);

  // Handle export cost schedule
  const handleExportCostSchedule = useCallback(async () => {
    if (!contractId) return;
    
    try {
      const blob = await contractsApi.exportContractCostSchedule(contractId);
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `contract_cost_schedule_${contract?.code || contractId}_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.csv`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
      toast.success('Đã xuất cost schedule thành công');
    } catch (error: any) {
      toast.error(error?.message || 'Không thể xuất cost schedule');
      console.error('Failed to export cost schedule:', error);
    }
  }, [contractId, contract]);
  
  if (contractLoading) {
    return (
      <Container>
        <div className="flex justify-center items-center py-12">
          <LoadingSpinner size="lg" message="Loading contract..." />
        </div>
      </Container>
    );
  }
  
  if (contractError || !contract) {
    return (
      <Container>
        <div className="space-y-6">
          <Button variant="secondary" onClick={() => navigate('/app/contracts')}>
            ← Quay lại danh sách
          </Button>
          <Card>
            <CardContent className="p-6">
              <div className="text-center py-12">
                <p className="text-red-600 mb-4">Không tìm thấy hợp đồng</p>
                <p className="text-sm text-[var(--color-text-muted)]">
                  {contractError instanceof Error ? contractError.message : 'Hợp đồng không tồn tại hoặc bạn không có quyền truy cập'}
                </p>
              </div>
            </CardContent>
          </Card>
        </div>
      </Container>
    );
  }
  
  return (
    <Container>
      <div className="space-y-6">
        {/* Back button */}
        <Button variant="secondary" onClick={() => navigate('/app/contracts')}>
          ← Quay lại danh sách
        </Button>
        
        {/* Contract Header */}
        <Card>
          <CardHeader>
            <div className="flex items-start justify-between">
              <div>
                <CardTitle className="text-xl">{contract.name}</CardTitle>
                <p className="text-sm text-[var(--color-text-muted)] mt-1">Code: {contract.code}</p>
              </div>
              {getStatusBadge(contract.status, 'contract')}
            </div>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <p className="text-sm text-[var(--color-text-muted)]">Khách hàng</p>
                <p className="font-medium">{contract.client?.name || '-'}</p>
              </div>
              <div>
                <p className="text-sm text-[var(--color-text-muted)]">Dự án</p>
                <p className="font-medium">{contract.project?.name || '-'}</p>
              </div>
              <div>
                <p className="text-sm text-[var(--color-text-muted)]">Tổng giá trị</p>
                <p className="font-medium text-lg">
                  {formatCurrency(contract.total_value, contract.currency)}
                </p>
              </div>
              <div>
                <p className="text-sm text-[var(--color-text-muted)]">Ngày ký</p>
                <p className="font-medium">{formatDate(contract.signed_at)}</p>
              </div>
            </div>
          </CardContent>
        </Card>
        
        {/* Cost Overview */}
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle>Tổng quan chi phí</CardTitle>
              <Button
                variant="secondary"
                size="sm"
                onClick={handleExportCostSchedule}
              >
                Export cost schedule
              </Button>
            </div>
          </CardHeader>
          <CardContent>
            {costSummaryLoading ? (
              <div className="flex justify-center items-center py-8">
                <LoadingSpinner size="md" message="Đang tải tổng quan chi phí..." />
              </div>
            ) : costSummaryError ? (
              <div className="text-center py-8">
                <p className="text-red-600 mb-2">Không tải được tổng quan chi phí</p>
                <p className="text-sm text-[var(--color-text-muted)] mb-4">
                  {costSummaryError instanceof Error ? costSummaryError.message : 'Đã xảy ra lỗi khi tải dữ liệu'}
                </p>
                <Button
                  variant="secondary"
                  size="sm"
                  onClick={() => refetchCostSummary()}
                >
                  Thử lại
                </Button>
              </div>
            ) : costSummaryData?.summary ? (
              <div className="space-y-4">
                {/* Row 1: Contract value, Budget, Actual */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <p className="text-sm text-[var(--color-text-muted)] mb-1">Giá trị HĐ</p>
                    <p className="text-lg font-semibold">
                      {formatCurrency(costSummaryData.summary.contract_value ?? 0, contract.currency)}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-[var(--color-text-muted)] mb-1">Tổng Budget</p>
                    <p className="text-lg font-semibold">
                      {formatCurrency(costSummaryData.summary.budget_total, contract.currency)}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-[var(--color-text-muted)] mb-1">Tổng Actual</p>
                    <p className="text-lg font-semibold">
                      {formatCurrency(costSummaryData.summary.actual_total, contract.currency)}
                    </p>
                  </div>
                </div>

                {/* Row 2: Budget vs Contract, Contract vs Actual */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <p className="text-sm text-[var(--color-text-muted)] mb-1">Budget – HĐ</p>
                    <p className={`text-lg font-semibold ${
                      costSummaryData.summary.budget_vs_contract_diff !== null && costSummaryData.summary.budget_vs_contract_diff > 0
                        ? 'text-[var(--color-semantic-warning-600)]'
                        : 'text-[var(--color-text-primary)]'
                    }`}>
                      {formatCurrency(costSummaryData.summary.budget_vs_contract_diff ?? 0, contract.currency)}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-[var(--color-text-muted)] mb-1">HĐ – Actual</p>
                    <p className={`text-lg font-semibold ${
                      costSummaryData.summary.contract_vs_actual_diff !== null && costSummaryData.summary.contract_vs_actual_diff < 0
                        ? 'text-[var(--color-semantic-warning-600)]'
                        : 'text-[var(--color-text-primary)]'
                    }`}>
                      {formatCurrency(costSummaryData.summary.contract_vs_actual_diff ?? 0, contract.currency)}
                    </p>
                  </div>
                </div>

                {/* Row 3: Payments summary */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <p className="text-sm text-[var(--color-text-muted)] mb-1">Đã lên lịch thanh toán</p>
                    <p className="text-lg font-semibold">
                      {formatCurrency(costSummaryData.summary.payments_scheduled_total, contract.currency)}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-[var(--color-text-muted)] mb-1">Đã thanh toán</p>
                    <p className="text-lg font-semibold text-[var(--color-semantic-success-600)]">
                      {formatCurrency(costSummaryData.summary.payments_paid_total, contract.currency)}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-[var(--color-text-muted)] mb-1">Còn phải thanh toán</p>
                    <p className="text-lg font-semibold">
                      {formatCurrency(costSummaryData.summary.remaining_to_pay, contract.currency)}
                    </p>
                  </div>
                </div>

                {/* Overdue payments (if any) */}
                {costSummaryData.summary.overdue_payments_count > 0 && (
                  <div className="mt-4 p-3 bg-[var(--color-semantic-danger-50)] rounded-lg border border-[var(--color-semantic-danger-200)]">
                    <p className="text-sm font-medium text-[var(--color-semantic-danger-700)] mb-1">
                      Quá hạn: {costSummaryData.summary.overdue_payments_count} đợt
                    </p>
                    <p className="text-lg font-semibold text-[var(--color-semantic-danger-700)]">
                      {formatCurrency(costSummaryData.summary.overdue_payments_total, contract.currency)}
                    </p>
                  </div>
                )}
              </div>
            ) : (
              <p className="text-sm text-[var(--color-text-muted)]">Không có dữ liệu tổng quan chi phí</p>
            )}
          </CardContent>
        </Card>
        
        {/* Payment Schedule */}
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle>Lịch thanh toán</CardTitle>
              {canManage && (
                <Button variant="primary" size="sm" onClick={handleAddPayment}>
                  + Thêm đợt thanh toán
                </Button>
              )}
            </div>
          </CardHeader>
          <CardContent>
            {/* Summary Bar */}
            <div className="mb-6 p-4 bg-[var(--color-surface-subtle)] rounded-lg">
              <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-4">
                <div>
                  <p className="text-xs text-[var(--color-text-muted)]">Tổng giá trị HĐ</p>
                  <p className="font-semibold">{formatCurrency(paymentSummary.totalValue, contract.currency)}</p>
                </div>
                <div>
                  <p className="text-xs text-[var(--color-text-muted)]">Đã lên lịch</p>
                  <p className="font-semibold">{formatCurrency(paymentSummary.scheduledTotal, contract.currency)}</p>
                </div>
                <div>
                  <p className="text-xs text-[var(--color-text-muted)]">Đã thanh toán</p>
                  <p className="font-semibold text-green-600">
                    {formatCurrency(paymentSummary.paidTotal, contract.currency)}
                  </p>
                </div>
                <div>
                  <p className="text-xs text-[var(--color-text-muted)]">Còn lại chưa phân bổ</p>
                  <p className="font-semibold">
                    {formatCurrency(paymentSummary.remainingToSchedule, contract.currency)}
                  </p>
                </div>
                <div>
                  <p className="text-xs text-[var(--color-text-muted)]">Còn phải thanh toán</p>
                  <p className="font-semibold">
                    {formatCurrency(paymentSummary.remainingToPay, contract.currency)}
                  </p>
                </div>
                {paymentSummary.overdueCount > 0 && (
                  <div>
                    <p className="text-xs text-[var(--color-text-muted)]">Quá hạn</p>
                    <p className="font-semibold text-red-600">
                      {paymentSummary.overdueCount} đợt ({formatCurrency(paymentSummary.overdueTotal, contract.currency)})
                    </p>
                  </div>
                )}
              </div>
              
              {/* Progress Bar (Optional) */}
              {paymentSummary.totalValue > 0 && (
                <div className="mt-4 space-y-2">
                  <div>
                    <div className="flex justify-between text-xs text-[var(--color-text-muted)] mb-1">
                      <span>Đã lên lịch: {paymentSummary.scheduledPercent.toFixed(1)}%</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div
                        className="bg-blue-500 h-2 rounded-full transition-all"
                        style={{ width: `${Math.min(paymentSummary.scheduledPercent, 100)}%` }}
                      />
                    </div>
                  </div>
                  <div>
                    <div className="flex justify-between text-xs text-[var(--color-text-muted)] mb-1">
                      <span>Đã thanh toán: {paymentSummary.paidPercent.toFixed(1)}%</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div
                        className="bg-green-500 h-2 rounded-full transition-all"
                        style={{ width: `${Math.min(paymentSummary.paidPercent, 100)}%` }}
                      />
                    </div>
                  </div>
                </div>
              )}
            </div>
            
            {/* Payments Table */}
            {paymentsLoading ? (
              <div className="flex justify-center items-center py-12">
                <LoadingSpinner size="md" message="Loading payments..." />
              </div>
            ) : paymentsError ? (
              <div className="text-center py-12">
                <p className="text-red-600 mb-2">Không tải được lịch thanh toán</p>
                <p className="text-sm text-[var(--color-text-muted)] mb-4">
                  {paymentsError instanceof Error ? paymentsError.message : 'Đã xảy ra lỗi khi tải dữ liệu'}
                </p>
                <Button
                  variant="secondary"
                  size="sm"
                  onClick={() => refetchPayments()}
                >
                  Thử lại
                </Button>
              </div>
            ) : payments.length === 0 ? (
              <div className="text-center py-12">
                <p className="text-[var(--color-text-muted)]">Chưa có đợt thanh toán nào</p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead className="bg-[var(--color-surface-subtle)] border-b border-[var(--color-border-subtle)]">
                    <tr>
                      <th className="px-4 py-3 text-left text-xs font-medium text-[var(--color-text-secondary)] uppercase tracking-wider">
                        Lần
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-[var(--color-text-secondary)] uppercase tracking-wider">
                        Loại
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-[var(--color-text-secondary)] uppercase tracking-wider">
                        Ngày đến hạn
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-[var(--color-text-secondary)] uppercase tracking-wider">
                        Số tiền
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-[var(--color-text-secondary)] uppercase tracking-wider">
                        Trạng thái
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-[var(--color-text-secondary)] uppercase tracking-wider">
                        Ngày thanh toán
                      </th>
                      {canManage && (
                        <th className="px-4 py-3 text-left text-xs font-medium text-[var(--color-text-secondary)] uppercase tracking-wider">
                          Thao tác
                        </th>
                      )}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-[var(--color-border-subtle)]">
                    {payments.map((payment) => {
                      const isOverdue = isPaymentOverdue(payment);
                      return (
                        <tr
                          key={payment.id}
                          className={`hover:bg-[var(--color-surface-hover)] ${
                            isOverdue ? 'bg-red-50' : ''
                          }`}
                        >
                          <td className="px-4 py-3 whitespace-nowrap text-sm text-[var(--color-text-primary)]">
                            {payment.code || payment.name}
                          </td>
                          <td className="px-4 py-3 whitespace-nowrap text-sm text-[var(--color-text-secondary)]">
                            {payment.type || '-'}
                          </td>
                          <td className="px-4 py-3 whitespace-nowrap text-sm text-[var(--color-text-secondary)]">
                            {formatDate(payment.due_date)}
                          </td>
                          <td className="px-4 py-3 whitespace-nowrap text-sm text-[var(--color-text-primary)]">
                            {formatCurrency(payment.amount, payment.currency)}
                          </td>
                          <td className="px-4 py-3 whitespace-nowrap">
                            <div className="flex items-center gap-2">
                              {getStatusBadge(payment.status, 'payment')}
                              {isOverdue && (
                                <span className="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">
                                  Quá hạn
                                </span>
                              )}
                            </div>
                          </td>
                          <td className="px-4 py-3 whitespace-nowrap text-sm text-[var(--color-text-secondary)]">
                            {formatDateTime(payment.paid_at)}
                          </td>
                        {canManage && (
                          <td className="px-4 py-3 whitespace-nowrap text-sm">
                            <div className="flex items-center gap-2">
                              <button
                                onClick={() => handleEditPayment(payment)}
                                className="text-blue-600 hover:text-blue-800"
                                title="Edit"
                              >
                                ✏️
                              </button>
                              <button
                                onClick={() => handleDeletePayment(payment)}
                                className="text-red-600 hover:text-red-800"
                                title="Delete"
                              >
                                🗑️
                              </button>
                            </div>
                          </td>
                        )}
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}
          </CardContent>
        </Card>
        
        {/* Payment Form Modal */}
        {showPaymentForm && (
          <Modal
            open={showPaymentForm}
            onOpenChange={setShowPaymentForm}
            title={editingPayment ? 'Chỉnh sửa đợt thanh toán' : 'Thêm đợt thanh toán'}
          >
            <ContractPaymentForm
              contractId={contractId!}
              payment={editingPayment}
              contractCurrency={contract.currency}
              onSubmit={editingPayment ? handleUpdatePayment : handleCreatePayment}
              onCancel={handleCloseForm}
              isLoading={createPayment.isPending || updatePayment.isPending}
              error={formError}
            />
          </Modal>
        )}
      </div>
    </Container>
  );
};

